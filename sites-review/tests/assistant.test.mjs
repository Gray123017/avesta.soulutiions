import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';
import worker from '../dist/server/index.js';
import {environment,call,jsonOptions} from './helpers.mjs';

test('Assistant fallback is honest, sourced and isolated from private records',async()=>{
 const env=environment();
 assert.equal((await call(worker,env,'/api/assistant',jsonOptions('POST',{message:'printer offline'}),null)).status,401);
 const result=await(await call(worker,env,'/api/assistant',jsonOptions('POST',{message:'printer offline',web:true}))).json();
 assert.equal(result.mode,'guide');assert.match(result.notice,/not connected/);assert.ok(result.sources[0].url.startsWith('https://support.microsoft.com/'));
 assert.equal((await call(worker,env,'/api/assistant',jsonOptions('POST',{message:'My NRC is 123456/78/9'}))).status,400);
 assert.equal((await call(worker,env,'/api/assistant',jsonOptions('POST',{message:'x'.repeat(1001)}))).status,400);
 assert.equal(env.sqlite.prepare('SELECT count(*) n FROM audit').get().n,0);
});
test('Live AI sends bounded context, returns real citation annotations and enforces a persistent budget',async()=>{
 const env=environment();env.OPENAI_API_KEY='test-only';let calls=0;
 const old=globalThis.fetch;globalThis.fetch=async(url,options)=>{calls++;assert.equal(url,'https://api.openai.com/v1/responses');const input=JSON.parse(options.body);assert.equal(input.store,false);assert.equal(input.tools[0].type,'web_search');assert.ok(input.input.length<=7);return new Response(JSON.stringify({status:'completed',output:[{type:'web_search_call'},{type:'message',content:[{type:'output_text',text:'Check the queue.',annotations:[{type:'url_citation',url:'https://support.microsoft.com/help',title:'Microsoft',start_index:0,end_index:16},{type:'url_citation',url:'javascript:alert(1)',title:'bad',start_index:0,end_index:1}]}]}]}));};
 try{
  const res=await(await call(worker,env,'/api/assistant',jsonOptions('POST',{message:'printer offline',web:true}))).json();assert.equal(res.mode,'web');assert.equal(res.citations.length,1);
  const hour=Math.floor(Date.now()/3600000);env.sqlite.prepare('UPDATE assistant_usage SET count=20 WHERE hour=?').run(hour);
  const limited=await(await call(worker,env,'/api/assistant',jsonOptions('POST',{message:'printer offline',web:true}))).json();assert.equal(limited.mode,'guide');assert.match(limited.notice,/limit/);assert.equal(calls,1);
 }finally{globalThis.fetch=old;}
});
test('Provider failure falls back without revealing credentials or raw provider errors',async()=>{
 const env=environment();env.OPENAI_API_KEY='test-only';const old=globalThis.fetch;globalThis.fetch=async()=>new Response('secret provider error',{status:401});
 try{const res=await(await call(worker,env,'/api/assistant',jsonOptions('POST',{message:'backup',web:true}))).json();assert.equal(res.mode,'guide');assert.ok(!JSON.stringify(res).includes('secret'));assert.match(res.notice,/temporarily unavailable/);}finally{globalThis.fetch=old;}
});
test('Read-only health distinguishes working storage from missing bindings',async()=>{
 const env=environment();const good=await call(worker,env,'/api/health',{},null);assert.equal(good.status,200);assert.equal((await good.json()).assistant,'guide-only');
 assert.equal((await call(worker,{},'/api/health',{},null)).status,503);assert.equal(env.objects.size,0);
});
test('Chat opens, answers safely, displays sources and clears history',async()=>{
 const w=new Window({url:'https://review.invalid',settings:{enableJavaScriptEvaluation:true,suppressInsecureJavaScriptEnvironmentWarning:true}});w.document.write('<body></body>');let payload;
 w.fetch=async(path,options)=>{if(path.endsWith('/status'))return new Response('{"webAvailable":false}');payload=JSON.parse(options.body);return new Response(JSON.stringify({answer:'<img src=x onerror=alert(1)>',sources:[{url:'javascript:alert(1)',title:'bad'},{url:'https://support.microsoft.com/help',title:'Microsoft'}],notice:'Saved help guide',mode:'guide'}));};
 w.eval(readFileSync('public/assistant.js','utf8'));const d=w.document;d.getElementById('chat-launch').click();assert.equal(d.getElementById('avesta-chat').hidden,false);
 d.getElementById('chat-question').value='printer offline';d.getElementById('chat-form').dispatchEvent(new w.Event('submit',{cancelable:true}));await new Promise(r=>setTimeout(r,20));
 assert.equal(payload.message,'printer offline');assert.equal(d.querySelectorAll('#chat-log img').length,0);assert.equal(d.querySelectorAll('#chat-log a').length,1);assert.equal(d.getElementById('chat-send').disabled,false);
 d.getElementById('chat-clear').click();assert.equal(d.getElementById('chat-log').children.length,0);d.getElementById('chat-close').click();assert.equal(d.getElementById('avesta-chat').hidden,true);await w.happyDOM.close();
});
