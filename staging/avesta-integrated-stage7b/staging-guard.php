<?php
// Parent Hostinger directory authentication must remain enabled. This is not a replacement for it.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
if (is_file(__DIR__ . '/PAUSED.flag')) { http_response_code(503); exit('This Stage 7B candidate is paused. Earlier stages are unchanged.'); }
$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
if (!in_array($host, ['staging.avesta.solutions', 'localhost', '127.0.0.1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This Avesta review build is staging-only. Production use is disabled.');
}

// This demonstration never accepts a form submission or other mutation request.
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=utf-8');
    exit('Demonstration only. Application submissions and writes are disabled.');
}
