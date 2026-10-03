import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync,mkdtempSync,writeFileSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {spawnSync} from 'node:child_process';
import {Window} from 'happy-dom';
import {guideAnswer} from '../assets/avesta/guide.js';
import {createGuidedHelp} from '../assets/avesta/guided.js';
const php=process.env.AVESTA_TEST_PHP||'php';
test('Server key stays private; paid requests are capped and citations accept only HTTPS',{skip:spawnSync(php,['-v']).status!==0},()=>{
 const dir=mkdtempSync(join(tmpdir(),'avesta-chat-'));
 try {
 writeFileSync(join(dir,'test.php'),`<?php
 require ${JSON.stringify(resolve('assistant-lib.php'))};
 function ck($v){if(!$v)throw new Exception('Chat check failed');}
 putenv('OPENAI_API_KEY'); mkdir(__DIR__.'/public_html');
 ck(av_chat_config(__DIR__.'/public_html')['key']==='');
 file_put_contents(__DIR__.'/avesta-secrets.php','<?php echo "private-output"; return ["OPENAI_API_KEY"=>"sk-abcdefghijklmnopqrstuvwxyz123456"];');
 ob_start(); $c=av_chat_config(__DIR__.'/public_html'); $out=ob_get_clean(); ck($out==='');ck($c['key']==='sk-abcdefghijklmnopqrstuvwxyz123456');
 $f=__DIR__.'/rate.php'; for($i=0;$i<10;$i++)ck(av_chat_reserve($f,'192.0.2.1',90000));
 ck(!av_chat_reserve($f,'192.0.2.1',90000));ck(av_chat_reserve($f,'192.0.2.1',93600));
 for($i=0;$i<89;$i++)ck(av_chat_reserve($f,'client-'.$i,93600));ck(!av_chat_reserve($f,'new',93600));
 ck(av_chat_reserve($f,'new',180000));ck(strpos(file_get_contents($f),'192.0.2.1')===false);
 ck(av_chat_answer(['output'=>[]])===null);
 $r=av_chat_answer(['output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'Safe answer','annotations'=>[['type'=>'url_citation','url'=>'https://support.microsoft.com/test','title'=>'Official'],['type'=>'url_citation','url'=>'javascript:alert(1)','title'=>'Bad']]]]]]]);
 ck($r['answer']==='Safe answer');ck(count($r['sources'])===1);echo 'passed';`);
 const r=spawnSync(php,[join(dir,'test.php')],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);assert.equal(r.stdout,'passed');
 }finally{rmSync(dir,{recursive:true,force:true});}
});
for(const fail of [false,true])test('AI mode sends only the opted-in question; '+(fail?'provider failure permits saved guidance':'answers and sources are escaped'),async()=>{
 const w=new Window({url:'https://avesta.solutions/',settings:{enableJavaScriptEvaluation:true,suppressInsecureJavaScriptEnvironmentWarning:true}});w.__guide=guideAnswer;w.__guided=createGuidedHelp;const calls=[];
 w.fetch=async(url,opts)=>{calls.push({url,opts});if(url.includes('action=status'))return new Response(JSON.stringify({configured:true}));if(url==='/assistant-api.php')return new Response(JSON.stringify(fail?{error:'OpenAI rejected the server key.'}:{answer:'<img src=x onerror=alert(1)>',sources:[{url:'https://support.microsoft.com/test',title:'Official'},{url:'javascript:alert(1)',title:'Bad'}]}),{status:fail?502:200});return new Response(readFileSync('assets/avesta/help.json','utf8'));};
 w.eval(readFileSync('assets/avesta/assistant.js','utf8').replace(/^import .*$/gm,'').replace(/^let guidePromise;/m,'const guideAnswer=window.__guide,createGuidedHelp=window.__guided;let guidePromise;'));
 await new Promise(r=>setTimeout(r,20));const d=w.document;assert.equal(d.querySelector('#chat-web').disabled,false);assert.equal(calls.filter(c=>c.opts?.method==='POST').length,0);
 d.querySelector('#chat-web').checked=true;d.querySelector('#chat-question').value='My printer is offline';d.querySelector('#chat-form').dispatchEvent(new w.Event('submit',{cancelable:true}));await new Promise(r=>setTimeout(r,20));
 const req=calls.find(c=>c.opts?.method==='POST');assert.deepEqual(JSON.parse(req.opts.body),{message:'My printer is offline',web:true,scope:'site'});assert.equal(req.opts.headers['X-Avesta-Chat'],'1');assert.equal(d.querySelector('#chat-send').disabled,false);
 if(fail){assert.match(d.querySelector('#chat-error').textContent,/server key/);d.querySelector('#chat-web').checked=false;d.querySelector('[data-chat="My printer is offline"]').click();await new Promise(r=>setTimeout(r,20));assert.match(d.querySelector('#chat-log').textContent,/Guided saved advice/);}
 else{assert.equal(d.querySelector('#chat-log img'),null);assert.ok(d.querySelector('#chat-log a[href="https://support.microsoft.com/test"]'));assert.equal(d.querySelector('#chat-log a[href^="javascript:"]'),null);}
 await w.happyDOM.close();
});

test('IT consultation redirects loan questions; general site keeps loan and software guidance',()=>{
 assert.match(guideAnswer('How do I apply for a loan?',[],'site').answer,/15%/);
 assert.doesNotMatch(guideAnswer('How do I apply for a loan?',[],'it').answer,/15%|30%/);
 assert.match(guideAnswer('How do I apply for a loan?',[],'it').answer,/IT consultation/);
 assert.match(guideAnswer('What IT services do you offer?',[],'it').answer,/backup and recovery/);
 assert.equal(guideAnswer('Where can I download Asset Tracker?',[],'site').sources[0].url,'/downloads.php');
});

test('Navigation changes chatbot scope, clears old answers, and sends the correct API mode',async()=>{
 const w=new Window({url:'https://avesta.solutions/',settings:{enableJavaScriptEvaluation:true,suppressInsecureJavaScriptEnvironmentWarning:true}});w.__guide=guideAnswer;w.__guided=createGuidedHelp;const calls=[];
 w.fetch=async(url,opts)=>{if(opts?.method==='POST')calls.push(JSON.parse(opts.body));return new Response(JSON.stringify(url.includes('status')?{configured:true}:{answer:'IT support answer'}));};
 w.eval(readFileSync('assets/avesta/assistant.js','utf8').replace(/^import .*$/gm,'').replace(/^let guidePromise;/m,'const guideAnswer=window.__guide,createGuidedHelp=window.__guided;let guidePromise;'));
 await new Promise(r=>setTimeout(r,20));const d=w.document;
 d.querySelector('#chat-log').append(d.createTextNode('old loan discussion'));
 w.history.pushState({},'', '/index.php?page=it');d.dispatchEvent(new w.CustomEvent('avesta:route'));
 assert.match(d.querySelector('#chat-mode').textContent,/IT consultation only/);
 assert.doesNotMatch(d.querySelector('#chat-log').textContent,/old loan/);
 assert.equal(d.querySelector('[data-chat="How do I apply for a loan?"]'),null);
 d.querySelector('#chat-web').checked=true;d.querySelector('#chat-question').value='Help with Wi-Fi';d.querySelector('#chat-form').dispatchEvent(new w.Event('submit',{cancelable:true}));
 await new Promise(r=>setTimeout(r,20));assert.equal(calls[0].scope,'it');
 w.history.pushState({},'', '/index.php?page=lending');d.dispatchEvent(new w.CustomEvent('avesta:route'));
 assert.match(d.querySelector('#chat-mode').textContent,/loans & IT/);
 assert.ok(d.querySelector('[data-chat="How do I apply for a loan?"]'));
 await w.happyDOM.close();
});

test('Server scopes exclude lending data from IT prompts',{skip:spawnSync(php,['-v']).status!==0},()=>{
 const script=`require ${JSON.stringify(resolve('assistant-lib.php'))}; $root=${JSON.stringify(resolve('.'))}; $site=av_chat_instructions($root,'site'); $it=av_chat_instructions($root,'it'); if(strpos($site,'15%')===false || strpos($site,'Airtel Money')===false || strpos($it,'15%')!==false || strpos($it,'CURRENT SCOPE: IT CONSULTATION ONLY')===false || strpos($it,'Cybersecurity')===false || av_chat_scope(['scope'=>'it'])!=='it' || av_chat_scope(['scope'=>'arbitrary'])!=='site')throw new Exception('scope failure'); echo 'passed';`;
 const r=spawnSync(php,['-r',script],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);assert.equal(r.stdout,'passed');
});
