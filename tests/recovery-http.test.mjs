import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync,copyFileSync,cpSync,writeFileSync,readFileSync,rmSync} from 'node:fs';
import {join} from 'node:path';
import {tmpdir} from 'node:os';
import {spawn,spawnSync} from 'node:child_process';
import {createServer} from 'node:net';
import {Script} from 'node:vm';
const php=process.env.AVESTA_TEST_PHP||'php';
test('Recovery pages and administrator fallback work through real HTTP forms',
 {skip:spawnSync(php,['-v']).status!==0,timeout:30000},async()=>{
 const dir=mkdtempSync(join(tmpdir(),'avesta-recovery-http-'));let server;
 try{
  for(const file of ['auth.php','api.php','login.php','recovery.php','recovery-lib.php','portal.php','apply.php'])copyFileSync(file,join(dir,file));
  cpSync('app',join(dir,'app'),{recursive:true});
  writeFileSync(join(dir,'fixture.php'),`<?php require __DIR__.'/auth.php'; av_create_user('admintest','Original-phrase-57','Test Admin','admin');`);
  writeFileSync(join(dir,'mail.php'),`<?php function av_recovery_send(string $email,string $code,string $purpose):bool {
    file_put_contents(__DIR__.'/outbox.json',json_encode(['email'=>$email,'code'=>$code,'purpose'=>$purpose])); return true;
  }`);
  const ini=['-d','session.save_path='+dir];
  assert.equal(spawnSync(php,[...ini,join(dir,'fixture.php')]).status,0);
  const socket=createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));const port=socket.address().port;await new Promise(r=>socket.close(r));
  const base='http://127.0.0.1:'+port;
  server=spawn(php,[...ini,'-d','auto_prepend_file='+join(dir,'mail.php'),'-S','127.0.0.1:'+port,'-t',dir],{stdio:'ignore'});
  const jar={cookie:''};
  async function request(path,data,client=jar,json=false){
    const res=await fetch(base+path,{redirect:'manual',headers:{...(client.cookie?{Cookie:client.cookie}:{}),...(data?{'Content-Type':json?'application/json':'application/x-www-form-urlencoded'}:{})},...(data?{method:'POST',body:json?JSON.stringify(data):new URLSearchParams(data)}:{})});
    for(const c of res.headers.getSetCookie()){if(c.startsWith('AVESTASESS='))client.cookie=c.split(';')[0];}
    return res;
  }
  for(let i=0;i<40;i++){try{await request('/login.php');break;}catch{await new Promise(r=>setTimeout(r,50));}}
  const csrf=html=>{const m=html.match(/name="csrf" value="([a-f0-9]+)"/);assert.ok(m);return m[1];};
  const body=async res=>{const s=await res.text();assert.ok(!/Fatal error|Warning:|Parse error/.test(s),s.slice(0,500));return s;};
  let html=await body(await request('/login.php?mode=up'));
  assert.ok(html.includes('name="email"'));
  let res=await request('/login.php?mode=up',{do:'up',username:'borrowertest',name:'Test Borrower',email:'borrower@example.test',password:'Original-phrase-57',password2:'Original-phrase-57'});
  assert.equal(res.status,302);assert.equal(res.headers.get('location'),'recovery.php?setup=1');
  html=await body(await request('/recovery.php?setup=1'));
  res=await request('/recovery.php?setup=1',{action:'request',csrf:'bad',email:'borrower@example.test',current:'Original-phrase-57'});
  assert.match(await body(res),/form expired/);
  html=await body(await request('/recovery.php?setup=1',{action:'request',csrf:csrf(html),email:'borrower@example.test',current:'Original-phrase-57'}));
  assert.ok(html.includes('name="code"'));
  let code=JSON.parse(readFileSync(join(dir,'outbox.json'),'utf8')).code;
  assert.ok(!html.includes(code));
  html=await body(await request('/recovery.php?setup=1',{action:'complete',csrf:csrf(html),code}));
  assert.match(html,/Email verified/);
  await request('/login.php',{do:'in',username:'bad',password:'wrong'}, {cookie:''}); // no effect on this account
  const anonymous={cookie:''};
  html=await body(await request('/recovery.php',null,anonymous));
  html=await body(await request('/recovery.php',{action:'request',csrf:csrf(html),identifier:'borrower@example.test'},anonymous));
  code=JSON.parse(readFileSync(join(dir,'outbox.json'),'utf8')).code;
  assert.match(html,/If an account exists for that email or username and has a verified recovery email/);
  assert.ok(!html.includes(code));
  html=await body(await request('/recovery.php',{action:'complete',csrf:csrf(html),code,password:'Changed-secure-phrase-28',password2:'Changed-secure-phrase-28'},anonymous));
  assert.match(html,/Password reset complete/);
  assert.equal((await request('/portal.php')).status,302); // previous borrower session was revoked
  res=await request('/login.php',{do:'in',username:'borrowertest',password:'Changed-secure-phrase-28'},anonymous);
  assert.equal(res.status,302);
  assert.equal((await request('/api.php?action=accounts',null,anonymous)).status,403);
  const admin={cookie:''};
  assert.equal((await request('/login.php',{do:'in',username:'admintest',password:'Original-phrase-57'},admin)).status,302);
  const data=await (await request('/api.php?action=accounts',null,admin)).json();
  const borrower=data.accounts.find(u=>u.username==='borrowertest');
  assert.ok(borrower.email_verified_at);assert.equal(borrower.email,'borrower@example.test');assert.equal(borrower.pass_hash,undefined);
  assert.ok(data.recovery_events.some(e=>e.action==='user.self_password_reset'));
  res=await request('/api.php?action=resetPassword',{id:borrower.id},admin,true);
  const reset=await res.json();assert.equal(reset.ok,true);assert.ok(reset.temporary_password);
  res=await request('/login.php',{do:'in',username:'borrowertest',password:reset.temporary_password},anonymous);
  assert.equal(res.status,302);
  assert.match((await request('/apply.php',null,anonymous)).headers.get('location'),/change=1/);
  html=await body(await request('/portal.php',null,admin));
  assert.ok(html.includes('id="acct-temp-output"'));
  assert.ok(html.includes('id="acct-recovery-history"'));
  assert.ok(html.includes('Recovery email'));
  for(const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi))if(!/\bsrc=|type=["']module/.test(match[1]))new Script(match[2]);
 }finally{
  if(server){const closed=new Promise(r=>server.once('exit',r));server.kill();await closed;}
  rmSync(dir,{recursive:true,force:true});
 }
});
