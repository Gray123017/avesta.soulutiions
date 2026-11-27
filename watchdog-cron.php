<?php
/**
 * Run the watchdog. Once a day is plenty; once an hour is fine too.
 *
 * In hPanel: Advanced → Cron Jobs → add a PHP job for
 *
 *     /usr/bin/php /home/<user>/public_html/watchdog-cron.php
 *
 * at whatever time suits. 07:00 Zambian time (05:00 in the cron, which keeps
 * UTC) means the night's activity is on the first screen somebody looks at.
 *
 * Deliberately a command-line script and not an API action. The alternative is
 * an endpoint that sends messages, reachable by anybody who learns its URL,
 * guarded by a token that then has to live somewhere and be rotated when it
 * leaks. There is nothing to leak here. The portal can still show what the
 * watchdog would say — api.php?action=watchdogDigest — but that only ever
 * reads.
 *
 * Refuses to run over the web even if it is requested, because the repository
 * root is the document root and every .php file in it is reachable. Nothing
 * secret is in here, but a stranger being able to make the server send mail is
 * a way to empty a mail quota, and the digest is not theirs to read.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/watchdog-lib.php';

$result = av_watchdog_run();

$n = count($result['findings']);
$sent = count($result['sent']);
$suppressed = count($result['suppressed']);

echo date('c'), "  watchdog: ", $n, " finding(s), ",
     $sent, " message(s) sent, ", $suppressed, " held back as already reported\n";

foreach ($result['findings'] as $f) {
    echo '  [', $f['severity'], '] ', $f['headline'], "\n";
}

foreach ($result['failed'] as $f) {
    echo '  FAILED: ', ($f['channel'] ?? '?'), ' — ', ($f['error'] ?? ''), "\n";
}

// Nothing to tell anybody is the normal outcome and not a failure, so this
// exits 0 either way. A non-zero exit would put a cron failure email in the
// inbox every quiet night, which is the habit this whole thing exists to break.
exit(0);
