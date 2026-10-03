import assert from 'node:assert/strict';
const base='http://127.0.0.1:8080';
for(let i=0;i<20;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
for(const p of ['/index.php','/index.php?page=lending','/index.php?page=it','/index.php?page=calculator','/loans.php','/it.php','/login.php','/legal.php?p=privacy']){
 const r=await fetch(base+p); assert.equal(r.status,200,p); const body=await r.text();assert.ok(!/Fatal error|Parse error|Warning:/.test(body),p);
}
const it=await fetch(base+'/it.php',{redirect:'manual'});assert.equal(it.status,302);assert.equal(it.headers.get('location'),'/index.php?page=it');
const apply=await fetch(base+'/apply.php',{redirect:'manual'});assert.equal(apply.status,302);assert.equal(apply.headers.get('location'),'/login.php?next=%2Fapply.php');
console.log('Public PHP routes and anonymous application sign-in gate passed. No account or customer records created.');
