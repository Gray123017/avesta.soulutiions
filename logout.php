<?php
require_once __DIR__ . '/auth.php';
av_logout('user');
header('Location: login.php');
exit;
