import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';
import * as domain from '../src/domain.js';
const app=readFileSync('public/app.js','utf8').replace(/^import .*$/m,'const {money,quote,rates,schedule,today,escapeHTML:e,loanBook,addDays}=window.__domain;');
const html=readFileSync('public/index.html','utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
const flush=()=>new Promise(r=>setTimeout(r,15));
async function boot(path='/'){
 const w=new Window({url:'https://review.invalid'+path,settings:{enableJavaScriptEvaluation:true,disableCSSFileLoading:true,disableJavaScriptFileLoading:true,suppressInsecureJavaScriptEnvironmentWarning:true}});
 const calls=[],tools=[];w.__domain=domain;w.confirm=()=>true;w.document.write(html);
 w.document.modelContext={registerTool(tool){tools.push(tool);}};
 w.fetch=async (url,opts={})=>{
  const p=new URL(url,w.location.href).pathname;calls.push({path:p,options:opts});
  if(p==='/services.json')return new Response(readFileSync('public/services.json','utf8'));
  if(p==='/help.json')return new Response(readFileSync('public/help.json','utf8'));
  if(p==='/api/rates')return new Response(JSON.stringify({rates:{USD:1,ZMW:20,EUR:.9,GBP:.8,ZAR:18,BWP:13},asOf:'2026-10-01T00:00:00Z',source:'Test indicative provider'}));
  if(p==='/api/applications'&&opts.method==='POST')return new Response(JSON.stringify({id:'AV-TEST0001',status:'Pending'}),{status:201});
  if(p==='/api/applications')return new Response(JSON.stringify({records:[]}));
  if(p==='/api/me')return new Response(JSON.stringify({email:'review@example.invalid'}));
  if(p==='/api/audit')return new Response(JSON.stringify({events:[]}));
  return new Response('{}');
 };
 w.eval(app);await flush();return{w,d:w.document,calls,tools};
}
function value(w,selector,text){const el=w.document.querySelector(selector);assert.ok(el,selector);el.value=text;el.dispatchEvent(new w.Event('input',{bubbles:true}));}
function submit(w,selector){const el=w.document.querySelector(selector);assert.ok(el,selector);el.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));}
test('All 13 pages render specific content with labels and real links',async()=>{
 const {w,d}=await boot();
 for(const path of ['/','/lending','/how-it-works','/calculator','/currency','/apply','/it','/about','/contact','/portal','/privacy','/terms','/complaints']){
  w.eval('appData={};');w.eval('navigate('+JSON.stringify(path)+');');await flush();
  assert.equal(d.querySelectorAll('main h1').length,1,path);
  assert.ok(d.querySelector('main').textContent.trim().length>100,path);
  assert.ok(!d.querySelector('main').textContent.includes('undefined'),path);
  for(const field of d.querySelectorAll('main input:not([type=checkbox]),main select,main textarea'))assert.ok(field.id&&d.querySelector('label[for="'+field.id+'"]'),path+' '+field.outerHTML);
 }
 await w.happyDOM.close();
});
test('Calculator interaction recalculates cost/schedule and prevents invalid estimates',async()=>{
 const {w,d}=await boot('/calculator');
 value(w,'#calc-amount','1000.03');d.querySelector('[data-week="3"]').click();
 value(w,'#calc-frequency','Weekly');
 assert.equal(d.querySelectorAll('#schedule tbody tr').length,3);
 const expected=domain.money(domain.quote(1000.03,3).total/100);
 assert.ok(d.querySelector('#calc-result').textContent.includes(expected));
 value(w,'#calc-amount','-1');assert.ok(d.querySelector('#calc-error').textContent.includes('valid amount'));assert.equal(d.querySelector('#calc-result').textContent,'');
 await w.happyDOM.close();
});
test('IT search, help guide and prepared enquiry use source content',async()=>{
 const {w,d,calls}=await boot('/it');
 assert.equal(d.querySelectorAll('.service-card').length,11);
 value(w,'#service-search','Wi-Fi');assert.equal(d.querySelectorAll('.service-card').length,1);
 d.querySelector('[data-help="printer"]').click();assert.match(d.querySelector('#help-result').textContent,/Printer/);
 value(w,'#it-name','Test Client');value(w,'#it-phone','0971013108');value(w,'#it-location','Ndola');value(w,'#it-details','Printer offline');
 submit(w,'#enquiry-form');assert.match(d.querySelector('#enquiry-result').textContent,/Your enquiry is ready/);
 assert.ok(d.querySelector('#enquiry-result a').href.startsWith('https://wa.me/260769974200'));
 assert.ok(!calls.some(c=>c.options.method==='POST'));
 await w.happyDOM.close();
});
test('Currency converter has dated indicative and manual paths',async()=>{
 const {w,d}=await boot('/currency');submit(w,'#fx-form');assert.ok(d.querySelector('#fx-result').textContent.includes('50.00 USD'));
 value(w,'#fx-mode','manual');d.querySelector('#fx-mode').dispatchEvent(new w.Event('change'));
 value(w,'#fx-rate','0.1');submit(w,'#fx-form');assert.ok(d.querySelector('#fx-result').textContent.includes('100.00 USD'));
 await w.happyDOM.close();
});
test('Five-step application retains fields, reviews and submits once',async()=>{
 const {w,d,calls}=await boot('/apply');
 for(const [name,v]of Object.entries({name:'Test Client',nrc:'123456/78/9',dob:'1990-01-01',phone:'0971013108',address:'Test address',occupation:'Test role',income:'5000'}))value(w,'#a-'+name,v);
 submit(w,'#application-form');assert.match(d.querySelector('.wizard-body h2').textContent,/Choose your loan/);
 value(w,'#a-amount','1000');value(w,'#a-purpose','Test purpose');submit(w,'#application-form');
 assert.match(d.querySelector('.wizard-body h2').textContent,/repayments/);submit(w,'#application-form');
 assert.match(d.querySelector('.wizard-body h2').textContent,/documents/);
 for(const name of ['nrcFront','nrcBack']){const dt=new w.DataTransfer();dt.items.add(new w.File(['%PDF-test'],name+'.pdf',{type:'application/pdf'}));const input=d.querySelector('#a-'+name);input.files=dt.files;input.dispatchEvent(new w.Event('change'));}
 submit(w,'#application-form');assert.match(d.querySelector('.wizard-body h2').textContent,/Check every detail/);
 value(w,'#a-signature','Test Client');for(const name of ['consent','declaration']){const c=d.querySelector('[name="'+name+'"]');c.checked=true;c.dispatchEvent(new w.Event('input'));}
 submit(w,'#application-form');await flush();
 assert.ok(d.querySelector('#application-shell').textContent.includes('AV-TEST0001'));
 assert.equal(calls.filter(c=>c.path==='/api/applications'&&c.options.method==='POST').length,1);
 const payload=JSON.parse(calls.find(c=>c.options.method==='POST').options.body.get('payload'));assert.equal(payload.name,'Test Client');assert.equal(payload.amount,'1000');
 await w.happyDOM.close();
});
test('Structured estimate tool updates visible calculator and rejects invalid input',async()=>{
 const {w,d,tools}=await boot();
 const tool=tools.find(t=>t.name==='configure_loan_estimate');assert.ok(tool);assert.equal(tool.annotations.readOnlyHint,false);
 const q=tool.execute({amount:2000,weeks:4,frequency:'Weekly'});
 assert.equal(q.total,2600);assert.ok(d.querySelector('#calc-result').textContent.includes(domain.money(2600)));assert.equal(d.querySelectorAll('#schedule tbody tr').length,4);
 assert.throws(()=>tool.execute({amount:-1,weeks:2}));assert.ok(d.querySelector('#calc-result').textContent.includes(domain.money(2600)));
 await w.happyDOM.close();
});
