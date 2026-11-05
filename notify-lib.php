<?php
/**
 * Telling somebody an application has arrived.
 *
 * What was here before was not a notification. The portal opened a mailto:
 * draft in the admin's own mail client, which only happens if an admin already
 * has the portal open — and then they send the email to themselves. An
 * application arriving at two in the morning reached nobody at all.
 *
 * This sends from the server, at the moment the application is stored, whether
 * or not anybody has a tab open.
 *
 * Two rules it is built around:
 *
 *   A notification must NEVER stop an application being accepted. Every send
 *   is wrapped; a mail server being down costs a message, not a customer.
 *
 *   A notification carries only what the person reading it needs to act. A
 *   WhatsApp message gets forwarded, and an NRC number in one is gone for
 *   good. Name, amount, term and reference — then "open the portal".
 *
 * The WhatsApp API key is deliberately NOT kept in the settings store. The
 * backup excludes secrets and includes settings, so a key saved here would end
 * up in an unencrypted archive that gets emailed around. It is read from
 * avesta-secrets.php instead; without one, WhatsApp is simply off.
 */

require_once __DIR__ . '/auth.php';

/** Where the settings live. Pass a path once, at the top of a test. */
function av_notify_path(?string $set = null): string {
    static $path = null;
    if ($set !== null) { $path = $set; av_notify_reset_cache(); }
    return $path ?? (__DIR__ . '/notify.json');
}

function av_notify_store(): string { return avStorePath(av_notify_path()); }

function av_notify_reset_cache(): void { av_notify_cache(null, true); }

function av_notify_cache(?array $set = null, bool $clear = false): ?array {
    static $data = null;
    if ($clear) { $data = null; return null; }
    if ($set !== null) $data = $set;
    return $data;
}

function av_notify_defaults(): array {
    return [
        'email'    => ['on' => false, 'to' => []],
        'whatsapp' => ['on' => false, 'to' => []],
    ];
}

function av_notify_load(): array {
    $cached = av_notify_cache();
    if ($cached !== null) return $cached;

    $data = avStoreRead(av_notify_path());
    if (!is_array($data)) $data = [];
    $data = array_replace_recursive(av_notify_defaults(), $data);

    foreach (['email', 'whatsapp'] as $channel) {
        $data[$channel]['on'] = !empty($data[$channel]['on']);
        $data[$channel]['to'] = array_values(array_filter(
            array_map('trim', (array) ($data[$channel]['to'] ?? [])),
            fn($v) => $v !== ''));
        // Never read back as a setting, whatever is in the file.
        unset($data[$channel]['api_key']);
    }
    av_notify_cache($data);
    return $data;
}

/**
 * Save the settings. Anything that looks like a credential is dropped on the
 * way in rather than trusted not to be sent — see the note at the top.
 */
function av_notify_save(array $in): bool {
    $data = array_replace_recursive(av_notify_load(), $in);
    foreach (['email', 'whatsapp'] as $channel) {
        unset($data[$channel]['api_key'], $data[$channel]['key'], $data[$channel]['token']);
    }
    av_notify_cache($data);
    return avStoreWrite(av_notify_path(), $data);
}

/** The WhatsApp key, from the secrets file. Never from the settings store. */
function av_notify_whatsapp_key(): string {
    static $key = null;
    if ($key !== null) return $key;

    $key = '';
    $file = __DIR__ . '/avesta-secrets.php';
    if (is_file($file)) {
        $secrets = @include $file;
        if (is_array($secrets)) $key = trim((string) ($secrets['textmebot_key'] ?? ''));
    }
    if ($key === '') $key = trim((string) (getenv('AVESTA_TEXTMEBOT_KEY') ?: ''));
    return $key;
}

/**
 * What goes out.
 *
 * Deliberately thin. Everything else about the applicant is one sign-in away,
 * behind an account that is logged; a message is not.
 */
function av_notify_message(array $record): array {
    $name   = trim((string) ($record['full_name'] ?? $record['name'] ?? 'Someone'));
    $ref    = trim((string) ($record['_key'] ?? ''));
    $amount = number_format((float) ($record['loan_amt'] ?? $record['loan_amount'] ?? 0), 2);
    $term   = trim((string) ($record['sel_dur'] ?? $record['loan_duration'] ?? ''));
    $when   = trim((string) ($record['submitted_at'] ?? date('Y-m-d H:i')));

    $channel = [
        'company_employee' => 'through their employer',
        'employed'         => 'formally employed',
        'self_employed'    => 'self-employed, on security',
    ][$record['applicant_category'] ?? ''] ?? '';

    $lines = [
        $name . ' has applied for K ' . $amount . ($term !== '' ? ' over ' . $term : '') . '.',
        $channel !== '' ? 'Applying ' . $channel . '.' : '',
        $ref !== '' ? 'Reference ' . $ref . '.' : '',
        'Received ' . $when . '.',
        '',
        'Sign in to the Avesta portal to review it. Nothing else about the '
        . 'applicant is in this message on purpose.',
    ];

    return [
        'subject' => 'New application — ' . $name . ' — K ' . $amount,
        'text'    => implode("\n", array_values(array_filter($lines, fn($l) => $l !== '' || true))),
    ];
}

/** Does this look like somewhere a message can actually go? */
function av_notify_recipient_ok(string $channel, string $to): bool {
    if ($channel === 'email') return (bool) filter_var($to, FILTER_VALIDATE_EMAIL);
    // A phone number in international form, digits only, no leading plus.
    return (bool) preg_match('/^\d{9,15}$/', preg_replace('/[^0-9]/', '', $to));
}

/**
 * Tell whoever is configured that an application has arrived.
 *
 * $send is the thing that actually delivers, so a test can watch without
 * sending anything. Production leaves it null and gets av_notify_deliver().
 *
 * Returns what happened. It never throws: the caller is in the middle of
 * accepting somebody's loan application, and losing that because a mail server
 * was down would be the worst trade in this codebase.
 */
function av_notify_new_application(array $record, ?callable $send = null): array {
    $out = ['sent' => [], 'failed' => [], 'skipped' => []];

    try {
        $settings = av_notify_load();
        $message  = av_notify_message($record);
        // Whether we are using the built-in sender matters below: only it needs
        // an API key, and a caller that supplies its own delivery should not
        // be refused for the want of one.
        $builtIn = $send === null;
        $send    = $send ?? 'av_notify_deliver';

        foreach (['email', 'whatsapp'] as $channel) {
            if (empty($settings[$channel]['on'])) {
                $out['skipped'][] = ['channel' => $channel, 'why' => 'switched off'];
                continue;
            }
            $to = $settings[$channel]['to'];
            if (!$to) {
                $out['skipped'][] = ['channel' => $channel, 'why' => 'nobody to send to'];
                continue;
            }
            if ($channel === 'whatsapp' && $builtIn && av_notify_whatsapp_key() === '') {
                $out['skipped'][] = ['channel' => $channel,
                                     'why' => 'no API key in avesta-secrets.php'];
                continue;
            }

            foreach ($to as $one) {
                if (!av_notify_recipient_ok($channel, $one)) {
                    $out['skipped'][] = ['channel' => $channel, 'to' => $one,
                                         'why' => 'not a usable address'];
                    continue;
                }
                try {
                    $ok = $send($channel, $one, $message);
                    if ($ok) $out['sent'][] = ['channel' => $channel, 'to' => $one];
                    else $out['failed'][] = ['channel' => $channel, 'to' => $one,
                                             'error' => 'the send was refused'];
                } catch (Throwable $e) {
                    $out['failed'][] = ['channel' => $channel, 'to' => $one,
                                        'error' => $e->getMessage()];
                }
            }
        }
    } catch (Throwable $e) {
        // Settings unreadable, or anything else. The application still stands.
        $out['failed'][] = ['channel' => 'all', 'error' => $e->getMessage()];
    }

    if ($out['failed']) {
        error_log('Avesta notification failures: ' . json_encode($out['failed']));
    }
    return $out;
}

/** The real sender. Kept separate so the decision logic above can be tested. */
function av_notify_deliver(string $channel, string $to, array $message): bool {
    if ($channel === 'email') {
        $host = $_SERVER['HTTP_HOST'] ?? 'avesta.solutions';
        $from = 'no-reply@' . preg_replace('/^www\./', '', $host);
        return @mail($to, $message['subject'], $message['text'],
            "From: Avesta Enterprises <" . $from . ">\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n");
    }

    if ($channel === 'whatsapp') {
        $key = av_notify_whatsapp_key();
        if ($key === '') return false;

        $url = 'https://api.textmebot.com/send.php?recipient=' . rawurlencode('+' . $to)
             . '&apikey=' . rawurlencode($key)
             . '&text=' . rawurlencode($message['subject'] . "\n\n" . $message['text']);

        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false;
    }
    return false;
}
