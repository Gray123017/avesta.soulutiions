'use strict';
const crypto=require('node:crypto');
const {detectProvider,byName}=require('./catalog.cjs');
const hash=s=>crypto.createHash('sha256').update(String(s)).digest('hex').slice(0,24);
const STATUS={payment_failed:'Payment failed',at_risk:'Interruption risk',paused:'Access paused',cancelled:'Cancellation confirmed by email',trial_expired:'Trial expired',free:'Free plan reported',paid:'Payment received',renewed:'Renewal confirmed by email',trial_active:'Trial started',renewal_notice:'Renewal notice',refund:'Refund reported',purchase:'Purchase reported',unknown:'Needs review'};
const datePattern='(?:\\d{4}-\\d{2}-\\d{2}|(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\\s+\\d{1,2}(?:st|nd|rd|th)?,?\\s+\\d{4}|\\d{1,2}\\s+(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\\s+\\d{4})';
function validDate(s){if(!/^\d{4}-\d{2}-\d{2}$/.test(s||''))return false; const d=new Date(s+'T12:00:00Z');return !isNaN(d)&&d.toISOString().slice(0,10)===s;}
function parseDate(s){if(/^\d{4}-/.test(s))return validDate(s)?s:null; const d=new Date(s.replace(/(\d)(st|nd|rd|th)/gi,'$1')+' 12:00 UTC'); return isNaN(d)?null:d.toISOString().slice(0,10);}
function extractDate(text,type){
  const phrases=[
    ['trial_end','trial (?:ends?|expires?|will end|will expire)(?: on)?'],
    ['access_end','(?:access|membership|subscription) (?:ends?|will end|expires?)(?: on)?'],
    ['renewal','(?:next (?:renewal|billing|payment|charge) date|renews?(?: automatically)?(?: on)?|will (?:renew|be charged)(?: on)?|renewal (?:on|date))'],
    ['payment_due','(?:payment (?:is )?due(?: on)?|due date)']
  ];
  for(const [kind,phrase] of phrases){const m=text.match(new RegExp(phrase+'[\\s:–—-]*('+datePattern+')','i'));if(m){const date=parseDate(m[1]);if(date)return{dueDate:date,dateConfidence:'explicit',eventKind:kind,dateEvidence:m[0]};}}
  return{dueDate:null,dateConfidence:'unknown',eventKind:type==='trial_expired'?'trial_end':'renewal',dateEvidence:''};
}
function classify(text){
  const s=text.toLowerCase();
  if(/(?:subscription|membership|plan|account|auto.?renewal).{0,45}(?:has been|is now|was|is|successfully) cancel[le]{1,2}d|(?:successfully cancel[le]{1,2}d|cancel[le]{1,2}d your (?:subscription|membership))|cancellation (?:confirmed|confirmation)|your (?:subscription|membership) (?:has )?ended/.test(s))return'cancelled';
  if(/(?:trial (?:has |is |already )?(?:expired|ended|over)|free trial.{0,25}(?:has expired|has ended))/.test(s))return'trial_expired';
  if(/(?:now on|moved to|switched to|downgraded to).{0,25}(?:free plan|free tier)/.test(s))return'free';
  if(/(?:access|service|account|subscription).{0,35}(?:has been|is currently|is now|is|are) (?:paused|suspended)|(?:paused|suspended) your (?:access|account|subscription)/.test(s))return'paused';
  if(/(?:payment|charge|transaction).{0,30}(?:failed|declined|unsuccessful)|(?:unable|couldn.t|could not|failed) to (?:process|collect|charge)|unsuccessful payment|payment attempt.{0,15}unsuccessful/.test(s))return'payment_failed';
  if(/(?:will|may|could|might) (?:be )?(?:suspended|cancel[le]{1,2}d|terminated)|(?:suspension|cancellation) (?:warning|risk)|at risk of.{0,15}suspension/.test(s))return'at_risk';
  if(/refund (?:has been|was|is|processed|issued)|(?:we.ve|we have) refunded|your refund/.test(s))return'refund';
  if(/(?:subscription|membership|plan).{0,30}(?:has been renewed|renewed successfully|has renewed)|successfully renewed/.test(s))return'renewed';
  if(/payment (?:successful|received|confirmed)|(?:thank you|thanks) for your payment|paid invoice|receipt for your (?:subscription|payment)|subscription receipt/.test(s))return'paid';
  if(/(?:trial (?:has )?(?:started|begun)|welcome to your.{0,20}trial)/.test(s))return'trial_active';
  if(/(?:subscription|membership|plan).{0,50}(?:will renew|renews|renewal)|next (?:billing|renewal) date/.test(s))return'renewal_notice';
  if(/(?:receipt|order confirmation|purchase confirmed|thank you for your purchase)/.test(s))return'purchase';
  return'unknown';
}
function cleanText(s){return String(s||'').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,' ').replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi,' ').replace(/<blockquote\b[^>]*>[\s\S]*?<\/blockquote>/gi,' ').replace(/<[^>]+>/g,' ').replace(/&nbsp;|&#160;/g,' ').replace(/&amp;/g,'&').replace(/&quot;/g,'"').replace(/&#39;|&apos;/g,"'").replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/\s+/g,' ').trim();}
function mimeText(payload){
  if(!payload)return'';
  const plain=[],html=[];
  function walk(p){if(p.filename)return;if(p.body?.data){const d=Buffer.from(p.body.data,'base64url').toString('utf8');if(p.mimeType==='text/plain')plain.push(d);else if(p.mimeType==='text/html')html.push(d);} for(const q of p.parts||[])walk(q);}
  walk(payload); return cleanText((plain.length?plain:html).join('\n').split(/\nOn .{10,200}wrote:|\n-{2,} Forwarded message/)[0]).slice(0,40000);
}
function parseMessage(message,account){
  const headers=Object.fromEntries((message.payload?.headers||[]).map(h=>[h.name.toLowerCase(),h.value]));
  const subject=String(headers.subject||'').slice(0,300), from=String(headers.from||'').slice(0,300);
  const body=mimeText(message.payload)||cleanText(message.snippet), text=subject+'\n'+body;
  const kind=classify(text);
  const relevant=/subscription|membership|billing|invoice|payment|renewal|auto.?renew|trial|receipt|suspend|credit.{0,15}(?:top.?up|exhausted)/i.test(text);
  if(!relevant)return null;
  if(kind==='unknown' && /unsubscribe|newsletter|digest|sale|offer|webinar/i.test(text) && !/invoice|payment (?:due|failed)|your (?:subscription|trial)/i.test(text))return null;
  const p=detectProvider(from,text);
  let scope='Account not identified';
  const sid=text.match(/subscription (?:id|number)\s*[:#]\s*([a-z0-9_-]{4,80})/i);
  const site=text.match(/(?:for (?:the )?(?:domain|website)|domain name)\s*[:\s]+([a-z0-9-]+(?:\.[a-z0-9-]+)+)/i);
  if(sid)scope='Subscription '+sid[1];else if(site)scope=site[1].toLowerCase();
  if(p.key==='hostinger'){const product=/email|mailbox/i.test(subject)?'Email':/domain/i.test(subject)?'Domain':/hosting/i.test(subject)?'Hosting':'Product unconfirmed';scope=product+' · '+scope;}
  const amount=text.match(/\b(USD|GBP|EUR|ZMW|ZAR|INR|CAD|AUD)\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{2})?)\b|(?:US\$|[$€£])\s*[0-9]+(?:,[0-9]{3})*(?:\.[0-9]{2})?/i)?.[0]||'';
  const currency=/^USD|^US\$/i.test(amount)?'USD':/^EUR|^€/i.test(amount)?'EUR':/^GBP|^£/i.test(amount)?'GBP':amount.match(/^[A-Z]{3}/)?.[0]||'Unspecified';
  const received=Number(message.internalDate); const date=Number.isFinite(received)&&received>0?new Date(received).toISOString():new Date().toISOString();
  const cleanExcerpt=body.replace(/https?:\/\/\S+/g,'[link omitted]').slice(0,1600);
  return {id:String(message.id),date,kind,status:STATUS[kind],providerKey:p.key,name:p.name,scope,account,category:p.category,identityReview:p.identityReview||scope.includes('not identified')||scope.includes('unconfirmed'),subject,from,excerpt:cleanExcerpt,amount,currency,...extractDate(text,kind),sourceUrl:'https://mail.google.com/mail/u/?authuser='+encodeURIComponent(account)+'#all/'+encodeURIComponent(message.id)};
}
function mergeEvidence(items,events){
  const result=structuredClone(items);
  for(const e of events){
    // Exact mailbox + provider + subscription/product scope only. Never fuzzy merge unrelated account identities.
    const key=[e.account,e.providerKey,e.scope].join('|');
    let row=result.find(x=>x.identityKey===key);
    if(!row){row={id:hash(key),identityKey:key,name:e.name,providerKey:e.providerKey,scope:e.scope,account:e.account,category:e.category,events:[],decision:'Review',usage:'Unknown',notes:'',liveStatus:'Not checked',reviewed:false,source:'email',amount:'',currency:e.currency,cycle:'Unknown'};result.push(row);}
    if(!row.events.some(x=>x.id===e.id)){row.events.push(e);row.events.sort((a,b)=>a.date.localeCompare(b.date));row.reviewed=false;}
    const latest=row.events.at(-1); const meaningful=[...row.events].reverse().find(x=>!['unknown','renewal_notice','purchase'].includes(x.kind))||latest;
    row.status=meaningful.status;row.statusKind=meaningful.kind;row.evidenceAt=meaningful.date;
    row.identityReview=latest.identityReview;row.latestEmailAt=latest.date;
    row.amount=latest.amount||row.amount;row.currency=latest.amount?latest.currency:row.currency;
    const dated=[...row.events].reverse().find(x=>x.dueDate);
    // A cancellation/free/trial-end invalidates an earlier renewal; a receipt doesn't invent a new date.
    const closes=['cancelled','free','trial_expired'].includes(meaningful.kind);
    if(dated && (!closes || dated.date>=meaningful.date || dated.eventKind==='access_end')){row.dueDate=dated.dueDate;row.dateConfidence=dated.dateConfidence;row.eventKind=dated.eventKind;row.dateEvidence=dated.dateEvidence;}
    else if(!row.manualDate){row.dueDate=null;row.dateConfidence='unknown';}
    if(row.manualDate){Object.assign(row,row.manualDate);}
  }
  return result;
}
function importRegister(data){
  if(!data || !Array.isArray(data.items))throw new Error('Choose a Subscription Desk backup or your subscription register JSON.');
  if(data.items.length>10000)throw new Error('This file contains too many records.');
  if(data.schemaVersion===1){return data.items.map(validateImportedRow);}
  return data.items.filter(x=>x.name).map((x,i)=>{
    const p=byName(x.name);const status=String(x.status||'Needs review');
    const detectedKind=classify(status);
    const statusKind=detectedKind!=='unknown'?detectedKind:/access paused|services? suspended/i.test(status)?'paused':/payment failure/i.test(status)?'payment_failed':x.section==='urgent'?'at_risk':'unknown';
    const id='import-'+String(x.id||i).slice(0,100);
    const events=(x.source_urls||[]).map((u,j)=>({id:id+'-'+j,sourceUrl:String(u),date:x.last_checked||data.as_of||'',status,kind:classify(status),subject:'Imported evidence reference',from:'See original email',excerpt:String(x.note||'').slice(0,4000)}));
    return{id,name:String(x.name).slice(0,300),providerKey:p?.key||'',account:'Imported register',scope:String(x.services?.join(', ')||'Review account in source'),category:p?.category||'Other',status,statusKind,amount:String(x.amount||''),currency:'Unspecified',cycle:'Unknown',dueDate:validDate(x.due_date)?x.due_date:null,dateConfidence:x.date_confidence||'unknown',eventKind:x.event_kind||'renewal',dateEvidence:String(x.note||''),decision:'Review',usage:'Unknown',notes:[x.note,x.recommendation].filter(Boolean).join('\n'),liveStatus:x.live_account_status||'Not checked',reviewed:false,identityReview:true,source:'import',events,evidenceAt:x.last_checked||'',importedReminder:x.calendar_reminder||null};
  });
}
function validateImportedRow(x){
  if(!x||typeof x.id!=='string'||typeof x.name!=='string')throw new Error('Invalid backup record.');
  const o={}; for(const k of ['id','name','identityKey','providerKey','account','scope','category','status','statusKind','amount','currency','cycle','dateConfidence','eventKind','dateEvidence','decision','usage','notes','liveStatus','source','evidenceAt','latestEmailAt'])o[k]=String(x[k]||'').slice(0,k==='notes'?10000:2000);
  o.dueDate=validDate(x.dueDate)?x.dueDate:null;o.reviewed=!!x.reviewed;o.identityReview=!!x.identityReview;
  o.events=Array.isArray(x.events)?x.events.slice(-1000).map(e=>({id:String(e.id||'').slice(0,200),date:String(e.date||'').slice(0,40),kind:String(e.kind||'unknown'),status:String(e.status||''),subject:String(e.subject||'').slice(0,300),from:String(e.from||'').slice(0,300),excerpt:String(e.excerpt||'').slice(0,4000),sourceUrl:String(e.sourceUrl||'').slice(0,2048),dueDate:validDate(e.dueDate)?e.dueDate:null,dateConfidence:String(e.dateConfidence||'unknown'),eventKind:String(e.eventKind||'renewal'),dateEvidence:String(e.dateEvidence||'').slice(0,600),amount:String(e.amount||'').slice(0,100),currency:String(e.currency||'Unspecified'),identityReview:!!e.identityReview})):[];
  if(x.manualDate?.dueDate&&validDate(x.manualDate.dueDate))o.manualDate={dueDate:x.manualDate.dueDate,dateConfidence:'user-confirmed',eventKind:String(x.manualDate.eventKind||'renewal'),dateEvidence:String(x.manualDate.dateEvidence||'User confirmed')};
  if(x.importedReminder?.due_date&&validDate(x.importedReminder.due_date))o.importedReminder={due_date:x.importedReminder.due_date,reminder_date:validDate(x.importedReminder.reminder_date)?x.importedReminder.reminder_date:null,status:'Imported reference — verify in Calendar'};
  // Do not trust external Calendar event IDs on import; deduplication checks app-created IDs server-side.
  return o;
}
function recommendation(row){
  if(row.decision==='Keep')return'Keep — your choice';if(row.decision==='Cancel')return'Cancellation selected — finish with provider';
  if(row.usage==='Unused')return'Candidate to cancel after checking dependencies';
  if(['trial_expired','free','cancelled'].includes(row.statusKind))return'Check for later charges; no new purchase needed';
  if(row.usage==='Essential')return'Keep candidate — resolve billing if needed';
  if(['payment_failed','paused','at_risk'].includes(row.statusKind))return'Review quickly: service interruption possible';
  return'Confirm usage, price and account before deciding';
}
module.exports={STATUS,hash,validDate,parseDate,extractDate,classify,cleanText,mimeText,parseMessage,mergeEvidence,importRegister,validateImportedRow,recommendation};
