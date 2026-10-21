<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
try {
    $b=av7_post();
    if ($_GET || array_diff(array_keys($b),['username','password','csrf'])) throw new Av7Error(400,'Unexpected sign-in fields.');
    if (!is_string($b['username']??null) || !is_string($b['password']??null)) throw new Av7Error(400,'Use the supplied fictional lab account.');
    if (av7_principal()!==null) throw new Av7Error(409,'Sign out before testing a different account.');
    if (!av7_sign_in($b['username'],$b['password'])) { http_response_code(401); av7_login_page('Sign-in unsuccessful. Check the disposable lab credentials.'); exit; }
    header('Location: portal.php',true,303);
} catch (Av7Error $e) { av7_fail($e->status,$e->getMessage()); }
catch (Throwable $e) { av7_fail(503,'Sign-in could not be completed.'); }
