import assert from 'node:assert/strict';
const base='http://127.0.0.1:8080';
for(let i=0;i<20;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
for(const p of ['/index.php','/index.php?page=lending','/index.php?page=it','/index.php?page=calculator','/loans.php','/it.php','/login.php','/legal.php?p=privacy']){
 const r=await fetch(base+p); assert.equal(r.status,200,p); const body=await r.text();assert.ok(!/Fatal error|Parse error|Warning:/.test(body),p);
}
const it=await fetch(base+'/it.php',{redirect:'manual'});assert.equal(it.status,302);assert.equal(it.headers.get('location'),'/index.php?page=it');
const apply=await fetch(base+'/apply.php',{redirect:'manual'});assert.equal(apply.status,302);assert.equal(apply.headers.get('location'),'/login.php?next=%2Fapply.php');
// The portal is served here, at the web root, never from /app/ — so it must
// send people to /login.php. '../login.php' points above the document root and
// only reaches the sign-in page because browsers clamp the '..' back.
const portal=await fetch(base+'/portal.php',{redirect:'manual'});assert.equal(portal.status,302);assert.equal(portal.headers.get('location'),'/login.php?next=%2Fportal.php');
// An action this server did not carry out must never answer ok:true. A browser
// re-issues a POST as a GET when it follows a redirect, so a submission that
// came back 200 ok:true would tell an applicant their loan application was in
// when nothing had been stored.
const submit=await fetch(base+'/api.php?action=submit');assert.equal(submit.status,405,'GET ?action=submit');assert.equal((await submit.json()).ok,false);
const bogus=await fetch(base+'/api.php?action=noSuchAction');assert.equal(bogus.status,404);assert.equal((await bogus.json()).ok,false);
const ping=await fetch(base+'/api.php?action=ping');assert.equal(ping.status,200);
const pinged=await ping.json();assert.equal(pinged.ok,true);
for(const f of ['php','records','post_max_size','memory_limit','docs_writable'])assert.ok(!(f in pinged),`ping must not tell a stranger ${f}`);
console.log('Public PHP routes and anonymous application sign-in gate passed. No account or customer records created.');
