<?php
/**
 * The watchdog: the thing that notices.
 *
 * Everything it reads was already being written down. The audit log has
 * recorded who opened which identity document, and when, since the day it was
 * added. The loan book knows which loans are past their date. The remittance
 * store knows which money arrived from an employer and could not be matched to
 * anybody. None of it was ever looked at unless a person went looking, and
 * nobody goes looking at two in the morning.
 *
 * It is a set of rules over that data and nothing more. Calling it an AI agent
 * would oversell it: there is no model here and no judgement, only thresholds
 * that somebody has to agree with. The thresholds are the whole design, which
 * is why they sit in one function at the top where they can be argued with.
 *
 * Two rules it is built around, both of them learned from notify-lib.php:
 *
 *   A digest nobody reads is worse than no digest, because it looks like
 *   monitoring while being wallpaper. So nothing goes out when nothing has
 *   happened, and a standing problem is not re-sent every night until it stops
 *   meaning anything.
 *
 *   A digest carries no applicant's name and no NRC number. It gets forwarded.
 *   Staff usernames are the entire point of an alert and stay; which applicant
 *   it concerned is one sign-in away, behind an account that is logged.
 *
 * It never throws. It runs beside real work and must not be able to stop it.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notify-lib.php';
require_once __DIR__ . '/remittance-lib.php';
require_once __DIR__ . '/disbursement-lib.php';

// loanSummary() lives in api.php. Pulling it in this way — the same way the
// pages do for fxConvert() — means the rule about which loans are overdue is
// the rule the borrower is shown on their own account page. A watchdog that
// disagreed with that would be worse than no watchdog.
if (!function_exists('loanSummary')) {
    if (!defined('AVESTA_LIB')) define('AVESTA_LIB', 1);
    require_once __DIR__ . '/api.php';
}

/**
 * The thresholds.
 *
 * Deliberately NOT published in assets/avesta/ alongside rates.json and
 * categories.json. Those are published because an applicant is entitled to the
 * price of credit before they borrow. These are the opposite kind of number:
 * telling a stranger that the sixth failed sign-in is the one that raises an
 * alarm tells them they have five of them for free.
 */
function av_watchdog_thresholds(): array {
    return [
        // Failed sign-ins, within the window, against one account or from one
        // address. Three is somebody mistyping a password on a phone.
        'failed_logins'  => 6,

        // Identity documents opened by one person within the window. Working
        // through a morning's applications is the job; fifteen is a sweep.
        'documents_bulk' => 15,

        // How long a standing problem stays quiet after it has been reported
        // once. Long enough not to nag, short enough that something left alone
        // comes back and asks again.
        'cooldown_days'  => 7,

        // Zambia keeps CAT all year, and this server keeps UTC, so every
        // timestamp has to be moved before the hour on it means anything.
        // Generous on purpose: only genuinely nocturnal access should fire, or
        // the rule cries wolf at anybody starting early.
        'hours' => ['zone' => 'Africa/Lusaka', 'from' => 6, 'to' => 20],
    ];
}

/* ── where the state lives ───────────────────────────────────────────────── */

/** Pass a path once, at the top of a test. */
function av_watchdog_path(?string $set = null): string {
    static $path = null;
    if ($set !== null) { $path = $set; av_watchdog_reset_cache(); }
    return $path ?? (__DIR__ . '/watchdog.json');
}

function av_watchdog_store(): string { return avStorePath(av_watchdog_path()); }

function av_watchdog_reset_cache(): void { av_watchdog_cache(null, true); }

function av_watchdog_cache(?array $set = null, bool $clear = false): ?array {
    static $data = null;
    if ($clear) { $data = null; return null; }
    if ($set !== null) $data = $set;
    return $data;
}

function av_watchdog_load(): array {
    $cached = av_watchdog_cache();
    if ($cached !== null) return $cached;

    $data = avStoreRead(av_watchdog_path());
    if (!is_array($data)) $data = [];
    $data += ['last_run' => '', 'reported' => []];
    if (!is_array($data['reported'])) $data['reported'] = [];
    av_watchdog_cache($data);
    return $data;
}

function av_watchdog_save(array $data): bool {
    av_watchdog_cache($data);
    return avStoreWrite(av_watchdog_path(), $data);
}

/* ── reading a timestamp the way Ndola reads it ──────────────────────────── */

/**
 * The hour of day an event happened, in Zambian time, or null if the stamp is
 * unusable. This is the function the out-of-hours rule turns on: the server
 * keeps UTC and Ndola is two hours ahead, so skipping it makes the rule wrong
 * in both directions — 23:30 UTC looks like the end of the evening and is half
 * past one in the morning, and 05:00 UTC looks nocturnal and is a reasonable
 * seven o'clock start.
 */
function av_watchdog_local_hour(string $ts): ?int {
    $when = av_watchdog_time($ts);
    if ($when === null) return null;
    try {
        return (int) $when->setTimezone(
            new DateTimeZone(av_watchdog_thresholds()['hours']['zone']))->format('G');
    } catch (Throwable $e) {
        return null;
    }
}

/** A timestamp as a DateTimeImmutable, or null. Never throws. */
function av_watchdog_time(?string $ts): ?DateTimeImmutable {
    $ts = trim((string) $ts);
    if ($ts === '') return null;
    try {
        $when = new DateTimeImmutable($ts);
    } catch (Throwable $e) {
        return null;
    }
    // "not a date" parses as today on some builds rather than throwing, so a
    // stamp that carries no digits at all is treated as unusable.
    return preg_match('/\d/', $ts) ? $when : null;
}

/** Today's date as Ndola has it, for the rules that work in calendar days. */
function av_watchdog_local_date(string $now): string {
    $when = av_watchdog_time($now) ?? new DateTimeImmutable('now');
    try {
        return $when->setTimezone(
            new DateTimeZone(av_watchdog_thresholds()['hours']['zone']))->format('Y-m-d');
    } catch (Throwable $e) {
        return $when->format('Y-m-d');
    }
}

function av_watchdog_out_of_hours(string $ts): bool {
    $hour = av_watchdog_local_hour($ts);
    if ($hour === null) return false;
    $h = av_watchdog_thresholds()['hours'];
    return $hour < (int) $h['from'] || $hour >= (int) $h['to'];
}

/* ── the rules ───────────────────────────────────────────────────────────── */

/**
 * Look at everything and say what is worth somebody's attention.
 *
 * Takes its data as arguments rather than reading the stores itself, so every
 * rule above can be tested against a loan book somebody wrote by hand. The
 * production caller is av_watchdog_run(), which does the reading.
 *
 * $input: now, since, audit[], records[], remittances[]
 */
function av_watchdog_scan(array $input): array {
    $now   = (string) ($input['now'] ?? date('c'));
    $since = (string) ($input['since'] ?? '');
    $t     = av_watchdog_thresholds();

    $window = ['since' => $since, 'until' => $now];
    $findings = [];

    $nowT   = av_watchdog_time($now);
    $sinceT = av_watchdog_time($since);

    // ── the audit log ────────────────────────────────────────────────────
    // Only rows inside the window, and only rows that parse. A log with a
    // mangled line in it must still be readable; the alternative is a watchdog
    // that goes silent exactly when something has gone wrong.
    $rows = [];
    foreach ((array) ($input['audit'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $when = av_watchdog_time((string) ($row['ts'] ?? ''));
        if ($when === null) continue;
        if ($sinceT !== null && $when < $sinceT) continue;
        if ($nowT !== null && $when > $nowT) continue;
        $rows[] = $row;
    }

    $of = function (string ...$actions) use ($rows): array {
        return array_values(array_filter($rows,
            fn($r) => in_array((string) ($r['action'] ?? ''), $actions, true)));
    };
    $add = function (array $f) use (&$findings): void { $findings[] = $f; };

    // Failed sign-ins. Counted two ways on purpose: against one account, which
    // is somebody working on a password, and from one address, which is
    // somebody working through a list of usernames. The second never trips the
    // first, and it is the one that looks like a real attempt.
    $failed = $of('login.failed');
    if (count($failed) >= $t['failed_logins']) {
        $byUser = $byIp = [];
        foreach ($failed as $r) {
            $byUser[(string) ($r['user'] ?? '?')][] = $r;
            $byIp[(string) ($r['ip'] ?? '?')][] = $r;
        }
        arsort($byUser); arsort($byIp);

        $worstUser = array_key_first($byUser);
        $worstIp   = array_key_first($byIp);

        if (count($byUser[$worstUser]) >= $t['failed_logins']) {
            $n = count($byUser[$worstUser]);
            $add(['kind' => 'failed_logins', 'severity' => 'high', 'standing' => false,
                  'count' => $n,
                  'headline' => av_watchdog_plural($n, 'failed sign-in') . ' for ' . $worstUser,
                  'detail' => 'All against one account. If that is not ' . $worstUser
                            . ' having trouble, somebody is working on their password.']);
        } elseif (count($byIp[$worstIp]) >= $t['failed_logins']) {
            $n = count($byIp[$worstIp]);
            $add(['kind' => 'failed_logins', 'severity' => 'high', 'standing' => false,
                  'count' => $n,
                  'headline' => av_watchdog_plural($n, 'failed sign-in') . ' from ' . $worstIp,
                  'detail' => 'Spread across ' . av_watchdog_plural(count($byUser), 'different account') . ' from '
                            . 'one address, which is what working through a list of '
                            . 'usernames looks like.']);
        }
    }

    if ($locked = $of('login.locked_out')) {
        $who = array_values(array_unique(array_map(
            fn($r) => (string) ($r['user'] ?? '?'), $locked)));
        $add(['kind' => 'lockout', 'severity' => 'high', 'standing' => false,
              'count' => count($locked),
              'headline' => 'Account locked out: ' . implode(', ', $who),
              'detail' => 'The lockout did its job. Worth knowing whether it was them.']);
    }

    if ($disabled = $of('login.disabled_account')) {
        $who = array_values(array_unique(array_map(
            fn($r) => (string) ($r['user'] ?? '?'), $disabled)));
        $add(['kind' => 'disabled_account_attempt', 'severity' => 'medium', 'standing' => false,
              'count' => count($disabled),
              'headline' => 'Somebody tried a disabled account: ' . implode(', ', $who),
              'detail' => 'The account is off, so nothing happened. Somebody still has '
                        . 'the password.']);
    }

    // A signed-in account asking for something its role refuses. Either a
    // member of staff is exploring, or a session is not in the hands it
    // belongs to. Both are worth the day they happened, not a month later.
    if ($denied = $of('access.denied', 'api.forbidden',
                      'api.forbidden_privileged_account', 'api.forbidden_super_admin_create')) {
        $who = array_values(array_unique(array_map(
            fn($r) => (string) ($r['user'] ?? '?'), $denied)));
        $add(['kind' => 'privilege_refusals', 'severity' => 'high', 'standing' => false,
              'count' => count($denied),
              'headline' => av_watchdog_plural(count($denied), 'refusal') . ' above their role ('
                          . implode(', ', $who) . ')',
              'detail' => 'A signed-in account asked for something its role does not '
                        . 'allow. The server refused; the asking is the point.']);
    }

    // Identity documents. The two rules that made this worth building.
    $viewed = $of('document.viewed');

    $night = array_values(array_filter($viewed,
        fn($r) => av_watchdog_out_of_hours((string) ($r['ts'] ?? ''))));
    if ($night) {
        $who = array_values(array_unique(array_map(
            fn($r) => (string) ($r['user'] ?? '?'), $night)));
        $h = $t['hours'];
        $add(['kind' => 'documents_out_of_hours', 'severity' => 'high', 'standing' => false,
              'count' => count($night),
              'headline' => 'Identity documents opened out of hours by '
                          . implode(', ', $who),
              'detail' => count($night) . ' opened outside '
                        . sprintf('%02d:00–%02d:00', (int) $h['from'], (int) $h['to'])
                        . ' Zambian time. These are NRC scans and payslips.']);
    }

    $byUser = [];
    foreach ($viewed as $r) $byUser[(string) ($r['user'] ?? '?')][] = $r;
    foreach ($byUser as $user => $theirs) {
        if (count($theirs) < $t['documents_bulk']) continue;
        $add(['kind' => 'documents_bulk', 'severity' => 'medium', 'standing' => false,
              'count' => count($theirs),
              'headline' => $user . ' opened ' . av_watchdog_plural(count($theirs), 'applicant\'s', 'applicants\'')
                          . ' documents',
              'detail' => 'Working through a morning\'s applications is the job. This '
                        . 'many in one window is a sweep, which is worth a reason.']);
    }

    if ($refused = $of('document.denied')) {
        $who = array_values(array_unique(array_map(
            fn($r) => (string) ($r['user'] ?? '?'), $refused)));
        $add(['kind' => 'document_denied', 'severity' => 'high', 'standing' => false,
              'count' => count($refused),
              'headline' => 'Somebody reached for another applicant\'s document ('
                          . implode(', ', $who) . ')',
              'detail' => 'Refused, and recorded. A borrower asking for a document that '
                        . 'is not theirs did not do it by accident.']);
    }

    if ($backups = $of('backup.downloaded')) {
        $who = array_values(array_unique(array_map(
            fn($r) => (string) ($r['user'] ?? '?'), $backups)));
        $add(['kind' => 'backup_taken', 'severity' => 'medium', 'standing' => false,
              'count' => count($backups),
              'headline' => 'Backup taken by ' . implode(', ', $who),
              'detail' => 'That archive holds every identity document and password hash '
                        . 'on the server, and it is not encrypted. Expected sometimes. '
                        . 'Always worth a line.']);
    }

    foreach ([['record.deleted', 'records_deleted', ['application', 'applications']],
              ['payment.deleted', 'payments_deleted', ['payment', 'payments']]] as [$action, $kind, $noun]) {
        if (!($gone = $of($action))) continue;
        $who = array_values(array_unique(array_map(
            fn($r) => (string) ($r['user'] ?? '?'), $gone)));
        $add(['kind' => $kind, 'severity' => 'medium', 'standing' => false,
              'count' => count($gone),
              'headline' => av_watchdog_plural(count($gone), $noun[0], $noun[1])
                          . ' deleted by ' . implode(', ', $who),
              'detail' => 'Nothing else in the system will ever mention this again.']);
    }

    // ── the loan book ────────────────────────────────────────────────────
    $records = [];
    foreach ((array) ($input['records'] ?? []) as $r) {
        if (is_array($r) && $r !== []) $records[] = $r;
    }
    $today = av_watchdog_local_date($now);

    // Marked paid out with no date. This is its own problem, not a late loan:
    // the payroll schedule skips these and the rate engine cannot age them, so
    // they will never show as overdue and never attract the penalty. Money
    // that has gone out and can never be chased.
    $undated = av_disbursement_undated($records);
    if ($undated) {
        $add(['kind' => 'undated_disbursements', 'severity' => 'high', 'standing' => true,
              'count' => count($undated),
              'headline' => av_watchdog_plural(count($undated), 'loan') . ' marked paid out with no date',
              'detail' => 'Neither the payroll schedule nor the delay penalty can see '
                        . 'these. They need a date putting on them by hand: '
                        . av_watchdog_refs($undated)]);
    }

    $overdue = [];
    $owed = 0;
    foreach ($records as $r) {
        if (av_status_closed((string) ($r['status'] ?? ''))) continue;
        try {
            $s = loanSummary($r, $today);
        } catch (Throwable $e) {
            continue;
        }
        if (($s['state'] ?? '') !== 'overdue') continue;
        if (($s['balance'] ?? 0) <= 0) continue;
        $overdue[] = $r;
        $owed += (int) round(((float) $s['balance']) * 100);
    }
    if ($overdue) {
        $add(['kind' => 'overdue_loans', 'severity' => 'medium', 'standing' => true,
              'count' => count($overdue),
              'headline' => av_watchdog_plural(count($overdue), 'loan') . ' overdue, '
                          . av_money($owed) . ' outstanding',
              'detail' => 'Carrying the delay charge and not being chased by anything '
                        . 'automatic: ' . av_watchdog_refs($overdue)]);
    }

    $pending = av_disbursement_pending($records);
    if ($pending) {
        $add(['kind' => 'pending_payouts', 'severity' => 'low', 'standing' => true,
              'count' => count($pending),
              'headline' => av_watchdog_plural(count($pending), 'loan') . ' approved and waiting to be paid out',
              'detail' => 'Somebody has been told yes and is waiting on the money: '
                        . av_watchdog_refs($pending)]);
    }

    // ── money from employers ─────────────────────────────────────────────
    $held = [];
    $heldNgwee = 0;
    foreach ((array) ($input['remittances'] ?? []) as $rem) {
        if (!is_array($rem)) continue;
        $h = (int) ($rem['held_ngwee'] ?? 0);
        if ($h <= 0) continue;
        $held[] = $rem;
        $heldNgwee += $h;
    }
    if ($held) {
        $add(['kind' => 'held_remittances', 'severity' => 'medium', 'standing' => true,
              'count' => count($held),
              'headline' => av_money($heldNgwee) . ' received and not matched to anybody',
              'detail' => av_watchdog_plural(count($held), 'payment') . ' from an employer that could not be '
                        . 'split across their staff. Until it is, those borrowers are '
                        . 'not credited for money they have already had deducted.']);
    }

    // Most serious first, and stable within a severity so the digest does not
    // reshuffle itself between runs.
    $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
    usort($findings, fn($a, $b) =>
        ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9));

    // An id per finding, used to keep a standing problem from being sent every
    // night. A standing one is stable, so the cooldown can recognise it; an
    // incident carries the day it happened, so tomorrow's is a new one.
    foreach ($findings as $i => $f) {
        $findings[$i]['id'] = $f['standing']
            ? $f['kind']
            : $f['kind'] . ':' . substr($now, 0, 10);
    }

    return ['findings' => $findings, 'window' => $window];
}

/** "1 loan", "2 loans". A digest that says "1 loans" reads as carelessly made. */
function av_watchdog_plural(int $n, string $one, string $many = ''): string {
    return $n . ' ' . ($n === 1 ? $one : ($many !== '' ? $many : $one . 's'));
}

/** Up to five references, so the line stays readable. */
function av_watchdog_refs(array $records, int $limit = 5): string {
    $keys = [];
    foreach ($records as $r) {
        $key = trim((string) ($r['_key'] ?? ''));
        if ($key !== '') $keys[] = $key;
        if (count($keys) >= $limit) break;
    }
    $more = count($records) - count($keys);
    return implode(', ', $keys) . ($more > 0 ? ' and ' . $more . ' more' : '');
}

/* ── what goes out ───────────────────────────────────────────────────────── */

/**
 * The digest.
 *
 * No applicant's name and no NRC number, for the same reason the new
 * application alert carries none: this gets forwarded, and an identity number
 * in a forwarded message is gone for good. Loan references are here because
 * they are what somebody needs to open the right record; staff usernames are
 * here because which account did something is the entire point of an alert.
 */
function av_watchdog_message(array $findings, array $window = []): array {
    $high = count(array_filter($findings, fn($f) => ($f['severity'] ?? '') === 'high'));
    $n = count($findings);

    $subject = 'Avesta: ' . $n . ' thing' . ($n === 1 ? '' : 's')
             . ' worth a look' . ($high > 0 ? ' (' . $high . ' urgent)' : '');

    $lines = [];
    $since = substr((string) ($window['since'] ?? ''), 0, 16);
    $until = substr((string) ($window['until'] ?? ''), 0, 16);
    if ($since !== '' && $until !== '') {
        $lines[] = 'Since ' . str_replace('T', ' ', $since)
                 . ' UTC, to ' . str_replace('T', ' ', $until) . ' UTC.';
        $lines[] = '';
    }

    foreach ($findings as $f) {
        $mark = ($f['severity'] ?? '') === 'high' ? '!' : '-';
        $lines[] = $mark . ' ' . ($f['headline'] ?? '');
        if (($f['detail'] ?? '') !== '') $lines[] = '  ' . $f['detail'];
        $lines[] = '';
    }

    $lines[] = 'Open the portal at avesta.solutions to see any of this in full.';
    $lines[] = 'Nothing here names an applicant on purpose — a forwarded message should '
             . 'not be able to.';

    return ['subject' => $subject, 'text' => implode("\n", $lines)];
}

/* ── doing it ────────────────────────────────────────────────────────────── */

/**
 * Read everything, apply the rules, and tell somebody if there is anything to
 * tell them.
 *
 * $send is the thing that delivers, so a test can watch without sending.
 * $input lets a test supply the data; production leaves it null and the stores
 * are read.
 *
 * Never throws.
 */
function av_watchdog_run(?callable $send = null, ?string $now = null,
                         ?array $input = null): array {
    $out = ['findings' => [], 'sent' => [], 'failed' => [], 'skipped' => [], 'suppressed' => []];

    try {
        $now = $now ?: date('c');
        $state = av_watchdog_load();

        // The window runs from the last run, so an incident cannot be reported
        // twice. First time out, a day.
        $since = (string) ($state['last_run'] ?? '');
        if ($since === '' || av_watchdog_time($since) === null) {
            $t = av_watchdog_time($now) ?? new DateTimeImmutable('now');
            $since = $t->modify('-1 day')->format('c');
        }

        if ($input === null) {
            $input = [
                'audit'       => av_watchdog_audit_window($since),
                'records'     => function_exists('loadRecords') ? loadRecords() : [],
                'remittances' => av_watchdog_remittance_rows(),
            ];
        }
        $input['now']   = $now;
        $input['since'] = $input['since'] ?? $since;

        $scan = av_watchdog_scan($input);
        $out['findings'] = $scan['findings'];

        // A standing problem already reported stays quiet for the cooldown.
        $cooldown = (int) av_watchdog_thresholds()['cooldown_days'];
        $nowT = av_watchdog_time($now);
        $toSend = [];
        foreach ($scan['findings'] as $f) {
            $last = av_watchdog_time((string) ($state['reported'][$f['id']] ?? ''));
            if ($last !== null && $nowT !== null
                && $nowT < $last->modify('+' . $cooldown . ' days')) {
                $out['suppressed'][] = ['id' => $f['id'], 'since' => $last->format('c')];
                continue;
            }
            $toSend[] = $f;
        }

        if ($toSend) {
            $result = av_notify_send(av_watchdog_message($toSend, $scan['window']), $send);
            $out['sent']    = $result['sent'];
            $out['failed']  = $result['failed'];
            $out['skipped'] = $result['skipped'];

            // Only mark a finding reported if somebody actually got it.
            // Otherwise a mail server being down once buries the problem for a
            // week, which is the opposite of the point.
            if ($result['sent']) {
                foreach ($toSend as $f) $state['reported'][$f['id']] = $now;
            }
        }

        // Keep the ledger from growing without end: anything well past its
        // cooldown can never suppress anything again.
        foreach ($state['reported'] as $id => $ts) {
            $when = av_watchdog_time((string) $ts);
            if ($when === null
                || ($nowT !== null && $nowT > $when->modify('+' . ($cooldown * 4) . ' days'))) {
                unset($state['reported'][$id]);
            }
        }

        $state['last_run'] = $now;
        av_watchdog_save($state);
    } catch (Throwable $e) {
        $out['failed'][] = ['channel' => 'all', 'error' => $e->getMessage()];
        error_log('Avesta watchdog failed: ' . $e->getMessage());
    }

    if ($out['failed']) {
        error_log('Avesta watchdog delivery failures: ' . json_encode($out['failed']));
    }
    return $out;
}

/** Audit rows at or after a timestamp. Reads the file once. */
function av_watchdog_audit_window(string $since): array {
    if (!defined('AUDIT_FILE') || !is_file(AUDIT_FILE)) return [];
    $sinceT = av_watchdog_time($since);
    $lines = @file(AUDIT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $out = [];
    // Backwards, so a long log stops being read as soon as it is out of range.
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $row = json_decode($lines[$i], true);
        if (!is_array($row)) continue;
        $when = av_watchdog_time((string) ($row['ts'] ?? ''));
        if ($when === null) continue;
        if ($sinceT !== null && $when < $sinceT) break;
        $out[] = $row;
    }
    return $out;
}

/** Every remittance, flattened out of the per-employer store. */
function av_watchdog_remittance_rows(): array {
    $data = av_remittances_load();
    $out = [];
    foreach ((array) $data as $key => $value) {
        if (!is_array($value)) continue;
        // The store is keyed by employer, each holding that employer's rows.
        foreach ($value as $row) {
            if (is_array($row) && isset($row['received_ngwee'])) $out[] = $row;
        }
        if (isset($value['received_ngwee'])) $out[] = $value;
    }
    return $out;
}
