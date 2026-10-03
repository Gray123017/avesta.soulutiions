<?php
/** Keep existing IT bookmarks on the current public IT page. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Location: /index.php?page=it', true, 302);
exit;
