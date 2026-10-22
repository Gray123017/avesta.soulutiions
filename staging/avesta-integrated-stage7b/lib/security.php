<?php
declare(strict_types=1);
if (!defined('AV7_BOOTSTRAP')) { http_response_code(404); exit; }
final class Av7Error extends RuntimeException {
    public int $status;
    public function __construct(int $status, string $message) { parent::__construct($message); $this->status=$status; }
}
function av7_headers(): void {
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'; worker-src 'none'");
}
function av7_request_guard(array $c): void {
    $host=$_SERVER['HTTP_HOST']??'';
    $local=($c['local_test']??false)===true && PHP_SAPI==='cli-server'
        && in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)
        && preg_match('/^127\.0\.0\.1:[0-9]{2,5}$/D',$host)===1;
    if (!$local && $host!=='staging.avesta.solutions') throw new Av7Error(403,'This isolated lab is restricted to the staging host.');
    if (!$local && !((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (int)($_SERVER['SERVER_PORT']??0)===443))
        throw new Av7Error(403,'HTTPS must be verified at the hosting server before this lab can run.');
    // Forwarded-Proto and forwarded-IP headers are deliberately not trusted.
    $script=$_SERVER['SCRIPT_NAME']??'';
    if (dirname($script)!=='/avesta-integrated-stage7b' || !in_array(basename($script),['signin.php','login.php','logout.php','records.php','portal.php','auth-api.php'],true))
        throw new Av7Error(403,'Use the documented isolated staging folder.');
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); throw new Av7Error(405,'Method not supported.'); }
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>4096) throw new Av7Error(413,'Request too large for this authentication lab.');
    if (($c['enabled']??false)!==true) throw new Av7Error(503,'The authentication lab is paused. Complete the private-staging setup before enabling it.');
    if (($c['staff_scope']??'')!=='assigned_only') throw new Av7Error(503,'The synthetic staff-access policy must be assigned_only.');
    $GLOBALS['av7_local']=$local;
}
function av7_origin(): string { return (($GLOBALS['av7_local']??false)?'http://':'https://').($_SERVER['HTTP_HOST']??''); }
function av7_same_origin_url(string $value): bool {
    $actual=parse_url(trim($value));
    $expected=parse_url(av7_origin());
    if (!is_array($actual) || !is_array($expected)) return false;
    $actualScheme=strtolower((string)($actual['scheme']??''));
    $expectedScheme=strtolower((string)($expected['scheme']??''));
    $actualHost=strtolower((string)($actual['host']??''));
    $expectedHost=strtolower((string)($expected['host']??''));
    $actualPort=isset($actual['port'])?(int)$actual['port']:($actualScheme==='https'?443:($actualScheme==='http'?80:0));
    $expectedPort=isset($expected['port'])?(int)$expected['port']:($expectedScheme==='https'?443:($expectedScheme==='http'?80:0));
    return $actualScheme!=='' && $actualScheme===$expectedScheme
        && $actualHost!=='' && hash_equals($expectedHost,$actualHost)
        && $actualPort===$expectedPort;
}
function av7_only(array $allowed): void {
    if (!in_array($_SERVER['REQUEST_METHOD']??'GET',$allowed,true)) { header('Allow: '.implode(', ',$allowed)); throw new Av7Error(405,'Method not supported.'); }
}
function av7_csrf(): string {
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function av7_post(): array {
    av7_only(['POST']);
    if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/x-www-form-urlencoded') throw new Av7Error(415,'Use the provided sign-in or sign-out form.');
    $origin=trim((string)($_SERVER['HTTP_ORIGIN']??''));
    $referer=trim((string)($_SERVER['HTTP_REFERER']??''));
    $originVerified=false; $refererVerified=false;
    if ($origin!=='') {
        if (!av7_same_origin_url($origin)) throw new Av7Error(403,'This request is not from the lab page.');
        $originVerified=true;
    } elseif ($referer!=='') {
        if (!av7_same_origin_url($referer)) throw new Av7Error(403,'This request is not from the lab page.');
        $refererVerified=true;
    }
    $fetchSite=strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')));
    if ($fetchSite!=='') {
        if (!in_array($fetchSite,['same-origin','same-site','none','cross-site'],true)) throw new Av7Error(403,'This request is not from the lab page.');
        if ($fetchSite==='cross-site') throw new Av7Error(403,'This request is not from the lab page.');
        if ($fetchSite==='same-site' && !$originVerified && !$refererVerified) throw new Av7Error(403,'This request is not from the lab page.');
    }
    $token=$_POST['csrf']??null;
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D',$token)!==1 || !hash_equals(av7_csrf(),$token))
        throw new Av7Error(403,'The form has expired. Reload the sign-in page and try again.');
    return $_POST;
}
function av7_rotate(): void {
    $_SESSION=[];
    if (!session_regenerate_id(true)) throw new Av7Error(503,'A fresh private session could not be created.');
    $_SESSION['csrf']=bin2hex(random_bytes(32));
}
function av7_principal(): ?array {
    if (!isset($_SESSION['uid'])) return null;
    $now=time(); $created=$_SESSION['created']??0; $seen=$_SESSION['seen']??0;
    $u=null;
    foreach (av7_users() as $row) if (($row['id']??null)===($_SESSION['uid']??null)) { $u=$row; break; }
    $valid=is_array($u) && ($u['active']??false)===true
        && in_array($u['role']??'', ['borrower','staff','admin','super_admin'],true)
        && is_string($u['version']??null) && ($u['version']??null)===($_SESSION['version']??null)
        && ($u['role']??null)===($_SESSION['role_snapshot']??null)
        && ($u['username']??null)===($_SESSION['username_snapshot']??null)
        && ($u['must_change']??false)===false
        && is_int($created) && is_int($seen) && $created>0 && $seen>0 && $seen>=$created
        && $created<=$now && $seen<=$now && $now-$seen<900 && $now-$created<7200;
    if (!$valid) { av7_rotate(); return null; }
    $_SESSION['seen']=$now;
    return ['id'=>$u['id'],'username'=>$u['username'],'name'=>$u['name'],'role'=>$u['role']];
}
function av7_require(): array {
    $u=av7_principal();
    if ($u===null) throw new Av7Error(401,'Sign in to the isolated lab to read its fictional records.');
    return $u;
}
function av7_can_read(array $u,array $r): bool {
    return match($u['role']??'') {
        'super_admin','admin'=>true,
        'staff'=>($r['assigned_to']??'')===$u['id'],
        'borrower'=>($r['owner_id']??'')===$u['id'],
        default=>false
    };
}
function av7_public_record(array $r): array {
    return array_intersect_key($r,array_flip(['id','kind','title','status','owner_label','assignee_label','sample_note']));
}
function av7_visible(array $u): array {
    return array_values(array_map('av7_public_record',array_filter(av7_records(),fn($r)=>av7_can_read($u,$r))));
}
function av7_record(array $u,string $id): array {
    if (preg_match('/^DEMO-(APP|TASK)-[0-9]{3}$/D',$id)!==1) throw new Av7Error(404,'Record not available.');
    foreach (av7_records() as $r) if (($r['id']??'')===$id && av7_can_read($u,$r)) return av7_public_record($r);
    throw new Av7Error(404,'Record not available.');
}
function av7_sign_in(string $username,string $password): bool {
    $username=strtolower(trim($username));
    if (strlen($username)>64 || strlen($password)>128) throw new Av7Error(400,'Use the supplied fictional lab account.');
    av7_reserve_attempt($username);
    $user=null;
    foreach (av7_users() as $u) if (($u['username']??'')===$username) { $user=$u; break; }
    $hash=$user['password_hash']??$GLOBALS['av7_config']['dummy_hash'];
    $matches=password_verify($password,$hash);
    $ok=$matches && is_array($user) && ($user['active']??false)===true
        && in_array($user['role']??'', ['borrower','staff','admin','super_admin'],true)
        && ($user['must_change']??false)===false && is_string($user['version']??null);
    if (!$ok) { av7_event('sign_in_failed',null); return false; }
    av7_event('sign_in_success',$user['id']);
    av7_clear_account_attempts($username);
    av7_rotate();
    $_SESSION['uid']=$user['id']; $_SESSION['version']=$user['version'];
    $_SESSION['role_snapshot']=$user['role']; $_SESSION['username_snapshot']=$user['username'];
    $_SESSION['created']=time(); $_SESSION['seen']=time();
    return true;
}
