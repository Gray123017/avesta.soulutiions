<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
try {
    $b=av7_post();
    if ($_GET || array_diff(array_keys($b),['csrf'])) throw new Av7Error(400,'Unexpected sign-out fields.');
    $u=av7_principal();
    // Always invalidate, even if bounded event logging is unavailable.
    try { if ($u!==null) av7_event('sign_out',$u['id']); } catch(Throwable $ignored) {}
    $_SESSION=[];
    if (!session_destroy()) throw new Av7Error(503,'Server-side sign-out could not be completed. Keep the lab paused until private session storage is checked.');
    setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/avesta-integrated-stage7b/','secure'=>!($GLOBALS['av7_local']??false),'httponly'=>true,'samesite'=>'Strict']);
    header('Location: signin.php?signedout=1',true,303);
} catch (Av7Error $e) { av7_fail($e->status,$e->getMessage()); }
catch (Throwable $e) { av7_fail(503,'Sign-out could not be completed. Close this browser session.'); }
