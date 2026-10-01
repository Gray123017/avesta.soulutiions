<?php
/** AVESTA CONSULTING — the IT office. See loans.php for the pattern. */
define('AV_SITE', 'it');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
require __DIR__ . '/app/index.php';
