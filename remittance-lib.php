<?php
/**
 * Payroll deduction and remittance.
 *
 * An employer deducts repayments from its staff's salaries and sends Avesta
 * one payment for the month. Two numbers have to agree: what should have been
 * deducted, and what actually arrived. Where they do not, somebody's loan is
 * short, and the point of this file is that it is obvious whose.
 *
 * Everything is in ngwee — whole hundredths of a kwacha — for the reason
 * rates-lib.php already is. Splitting money between people in floating point
 * loses a ngwee here and there, and the loss shows up months later as a
 * balance that will not clear.
 *
 * Two rules worth stating plainly, because both are refusals rather than
 * features:
 *
 *   A breakdown that does not add up to the payment is REFUSED. Recording it
 *   would put the loan book out by the difference, quietly, for good.
 *
 *   A payment that does not match what was expected, sent without a
 *   breakdown, is NOT allocated. Avesta cannot know whose deduction was
 *   missed, and a guess credits the wrong person's loan. It waits for a
 *   person. The only automatic allocation is the unambiguous one: the exact
 *   amount that was asked for.
 *
 * Nothing here invents an amount to take off somebody's salary. A loan whose
 * schedule cannot be worked out is flagged for staff and expects nothing.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rates-lib.php';      // av_instalments(), av_money(): one place does money
require_once __DIR__ . '/employers-lib.php';

/** Where remittances live. Pass a path once, at the top of a test. */
function av_remittances_path(?string $set = null): string {
    static $path = null;
    if ($set !== null) { $path = $set; av_remittances_reset_cache(); }
    return $path ?? (__DIR__ . '/remittances.json');
}

function av_remittances_store(): string {
    return avStorePath(av_remittances_path());
}

function av_remittances_reset_cache(): void { av_remittances_cache(null, true); }

function av_remittances_cache(?array $set = null, bool $clear = false): ?array {
    static $data = null;
    if ($clear) { $data = null; return null; }
    if ($set !== null) $data = $set;
    return $data;
}

function av_remittances_load(): array {
    $cached = av_remittances_cache();
    if ($cached !== null) return $cached;

    $data = avStoreRead(av_remittances_path());
    if (!is_array($data) || !isset($data['remittances']) || !is_array($data['remittances'])) {
        $data = ['version' => 1, 'remittances' => []];
    }
    av_remittances_cache($data);
    return $data;
}

function av_remittances_save(array $data): bool {
    $data['version'] = 1;
    av_remittances_cache($data);
    return avStoreWrite(av_remittances_path(), $data);
}

function av_remittances_for(string $employerId, string $period = ''): array {
    $out = [];
    foreach (av_remittances_load()['remittances'] as $r) {
        if (($r['employer_id'] ?? '') !== $employerId) continue;
        if ($period !== '' && ($r['period'] ?? '') !== $period) continue;
        $out[] = $r;
    }
    return $out;
}

// ── when a loan's instalments fall ──────────────────────────────────────────

/** Kwacha as written on a record, to whole ngwee. */
function av_ngwee(string|int|float|null $amount): int {
    return (int) round(((float) $amount) * 100);
}

/** How far apart this plan's instalments are, or null if it is not one we know. */
function av_plan_step(string $plan): ?array {
    $p = strtolower(trim($plan));
    if ($p === 'weekly')     return ['days', 7];
    if ($p === 'bi-weekly' || $p === 'biweekly' || $p === 'fortnightly') return ['days', 14];
    if ($p === 'monthly')    return ['months', 1];
    if ($p === 'lump sum' || $p === 'lumpsum' || $p === 'lump') return ['once', 0];
    return null;
}

/**
 * The instalments on a record: when each is due and how much it is.
 *
 * Returns null when the record does not say enough to work it out. That is
 * deliberate and it is the whole point: a missing first payment date means
 * nobody knows when this is due, and guessing produces a deduction from a real
 * person's wages on a date nobody agreed to.
 */
function av_loan_due_dates(array $record): ?array {
    $total = av_ngwee($record['total_repayment'] ?? $record['total_repay'] ?? null);
    $count = (int) ($record['installments'] ?? $record['num_inst'] ?? 0);
    $first = trim((string) ($record['first_payment'] ?? ''));
    $plan  = (string) ($record['repay_plan'] ?? $record['repayment_plan'] ?? '');

    if ($total <= 0 || $count < 1 || $first === '') return null;

    $start = date_create_immutable($first);
    if ($start === false) return null;

    $step = av_plan_step($plan);
    if ($step === null) return null;
    if ($step[0] === 'once') $count = 1;

    $amounts = av_instalments($total, $count);
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        if ($step[0] === 'days') {
            $when = $start->modify('+' . ($i * $step[1]) . ' days');
        } elseif ($step[0] === 'months') {
            // Anchored to the start day rather than added month by month, so a
            // schedule starting on the 30th does not walk backwards through
            // February and never recovers.
            $when = av_add_months($start, $i * $step[1]);
        } else {
            $when = $start;
        }
        $out[] = ['date' => $when->format('Y-m-d'), 'ngwee' => $amounts[$i]];
    }
    return $out;
}

/** Add months without rolling into the next one when the day does not exist. */
function av_add_months(DateTimeImmutable $start, int $months): DateTimeImmutable {
    if ($months === 0) return $start;
    $day = (int) $start->format('j');
    $firstOfMonth = $start->modify('first day of this month')->modify('+' . $months . ' months');
    $lastDay = (int) $firstOfMonth->format('t');
    return $firstOfMonth->setDate(
        (int) $firstOfMonth->format('Y'), (int) $firstOfMonth->format('n'), min($day, $lastDay));
}

/** The first and last day of a 'YYYY-MM' period. */
function av_period_bounds(string $period): ?array {
    if (!preg_match('/^\d{4}-\d{2}$/', $period)) return null;
    $start = date_create_immutable($period . '-01');
    if ($start === false) return null;
    return [$start->format('Y-m-d'), $start->format('Y-m-t')];
}

// ── the schedule ────────────────────────────────────────────────────────────

/** What is still owed on a record, in ngwee, from its own payment list. */
function av_record_outstanding_ngwee(array $record): int {
    $total = av_ngwee($record['total_repayment'] ?? $record['total_repay'] ?? null);
    $paid  = 0;
    foreach (($record['_payments'] ?? []) as $p) {
        $paid += av_ngwee($p['amount'] ?? 0);
    }
    $left = $total - $paid;
    return $left > 0 ? $left : 0;
}

/** Statuses that are not being collected any more. */
function av_status_closed(string $status): bool {
    return in_array(strtolower(trim($status)),
        ['repaid', 'rejected', 'cancelled', 'canceled', 'written off', 'settled'], true);
}

/**
 * What each of an employer's staff owes for a period.
 *
 * Built from the employer register and the loan records together: the register
 * says who is on which payroll number, the records say what they owe. Somebody
 * who has left is still on it — the balance comes out of their final pay, and
 * dropping them off is how a debt quietly stops being collected.
 */
function av_deduction_schedule(string $employerId, string $period, array $records): array {
    $bounds = av_period_bounds($period);
    $employer = av_employer_find($employerId);

    $out = ['employer_id' => $employerId,
            'employer'    => $employer['name'] ?? '',
            'period'      => $period,
            'lines'       => [], 'expected_ngwee' => 0, 'needs_attention' => 0];

    if ($employer === null || $bounds === null) return $out;
    [$from, $to] = $bounds;

    // Loans by the account that owns them, so a payroll number finds its loan.
    $byOwner = [];
    foreach ($records as $r) {
        $owner = (string) ($r['_owner'] ?? '');
        if ($owner === '') continue;
        if (av_status_closed((string) ($r['status'] ?? ''))) continue;
        if (av_record_outstanding_ngwee($r) <= 0) continue;
        $byOwner[$owner][] = $r;
    }

    foreach ($employer['employees'] as $person) {
        $owner = (string) ($person['user_id'] ?? '');
        foreach (($byOwner[$owner] ?? []) as $record) {
            $line = [
                'enrolment_id' => $person['id'],
                'payroll_no'   => $person['payroll_no'] ?? '',
                'name'         => $person['name'] ?? ($record['full_name'] ?? ''),
                'branch_id'    => $person['branch_id'] ?? '',
                'loan_key'     => $record['_key'] ?? '',
                'outstanding_ngwee' => av_record_outstanding_ngwee($record),
                'expected_ngwee'    => 0,
                'left'           => ($person['status'] ?? '') === 'left',
                'left_at'        => $person['left_at'] ?? null,
                'needs_attention' => false,
                'problem'        => '',
            ];

            $due = av_loan_due_dates($record);
            if ($due === null) {
                $line['needs_attention'] = true;
                $line['problem'] = 'This loan does not record when it is due, so nothing has '
                                 . 'been asked for. Check the repayment schedule on the '
                                 . 'application before deducting anything.';
                $out['lines'][] = $line;
                $out['needs_attention']++;
                continue;
            }

            $wanted = 0;
            foreach ($due as $d) {
                if ($d['date'] >= $from && $d['date'] <= $to) $wanted += $d['ngwee'];
            }
            // Never more than is actually owed: a loan paid down early must not
            // keep taking a full instalment off somebody's salary.
            $line['expected_ngwee'] = min($wanted, $line['outstanding_ngwee']);

            if ($line['left']) {
                $line['needs_attention'] = true;
                $line['problem'] = 'Left on ' . ($line['left_at'] ?? 'an unrecorded date')
                                 . '. The balance is settled from the final pay.';
                $out['needs_attention']++;
            }

            $out['lines'][] = $line;
            $out['expected_ngwee'] += $line['expected_ngwee'];
        }
    }
    return $out;
}

// ── what arrived ────────────────────────────────────────────────────────────

/**
 * Split a payment across what was expected, without losing a ngwee.
 *
 * Proportional, with the remainder handed out one ngwee at a time to the
 * largest expectations first. The sum of the parts is always exactly the
 * amount given — that is the property worth having, and the one a test
 * should hold on to.
 */
function av_remittance_allocate(int $totalNgwee, array $expected): array {
    $wanted = 0;
    foreach ($expected as $e) $wanted += (int) ($e['expected_ngwee'] ?? 0);
    if ($wanted <= 0 || $totalNgwee <= 0) return [];

    $out = [];
    $given = 0;
    foreach ($expected as $e) {
        $share = intdiv($totalNgwee * (int) $e['expected_ngwee'], $wanted);
        $out[] = ['loan_key' => $e['loan_key'] ?? '', 'ngwee' => $share,
                  'expected_ngwee' => (int) $e['expected_ngwee']];
        $given += $share;
    }

    // Integer division always rounds down, so there is a little left over.
    $left = $totalNgwee - $given;
    $order = array_keys($out);
    usort($order, fn($a, $b) => $out[$b]['expected_ngwee'] <=> $out[$a]['expected_ngwee']);
    for ($i = 0; $left > 0 && $i < count($order); $i++, $left--) {
        $out[$order[$i]]['ngwee']++;
    }
    return $out;
}

/**
 * Record what an employer sent, and allocate it where that is unambiguous.
 *
 * $lines is the employer's own breakdown when they sent one. It must add up to
 * the payment exactly — a breakdown that does not is refused rather than
 * reconciled, because the difference would otherwise sit in the loan book
 * forever with nothing pointing at it.
 *
 * Returns the remittance, the records with any payments posted onto them, and
 * how many were posted. The caller saves the records; this does not, because
 * the loan book has its own locking and one writer.
 */
function av_remittance_record(string $employerId, string $period, int $receivedNgwee,
                              ?array $lines, array $records): array {
    if ($receivedNgwee <= 0) {
        return ['ok' => false, 'error' => 'A remittance has to be an amount above zero.'];
    }
    $schedule = av_deduction_schedule($employerId, $period, $records);

    if (is_array($lines) && $lines !== []) {
        $sum = 0;
        foreach ($lines as $l) $sum += (int) ($l['ngwee'] ?? 0);
        if ($sum !== $receivedNgwee) {
            $diff = abs($sum - $receivedNgwee);
            return ['ok' => false,
                    'difference_ngwee' => $diff,
                    'error' => 'The breakdown and the payment do not add up: the lines come to '
                             . av_money($sum) . ' and the payment is ' . av_money($receivedNgwee)
                             . ', a difference of ' . av_money($diff) . '. '
                             . 'Fix one or the other before recording it.'];
        }
        $allocated = [];
        foreach ($lines as $l) {
            $allocated[] = ['loan_key' => (string) ($l['loan_key'] ?? ''),
                            'ngwee' => (int) ($l['ngwee'] ?? 0)];
        }
        $status = 'allocated';
    } elseif ($receivedNgwee === $schedule['expected_ngwee'] && $schedule['expected_ngwee'] > 0) {
        // The exact amount asked for. There is only one way it can be split.
        $allocated = [];
        foreach ($schedule['lines'] as $l) {
            if ($l['expected_ngwee'] > 0) {
                $allocated[] = ['loan_key' => $l['loan_key'], 'ngwee' => $l['expected_ngwee']];
            }
        }
        $status = 'allocated';
    } else {
        // Avesta cannot know whose deduction was missed, and guessing credits
        // the wrong person's loan. It waits for somebody to say.
        $allocated = [];
        $status = 'needs_allocation';
    }

    $remittance = [
        'id'               => 'rem_' . bin2hex(random_bytes(6)),
        'employer_id'      => $employerId,
        'employer'         => $schedule['employer'],
        'period'           => $period,
        'received_ngwee'   => $receivedNgwee,
        'expected_ngwee'   => $schedule['expected_ngwee'],
        'shortfall_ngwee'  => max(0, $schedule['expected_ngwee'] - $receivedNgwee),
        'allocated_ngwee'  => array_sum(array_column($allocated, 'ngwee')),
        'lines'            => $allocated,
        'status'           => $status,
        'received_at'      => date('c'),
        'recorded_by'      => av_user()['username'] ?? 'system',
        'reference'        => '',
    ];

    [$records, $posted] = av_remittance_post($remittance, $records);

    $data = av_remittances_load();
    $data['remittances'][] = $remittance;
    if (!av_remittances_save($data)) {
        return ['ok' => false, 'error' => 'The remittance could not be saved.'];
    }
    return ['ok' => true, 'remittance' => $remittance, 'records' => $records, 'posted' => $posted];
}

/**
 * Write a remittance's allocated lines onto the loan records as payments.
 *
 * In the loan book's own shape, because there is one ledger. A payment carries
 * the remittance id so it can be traced back, and so the same remittance can
 * never be posted twice.
 */
function av_remittance_post(array $remittance, array $records): array {
    $posted = 0;
    foreach ($remittance['lines'] as $line) {
        if ((int) $line['ngwee'] <= 0) continue;

        foreach ($records as $i => $record) {
            if (($record['_key'] ?? '') !== $line['loan_key']) continue;

            if (!isset($records[$i]['_payments']) || !is_array($records[$i]['_payments'])) {
                $records[$i]['_payments'] = [];
            }
            foreach ($records[$i]['_payments'] as $existing) {
                // Already posted. Recording the same remittance twice would
                // clear a loan nobody has paid down.
                if (($existing['remittance_id'] ?? '') === $remittance['id']) continue 2;
            }

            $records[$i]['_payments'][] = [
                'id'            => 'p_' . bin2hex(random_bytes(6)),
                'amount'        => round($line['ngwee'] / 100, 2),
                'date'          => substr($remittance['received_at'], 0, 10),
                'method'        => 'Payroll deduction',
                'note'          => $remittance['employer'] . ', ' . $remittance['period'],
                'remittance_id' => $remittance['id'],
                'taken_by'      => $remittance['recorded_by'],
                'recorded'      => date('c'),
            ];
            $posted++;
            break;
        }
    }
    return [$records, $posted];
}

/**
 * Expected against received for a period, and who is short.
 *
 * The per-person line is the one that matters: a total that is K600 light
 * tells you there is a problem, and this tells you it is AV-0031.
 */
function av_remittance_reconcile(string $employerId, string $period, array $records): array {
    $paidPerLoan = [];
    $received = 0;
    $ours = [];
    foreach (av_remittances_for($employerId, $period) as $rem) {
        $received += (int) $rem['received_ngwee'];
        $ours[$rem['id']] = true;
        foreach ($rem['lines'] as $l) {
            $paidPerLoan[$l['loan_key']] = ($paidPerLoan[$l['loan_key']] ?? 0) + (int) $l['ngwee'];
        }
    }

    // What was ASKED FOR, which is not the same as what is left now. This
    // period's own deductions have already been posted to the loan book, so
    // building the schedule straight from the records would show a borrower
    // who paid in full as owing nothing and quietly drop them out of the
    // reconciliation — the exact person whose line needs to read "paid".
    // Taking this period's postings back off reconstructs the ask.
    $before = [];
    foreach ($records as $record) {
        $payments = [];
        foreach (($record['_payments'] ?? []) as $p) {
            if (isset($ours[$p['remittance_id'] ?? ''])) continue;
            $payments[] = $p;
        }
        $record['_payments'] = $payments;
        $before[] = $record;
    }
    $schedule = av_deduction_schedule($employerId, $period, $before);

    $lines = [];
    foreach ($schedule['lines'] as $l) {
        $paid = $paidPerLoan[$l['loan_key']] ?? 0;
        $lines[] = $l + [
            'received_ngwee'  => $paid,
            'shortfall_ngwee' => max(0, $l['expected_ngwee'] - $paid),
        ];
    }

    return [
        'employer_id'     => $employerId,
        'employer'        => $schedule['employer'],
        'period'          => $period,
        'expected_ngwee'  => $schedule['expected_ngwee'],
        'received_ngwee'  => $received,
        'shortfall_ngwee' => max(0, $schedule['expected_ngwee'] - $received),
        'needs_attention' => $schedule['needs_attention'],
        'lines'           => $lines,
    ];
}
