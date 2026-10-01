<?php
/**
 * AVESTA LENDING — the loans office.
 *
 * Same codebase as the IT office, different front door. AV_SITE tells the
 * page which side it is serving; everything belonging to the other side is
 * stripped out before the visitor sees it.
 */
define('AV_SITE', 'lending');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
require __DIR__ . '/app/index.php';
