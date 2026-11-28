<?php
/**
 * Outgoing email, through Hostinger's mail server as a real mailbox.
 *
 * WHY THIS EXISTS
 *
 * Until 28 November 2026 both senders on this site used PHP mail(): the
 * password-reset codes in recovery-lib.php, and the new-application alerts in
 * notify-lib.php. Checked against DNS that day, avesta.solutions publishes
 *
 *   MX     5 mx1.hostinger.com, 10 mx2.hostinger.com
 *   SPF    v=spf1 include:_spf.mail.hostinger.com include:_spf.reach.hostinger.com ~all
 *   DMARC  v=DMARC1; p=none
 *   DKIM   nothing, at any selector tried
 *
 * so the domain says "Hostinger's mail servers may send for me" — and mail()
 * was not one of them. mail() sets From: as a header and leaves the envelope
 * sender as the hosting account, and SPF is checked against the envelope. The
 * two never aligned, there was no DKIM to fall back on, and notify-lib.php was
 * sending as no-reply@avesta.solutions, which is not a mailbox at all: the only
 * one on the domain is info@avesta.solutions. Mail like that gets junked.
 *
 * For a lender that is not cosmetic. The sign-in code is the only way a
 * borrower gets into their account, so a code in a spam folder is a borrower
 * locked out, and nothing anywhere said so — av_recovery_send() returned
 * whatever mail() handed back, which is "queued locally", not "delivered".
 *
 * So: hand the message to smtp.hostinger.com over TLS, signed in as
 * info@avesta.solutions, with the envelope sender set to the same address as
 * the From header. SPF then covers it and alignment passes. DKIM is still
 * worth adding in hPanel; it is not needed for this to work, and it is noted
 * in the deployment checklist rather than pretended to be done here.
 *
 * WHAT IT WILL NOT DO
 *
 * It will not send unencrypted to anything but loopback, because that would
 * put the mailbox password on the wire. It will not report success when no
 * password is configured — silently returning true is how this went unnoticed
 * for as long as it did. And it never puts the password in a return value, an
 * error string or the audit log, because admins read all three.
 *
 * The password itself is set from the portal and stored in avesta-secrets.php
 * beside the TextMeBot key, which is in .gitignore, denied in .htaccess and
 * deliberately absent from av_backup_allowlist().
 */

/** One secrets file for the whole site. notify-lib.php owns the path when loaded. */
function av_mail_secrets_path(): string
{
    if (function_exists('av_notify_secrets_path')) return av_notify_secrets_path();
    return __DIR__ . '/avesta-secrets.php';
}

/**
 * Where to send and as whom. Environment first so a test can point this at a
 * socket on loopback; then the secrets file; then the live defaults.
 */
function av_mail_config(): array
{
    $secrets = [];
    $file = av_mail_secrets_path();
    if (is_file($file)) {
        $loaded = @include $file;
        if (is_array($loaded)) $secrets = $loaded;
    }

    $pick = function (string $env, string $secret, string $default) use ($secrets): string {
        $v = getenv($env);
        if ($v !== false && $v !== '') return trim((string) $v);
        // An env var set to empty deliberately means empty, not "fall through".
        if ($v === '') return '';
        if (isset($secrets[$secret]) && trim((string) $secrets[$secret]) !== '') {
            return trim((string) $secrets[$secret]);
        }
        return $default;
    };

    $host   = $pick('AVESTA_SMTP_HOST',   'smtp_host', 'smtp.hostinger.com');
    $port   = (int) $pick('AVESTA_SMTP_PORT', 'smtp_port', '465');
    $scheme = strtolower($pick('AVESTA_SMTP_SCHEME', 'smtp_scheme', 'ssl'));
    $user   = $pick('AVESTA_SMTP_USER',   'smtp_user', 'info@avesta.solutions');
    $from   = $pick('AVESTA_SMTP_FROM',   'smtp_from', $user !== '' ? $user : 'info@avesta.solutions');

    // The password is the one value with no default and no business in a log.
    $pass = getenv('AVESTA_SMTP_PASS');
    if ($pass === false) $pass = (string) ($secrets['smtp_password'] ?? '');

    return [
        'host'      => $host,
        'port'      => $port > 0 ? $port : 465,
        'scheme'    => in_array($scheme, ['ssl', 'tls', 'tcp'], true) ? $scheme : 'ssl',
        'user'      => $user,
        'pass'      => (string) $pass,
        'from'      => $from,
        'from_name' => $pick('AVESTA_SMTP_FROM_NAME', 'smtp_from_name', 'Avesta Enterprises'),
    ];
}

/** True when a mailbox password is configured, which is what "email works" means here. */
function av_mail_available(): bool
{
    $c = av_mail_config();
    return $c['pass'] !== '' && $c['user'] !== '' && $c['host'] !== '';
}

/** A line an admin can be shown. Deliberately carries no password. */
function av_mail_describe(): string
{
    $c = av_mail_config();
    return av_mail_available()
        ? sprintf('Sending as %s via %s:%d (%s).', $c['from'], $c['host'], $c['port'], $c['scheme'])
        : sprintf('Not sending: no mailbox password configured for %s.', $c['user'] ?: '(no mailbox set)');
}

function av_mail_is_loopback(string $host): bool
{
    return in_array($host, ['127.0.0.1', '::1', 'localhost'], true);
}

/** Strips anything that would let a value break out of its header and add another. */
function av_mail_header_safe(string $v, int $max = 300): string
{
    $v = str_replace(["\r", "\n", "\0"], '', $v);
    return substr(trim($v), 0, $max);
}

/** Reads one SMTP reply, following 250-style continuation lines. */
function av_mail_reply($sock): array
{
    $lines = [];
    while (true) {
        $line = fgets($sock, 2048);
        if ($line === false) return ['code' => 0, 'text' => 'The mail server closed the connection.'];
        $lines[] = rtrim($line, "\r\n");
        // "250-more" continues; "250 done" ends.
        if (preg_match('/^(\d{3})([ ]|$)/', $line, $m)) {
            return ['code' => (int) $m[1], 'text' => implode(' / ', $lines)];
        }
        if (count($lines) > 60) return ['code' => 0, 'text' => 'The mail server would not stop talking.'];
    }
}

function av_mail_cmd($sock, string $cmd, int $expect, string $what, bool $secret = false): ?string
{
    fwrite($sock, $cmd . "\r\n");
    $r = av_mail_reply($sock);
    if ((int) ($r['code'] / 100) !== (int) ($expect / 100) && $r['code'] !== $expect) {
        // Never echo $cmd: for AUTH steps it is the password, base64'd.
        return $what . ' was refused (' . ($r['code'] ?: 'no reply') . ': '
             . ($secret ? 'credentials rejected' : av_mail_header_safe($r['text'], 160)) . ').';
    }
    return null;
}

/**
 * Sends one plain-text message. Returns ['ok' => bool, 'error' => string].
 *
 * A false return is load-bearing: recovery-lib.php rolls its challenge back and
 * audits recovery.mail_failed, so a borrower is told to try another way rather
 * than left waiting for a code that is not coming.
 */
function av_mail_send(string $to, string $subject, string $text): array
{
    $c = av_mail_config();

    $to      = av_mail_header_safe($to, 254);
    $subject = av_mail_header_safe($subject, 200);

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'That is not a valid email address.'];
    }
    if (!av_mail_available()) {
        return ['ok' => false, 'error' => 'No mailbox password is configured on the server, so '
              . 'nothing can be sent. Add it in Notification Settings.'];
    }
    if ($c['scheme'] === 'tcp' && !av_mail_is_loopback($c['host'])) {
        return ['ok' => false, 'error' => 'Refusing to sign in to ' . av_mail_header_safe($c['host'], 80)
              . ' without TLS: that would send the mailbox password in the clear. '
              . 'Use ssl on port 465.'];
    }

    $target = ($c['scheme'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'SNI_enabled'       => true,
        'peer_name'         => $c['host'],
    ]]);

    $errNo = 0; $errStr = '';
    $sock = @stream_socket_client($target, $errNo, $errStr, 15,
        STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) {
        return ['ok' => false, 'error' => 'Could not reach the mail server ('
              . av_mail_header_safe($errStr ?: 'no route', 120) . ').'];
    }
    stream_set_timeout($sock, 20);

    try {
        $hello = av_mail_reply($sock);
        if ($hello['code'] !== 220) {
            return ['ok' => false, 'error' => 'The mail server did not greet us ('
                  . ($hello['code'] ?: 'no reply') . ').'];
        }

        $heloName = preg_replace('/[^A-Za-z0-9.\-]/', '', explode('@', $c['from'])[1] ?? 'avesta.solutions');
        if (($e = av_mail_cmd($sock, 'EHLO ' . $heloName, 250, 'EHLO')) !== null) {
            return ['ok' => false, 'error' => $e];
        }

        if ($c['scheme'] === 'tls') {
            if (($e = av_mail_cmd($sock, 'STARTTLS', 220, 'STARTTLS')) !== null) {
                return ['ok' => false, 'error' => $e];
            }
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return ['ok' => false, 'error' => 'TLS could not be negotiated with the mail server.'];
            }
            if (($e = av_mail_cmd($sock, 'EHLO ' . $heloName, 250, 'EHLO after STARTTLS')) !== null) {
                return ['ok' => false, 'error' => $e];
            }
        }

        // AUTH LOGIN, which Hostinger accepts. Prompts for user then password.
        if (($e = av_mail_cmd($sock, 'AUTH LOGIN', 334, 'Sign-in', true)) !== null) {
            return ['ok' => false, 'error' => $e];
        }
        if (($e = av_mail_cmd($sock, base64_encode($c['user']), 334, 'Sign-in', true)) !== null) {
            return ['ok' => false, 'error' => $e];
        }
        if (($e = av_mail_cmd($sock, base64_encode($c['pass']), 235, 'Sign-in', true)) !== null) {
            return ['ok' => false, 'error' => $e];
        }

        // The envelope sender. This is the address SPF is checked against, and
        // it deliberately matches the From header below.
        if (($e = av_mail_cmd($sock, 'MAIL FROM:<' . $c['from'] . '>', 250, 'The sender address')) !== null) {
            return ['ok' => false, 'error' => $e];
        }
        if (($e = av_mail_cmd($sock, 'RCPT TO:<' . $to . '>', 250, 'The recipient address')) !== null) {
            return ['ok' => false, 'error' => $e];
        }
        if (($e = av_mail_cmd($sock, 'DATA', 354, 'DATA')) !== null) {
            return ['ok' => false, 'error' => $e];
        }

        $domain = explode('@', $c['from'])[1] ?? 'avesta.solutions';
        $headers = [
            'Date: ' . date('r'),
            'From: ' . av_mail_header_safe($c['from_name'], 80) . ' <' . $c['from'] . '>',
            'To: ' . $to,
            'Subject: ' . $subject,
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Auto-Submitted: auto-generated',
        ];

        // Normalise to CRLF, then dot-stuff: a line that is just "." would
        // otherwise end the message early and drop everything after it.
        $body = str_replace(["\r\n", "\r"], "\n", $text);
        $body = str_replace("\n", "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body);

        fwrite($sock, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
        $queued = av_mail_reply($sock);
        if ($queued['code'] !== 250) {
            return ['ok' => false, 'error' => 'The mail server would not accept the message ('
                  . ($queued['code'] ?: 'no reply') . ': ' . av_mail_header_safe($queued['text'], 160) . ').'];
        }

        @fwrite($sock, "QUIT\r\n");
        return ['ok' => true, 'error' => ''];
    } finally {
        if (is_resource($sock)) @fclose($sock);
    }
}
