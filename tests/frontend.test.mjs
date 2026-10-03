import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';
import * as domain from '../assets/avesta/domain.js';
import {createGuidedHelp} from '../assets/avesta/guided.js';
import {guideAnswer} from '../assets/avesta/guide.js';
const script=readFileSync('assets/avesta/app.js','utf8').replace(/^import .*$/m,'const {money,quote,rates,schedule,today,escapeHTML:e}=window.__domain;');
const html=readFileSync('index.php','utf8').split('?>')[1].replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
const flush=()=>new Promise(r=>setTimeout(r,20));
async function boot(page='',ratesFail=false,width=1024){
 const w=new Window({url:'https://avesta.test/index.php'+(page?'?page='+page:''),settings:{enableJavaScriptEvaluation:true,disableCSSFileLoading:true,disableJavaScriptFileLoading:true,suppressInsecureJavaScriptEnvironmentWarning:true}});
 w.happyDOM.setWindowSize({width,height:800});w.__domain=domain;w.document.write(html);const calls=[];
 w.fetch=async url=>{calls.push(url);if(url==='/assistant-api.php?action=status')return new Response(JSON.stringify({configured:false,webAvailable:false}));if(url==='/api.php?action=rates')return new Response(JSON.stringify(ratesFail?{ok:false,error:'Feed unavailable'}:{ok:true,table:{USD:1,ZMW:20,EUR:.9,GBP:.8,ZAR:18,BWP:13},as_of:'2026-10-01',feed:'Test feed',stale:true}),{status:ratesFail?502:200});if(url.startsWith('/assets/avesta/'))return new Response(readFileSync('.'+url,'utf8'));throw new Error('Unexpected endpoint '+url);};
 w.eval(script);await flush();return {w,d:w.document,calls};
}
test('All eight redesigned public pages render and link to PHP destinations',async()=>{
 for(const page of ['','lending','how-it-works','calculator','currency','it','about','contact']){
 const {w,d,calls}=await boot(page);assert.equal(d.querySelectorAll('main h1').length,1,page);assert.ok(d.querySelector('main').textContent.length>100);assert.ok(!d.querySelector('main').textContent.includes('undefined'));
 for(const a of d.querySelectorAll('a[href^="/"]')){const u=new URL(a.href);assert.ok(u.pathname==='/'||u.pathname.endsWith('.php'),a.href);}
 assert.ok(d.querySelector('a[href="/apply.php"]'));assert.ok(d.querySelector('a[href="/login.php"]'));assert.ok(d.querySelector('a[href="/legal.php?p=privacy"]'));assert.ok(calls.every(c=>!c.startsWith('/api/')));
 await w.happyDOM.close();}
});
test('Navigation, browser history and mobile menu remain usable',async()=>{
 const {w,d}=await boot();d.querySelector('.desktop-nav a').click();await flush();assert.equal(w.location.search,'?page=lending');assert.ok(d.querySelector('.quote-card'));
 d.querySelector('.menu-toggle').click();assert.equal(d.querySelector('#mobile-nav').hidden,false);d.dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape'}));assert.equal(d.querySelector('#mobile-nav').hidden,true);
 w.history.replaceState({},'','/index.php?page=contact');w.dispatchEvent(new w.PopStateEvent('popstate'));assert.ok(d.querySelector('main').textContent.includes('info@avesta.solutions'));await w.happyDOM.close();
});
test('Calculator retains rates and routes to existing application without an incompatible API',async()=>{
 const {w,d}=await boot('calculator');d.querySelector('[data-week="3"]').click();assert.ok(d.querySelector('#calc-result').textContent.includes(domain.money(1250)));assert.equal(d.querySelector('#calc-apply').getAttribute('href'),'/apply.php');
 d.querySelector('#calc-amount').value='-1';d.querySelector('#calc-amount').dispatchEvent(new w.Event('input'));assert.ok(d.querySelector('#calc-error').textContent.includes('valid amount'));await w.happyDOM.close();
});
test('PHP exchange rates adapt and stale rates are disclosed; failure enables manual conversion',async()=>{
 for(const fail of [false,true]){const {w,d}=await boot('currency',fail);if(fail){assert.equal(d.querySelector('#fx-mode').value,'manual');assert.equal(d.querySelector('#fx-manual').hidden,false);}else{assert.ok(d.querySelector('#fx-source').textContent.includes('cached'));d.querySelector('#fx-form').dispatchEvent(new w.Event('submit',{cancelable:true}));assert.ok(d.querySelector('#fx-result').textContent.includes('50.00'),d.querySelector('#fx-result').textContent);}await w.happyDOM.close();}
});
test('IT service filtering, safe guide and enquiry preparation work without submissions',async()=>{
 const {w,d,calls}=await boot('it');assert.equal(d.querySelectorAll('.service-card').length,12);assert.ok(d.querySelector('.service-card a[href="/downloads.php"]'));d.querySelector('[data-help="printer"]').click();assert.ok(d.querySelector('#help-result a[href^="https://support.microsoft.com"]'));
 for(const [id,v] of [['it-name','Test'],['it-phone','000'],['it-location','Ndola'],['it-details','Printer offline']])d.getElementById(id).value=v;
 d.querySelector('#enquiry-form').dispatchEvent(new w.Event('submit',{cancelable:true}));assert.ok(d.querySelector('#enquiry-result a[href^="https://wa.me/"]'));assert.equal(calls.length,2);await w.happyDOM.close();
});
test('G.I.T gives source links locally and escapes chat content',async()=>{
 const {w,d,calls}=await boot();w.__guide=guideAnswer;w.__guided=createGuidedHelp;w.eval(readFileSync('assets/avesta/assistant.js','utf8').replace(/^import .*$/gm,'').replace(/^let guidePromise;/m,'const guideAnswer=window.__guide,createGuidedHelp=window.__guided;let guidePromise;'));
 d.querySelector('#chat-launch').click();assert.equal(d.querySelector('#avesta-chat').hidden,false);d.querySelector('[data-chat="My printer is offline"]').click();await flush();assert.ok(d.querySelector('#chat-log a[href^="https://support.microsoft.com"]'));assert.equal(d.querySelector('#chat-web').disabled,true);assert.ok(calls.every(c=>c.startsWith('/assets/avesta/')||c==='/assistant-api.php?action=status'));
 d.querySelector('#chat-question').value='<img src=x onerror=alert(1)>';d.querySelector('#chat-form').dispatchEvent(new w.Event('submit',{cancelable:true}));await flush();assert.equal(d.querySelectorAll('#chat-log img').length,0);await w.happyDOM.close();
});

test('Phone service and footer sections start collapsed, expand on tap and retain links',async()=>{
 const {w,d}=await boot('it',false,390);
 // Initialize happy-dom's media listener state at the starting phone width.
 w.dispatchEvent(new w.Event('resize'));await flush();
 const services=[...d.querySelectorAll('#service-grid [data-compact-toggle]')];assert.equal(services.length,12);
 for(const button of services){assert.equal(button.getAttribute('aria-expanded'),'false');assert.equal(d.getElementById(button.getAttribute('aria-controls')).hidden,true);}
 services[0].click();assert.equal(services[0].getAttribute('aria-expanded'),'true');assert.equal(d.getElementById(services[0].getAttribute('aria-controls')).hidden,false);
 services[1].click();assert.equal(services[0].getAttribute('aria-expanded'),'false');assert.equal(services[1].getAttribute('aria-expanded'),'true');
 services[1].click();assert.equal(services[1].getAttribute('aria-expanded'),'false');
 const software=services.at(-1);software.click();const details=d.getElementById(software.getAttribute('aria-controls'));assert.ok(details.querySelector('a[href="/downloads.php"]'));
 details.querySelector('[data-enquire]').click();assert.equal(d.querySelector('#it-service').value,'G.I.T Device Health Monitoring');
 const footer=[...d.querySelectorAll('.footer-grid [data-compact-toggle]')];assert.equal(footer.length,3);
 for(const button of footer)assert.equal(button.getAttribute('aria-expanded'),'false');
 footer[0].click();assert.equal(d.getElementById(footer[0].getAttribute('aria-controls')).hidden,false);
 footer[1].click();assert.equal(footer[0].getAttribute('aria-expanded'),'false');assert.ok(d.getElementById(footer[1].getAttribute('aria-controls')).querySelector('a[href="mailto:info@avesta.solutions"]'));
 d.querySelector('#service-search').value='printer';d.querySelector('#service-search').dispatchEvent(new w.Event('input'));assert.equal(d.querySelectorAll('#service-grid .service-card').length,1);assert.equal(d.querySelector('#service-grid [data-compact-toggle]').getAttribute('aria-expanded'),'false');
 w.happyDOM.setWindowSize({width:1280,height:800});await flush();for(const button of d.querySelectorAll('[data-compact-toggle]'))assert.equal(button.getAttribute('aria-expanded'),'true');
 w.happyDOM.setWindowSize({width:320,height:800});await flush();for(const button of d.querySelectorAll('[data-compact-toggle]'))assert.equal(button.getAttribute('aria-expanded'),'false');
 await w.happyDOM.close();
});
