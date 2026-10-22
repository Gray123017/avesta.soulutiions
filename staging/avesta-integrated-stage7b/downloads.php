<?php
// Read-only alias to the isolated Software Centre, never an installer response.
require_once __DIR__ . '/staging-guard.php';
header('Location: /avesta-integrated-stage7b/index.php?page=software', true, 302);
exit;
