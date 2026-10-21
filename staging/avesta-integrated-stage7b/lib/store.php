<?php
declare(strict_types=1);
if (!defined('AV7_BOOTSTRAP')) { http_response_code(404); exit; }
function av7_read_json(string $path): array {
    if (is_link($path) || !is_file($path) || !is_readable($path) || filesize($path)>262144) throw new Av7Error(503,'Required isolated storage is unavailable.');
    $raw=file_get_contents($path);
    try { $data=json_decode($raw===false?'':$raw,true,32,JSON_THROW_ON_ERROR); }
    catch(Throwable $e) { throw new Av7Error(503,'Required isolated storage is unreadable. Nothing was replaced.'); }
    if (!is_array($data)) throw new Av7Error(503,'Required isolated storage is invalid.');
    return $data;
}
function av7_atomic(string $path,array $data): void {
    if (is_link($path)) throw new Av7Error(503,'Isolated storage cannot use symbolic links.');
    $body=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    if (strlen($body)>262144) throw new Av7Error(503,'Isolated storage limit reached.');
    $tmp=$path.'.tmp-'.bin2hex(random_bytes(6));
    $f=fopen($tmp,'x+b');
    if (!$f) throw new Av7Error(503,'Isolated storage could not be written.');
    try {
        chmod($tmp,0600); $written=fwrite($f,$body);
        if ($written!==strlen($body) || !fflush($f)) throw new Av7Error(503,'Isolated storage could not be written completely.');
        fclose($f); $f=null;
        if (!rename($tmp,$path)) throw new Av7Error(503,'Isolated storage could not be committed.');
    } finally { if (is_resource($f)) fclose($f); if (file_exists($tmp)) unlink($tmp); }
}
function av7_lock(string $name,callable $fn) {
    $file=$GLOBALS['av7_private'].'/.'.$name.'.lock';
    if (is_link($file)) throw new Av7Error(503,'Isolated storage lock is invalid.');
    $f=fopen($file,'c+b');
    if (!$f) throw new Av7Error(503,'Isolated storage lock is unavailable.');
    chmod($file,0600);
    try { if (!flock($f,LOCK_EX)) throw new Av7Error(503,'Isolated storage is busy.'); return $fn(); }
    finally { flock($f,LOCK_UN); fclose($f); }
}
function av7_prepare(array $c): void {
    umask(0077);
    $path=$c['private_dir']??'';
    $roots=$c['public_roots']??[];
    $doc=realpath($_SERVER['DOCUMENT_ROOT']??'');
    if (!$doc || !is_string($path) || $path==='' || $path[0]!=='/' || str_contains($path,'..') || !is_array($roots) || !$roots)
        throw new Av7Error(503,'Configure an absolute private directory outside every public web root.');
    if (!($GLOBALS['av7_local']??false) && (basename(dirname(__DIR__,3))!=='public_html' || realpath(dirname(__DIR__,2))!==$doc))
        throw new Av7Error(503,'The hosting folder does not match the isolated staging layout.');
    $parent=realpath(dirname($path));
    if (!$parent || is_link($path)) throw new Av7Error(503,'The private directory parent is unavailable or unsafe.');
    $resolved=rtrim($parent,'/').'/'.basename($path);
    if ($resolved!==rtrim($path,'/')) throw new Av7Error(503,'Use a normalized private directory path.');
    foreach (array_merge($roots,[$doc]) as $root) {
        $root=realpath($root);
        if (!$root || $resolved===$root || str_starts_with($resolved,$root.'/')) throw new Av7Error(503,'Private authentication storage must not be inside a public web root.');
    }
    $created=false;
    if (!is_dir($path)) {
        if (!mkdir($path,0700) && !is_dir($path)) throw new Av7Error(503,'The private directory could not be created. Ask hosting support to provide private writable storage.');
        $created=true;
    }
    if (!is_writable($path) || (fileperms($path)&0077)!==0) throw new Av7Error(503,'The isolated private directory must be writable only by its hosting account.');
    $GLOBALS['av7_private']=$path;
    av7_lock('initialize', function() use($path,$c,$created) {
        $marker=$path.'/instance.json';
        if (!is_file($marker)) {
            $entries=array_diff(scandir($path),['.','..','.initialize.lock']);
            // Never fill in missing state in a previously provisioned directory.
            if (!$created || $entries) throw new Av7Error(503,'The private lab is not initialized cleanly. No existing state was replaced.');
            $users=require __DIR__.'/seed-users.php';
            $records=require __DIR__.'/seed-records.php';
            if (!is_array($users) || count($users)!==6 || !is_array($records) || count($records)!==5) throw new Av7Error(503,'Synthetic seed files are invalid.');
            av7_atomic($path.'/users.json',$users);
            av7_atomic($path.'/records.json',$records);
            av7_atomic($path.'/attempts.json',[]);
            av7_atomic($path.'/events.json',[]);
            if (!mkdir($path.'/sessions',0700)) throw new Av7Error(503,'Private session storage could not be created.');
            av7_atomic($marker,['instance'=>$c['instance'],'created'=>time(),'records_sha256'=>hash_file('sha256',$path.'/records.json')]);
        }
        $m=av7_read_json($marker);
        if (($m['instance']??null)!==($c['instance']??null) || !is_int($m['created']??null) || $m['created']>time() || time()-$m['created']>604800)
            throw new Av7Error(503,'This isolated lab instance is unavailable or has reached its seven-day review limit.');
        if (!is_dir($path.'/sessions') || is_link($path.'/sessions') || (fileperms($path.'/sessions')&0077)!==0)
            throw new Av7Error(503,'Private session storage is unavailable or unsafe.');
        if (!is_file($path.'/records.json') || !hash_equals((string)($m['records_sha256']??''),(string)hash_file('sha256',$path.'/records.json')))
            throw new Av7Error(503,'The read-only record fixture changed or is missing. No replacement was created.');
        // Even corrupted security counters fail closed; do not silently reset them.
        av7_read_json($path.'/users.json'); av7_read_json($path.'/attempts.json'); av7_read_json($path.'/events.json');
    });
}
function av7_users(): array {
    $rows=av7_read_json($GLOBALS['av7_private'].'/users.json');
    if (!array_is_list($rows) || count($rows)>12) throw new Av7Error(503,'The isolated test-account store is invalid.');
    $ids=[]; $names=[];
    foreach($rows as $row) {
        if (!is_array($row) || !is_string($row['id']??null) || !str_starts_with($row['id'],'DEMO-U-') || !is_string($row['username']??null)
            || !is_string($row['name']??null) || !is_string($row['password_hash']??null) || isset($ids[$row['id']]) || isset($names[$row['username']]))
            throw new Av7Error(503,'The isolated test-account store is invalid.');
        $ids[$row['id']]=true; $names[$row['username']]=true;
    }
    return $rows;
}
function av7_records(): array { return av7_read_json($GLOBALS['av7_private'].'/records.json'); }
function av7_attempt_key(string $kind,string $value): string { return $kind.':'.hash_hmac('sha256',$value,$GLOBALS['av7_config']['key']); }
function av7_reserve_attempt(string $username): void {
    av7_lock('attempts',function() use($username) {
        $path=$GLOBALS['av7_private'].'/attempts.json'; $all=av7_read_json($path); $now=time();
        foreach($all as $k=>$r) {
            if (!is_array($r) || !is_int($r['first']??null) || !is_int($r['count']??null) || $r['first']>$now || $r['count']<1)
                throw new Av7Error(503,'Login-attempt state is unreadable.');
            if ($now-$r['first']>=900) unset($all[$k]);
        }
        $keys=[av7_attempt_key('account',$username)=>5,av7_attempt_key('remote',$_SERVER['REMOTE_ADDR']??'unknown')=>30];
        foreach($keys as $key=>$limit) if (($all[$key]['count']??0)>=$limit) {
            header('Retry-After: '.max(1,900-($now-$all[$key]['first'])));
            throw new Av7Error(429,'Too many sign-in attempts. Wait before trying again.');
        }
        if(count($all)>1020) throw new Av7Error(503,'Authentication capacity reached. Try later.');
        foreach($keys as $key=>$limit) {
            if(!isset($all[$key])) $all[$key]=['first'=>$now,'count'=>0];
            $all[$key]['count']++;
        }
        av7_atomic($path,$all);
    });
}
function av7_clear_account_attempts(string $username): void {
    av7_lock('attempts',function() use($username) {
        $path=$GLOBALS['av7_private'].'/attempts.json';$all=av7_read_json($path);
        unset($all[av7_attempt_key('account',$username)]);av7_atomic($path,$all);
    });
}
function av7_event(string $event,?string $id): void {
    av7_lock('events',function() use($event,$id) {
        $path=$GLOBALS['av7_private'].'/events.json';$all=av7_read_json($path);
        $all[]=['at'=>gmdate('c'),'event'=>$event,'test_user'=>$id];
        av7_atomic($path,array_slice($all,-128));
    });
}
function av7_start_session(array $c): void {
    if (session_status()!==PHP_SESSION_NONE) throw new Av7Error(503,'The lab cannot share another application session.');
    ini_set('session.use_cookies','1'); ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    ini_set('session.use_trans_sid','0'); ini_set('session.serialize_handler','php_serialize');
    ini_set('session.gc_maxlifetime','7200'); ini_set('session.gc_probability','1'); ini_set('session.gc_divisor','100');
    if (ini_get('session.use_cookies')!=='1' || ini_get('session.use_strict_mode')!=='1' || ini_get('session.use_only_cookies')!=='1' || ini_get('session.use_trans_sid')!=='0')
        throw new Av7Error(503,'The required session settings could not be applied.');
    session_name('AVESTA7B'.$c['instance']);
    session_save_path($GLOBALS['av7_private'].'/sessions');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/avesta-integrated-stage7b/','secure'=>!($GLOBALS['av7_local']??false),'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) throw new Av7Error(503,'The private session could not be started.');
    av7_csrf();
}
