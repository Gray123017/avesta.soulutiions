'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {classify,parseMessage,mergeEvidence,extractDate,importRegister,validDate}=require('../src/core/model.cjs');
const {reminderPlan,calendarEvent,ics}=require('../src/core/reminders.cjs');
const {isSafeEvidenceUrl,safeHttps}=require('../src/core/catalog.cjs');
const {validateClient,authorize,GoogleClient}=require('../src/core/google.cjs');
const {Store}=require('../src/core/store.cjs');
const {spreadsheet,reportHTML}=require('../src/core/exports.cjs');
const message=(id,subject,body,date='2026-10-17',from='Billing <billing@anthropic.com>')=>({id,internalDate:String(Date.parse(date+'T12:00:00Z')),payload:{mimeType:'text/plain',headers:[{name:'From',value:from},{name:'Subject',value:subject}],body:{data:Buffer.from(body).toString('base64url')}}});
test('failure, future cancellation, refund and newsletter unsubscribe remain distinct',()=>{
  assert.equal(classify('Your subscription has been cancelled.'),'cancelled');
  assert.equal(classify('Your subscription is canceled.'),'cancelled');
  assert.equal(classify('Another unsuccessful payment attempt could result in cancellation.'),'payment_failed');
  assert.equal(classify('Your services could be suspended unless payment is made.'),'at_risk');
  assert.equal(classify('Your subscription could be cancelled.'),'at_risk');
  assert.equal(classify('Your refund has been issued.'),'refund');
  assert.equal(classify('Unsubscribe from these emails. Cancel your subscription in settings.'),'unknown');
  assert.equal(parseMessage(message('1','Weekly newsletter','Subscribe to our newsletter. Unsubscribe here.'),'a@example.com'),null);
});
test('explicit full dates are parsed; ambiguous dates and frequency do not create deadlines',()=>{
  assert.equal(extractDate('Your plan renews on October 24, 2026','renewal_notice').dueDate,'2026-10-24');
  assert.equal(extractDate('Next billing date: 2026-11-01','renewal_notice').dueDate,'2026-11-01');
  assert.equal(extractDate('Your trial ends on 29 October 2026','trial_active').eventKind,'trial_end');
  assert.equal(extractDate('Subscription ends on October 27, 2026','cancelled').eventKind,'access_end');
  for(const s of ['Renews on 10/11/26','Renews on October 24','Monthly plan. Paid October 1, 2026.','Invoice issued on 2026-11-01'])assert.equal(extractDate(s,'paid').dueDate,null,s);
  assert.equal(validDate('2026-02-30'),false);assert.equal(validDate('2028-02-29'),true);
});
test('new evidence reconciles chronologically and an old cancellation is not reactivated by marketing',()=>{
  const a=parseMessage(message('a','Claude payment failed','Your payment failed. Please update billing.','2026-10-01'),'a@example.com');
  const b=parseMessage(message('b','Claude cancellation confirmation','Your subscription has been cancelled. Access will end on October 27, 2026.','2026-10-03'),'a@example.com');
  let rows=mergeEvidence([], [b,a]);assert.equal(rows.length,1);assert.equal(rows[0].statusKind,'cancelled');assert.equal(rows[0].events[0].id,'a');assert.equal(rows[0].dueDate,'2026-10-27');
  rows=mergeEvidence(rows,[a,b]);assert.equal(rows[0].events.length,2);
  const promo={...b,id:'c',date:'2026-10-05T00:00:00Z',kind:'unknown',status:'Needs review',dueDate:null};rows=mergeEvidence(rows,[promo]);assert.equal(rows[0].statusKind,'cancelled');
});
test('account and product identities are separated; mail with no account identifier requires review',()=>{
  const a=parseMessage(message('a','Claude receipt','Receipt for your subscription. USD 20.00. Subscription ID: sub_one.'),'one@example.com');
  const b=parseMessage(message('b','Claude receipt','Receipt for your subscription. USD 20.00. Subscription ID: sub_two.'),'one@example.com');
  const c={...a,id:'c',account:'two@example.com'};
  const rows=mergeEvidence([],[a,b,c]);assert.equal(rows.length,3);assert.equal(rows[0].identityReview,false);
  const unknown=parseMessage(message('x','Claude receipt','Receipt for your subscription: $20.00'),'one@example.com');assert.equal(unknown.identityReview,true);assert.equal(unknown.currency,'Unspecified');
  const one=parseMessage(message('one','Google One receipt','Your Google One subscription receipt.','2026-10-01','billing@google.com'),'one@example.com');
  const play=parseMessage(message('play','Google Play receipt','Your Google Play subscription receipt.','2026-10-01','billing@google.com'),'one@example.com');assert.notEqual(one.providerKey,play.providerKey);
});
test('dates are invalidated by later cancellation and a payment does not invent another renewal',()=>{
  const first=parseMessage(message('a','Claude renewal','Your plan renews on October 24, 2026.','2026-10-01'),'a@example.com');
  const end=parseMessage(message('b','Claude cancellation confirmation','Your subscription has been cancelled.','2026-10-02'),'a@example.com');
  assert.equal(mergeEvidence([],[first,end])[0].dueDate,null);
  const receipt=parseMessage(message('c','Claude receipt','Payment received. USD 20.00.','2026-10-24'),'a@example.com');
  assert.equal(receipt.dueDate,null);
});
test('MIME parsing ignores attachments, strips HTML and token links from excerpts',()=>{
  const m=message('x','Claude notice','','2026-10-01');m.payload.body={};m.payload.mimeType='multipart/mixed';m.payload.parts=[{mimeType:'text/html',body:{data:Buffer.from('<h1>Your payment failed.</h1><script>alert(1)</script> https://example.com/?token=secret').toString('base64url')}},{mimeType:'text/plain',filename:'attachment.txt',body:{data:Buffer.from('Subscription cancellation confirmed').toString('base64url')}}];
  const e=parseMessage(m,'a@example.com');assert.equal(e.kind,'payment_failed');assert.ok(!e.excerpt.includes('secret'));assert.ok(!e.excerpt.includes('alert'));
});
test('reminders require reviewed exact future dates, are five days early, and deduplicate',()=>{
  const row={id:'abc',name:'Service',account:'me',dueDate:'2026-11-01',dateConfidence:'explicit',reviewed:true,eventKind:'renewal'};
  const now=new Date('2026-10-17T12:00:00Z');
  assert.equal(reminderPlan(row,'Africa/Lusaka',now).alertDate,'2026-10-27');
  assert.equal(calendarEvent(row,'Africa/Lusaka',now).start.dateTime,'2026-10-27T09:00:00');
  assert.equal(calendarEvent(row,'Africa/Lusaka',now).id,calendarEvent(row,'Africa/Lusaka',now).id);
  assert.throws(()=>reminderPlan({...row,dateConfidence:'estimated'},'UTC',now),/exact date/);
  assert.throws(()=>reminderPlan({...row,reviewed:false},'UTC',now),/Review the account/);
  assert.throws(()=>reminderPlan({...row,dueDate:'2026-10-18'},'UTC',now),/five-day reminder date has passed/);
  assert.match(ics([row],'Africa/Lusaka',now),/DTSTART;VALUE=DATE:20261027/);
});
test('dangerous evidence links and non-Desktop OAuth client files are rejected',()=>{
  for(const url of ['javascript:alert(1)','file:///etc/passwd','http://mail.google.com','https://mail.google.com.evil.example/a','https://mail.google.com@evil.example/','https://user:pass@mail.google.com/'])assert.equal(isSafeEvidenceUrl(url),false,url);
  assert.equal(isSafeEvidenceUrl('https://mail.google.com/mail/u/0/#all/abc'),true);
  assert.equal(safeHttps('https://localhost:3444/x'),null);
  assert.throws(()=>validateClient({web:{client_id:'x.apps.googleusercontent.com'}}),/Desktop/);
  assert.equal(validateClient({installed:{client_id:'x.apps.googleusercontent.com',client_secret:'s'}}).client_id,'x.apps.googleusercontent.com');
});
test('legacy register import preserves unknown dates and does not invent live billing status',()=>{
  const data={as_of:'2026-10-17',items:[{id:'S01',name:'Render',status:'Payment failed',amount:'Unknown',due_date:null,date_confidence:'unknown',note:'Suspension warning',source_urls:['https://mail.google.com/mail/u/0/#all/a'],calendar_reminder:{event_id:'old'}}]};
  const row=importRegister(data)[0];assert.equal(row.dueDate,null);assert.equal(row.liveStatus,'Not checked');assert.equal(row.reviewed,false);assert.equal(row.events.length,1);assert.ok(row.importedReminder);assert.equal(row.providerKey,'render');
  const urgent=importRegister({items:[{name:'Render',status:'Payment failure',section:'urgent'},{name:'Replit',status:'Services suspended',section:'urgent'}]});assert.equal(urgent[0].statusKind,'payment_failed');assert.equal(urgent[1].statusKind,'paused');
});
test('token storage never falls back to plaintext and local records survive restart',()=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'sd-store-'));const fake={isEncryptionAvailable:()=>false,getSelectedStorageBackend:()=> 'basic_text'};
  const store=new Store(dir,fake);store.saveTokens({access_token:'test-token'});store.state.items=[{id:'x',name:'Test'}];store.save();assert.equal(fs.existsSync(path.join(dir,'google-tokens.enc')),false);assert.equal(store.snapshot().auth.connected,true);assert.equal(JSON.stringify(store.snapshot()).includes('test-token'),false);const second=new Store(dir,fake);assert.equal(second.state.items[0].name,'Test');assert.equal(second.tokens,null);fs.rmSync(dir,{recursive:true,force:true});
});
test('OAuth uses system-browser PKCE, verifies state, and exchanges only the valid callback',async()=>{
  const realFetch=global.fetch;let sawExchange=false,authURL;
  global.fetch=async(url,options)=>{if(String(url)==='https://oauth2.googleapis.com/token'){assert.ok(options.body.get('code_verifier').length>=43);assert.equal(options.body.get('code'),'good-code');sawExchange=true;return new Response(JSON.stringify({access_token:'a',refresh_token:'r',expires_in:3600,scope:'https://www.googleapis.com/auth/gmail.readonly'}),{status:200});}if(String(url).endsWith('/profile'))return new Response(JSON.stringify({emailAddress:'owner@example.com'}),{status:200});return realFetch(url,options);};
  try{const tokens=await authorize({client_id:'test.apps.googleusercontent.com'},async url=>{authURL=new URL(url);assert.equal(authURL.hostname,'accounts.google.com');assert.equal(authURL.searchParams.get('code_challenge_method'),'S256');assert.ok(!url.includes('code_verifier'));const redirect=new URL(authURL.searchParams.get('redirect_uri'));assert.equal(redirect.hostname,'127.0.0.1');redirect.search=new URLSearchParams({state:'bad-state',code:'bad-code'}).toString();assert.equal((await realFetch(redirect)).status,400);assert.equal(sawExchange,false);redirect.search=new URLSearchParams({state:authURL.searchParams.get('state'),code:'good-code'}).toString();assert.equal((await realFetch(redirect)).status,200);});assert.equal(tokens.email,'owner@example.com');assert.ok(sawExchange);}finally{global.fetch=realFetch;}
});
test('Gmail scanner follows pagination, deduplicates known messages, and reports partial failures',async()=>{
  const g=new GoogleClient(()=>({}),()=>({email:'owner@example.com'}),()=>{});let lists=0;
  g.request=async url=>{if(url.includes('/messages?')){lists++;return lists===1?{messages:[{id:'a'},{id:'b'}],nextPageToken:'next'}:{messages:[{id:'c'}]};}if(url.includes('/b?'))throw Object.assign(new Error('Temporary failure'),{status:503});return message('c','Claude receipt','Receipt for your subscription. USD 20.00.');};
  const result=await g.scan({knownIds:new Set(['a']),range:'all'});assert.equal(lists,2);assert.equal(result.scan.skipped,1);assert.equal(result.scan.errors.length,1);assert.equal(result.scan.partial,true);assert.equal(result.events.length,1);assert.ok(!result.scan.query.includes('newer_than'));
});
test('spreadsheet exports untrusted text as text; PDF HTML escapes it',async()=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'sd-export-'));const data={items:[{id:'a',name:'=HYPERLINK("evil")<script>',scope:'<b>account</b>',events:[],notes:'<img src=x onerror=alert(1)>',decision:'Review',usage:'Unknown'}]};
  const file=path.join(dir,'test.xlsx');await spreadsheet(data,file);const ExcelJS=require('exceljs');const wb=new ExcelJS.Workbook();await wb.xlsx.readFile(file);assert.equal(wb.worksheets[0].getCell('A2').type,ExcelJS.ValueType.String);assert.equal(wb.worksheets[0].getCell('A2').value,data.items[0].name);const html=reportHTML(data);assert.ok(!html.includes('<script>'));assert.ok(!html.includes('<img src'));fs.rmSync(dir,{recursive:true,force:true});
});
