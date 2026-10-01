import {quote,validateApplication,statuses,today,addDays} from './domain.js';
import {assets} from './assets.js';
const json=(value,status=200)=>new Response(JSON.stringify(value),{status,headers:{'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store','X-Content-Type-Options':'nosniff'}});
const db=(env)=>{if(!env.DB)throw new Error('Storage is temporarily unavailable. Please try again.');return env.DB;};
const owner=(request)=>request.headers.get('oai-authenticated-user-id');
const log=(env,user,action,reference)=>db(env).prepare('INSERT INTO audit (id,owner,action,reference,time) VALUES (?,?,?,?,?)').bind(crypto.randomUUID(),user,action,reference,new Date().toISOString());
const record=(env,user,id)=>db(env).prepare('SELECT * FROM applications WHERE id=? AND owner=?').bind(id,user).first();
const hasFiles=(files,name)=>files.some(f=>f.field===name);
const securityHeaders={
 'X-Content-Type-Options':'nosniff','Referrer-Policy':'same-origin','X-Frame-Options':'SAMEORIGIN',
 'Content-Security-Policy':"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self' https://chatgpt.com https://*.chatgpt.com",
 'Permissions-Policy':'camera=(), microphone=(), geolocation=()','Cache-Control':'no-store'
};
const legacy={'/index.php':'/','/loans.php':'/lending','/it.php':'/it','/app/index.php':'/lending','/portal.php':'/portal','/app/admin.php':'/portal','/login.php':'/portal','/logout.php':'/signout-with-chatgpt?return_to=/'};
const routes=['/','/lending','/how-it-works','/calculator','/currency','/apply','/it','/about','/contact','/portal','/privacy','/terms','/complaints'];
async function api(request,env,url,user){
 const path=url.pathname,method=request.method;
 if(path==='/api/health'&&method==='GET')return health(env);
 if(path==='/api/assistant/status'&&method==='GET')return json({webAvailable:!!env.OPENAI_API_KEY});
 if(path==='/api/rates'&&method==='GET'){
  try{
   const res=await fetch('https://open.er-api.com/v6/latest/USD',{signal:AbortSignal.timeout(7000)});
   const r=await res.json();
   if(!res.ok||r.result!=='success'||!r.rates?.ZMW)throw new Error();
   return json({rates:r.rates,asOf:new Date(r.time_last_update_unix*1000).toISOString(),source:'ExchangeRate-API',indicative:true});
  }catch{return json({error:'Indicative rates are unavailable. Enter a rate manually to continue.'},503);}
 }
 if(!user)return json({error:'Sign in with ChatGPT to access this private workspace.'},401);
 if(method!=='GET'){
  const origin=request.headers.get('origin');if(origin&&origin!==url.origin)return json({error:'Request origin is not allowed.'},403);
  if(Number(request.headers.get('content-length')||0)>25*1024*1024)return json({error:'The upload is too large.'},413);
 }
 if(path==='/api/assistant'&&method==='POST')return assistant(request,env,user);
 if(path==='/api/me')return json({email:request.headers.get('oai-authenticated-user-email')||'',private:true});
 if(path==='/api/applications'&&method==='GET'){
  const r=await db(env).prepare('SELECT a.*, COALESCE((SELECT SUM(amount) FROM payments p WHERE p.application_id=a.id),0) AS paid FROM applications a WHERE owner=? ORDER BY submitted DESC LIMIT 500').bind(user).all();
  return json({records:r.results.map(r=>({...r,data:JSON.parse(r.data)}))});
 }
 if(path==='/api/applications'&&method==='POST'){
  const fd=await request.formData();let d;
  try{d=JSON.parse(fd.get('payload'));}catch{return json({error:'The application could not be read.'},400);}
  const q=validateApplication(d);const files=[];let bytes=0;
  for(const [field,value]of fd){if(field==='payload'||typeof value==='string'||!value.size)continue;
   const type=value.type;if(!['application/pdf','image/jpeg','image/png','image/webp'].includes(type)||value.size>5*1024*1024)throw new Error('Documents must be PDF, JPG, PNG or WebP, up to 5 MB each.');
   const head=new Uint8Array(await value.slice(0,12).arrayBuffer());
   const valid=type==='application/pdf'?String.fromCharCode(...head.slice(0,5))==='%PDF-':type==='image/jpeg'?head[0]===255&&head[1]===216:type==='image/png'?head[0]===137&&String.fromCharCode(...head.slice(1,4))==='PNG':String.fromCharCode(...head.slice(0,4))==='RIFF'&&String.fromCharCode(...head.slice(8,12))==='WEBP';
   if(!valid)throw new Error('The document format does not match its contents.');
   bytes+=value.size;files.push({field,file:value,id:crypto.randomUUID()});
  }
  if(files.length>9||bytes>20*1024*1024)throw new Error('Upload up to 9 documents, with a combined size of 20 MB.');
  if(!hasFiles(files,'nrcFront')||!hasFiles(files,'nrcBack'))throw new Error('Upload the front and back of your NRC.');
  if(d.security==='Collateral'&&!hasFiles(files,'collateral'))throw new Error('Upload a collateral photo.');
  if(d.security==='Employer letter'&&!hasFiles(files,'employerLetter'))throw new Error('Upload the employer letter.');
  if(!env.BUCKET)throw new Error('Document storage is temporarily unavailable. Please try again.');
  const id='AV-'+crypto.randomUUID().slice(0,8).toUpperCase();
  const keys=[];try{
   for(const f of files){await env.BUCKET.put(f.id,await f.file.arrayBuffer(),{httpMetadata:{contentType:f.file.type}});keys.push(f.id);}
   const inserts=[db(env).prepare('INSERT INTO applications (id,owner,name,phone,email,data,amount,weeks,total,status,submitted) VALUES (?,?,?,?,?,?,?,?,?,?,?)').bind(id,user,d.name,d.phone,d.email||'',JSON.stringify(d),q.principal,Number(d.weeks),q.total,'Pending',new Date().toISOString())];
   for(const f of files)inserts.push(db(env).prepare('INSERT INTO documents (id,application_id,owner,name,type,size) VALUES (?,?,?,?,?,?)').bind(f.id,id,user,f.field+' — '+f.file.name,f.file.type,f.file.size));
   inserts.push(log(env,user,'Application submitted',id));await db(env).batch(inserts);
  }catch(err){await Promise.all(keys.map(k=>env.BUCKET.delete(k)));throw err;}
  return json({id,status:'Pending'},201);
 }
 const match=path.match(/^\/api\/applications\/([^/]+)(?:\/(payments|documents))?$/);
 if(match){
  const id=decodeURIComponent(match[1]),r=await record(env,user,id);if(!r)return json({error:'Application not found.'},404);
  const sub=match[2];
  if(sub==='documents'&&method==='GET'){
   const docs=await db(env).prepare('SELECT id,name,type,size FROM documents WHERE application_id=? AND owner=?').bind(id,user).all();return json({documents:docs.results});
  }
  if(sub==='payments'&&method==='GET'){
   const p=await db(env).prepare('SELECT * FROM payments WHERE application_id=? ORDER BY date DESC,created DESC').bind(id).all();return json({payments:p.results});
  }
  if(sub==='payments'&&method==='POST'){
   if(!['Disbursed','Repaid'].includes(r.status))throw new Error('Record a disbursement before adding payments.');
   const d=await request.json(),amount=Math.round(Number(d.amount)*100);
   if(!Number.isSafeInteger(amount)||amount<=0||amount>100000000)throw new Error('Enter a positive payment amount.');
   if(!/^\d{4}-\d{2}-\d{2}$/.test(d.date)||!Number.isFinite(Date.parse(d.date))||d.date>today()||d.date<r.disbursed)throw new Error('Choose a payment date between disbursement and today.');
   const pid=crypto.randomUUID();
   const p=await db(env).prepare('INSERT INTO payments (id,application_id,amount,date,method,note,created) SELECT ?,?,?,?,?,?,? WHERE ? <= (SELECT total-COALESCE((SELECT SUM(amount) FROM payments WHERE application_id=?),0) FROM applications WHERE id=? AND owner=?)').bind(pid,id,amount,d.date,String(d.method||'Cash').slice(0,60),String(d.note||'').slice(0,500),new Date().toISOString(),amount,id,id,user).run();
   if(!p.meta.changes)throw new Error('Payment exceeds the outstanding balance. Check the amount.');
   await db(env).batch([db(env).prepare("UPDATE applications SET status=CASE WHEN total<=(SELECT COALESCE(SUM(amount),0) FROM payments WHERE application_id=?) THEN 'Repaid' ELSE 'Disbursed' END WHERE id=? AND owner=?").bind(id,id,user),log(env,user,'Payment recorded: '+(amount/100).toFixed(2)+' ZMW',id)]);
   return json({ok:true},201);
  }
  if(!sub&&method==='PATCH'){
   const d=await request.json();if(!statuses.includes(d.status))throw new Error('Choose a valid status.');
   if(d.status==='Repaid')throw new Error('Repaid status is set automatically when the balance is settled.');
   let disbursed=r.disbursed;
   if(d.status==='Disbursed'){if(!/^\d{4}-\d{2}-\d{2}$/.test(d.disbursed||'')||d.disbursed>today()||!Number.isFinite(Date.parse(d.disbursed)))throw new Error('Enter a valid disbursement date, no later than today.');disbursed=d.disbursed;}
   const paid=await db(env).prepare('SELECT COALESCE(SUM(amount),0) AS paid FROM payments WHERE application_id=?').bind(id).first();
   if(paid.paid>0&&d.status!=='Disbursed')throw new Error('A loan with payments must remain disbursed or repaid.');
   if(paid.paid>=r.total&&d.status==='Disbursed')throw new Error('This loan is already repaid.');
   await db(env).batch([db(env).prepare('UPDATE applications SET status=?,disbursed=? WHERE id=? AND owner=?').bind(d.status,disbursed,id,user),log(env,user,'Status: '+r.status+' to '+d.status,id)]);return json({ok:true});
  }
 }
 const doc=path.match(/^\/api\/documents\/([^/]+)$/);
 if(doc&&method==='GET'){
  const d=await db(env).prepare('SELECT * FROM documents WHERE id=? AND owner=?').bind(doc[1],user).first();if(!d)return json({error:'Document not found.'},404);
  const obj=await env.BUCKET.get(d.id);if(!obj)return json({error:'Document unavailable.'},404);
  await log(env,user,'Document viewed',d.application_id).run();
  return new Response(obj.body,{headers:{...securityHeaders,'Content-Type':d.type,'Content-Disposition':"attachment; filename*=UTF-8''"+encodeURIComponent(d.name)}});
 }
 const payment=path.match(/^\/api\/payments\/([^/]+)$/);
 if(payment&&method==='DELETE'){
  const p=await db(env).prepare('SELECT p.* FROM payments p JOIN applications a ON a.id=p.application_id WHERE p.id=? AND a.owner=?').bind(payment[1],user).first();if(!p)return json({error:'Payment not found.'},404);
  await db(env).batch([db(env).prepare('DELETE FROM payments WHERE id=?').bind(p.id),db(env).prepare("UPDATE applications SET status='Disbursed' WHERE id=? AND owner=?").bind(p.application_id,user),log(env,user,'Payment removed: '+(p.amount/100).toFixed(2)+' ZMW',p.application_id)]);return json({ok:true});
 }
 if(path==='/api/audit'&&method==='GET'){const a=await db(env).prepare('SELECT action,reference,time FROM audit WHERE owner=? ORDER BY time DESC LIMIT 100').bind(user).all();return json({events:a.results});}
 return json({error:'Not found.'},404);
}
export default {
 async fetch(request,env,ctx){
  const url=new URL(request.url);
  try{
   if(url.pathname.startsWith('/api/'))return await api(request,env,url,owner(request));
   if(legacy[url.pathname])return Response.redirect(url.origin+legacy[url.pathname],302);
   if(url.pathname==='/legal.php')return Response.redirect(url.origin+'/'+(['privacy','terms','complaints'].includes(url.searchParams.get('p'))?url.searchParams.get('p'):'privacy'),302);
   if(request.method!=='GET'&&request.method!=='HEAD')return new Response('Method not allowed',{status:405});
   let asset=assets[url.pathname];
   if(routes.includes(url.pathname))asset=assets['/index.html'];
   if(!asset)return new Response('Page not found',{status:404,headers:securityHeaders});
   const body=asset.binary?Uint8Array.from(atob(asset.body),c=>c.charCodeAt(0)):asset.body;
   return new Response(request.method==='HEAD'?null:body,{headers:{...securityHeaders,'Content-Type':asset.type,...(url.pathname.endsWith('.webp')||url.pathname.endsWith('.png')?{'Cache-Control':'private, max-age=86400'}:{})}});
  }catch(err){console.error('Avesta request failed',url.pathname,err.message);return json({error:err.message&& !/D1|SQLITE|internal|database|bucket/i.test(err.message)?err.message:'This service is temporarily unavailable. Your input has been kept. Please try again.'},400);}
 }
};
