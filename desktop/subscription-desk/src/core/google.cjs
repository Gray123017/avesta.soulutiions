'use strict';
const crypto=require('node:crypto');
const http=require('node:http');
const {parseMessage}=require('./model.cjs');
const {calendarEvent}=require('./reminders.cjs');
const GMAIL='https://www.googleapis.com/auth/gmail.readonly';
const CALENDAR='https://www.googleapis.com/auth/calendar.events';
const API='https://gmail.googleapis.com/gmail/v1/users/me';
async function jsonFetch(url,options={}){
  const response=await fetch(url,{...options,signal:options.signal||AbortSignal.timeout(45000)});
  const data=await response.json().catch(()=>({}));
  if(!response.ok){const error=new Error(data.error?.message||data.error_description||'Google request failed ('+response.status+').');error.status=response.status;error.code=typeof data.error==='string'?data.error:'';throw error;}
  return data;
}
function validateClient(data){const c=data?.installed;if(!c?.client_id||!/^[-a-zA-Z0-9_.]+\.apps\.googleusercontent\.com$/.test(c.client_id))throw new Error('Import the JSON for a Google OAuth Desktop app client. Web app credentials will not work.');if(c.client_secret && (typeof c.client_secret!=='string'||c.client_secret.length>1000))throw new Error('Invalid OAuth client file.');return{client_id:c.client_id,client_secret:c.client_secret||''};}
async function authorize(client,openBrowser,calendar=false,loginHint=''){
  if(!client?.client_id)throw new Error('Import your Google Desktop OAuth client in Settings first.');
  const verifier=crypto.randomBytes(48).toString('base64url'),state=crypto.randomBytes(32).toString('base64url');
  const challenge=crypto.createHash('sha256').update(verifier).digest('base64url');
  let server,timer;
  const code=await new Promise((resolve,reject)=>{
    const finish=(err,value)=>{clearTimeout(timer);server.close();err?reject(err):resolve(value);};
    server=http.createServer((req,res)=>{
      if(req.method!=='GET'){res.writeHead(405).end();return;}
      const u=new URL(req.url,'http://127.0.0.1');
      if(u.pathname!=='/oauth2callback'){res.writeHead(404).end();return;}
      const a=Buffer.from(u.searchParams.get('state')||''),b=Buffer.from(state);
      if(a.length!==b.length||!crypto.timingSafeEqual(a,b)){res.writeHead(400,{'Content-Type':'text/plain'}).end('Invalid sign-in state. Return to the app and try again.');return;}
      res.writeHead(200,{'Content-Type':'text/html; charset=utf-8','Content-Security-Policy':"default-src 'none'; style-src 'unsafe-inline'",'Cache-Control':'no-store'});
      res.end('<!doctype html><meta charset="utf-8"><title>Subscription Desk</title><body style="font:20px system-ui;padding:60px;background:#f4f7f3;color:#183e40"><h1>Return to Subscription Desk</h1><p>The sign-in response was received. You can close this tab.</p></body>');
      if(u.searchParams.has('error'))return finish(new Error('Google sign-in was declined or cancelled.'));
      if(!u.searchParams.get('code'))return finish(new Error('No authorization code was returned.'));
      finish(null,{code:u.searchParams.get('code'),redirect:'http://127.0.0.1:'+server.address().port+'/oauth2callback'});
    });
    server.on('error',reject);
    server.listen(0,'127.0.0.1',()=>{
      const url=new URL('https://accounts.google.com/o/oauth2/v2/auth');
      url.search=new URLSearchParams({client_id:client.client_id,redirect_uri:'http://127.0.0.1:'+server.address().port+'/oauth2callback',response_type:'code',scope:[GMAIL,...(calendar?[CALENDAR]:[])].join(' '),access_type:'offline',prompt:'consent select_account',code_challenge:challenge,code_challenge_method:'S256',state,...(loginHint?{login_hint:loginHint}:{})}).toString();
      Promise.resolve(openBrowser(url.href)).catch(err=>finish(err));
    });
    timer=setTimeout(()=>finish(new Error('Sign-in timed out after five minutes. Please try again.')),300000);
  });
  const tokens=await jsonFetch('https://oauth2.googleapis.com/token',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({client_id:client.client_id,...(client.client_secret?{client_secret:client.client_secret}:{}),code:code.code,redirect_uri:code.redirect,code_verifier:verifier,grant_type:'authorization_code'})});
  tokens.expires_at=Date.now()+(tokens.expires_in||3600)*1000;
  if(!tokens.scope?.split(' ').includes(GMAIL))throw new Error('Gmail read-only access was not granted. Sign in and allow it to scan subscription emails.');
  const profile=await jsonFetch(API+'/profile',{headers:{Authorization:'Bearer '+tokens.access_token}});
  return{...tokens,email:profile.emailAddress};
}
class GoogleClient{
  constructor(getClient,getTokens,saveTokens){this.getClient=getClient;this.getTokens=getTokens;this.saveTokens=saveTokens;this.refreshPromise=null;}
  async token(){
    let tokens=this.getTokens();if(!tokens?.access_token)throw new Error('Connect Gmail first.');
    if(tokens.expires_at>Date.now()+60000)return tokens.access_token;
    if(this.refreshPromise)return this.refreshPromise;
    if(!tokens.refresh_token)throw new Error('Please reconnect Gmail; this sign-in cannot be refreshed.');
    this.refreshPromise=(async()=>{const c=this.getClient();const fresh=await jsonFetch('https://oauth2.googleapis.com/token',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({client_id:c.client_id,...(c.client_secret?{client_secret:c.client_secret}:{}),refresh_token:tokens.refresh_token,grant_type:'refresh_token'})});tokens={...tokens,...fresh,expires_at:Date.now()+(fresh.expires_in||3600)*1000};this.saveTokens(tokens);return tokens.access_token;})();
    try{return await this.refreshPromise;}finally{this.refreshPromise=null;}
  }
  async request(url,options={}){
    const u=new URL(url);if(!['gmail.googleapis.com','www.googleapis.com'].includes(u.hostname)||u.protocol!=='https:')throw new Error('Unsupported API destination.');
    for(let attempt=0;attempt<4;attempt++){
      try{return await jsonFetch(url,{...options,headers:{...options.headers,Authorization:'Bearer '+await this.token()}});}
      catch(err){if(err.status===401&&attempt===0){const t=this.getTokens();this.saveTokens({...t,expires_at:0});continue;}if(![429,500,502,503,504].includes(err.status)||attempt===3)throw err;await new Promise(resolve=>setTimeout(resolve,500*2**attempt));}
    }
  }
  async scan({range='13m',signal,onProgress=()=>{},knownIds=new Set()}){
    const account=this.getTokens()?.email;if(!account)throw new Error('Connect Gmail first.');
    const query=(range==='all'?'':'newer_than:13m ')+'{subscription subscriptions subscribed membership billing invoice receipt renewal renews "payment failed" "payment received" "payment unsuccessful" "paid plan" "free trial" suspended "credit top-up"}';
    const events=[],errors=[];let pageToken='',listed=0,read=0,skipped=0,matched=0,partial=false;
    try{
      do{
        signal?.throwIfAborted();
        const u=new URL(API+'/messages');u.search=new URLSearchParams({q:query,maxResults:'100',...(pageToken?{pageToken}:{})}).toString();
        const page=await this.request(u.href,{signal});
        const messages=page.messages||[];listed+=messages.length;
        for(let start=0;start<messages.length;start+=4){
          signal?.throwIfAborted();
          const chunk=messages.slice(start,start+4);
          await Promise.all(chunk.map(async m=>{
            if(knownIds.has(m.id)){skipped++;return;}
            try{const detail=await this.request(API+'/messages/'+encodeURIComponent(m.id)+'?format=full',{signal});const e=parseMessage(detail,account);if(e){events.push(e);matched++;}read++;}
            catch(err){if(signal?.aborted)throw err;errors.push({id:m.id,status:err.status||0});}
          }));
          onProgress({listed,read,skipped,matched,errors:errors.length});
        }
        pageToken=page.nextPageToken||'';
        // Bound resource use and report the exact limitation instead of claiming full coverage.
        if(listed>=20000&&pageToken){partial=true;break;}
      }while(pageToken);
    }catch(err){if(signal?.aborted)partial=true;else{partial=true;errors.push({status:err.status||0,reason:err.message});}}
    return{events,scan:{at:new Date().toISOString(),account,query,range,listed,read,skipped,matched,errors,partial:partial||errors.length>0,cancelled:!!signal?.aborted,limit:20000}};
  }
  async createReminder(row,zone){
    if(row.importedReminder?.due_date===row.dueDate)throw new Error('The imported register already records a reminder for this deadline. Review it in Google Calendar instead of creating a duplicate.');
    if(!this.getTokens()?.scope?.split(' ').includes(CALENDAR))throw new Error('Enable Calendar access in Settings and reconnect Google first.');
    if(row.source==='email'&&row.account!==this.getTokens()?.email)throw new Error('Connect the Gmail account associated with this subscription first.');
    const event=calendarEvent(row,zone);
    if(row.calendar?.id&&row.calendar.id!==event.id)throw new Error('Remove the previous reminder in this app before replacing its date or timezone.');
    const base='https://www.googleapis.com/calendar/v3/calendars/primary/events';
    let saved;
    try{saved=await this.request(base+'/'+event.id);if(saved.status==='cancelled')throw new Error('This reminder was deleted in Calendar. Create a reminder manually or use the .ics export.');if(saved.extendedProperties?.private?.subscriptionId!==row.id)throw new Error('Calendar event ID conflict.');}
    catch(err){if(err.status!==404)throw err;try{saved=await this.request(base+'?sendUpdates=none',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(event)});}catch(e){if(e.status!==409)throw e;saved=await this.request(base+'/'+event.id);if(saved.extendedProperties?.private?.subscriptionId!==row.id)throw new Error('Calendar event ID conflict.');}}
    return{id:saved.id,url:saved.htmlLink,deadline:row.dueDate,zone,account:this.getTokens().email,createdAt:new Date().toISOString(),alertDate:event.start.dateTime.slice(0,10)};
  }
  async deleteReminder(row){
    if(!row.calendar?.id)return;
    if(row.calendar.account!==this.getTokens()?.email)throw new Error('Connect the Google account that owns this reminder first.');
    const url='https://www.googleapis.com/calendar/v3/calendars/primary/events/'+encodeURIComponent(row.calendar.id);
    let e;try{e=await this.request(url);}catch(err){if([404,410].includes(err.status))return;throw err;}
    if(e.extendedProperties?.private?.subscriptionDesk!=='1'||e.extendedProperties?.private?.subscriptionId!==row.id)throw new Error('This app can only remove reminders it created.');
    await this.request(url+'?sendUpdates=none',{method:'DELETE'});
  }
  async revoke(){const t=this.getTokens();if(t?.refresh_token||t?.access_token){const r=await fetch('https://oauth2.googleapis.com/revoke',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({token:t.refresh_token||t.access_token}),signal:AbortSignal.timeout(15000)});if(!r.ok&&r.status!==400)throw new Error('Google could not revoke access. Try again or remove access from your Google account.');}}
}
module.exports={GMAIL,CALENDAR,validateClient,authorize,GoogleClient};
