/* Stage 3 — source-derived IT catalogue and LOCAL enquiry simulation.
 * Ported concepts: approved Step 3 preview; avesta-site app/index.php enquiry and handoff.
 * No live API, Web Storage, file upload, notification provider or model invocation.
 */
import {escapeHTML as e} from './domain.js?v=20261003-steady-navigation';
import {STAGE_BASE} from './routing.js?v=20261018-stage7b-r1';
import {CATEGORY_NAMES, ADVISE, OTHER, LIMITS, catalogue, filterServices, findHelp, serviceForHelp,
        normalizeEnquiry, validateEnquiry, exampleEnquiry} from './support-model.js?v=20261018-stage7b-r1';
const icon=`<svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><rect x="14" y="19" width="72" height="48" rx="4"/><path d="M40 81h20M50 67v14M29 43l13 12 28-30"/></svg>`;
function card(s){return `<article class="it3-service"><span class="it3-number">${String(s.index+1).padStart(2,'0')} / ${e(s.category)}</span><h3>${e(s.name)}</h3><details><summary>Service details <span aria-hidden="true">+</span></summary><div class="it3-service-body"><p>${e(s.description)}</p><button class="button it3-small" type="button" data-it-service="${s.index}">Prepare sample request <span aria-hidden="true">→</span></button>${s.download?`<a class="it3-inline" href="${STAGE_BASE}/downloads.php">Software Centre</a>`:''}</div></details></article>`;}
export function renderConsulting(services,help,problems=[]){
 const categories=[...CATEGORY_NAMES];
 if(catalogue(services).some(s=>s.category==='Other services'))categories.push('Other services');
 return `<div class="it3" id="it3-root">
 <section class="it3-hero" aria-labelledby="it3-title"><div class="wrap it3-hero-grid"><div class="it3-copy">
  <div class="it3-tag">Avesta Consulting · Technology support</div><h1 id="it3-title">Less downtime.<br><em>More doing.</em></h1>
  <p>From computer repairs and reliable networks to data protection and practical IT advice, Avesta helps you keep work moving.</p>
  <div class="actions"><button type="button" class="button" data-it-scroll="it3-services">Explore IT services <span aria-hidden="true">→</span></button><button type="button" class="it3-textbutton" data-it-scroll="enquiry">Try the request journey <span aria-hidden="true">↓</span></button></div>
  <p class="it3-hero-note">Staging demonstration · No technician, ticket or message is connected.</p>
 </div><div class="it3-art" aria-hidden="true"><div class="it3-ring"></div><div class="it3-ring two"></div><div class="it3-seal">${icon}<small>AVESTA / TECHNOLOGY</small></div><span class="it3-label a">SECURITY</span><span class="it3-label b">CONNECTIVITY</span><span class="it3-label c">SUPPORT</span></div></div></section>
 <section class="it3-trust" aria-label="Technology services"><div class="wrap"><div><span aria-hidden="true">◇</span><p><b>Devices & software</b><small>Repair, maintenance and setup</small></p></div><div><span aria-hidden="true">◎</span><p><b>Networks & protection</b><small>Connectivity, backups and security</small></p></div><div><span aria-hidden="true">✓</span><p><b>Data & advice</b><small>Practical digital support</small></p></div></div></section>
 <section class="it3-section" id="it3-services" tabindex="-1"><div class="wrap">
  <div class="it3-section-head"><div><p class="it3-kicker">Explore what we offer</p><h2>IT solutions,<br>clearly organised.</h2></div><p>Choose a category, search for a service and open its details. Preparing a sample request does not contact Avesta.</p></div>
  <div id="it3-data-warning" class="it3-warning" ${problems.length?'':'hidden'} role="status">${problems.includes('services')?'The service catalogue is unavailable. Sample enquiries are disabled until its data is restored. ':''}${problems.includes('help')?'The written help catalogue is unavailable. Other available sections remain usable.':''}</div>
  <label class="it3-search" for="it3-search">Search services<input id="it3-search" type="search" maxlength="160" autocomplete="off" placeholder="Try Wi-Fi, printer or backup"></label>
  <div class="it3-filters" role="group" aria-label="Filter IT services">${categories.map((name,i)=>`<button type="button" data-it-filter="${e(name)}" aria-pressed="${i===0}">${e(name)}</button>`).join('')}</div>
  <p class="it3-count" id="it3-count" role="status"></p><div class="it3-grid" id="it3-grid"></div>
  <div class="it3-empty" id="it3-empty" hidden><h3>No matching services</h3><p>Try a different search or clear the filters.</p><button type="button" class="button outline" id="it3-clear-search">Clear filters</button></div>
 </div></section>
 <section class="it3-section it3-soft" id="it3-process" tabindex="-1"><div class="wrap it3-split"><div><p class="it3-kicker">A practical process</p><h2>A clear route<br>to the next step.</h2><p>The live service journey begins with an enquiry and an agreed scope. Here, you can test its presentation without sending a request or agreeing to work.</p></div><ol class="it3-process"><li><span>01</span><div><h3>Tell us the issue</h3><p>Choose the service and describe the device, network or work you need help with.</p></div></li><li><span>02</span><div><h3>Review the approach</h3><p>The live team would discuss scope and cost before any work starts. No price or response-time promise is made by this demo.</p></div></li><li><span>03</span><div><h3>Receive support</h3><p>Technician assignment and status tracking belong to the later authenticated portal integration.</p></div></li></ol></div></section>
 <section class="it3-section" id="help" tabindex="-1"><div class="wrap it3-split"><div><p class="it3-kicker">Written IT help</p><h2>Start with a<br>safe first step.</h2><p>Search the existing Avesta help catalogue. These are written examples, not live AI responses, a current diagnostic assessment or remote access to your device.</p><div class="it3-help-chips" aria-label="Sample help questions">${[['printer','Printer offline'],['wifi','Wi-Fi drops'],['nointernet','No internet'],['slow','Slow computer'],['virus','Suspicious activity'],['backup','Backups']].map(([id,label])=>`<button type="button" data-it-help="${id}" ${problems.includes('help')?'disabled':''}>${label}</button>`).join('')}</div><p class="it3-muted">Use fictional problems only. Do not enter passwords, remote-access codes or customer information.</p></div>
 <div class="it3-panel"><form id="it3-help-form" autocomplete="off" novalidate><fieldset id="it3-help-fields" disabled><legend class="sr-only">Search written help</legend><label for="it3-question">Describe a sample problem<input id="it3-question" type="search" autocomplete="off" maxlength="300" placeholder="For example: printer offline"></label><button class="button secondary" type="submit">Find guidance</button></fieldset></form><div id="it3-help-result" class="it3-help-result" aria-live="polite"><p>Choose a topic or enter a fictional example to read relevant guidance.</p></div><button id="it3-handoff" class="button outline" type="button" hidden>Carry this into a sample enquiry <span aria-hidden="true">↓</span></button><p class="it3-small-note">No live research or outgoing messages. Reference titles are shown as text to keep this test on staging.</p></div></div></section>
 <section class="it3-section it3-soft" id="enquiry" tabindex="-1"><div class="wrap it3-enquiry-layout"><aside><p class="it3-kicker">Request journey · Demo only</p><h2>Tell us what<br>needs sorting.</h2><p>Test service selection, review and a simulated handoff. Nothing is submitted, saved as a ticket, assigned to staff or sent by email or WhatsApp.</p><div class="it3-boundary"><b>Use the fictional example</b><p>No real names, contact numbers, documents, passwords or device-access codes. Enter a contact label such as <strong>DEMO-0000</strong>.</p></div><p class="it3-muted">Form details are held only in this page's working state. Clear, completion or leaving this page resets the demonstration. Browser and device behaviour is outside the app's control.</p></aside>
 <div class="it3-panel it3-form-panel"><ol class="it3-progress" aria-label="Sample enquiry progress"><li data-it-progress="details" aria-current="step"><span>1</span>Details</li><li data-it-progress="review"><span>2</span>Review</li><li data-it-progress="complete"><span>3</span>Demo complete</li></ol>
 <div id="it3-feedback" role="status" aria-live="polite"></div>
 <div id="it3-details"><h3 id="it3-details-title" tabindex="-1">Prepare a sample enquiry</h3><p class="it3-muted">Fields marked required are for testing the journey, not a live service agreement.</p><button class="button outline it3-example" id="it3-fill" type="button" disabled>Fill a fictional example</button>
 <div class="it3-error-summary" id="it3-error-summary" role="alert" tabindex="-1" hidden></div>
 <form id="it3-enquiry-form" autocomplete="off" novalidate><fieldset id="it3-enquiry-fields" disabled><legend class="sr-only">Fictional enquiry details</legend><div class="it3-form-grid">
  <label for="it3-name">Fictional name <span class="it3-required">(required)</span><input id="it3-name" maxlength="80" autocomplete="off" placeholder="Demo Client" aria-describedby="it3-name-error"><span class="it3-field-error" id="it3-name-error"></span></label>
  <label for="it3-phone">Phone / WhatsApp demo label <span class="it3-required">(required)</span><input id="it3-phone" maxlength="40" autocomplete="off" placeholder="DEMO-0000" aria-describedby="it3-phone-error"><span class="it3-field-error" id="it3-phone-error"></span></label>
  <label for="it3-service" class="it3-full">What is it about? <span class="it3-required">(required)</span><select id="it3-service" aria-describedby="it3-service-error"><option>${e(ADVISE)}</option>${services.map(s=>`<option>${e(s.name)}</option>`).join('')}<option>${e(OTHER)}</option></select><span class="it3-field-error" id="it3-service-error"></span></label>
  <label for="it3-location" class="it3-full">Fictional town / location <span class="it3-required">(optional)</span><input id="it3-location" maxlength="100" autocomplete="off" placeholder="Demo office, Ndola" aria-describedby="it3-location-error"><span class="it3-field-error" id="it3-location-error"></span></label>
  <label for="it3-detail" class="it3-full">What is happening? <span class="it3-required">(required)</span><textarea id="it3-detail" maxlength="2000" rows="5" autocomplete="off" placeholder="Describe a fictional problem in at least 15 characters." aria-describedby="it3-detail-error it3-detail-count"></textarea><span id="it3-detail-count" class="it3-counter">0 / 2,000 characters</span><span class="it3-field-error" id="it3-detail-error"></span></label>
 </div><label class="it3-check" for="it3-acknowledged"><input id="it3-acknowledged" type="checkbox" aria-describedby="it3-acknowledged-error"><span>I am using fictional information and understand that this demonstration will not send a request.</span></label><span class="it3-field-error" id="it3-acknowledged-error"></span><div class="it3-form-actions"><button type="submit" class="button secondary">Review sample enquiry <span aria-hidden="true">→</span></button><button id="it3-clear" class="it3-clear" type="button">Clear details</button></div></fieldset></form>
 <noscript><p>JavaScript is required for this local demonstration. Form submission is disabled.</p></noscript></div>
 <section id="it3-review" hidden aria-labelledby="it3-review-title"><p class="it3-kicker">Review before any next step</p><h3 id="it3-review-title" tabindex="-1">Your sample enquiry</h3><p>No request has been sent. Check the fictional details below.</p><dl id="it3-review-data" class="it3-review-data"></dl><div class="it3-boundary"><b>Simulated handoff only</b><p>The next button demonstrates a confirmation. It does not create a request number, notify anyone or reserve an appointment.</p></div><div class="it3-form-actions"><button class="button secondary" id="it3-simulate" type="button">Complete demonstration</button><button class="button outline" id="it3-edit" type="button">Edit details</button><button class="it3-clear" id="it3-cancel" type="button">Cancel & clear</button></div></section>
 <section id="it3-complete" hidden aria-labelledby="it3-complete-title"><div class="it3-done-icon" aria-hidden="true">✓</div><p class="it3-kicker">Demonstration complete</p><h3 id="it3-complete-title" tabindex="-1">Nothing has been submitted.</h3><p>No ticket was created, no technician was assigned and no email or WhatsApp message was sent. The app has cleared the working form details and help question.</p><p class="it3-muted">Live enquiries and request tracking will require a separate, authenticated backend integration.</p><button class="button secondary" id="it3-again" type="button">Try another sample</button></section>
 </div></div></section>
 <section class="it3-section"><div class="wrap it3-final"><div><p class="it3-kicker">One coherent Avesta experience</p><h2>Practical support.<br>Clear next steps.</h2><p>The approved homepage and Lending demonstrations remain available in this isolated build.</p></div><div class="actions"><a class="button" href="${STAGE_BASE}/index.php">Back to homepage</a><a class="button outline" href="${STAGE_BASE}/index.php?page=lending">Explore Lending</a><a class="it3-inline" href="${STAGE_BASE}/downloads.php">Downloads remain disabled</a></div></div></section>
 </div>`;
}
export function bindConsulting(main,services,help,problems=[]){
 const root=main.querySelector('#it3-root'); if(!root)return ()=>{};
 const $=s=>root.querySelector(s), $$=s=>[...root.querySelectorAll(s)];
 const controller=new AbortController(), opts={signal:controller.signal};
 const on=(el,event,fn)=>el.addEventListener(event,fn,opts);
 let category='All services', lastQuestion='', lastArticle=null, reviewSnapshot=null;
 const ready=!problems.includes('services');
 const controls={name:$('#it3-name'),phone:$('#it3-phone'),service:$('#it3-service'),location:$('#it3-location'),detail:$('#it3-detail'),acknowledged:$('#it3-acknowledged')};
 function focus(el){el?.focus({preventScroll:true});}
 function scroll(id){const el=document.getElementById(id); if(!el)return;el.scrollIntoView({behavior:'auto',block:'start'});}
 function say(message){$('#it3-feedback').textContent=message;}
 function progress(state){
  for(const el of $$('[data-it-progress]')){
   const active=el.dataset.itProgress===state;
   if(active)el.setAttribute('aria-current','step'); else el.removeAttribute('aria-current');
  }
 }
 function stage(state,moveFocus=true){
  $('#it3-details').hidden=state!=='details';$('#it3-review').hidden=state!=='review';$('#it3-complete').hidden=state!=='complete';progress(state);
  if(moveFocus){scroll('enquiry');focus($('#it3-'+(state==='details'?'details':state)+'-title'));}
 }
 function updateCount(){ $('#it3-detail-count').textContent=controls.detail.value.length.toLocaleString('en-GB')+' / 2,000 characters'; }
 function errors(list={}){
  for(const [key,el] of Object.entries(controls)){
   $('#it3-'+key+'-error').textContent=list[key]||'';
   if(list[key])el.setAttribute('aria-invalid','true');else el.removeAttribute('aria-invalid');
  }
  const panel=$('#it3-error-summary');panel.hidden=!Object.keys(list).length;
  panel.replaceChildren();
  if(!panel.hidden){const title=document.createElement('strong');title.textContent='Please check the highlighted fields.';panel.append(title);const ul=document.createElement('ul');for(const [key,value] of Object.entries(list)){const li=document.createElement('li'),b=document.createElement('button');b.type='button';b.dataset.itError=key;b.textContent=value;li.append(b);ul.append(li);}panel.append(ul);}
 }
 function values(){return normalizeEnquiry(Object.fromEntries(Object.entries(controls).map(([key,el])=>[key,key==='acknowledged'?el.checked:el.value])));}
 function wipeDetails(){
  $('#it3-enquiry-form').reset();
  for(const [key,el] of Object.entries(controls)){if(key==='acknowledged')el.checked=false;else el.value=key==='service'?ADVISE:'';}
  reviewSnapshot=null;$('#it3-review-data').replaceChildren();errors();updateCount();
 }
 on(window,'avesta:software-support',ev=>{
  const name=ev.detail?.name;
  if(!ready||typeof name!=='string'||name.length>100)return;
  stage('details',false); controls.service.value='Something else';
  if(!controls.detail.value.trim())controls.detail.value='Fictional setup enquiry for '+name+'. Please explain the requirements and installation steps for this demonstration.';
  updateCount(); say('Software context added for '+name+'. Nothing has been sent.');scroll('enquiry');focus(controls.name);
 });
 function wipeHelp(){lastQuestion='';lastArticle=null;$('#it3-question').value='';$('#it3-help-result').innerHTML='<p>Choose a topic or enter a fictional example to read relevant guidance.</p>';$('#it3-handoff').hidden=true;}
 function reset(moveFocus=true){wipeDetails();wipeHelp();say('');stage('details',moveFocus);}
 function display(){
  const found=filterServices(services,category,$('#it3-search').value);
  $('#it3-count').textContent=found.length+' of '+services.length+' services shown';
  $('#it3-grid').innerHTML=found.map(card).join('');
  $('#it3-empty').hidden=found.length>0||!ready;
 }
 function detailsFromService(index){
  if(!ready||!Number.isInteger(index)||!services[index])return;
  reviewSnapshot=null;$('#it3-review-data').replaceChildren();stage('details',false);controls.service.value=services[index].name;
  say('Selected '+services[index].name+'. Existing fictional details have been kept.');scroll('enquiry');focus(controls.name);
 }
 function showHelp(query,explicitId=null){
  if(problems.includes('help'))return;
  lastQuestion=String(query).trim().slice(0,LIMITS.query);
  lastArticle=explicitId?help.find(h=>h.id===explicitId)||null:findHelp(help,lastQuestion);
  const result=$('#it3-help-result');
  if(!lastQuestion){result.innerHTML='<p>Enter a sample problem or choose a topic.</p>';$('#it3-handoff').hidden=true;return;}
  result.innerHTML=lastArticle?`<p class="it3-kicker">From the existing written catalogue</p><h3>${e(lastArticle.title)}</h3><p>${e(lastArticle.answer)}</p><ol>${lastArticle.steps.map(step=>`<li>${e(step)}</li>`).join('')}</ol>${lastArticle.warn?`<p class="it3-warning">${e(lastArticle.warn)}</p>`:''}${Array.isArray(lastArticle.sources)&&lastArticle.sources.length?`<p class="it3-small-note">Source labels: ${lastArticle.sources.map(s=>e(s.title||'Catalogue reference')).join(' · ')}. Links are not opened in this staging demonstration.</p>`:''}`:'<h3>No matching written example</h3><p>This guide has not diagnosed the problem. You can carry the fictional description into a sample enquiry for a demonstration of technician handoff.</p>';
  $('#it3-handoff').hidden=!ready;
 }
 $('#it3-enquiry-fields').disabled=!ready;$('#it3-fill').disabled=!ready;
 $('#it3-help-fields').disabled=problems.includes('help');
 on(root,'click',ev=>{
  const b=ev.target instanceof Element?ev.target.closest('button'):null;if(!b||!root.contains(b))return;
  if(b.dataset.itFilter){category=b.dataset.itFilter;$$('[data-it-filter]').forEach(el=>el.setAttribute('aria-pressed',String(el===b)));display();}
  if(b.hasAttribute('data-it-service'))detailsFromService(Number(b.dataset.itService));
  if(b.dataset.itScroll)scroll(b.dataset.itScroll);
  if(b.dataset.itHelp){const article=help.find(h=>h.id===b.dataset.itHelp);if(article){$('#it3-question').value=article.title;showHelp(article.title,article.id);}}
  if(b.dataset.itError)focus(controls[b.dataset.itError]);
 });
 on($('#it3-search'),'input',display);
 on($('#it3-clear-search'),'click',()=>{category='All services';$('#it3-search').value='';$$('[data-it-filter]').forEach((b,i)=>b.setAttribute('aria-pressed',String(i===0)));display();focus($('#it3-search'));});
 on($('#it3-help-form'),'submit',ev=>{ev.preventDefault();showHelp($('#it3-question').value);});
 on($('#it3-handoff'),'click',()=>{
  if(!ready||!lastQuestion)return;
  const message='Fictional help-guide example: '+lastQuestion;
  const keep=Boolean(controls.detail.value.trim());
  if(!keep)controls.detail.value=message.slice(0,LIMITS.detail);
  if(controls.service.value===ADVISE)controls.service.value=serviceForHelp(lastArticle?.id,services);
  stage('details',false);reviewSnapshot=null;$('#it3-review-data').replaceChildren();updateCount();
  say(keep?'Existing fictional description kept. You may edit it before review.':'Fictional help question copied locally. Nothing was sent to a technician.');scroll('enquiry');focus(controls.name);
 });

 // The Stage 7B floating guide can pass only an existing public topic ID in this tab.
 on(window,'avesta:guide-topic',ev=>{
  if(!ready||typeof ev.detail?.id!=='string')return;
  const article=help.find(h=>h.id===ev.detail.id);if(!article)return;
  const keep=Boolean(controls.detail.value.trim());
  if(!keep)controls.detail.value=('Fictional guidance example: '+article.title).slice(0,LIMITS.detail);
  if(controls.service.value===ADVISE)controls.service.value=serviceForHelp(article.id,services);
  stage('details',false);reviewSnapshot=null;$('#it3-review-data').replaceChildren();updateCount();
  say(keep?'Existing fictional description kept. No message was sent.':'Saved topic copied locally. No question text, account details or message was sent.');
  scroll('enquiry');focus(controls.name);
 });
 on($('#it3-fill'),'click',()=>{
  const d=exampleEnquiry(services); for(const [key,el] of Object.entries(controls)){if(key==='acknowledged')el.checked=false;else el.value=d[key];}
  reviewSnapshot=null;errors();updateCount();say('Fictional example filled. Read and tick the demonstration acknowledgement before review.');
 });
 on($('#it3-enquiry-form'),'input',()=>{updateCount();reviewSnapshot=null;});
 on($('#it3-enquiry-form'),'submit',ev=>{
  ev.preventDefault();if(!ready)return;
  const d=values(),list=validateEnquiry(d,services);errors(list);
  if(Object.keys(list).length){focus($('#it3-error-summary'));return;}
  reviewSnapshot=JSON.stringify(d);const out=$('#it3-review-data');out.replaceChildren();
  for(const [label,key] of [['Fictional name','name'],['Demo contact label','phone'],['Service','service'],['Fictional location','location'],['Fictional issue','detail']]){
   const row=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;dd.textContent=d[key]||'Not specified';row.append(dt,dd);out.append(row);
  }
  say('');stage('review');
 });
 on($('#it3-edit'),'click',()=>{stage('details');say('Edit your fictional details, then review them again.');});
 on($('#it3-simulate'),'click',()=>{
  const d=values(),list=validateEnquiry(d,services);
  if(!ready||Object.keys(list).length||reviewSnapshot!==JSON.stringify(d)){stage('details');errors(list);say('Details changed. Please review the current values again.');return;}
  wipeDetails();wipeHelp();say('');stage('complete');
 });
 for(const id of ['it3-clear','it3-cancel','it3-again'])on($('#'+id),'click',()=>reset());
 on(window,'pagehide',()=>reset(false));
 on(window,'pageshow',ev=>{if(ev.persisted)reset(false);});
 display();updateCount();
 return ()=>{reset(false);controller.abort();};
}
