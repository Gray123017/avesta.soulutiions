<?php
// Use the existing account gate, then open the existing loan application.
require_once __DIR__ . '/auth.php';
av_require_login([], '/login.php');
header('Cache-Control: no-store, private');
header('Location: /loans.php#apply');
exit;
