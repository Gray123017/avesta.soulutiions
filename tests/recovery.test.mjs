import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync,copyFileSync,writeFileSync,rmSync} from 'node:fs';
import {join} from 'node:path';
import {tmpdir} from 'node:os';
import {spawnSync} from 'node:child_process';

const php=process.env.AVESTA_TEST_PHP || 'php';
test('Recovery codes: verification, single use, expiry, limits, session invalidation and admin fallback',
  {skip:spawnSync(php,['-v']).status!==0},()=>{
  const dir=mkdtempSync(join(tmpdir(),'avesta-recovery-'));
  try {
    for(const file of ['auth.php','recovery-lib.php'])copyFileSync(file,join(dir,file));
    writeFileSync(join(dir,'test.php'),`<?php
      $mail=[]; $deliver=true; $checks=0;
      function av_recovery_send(string $email,string $code,string $purpose):bool {
        global $mail,$deliver; $mail[]=[$email,$code,$purpose]; return $deliver;
      }
      require __DIR__.'/recovery-lib.php';
      function check($test,$label){global $checks;if(!$test)throw new Exception($label);$checks++;}
      function clearCodes(){av_recovery_save(['challenges'=>[],'rates'=>[]]);}
      foreach(['admin','staff','borrower'] as $role){
        $r=av_create_user($role.'test','Original-phrase-57','Test '.$role,$role,$role.'@example.test');
        check($r['ok'],'create '.$role);
      }
      $r=av_create_user('badmail','Original-phrase-57','Bad email','borrower','not an email');
      check(!$r['ok'],'reject invalid email');
      foreach(['admin','staff','borrower'] as $role){
        clearCodes(); $u=av_find_user($role.'test');
        $r=av_recovery_issue($u['username'],'verify',$u,$u['email']);
        check($r['ok'],'verification issued for '.$role);
        $code=end($mail)[1];
        check(!str_contains(file_get_contents(avStorePath(AV_EMAIL_RECOVERY_FILE)),$code),'code not stored in cleartext');
        check(!av_recovery_complete($r['challenge'],$code,'verify','', 'wrong-user')['ok'],'verification bound to user');
        check(av_recovery_complete($r['challenge'],$code,'verify','',$u['id'])['ok'],'verify '.$role);
        check(!av_recovery_complete($r['challenge'],$code,'verify','',$u['id'])['ok'],'verification single use');
        clearCodes(); $r=av_recovery_issue($u['email']);$code=end($mail)[1];
        check(av_recovery_complete($r['challenge'],$code,'reset','New-secure-phrase-74')['ok'],'email reset '.$role);
        check(password_verify('New-secure-phrase-74',av_find_user($u['username'])['pass_hash']),'new password stored');
        check(!av_recovery_complete($r['challenge'],$code,'reset','Other-phrase-58')['ok'],'reset single use');
      }
      clearCodes(); $before=count($mail); $r=av_recovery_issue('missing@example.test');
      check($r['ok'] && strlen($r['challenge'])===32,'neutral unknown-account response');
      check(count($mail)===$before,'no message to unregistered recipient');
      clearCodes();$r=av_recovery_issue('borrower@example.test');$code=end($mail)[1];
      $again=av_recovery_issue('borrower@example.test');
      check(count($mail)===$before+1 && $again['ok'],'resend cooldown');
      for($i=0;$i<5;$i++)check(!av_recovery_complete($r['challenge'],'00000000','reset','Valid-new-phrase-91')['ok'],'wrong code');
      check(!av_recovery_complete($r['challenge'],$code,'reset','Valid-new-phrase-91')['ok'],'five-guess limit');
      clearCodes();$r=av_recovery_issue('borrower@example.test');$code=end($mail)[1];
      $state=avStoreRead(AV_EMAIL_RECOVERY_FILE);$state['challenges'][$r['challenge']]['expires']=time()-1;av_recovery_save($state);
      check(!av_recovery_complete($r['challenge'],$code,'reset','Valid-new-phrase-91')['ok'],'expired code');
      clearCodes();av_login('borrowertest','New-secure-phrase-74');$oldSession=$_SESSION;
      $r=av_recovery_issue('borrower@example.test');$code=end($mail)[1];
      check(av_recovery_complete($r['challenge'],$code,'reset','Changed-secure-phrase-28')['ok'],'reset with existing session');
      $_SESSION=$oldSession;check(av_user()===null,'old session revoked');
      clearCodes();$u=av_find_user('borrowertest');$r=av_recovery_issue($u['email']);$code=end($mail)[1];
      $fallback=av_admin_reset_password($u['id'],'admintest');
      check($fallback['ok'],'administrator fallback');
      check(password_verify($fallback['temporary_password'],av_find_user('borrowertest')['pass_hash']),'temporary password works');
      check(av_find_user('borrowertest')['must_change'],'temporary password requires change');
      check(!av_recovery_complete($r['challenge'],$code,'reset','Valid-new-phrase-91')['ok'],'admin reset revokes earlier code');
      clearCodes();$sent=count($mail);
      for($i=0;$i<6;$i++){
        $state=avStoreRead(AV_EMAIL_RECOVERY_FILE) ?: ['challenges'=>[],'rates'=>[]];
        foreach($state['rates'] as &$rate)$rate['last']=time()-61;unset($rate);av_recovery_save($state);
        $hourly=av_recovery_issue('admin@example.test');check($hourly['ok'],'neutral hourly limit');
      }
      check(count($mail)===$sent+5,'five requests per account per hour');
      clearCodes();$u=av_find_user('stafftest');
      $users=av_load_users();foreach($users as &$target)if($target['id']===$u['id'])$target['active']=false;unset($target);av_save_users($users);
      $sent=count($mail);$disabled=av_recovery_issue('staff@example.test');check($disabled['ok'] && count($mail)===$sent,'disabled accounts cannot reset');
      foreach($users as &$target)if($target['id']===$u['id'])$target['active']=true;unset($target);av_save_users($users);
      clearCodes();$deliver=false;$u=av_find_user('stafftest');
      $r=av_recovery_issue($u['username'],'verify',$u,$u['email']);
      check(!$r['ok'],'verification sending failure reported');
      check(count(avStoreRead(AV_EMAIL_RECOVERY_FILE)['challenges'])===0,'undelivered code invalidated');
      $r=av_recovery_issue('admin@example.test');check($r['ok'],'reset send failure neutral');
      $events=av_audit_read(200);
      check(count(array_filter($events,fn($e)=>$e['action']==='user.password_reset'))===1,'fallback audit recorded');
      check(count(array_filter($events,fn($e)=>$e['action']==='user.self_password_reset'))===4,'email reset audit recorded');
      check(count(array_filter($events,fn($e)=>$e['action']==='recovery.mail_failed'))===2,'delivery failure audit recorded');
      echo json_encode(['checks'=>$checks]);
    `);
    const r=spawnSync(php,['-d','session.save_path='+dir,join(dir,'test.php')],{encoding:'utf8'});
    assert.equal(r.status,0,r.stderr+r.stdout);
    assert.ok(JSON.parse(r.stdout).checks>=45);
  } finally {rmSync(dir,{recursive:true,force:true});}
});
