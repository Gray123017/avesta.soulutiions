<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
try {
    av7_only(['GET','HEAD']);
    if (array_diff(array_keys($_GET),['view','id'])) throw new Av7Error(400,'Identity and ownership cannot be selected by a portal parameter.');
    $u=av7_require();
    $view=$_GET['view']??'overview';
    if (!is_string($view) || !in_array($view,['overview','applications','support','account','privileged'],true)) throw new Av7Error(400,'Workspace section unavailable.');
    if($view==='privileged' && $u['role']!=='super_admin') throw new Av7Error(403,'This read-only view requires the distinct super_admin test identity.');
    $record=null;
    if (array_key_exists('id',$_GET)) {
        if (!is_string($_GET['id']) || isset($_GET['view'])) throw new Av7Error(404,'Record not available.');
        $record=av7_record($u,$_GET['id']);
    }
    $rows=av7_visible($u);
    av7_workspace_page($u,$rows,$view,$record);
} catch(Av7Error $e) { av7_fail($e->status,$e->getMessage()); }
catch(Throwable $e) { av7_fail(503,'The permitted-record workspace is unavailable. No production data is connected.'); }
