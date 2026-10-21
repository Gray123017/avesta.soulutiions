<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
try {
    av7_only(['GET','HEAD']);
    if ($_GET && array_keys($_GET)!==['signedout']) throw new Av7Error(400,'Unexpected query parameters.');
    if (av7_principal()!==null) { header('Location: portal.php',true,303); exit; }
    av7_login_page(isset($_GET['signedout'])?'Signed out of this isolated lab.':'');
} catch (Av7Error $e) { av7_fail($e->status,$e->getMessage()); }
catch (Throwable $e) { av7_fail(503,'The isolated lab is unavailable.'); }
