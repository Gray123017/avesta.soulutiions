import {test} from 'node:test';
import assert from 'node:assert/strict';
import worker from '../dist/server/index.js';
import {environment,form,call,jsonOptions} from './helpers.mjs';
import {today} from '../src/domain.js';
test('Every page/legacy route and packaged asset is served',async()=>{
 const env=environment();
 for(const path of ['/','/lending','/how-it-works','/calculator','/currency','/apply','/it','/about','/contact','/portal','/privacy','/terms','/complaints']){const res=await call(worker,env,path);assert.equal(res.status,200,path);assert.match(res.headers.get('content-type'),/text\/html/);assert.match(await res.text(),/Avesta/);}
 for(const path of ['/app.js','/styles.css','/domain.js','/services.json','/help.json','/images/how-it-works.webp','/icons/icon-192.png'])assert.equal((await call(worker,env,path)).status,200,path);
 assert.equal((await call(worker,env,'/loans.php')).headers.get('location'),'https://review.invalid/lending');
 assert.equal((await call(worker,env,'/legal.php?p=terms')).headers.get('location'),'https://review.invalid/terms');
 assert.equal((await call(worker,env,'/records_data.json')).status,404);
 assert.equal((await call(worker,env,'/unknown')).status,404);
});
test('Private records require identity and reject cross-origin mutations',async()=>{
 const env=environment();
 assert.equal((await call(worker,env,'/api/applications',{},null)).status,401);
 assert.equal((await call(worker,env,'/api/applications',{method:'POST',headers:{origin:'https://malicious.invalid'},body:form()})).status,403);
});
test('Submission persists records and documents, isolates users, and records activity',async()=>{
 const env=environment(),res=await call(worker,env,'/api/applications',{method:'POST',body:form()});
 assert.equal(res.status,201);const id=(await res.json()).id;
 assert.match(id,/^AV-/);assert.equal(env.objects.size,2);
 const list=await(await call(worker,env,'/api/applications')).json();assert.equal(list.records.length,1);assert.equal(list.records[0].total,120000);
 const other=await(await call(worker,env,'/api/applications',{},'other-owner')).json();assert.equal(other.records.length,0);
 const documents=await(await call(worker,env,'/api/applications/'+id+'/documents')).json();
 const doc=documents.documents[0];assert.equal((await call(worker,env,'/api/documents/'+doc.id,{},'other-owner')).status,404);
 assert.equal((await call(worker,env,'/api/documents/'+doc.id)).status,200);
 const audit=await(await call(worker,env,'/api/audit')).json();assert.equal(audit.events.length,2);
});
test('Missing/forged documents never create partial applications',async()=>{
 const env=environment();const f=form();f.delete('nrcBack');
 assert.equal((await call(worker,env,'/api/applications',{method:'POST',body:f})).status,400);assert.equal(env.objects.size,0);
 const bad=form();bad.set('nrcFront',new File(['fake'], 'bad.pdf',{type:'application/pdf'}));
 assert.equal((await call(worker,env,'/api/applications',{method:'POST',body:bad})).status,400);assert.equal(env.sqlite.prepare('SELECT COUNT(*) AS n FROM applications').get().n,0);
});
test('Repayment lifecycle rejects future dates and overpayments, settles and recalculates',async()=>{
 const env=environment(),id=(await(await call(worker,env,'/api/applications',{method:'POST',body:form()})).json()).id;
 assert.equal((await call(worker,env,'/api/applications/'+id+'/payments',jsonOptions('POST',{amount:50,date:today()}))).status,400);
 assert.equal((await call(worker,env,'/api/applications/'+id,jsonOptions('PATCH',{status:'Disbursed',disbursed:today()}))).status,200);
 assert.equal((await call(worker,env,'/api/applications/'+id+'/payments',jsonOptions('POST',{amount:1201,date:today()}))).status,400);
 assert.equal((await call(worker,env,'/api/applications/'+id+'/payments',jsonOptions('POST',{amount:5,date:'2099-01-01'}))).status,400);
 assert.equal((await call(worker,env,'/api/applications/'+id+'/payments',jsonOptions('POST',{amount:600,date:today(),method:'Cash'}))).status,201);
 assert.equal((await call(worker,env,'/api/applications/'+id+'/payments',jsonOptions('POST',{amount:600,date:today(),method:'Cash'}))).status,201);
 const row=env.sqlite.prepare('SELECT status FROM applications WHERE id=?').get(id);assert.equal(row.status,'Repaid');
 const payment=env.sqlite.prepare('SELECT id FROM payments WHERE application_id=? LIMIT 1').get(id);
 assert.equal((await call(worker,env,'/api/payments/'+payment.id,{method:'DELETE'},'other-owner')).status,404);
 assert.equal((await call(worker,env,'/api/payments/'+payment.id,{method:'DELETE'})).status,200);
 assert.equal(env.sqlite.prepare('SELECT status FROM applications WHERE id=?').get(id).status,'Disbursed');
});
