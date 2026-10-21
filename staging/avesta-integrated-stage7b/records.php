<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
try {
    av7_only(['GET','HEAD']);
    if (array_diff(array_keys($_GET),['id'])) throw new Av7Error(400,'Record ownership and roles cannot be selected in a URL.');
    $u=av7_require();
    if(isset($_GET['id']) && !is_string($_GET['id'])) throw new Av7Error(404,'Record not available.');
    $rows=isset($_GET['id'])?[av7_record($u,$_GET['id'])]:av7_visible($u);
    av7_record_page($u,$rows,isset($_GET['id']));
} catch (Av7Error $e) { av7_fail($e->status,$e->getMessage()); }
catch (Throwable $e) { av7_fail(503,'Permitted sample records could not be read.'); }
