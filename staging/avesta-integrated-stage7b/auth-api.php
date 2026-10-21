<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
try {
    av7_only(['GET','HEAD']);
    if (array_diff(array_keys($_GET),['action','id'])) throw new Av7Error(400,'Unexpected query parameters.');
    $action=$_GET['action']??'me';
    if (!is_string($action) || !in_array($action,['me','records','record','privileged-summary'],true)) throw new Av7Error(404,'Read-only operation not available.');
    if($action!=='record' && isset($_GET['id'])) throw new Av7Error(400,'Unexpected record identifier.');
    $u=av7_require();
    if ($action==='me') $data=['ok'=>true,'user'=>$u,'record_access'=>match($u['role']){'borrower'=>'own fixtures only','staff'=>'assigned fixtures only',default=>'all fixtures, read-only'}];
    elseif ($action==='records') $data=['ok'=>true,'records'=>av7_visible($u)];
    elseif ($action==='record') {
        if (!is_string($_GET['id']??null)) throw new Av7Error(404,'Record not available.');
        $data=['ok'=>true,'record'=>av7_record($u,$_GET['id'])];
    } else {
        if($u['role']!=='super_admin') throw new Av7Error(403,'This read-only check requires the distinct super_admin test identity.');
        $data=['ok'=>true,'role'=>'super_admin','read_only_fixture_count'=>count(av7_records()),'account_mutations_enabled'=>false];
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_THROW_ON_ERROR);
} catch (Av7Error $e) { av7_fail($e->status,$e->getMessage()); }
catch (Throwable $e) { av7_fail(503,'Read-only authentication service unavailable.'); }
