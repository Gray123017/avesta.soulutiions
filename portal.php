<?php
/** Admin portal — staff and admins only. See index.php for the pattern. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
require __DIR__ . '/app/admin.php';
