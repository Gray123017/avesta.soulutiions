<?php
require_once __DIR__ . '/staging-guard.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
http_response_code(403);
echo json_encode(['ok'=>false,'error'=>'Staging only: API calls, accounts and transactions are disabled.']);
