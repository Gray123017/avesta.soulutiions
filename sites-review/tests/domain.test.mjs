import {test} from 'node:test';
import assert from 'node:assert/strict';
import {quote,schedule,validateApplication,loanBook,escapeHTML} from '../src/domain.js';
import {validData} from './helpers.mjs';
test('Published flat rates and integer-ngwee schedules stay exact',()=>{
 const totals=[115000,120000,125000,130000];
 for(let week=1;week<=4;week++){const q=quote(1000,week,'Weekly');assert.equal(q.total,totals[week-1]);assert.equal(q.instalments.reduce((a,b)=>a+b,0),q.total);}
 for(const amount of [0.01,1.01,1000.03,1599.99])for(const w of [1,2,3,4])for(const f of ['Weekly','Bi-weekly','Monthly','Lump sum']){const q=quote(amount,w,f);assert.equal(q.instalments.reduce((a,b)=>a+b,0),q.total);}
 assert.deepEqual(schedule(1000,3,'Bi-weekly','2026-10-01').map(r=>r.date),['2026-10-15','2026-10-22']);
 assert.throws(()=>quote(1000,0));assert.throws(()=>quote(-5,2));assert.throws(()=>quote(Infinity,2));
});
test('Application identity, consent and conditional security are validated',()=>{
 assert.equal(validateApplication(validData).total,120000);
 for(const invalid of [{nrc:'broken'},{phone:'123'},{dob:'2015-01-01'},{email:'bad'},{consent:false},{signature:'Other person'},{security:'Collateral',securityDetails:''}])assert.throws(()=>validateApplication({...validData,...invalid}));
});
test('Loan book counts active outstanding/overdue without fictitious pending debt',()=>{
 const r=[{total:120000,paid:20000,weeks:2,status:'Disbursed',disbursed:'2026-09-01'},{total:120000,paid:120000,weeks:2,status:'Repaid',disbursed:'2026-09-01'},{total:999999,paid:0,weeks:4,status:'Pending'}];
 const b=loanBook(r,'2026-10-01');assert.equal(b.balance,100000);assert.equal(b.collected,140000);assert.equal(b.overdue,100000);assert.equal(b.overdueCount,1);
 assert.equal(escapeHTML('<script>"&'), '&lt;script&gt;&quot;&amp;');
});
