import {renderSoftware, bindSoftware} from './software.js?v=20261018-stage7b-r1';
import {validSoftware} from './software-model.js?v=20261018-stage7b-r1';
let software = [], cleanupSoftware = () => {};
import {money,quote,rates,schedule,today,escapeHTML as e} from './domain.js?v=20261003-steady-navigation';
import {STAGE_BASE, hostedURL} from './routing.js?v=20261018-stage7b-r1';
import {renderLending, renderCalculator, renderHowItWorks, bindLendingEstimator} from './lending.js?v=20261018-stage7b-r1';
import {renderConsulting, bindConsulting} from './consulting.js?v=20261018-stage7b-r1';
import {validServices, validHelp} from './support-model.js?v=20261018-stage7b-r1';
let cleanupConsulting = () => {};
let publicDataProblems = [];
const $=(s,root=document)=>root.querySelector(s);
const $$=(s,root=document)=>[...root.querySelectorAll(s)];
const main=$('#main');
let appSaving=false;
let services=[],help=[],filter='All',route=currentRoute(),calcWeeks=2,appStep=0,appData={},appFiles={},records=[],currentRecord=null,toastTimer;
const pageTitles={'/software':'Software Centre','/':'Lending & IT Consulting','/lending':'Avesta Lending','/it':'Avesta Consulting','/calculator':'Loan Calculator','/how-it-works':'How It Works','/currency':'Currency Converter','/apply':'Loan Application','/about':'About Avesta','/contact':'Contact','/portal':'Private Portal','/privacy':'Privacy','/terms':'Terms','/complaints':'Complaints'};
const lendingPaths=['/lending','/how-it-works','/calculator','/currency','/apply'];
const icon=(name)=>{const paths={clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',check:'<path d="M5 12l4 4L19 6"/>',phone:'<path d="M7 3H4v5c0 7 5 12 12 12h5v-3l-5-2-2 2c-3-1-6-4-7-7l2-2z"/>',mail:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',pin:'<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',shield:'<path d="m12 3 8 3v5c0 5-4 9-8 10-4-1-8-5-8-10V6z"/><path d="m8 12 3 3 5-6"/>',file:'<path d="M14 3H5v18h14V8zM14 3v5h5M8 12h8M8 16h6"/>',tech:'<rect x="3" y="3" width="18" height="13" rx="2"/><path d="M8 21h8M12 16v5"/>'};return `<span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24">${paths[name]||paths.check}</svg></span>`;};
const head=(eyebrow,title,subtitle)=>`<section class="page-head"><div class="wrap"><div class="eyebrow">${eyebrow}</div><h1>${title}</h1>${subtitle?`<p>${subtitle}</p>`:''}</div></section>`;
const cta=(title,text,label='/apply',button='Apply for a loan')=>`<div class="cta"><div><h2>${title}</h2><p>${text}</p></div><a class="button" href="${label}">${button}</a></div>`;
function toast(text){$('#toast').textContent=text;$('#toast').classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>$('#toast').classList.remove('show'),5000);}
function currentRoute(){return '/'+(new URLSearchParams(location.search).get('page')||'');}
function mapLinks(){
 document.querySelectorAll('a[href]').forEach(a=>{
  const raw=a.getAttribute('href');if(!raw)return;
  const u=new URL(raw,location.href);
  if(u.origin!==location.origin||!['http:','https:'].includes(u.protocol)){
   a.setAttribute('href',STAGE_BASE+'/notice.php');a.removeAttribute('target');return;
  }
  if(raw.startsWith('/')&&!raw.startsWith('//'))a.setAttribute('href',hostedURL(raw));
 });
}
function scrollToRouteAnchor(){
 if(!location.hash)return;
 try{const id=decodeURIComponent(location.hash.slice(1));requestAnimationFrame(()=>document.getElementById(id)?.scrollIntoView({behavior:'auto',block:'start'}));}catch{/* Invalid hashes never prevent rendering. */}
}
function navigate(path,push=true){
 const target=hostedURL(path),u=new URL(target,location.origin);
 if(u.pathname!==STAGE_BASE+'/index.php'){location.assign(target);return;}
 if(target===location.pathname+location.search+location.hash){scrollToRouteAnchor();return;}
 if(push)history.pushState({},'',target);route=currentRoute();render();
 if(location.hash)scrollToRouteAnchor();else window.scrollTo({top:0,behavior:'instant'});
 main.focus({preventScroll:true});
}
document.addEventListener('click',ev=>{
 if(ev.defaultPrevented||ev.button>0)return;
 const a=ev.target instanceof Element?ev.target.closest('a'):null;if(!a)return;
 if(!a.hasAttribute('href')||a.getAttribute('aria-disabled')==='true'){ev.preventDefault();return;}
 const u=new URL(a.href);
 // Staging does not open contact channels or production destinations.
 if(u.origin!==location.origin||!['http:','https:'].includes(u.protocol)){
  ev.preventDefault();location.assign(STAGE_BASE+'/notice.php');return;
 }
 if(ev.ctrlKey||ev.metaKey||ev.shiftKey||ev.altKey||a.target||a.hasAttribute('download'))return;
 const mapped=new URL(hostedURL(u.pathname+u.search+u.hash),location.origin);
 if(mapped.pathname===STAGE_BASE+'/index.php'){ev.preventDefault();navigate(mapped.pathname+mapped.search+mapped.hash);}
});
window.addEventListener('popstate',()=>{route=currentRoute();render();});
$('.menu-toggle').addEventListener('click',()=>{const open=$('.menu-toggle').getAttribute('aria-expanded')!=='true';$('.menu-toggle').setAttribute('aria-expanded',String(open));$('.menu-toggle').setAttribute('aria-label',open?'Close navigation':'Open navigation');$('#mobile-nav').hidden=!open;});
document.addEventListener('keydown',ev=>{if(ev.key==='Escape'){$('#mobile-nav').hidden=true;$('.menu-toggle').setAttribute('aria-expanded','false');$('.menu-toggle').setAttribute('aria-label','Open navigation');}});
document.addEventListener('pointerdown',ev=>{
 const nav=$('#mobile-nav');const toggle=$('.menu-toggle');
 if(nav&&!nav.hidden&&!ev.target.closest('.header')){nav.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Open navigation');}
});
$('#mobile-nav').addEventListener('click',ev=>{
 if(ev.target.closest('a')){$('#mobile-nav').hidden=true;$('.menu-toggle').setAttribute('aria-expanded','false');$('.menu-toggle').setAttribute('aria-label','Open navigation');$('.menu-toggle').setAttribute('aria-label','Open navigation');}
});
$('.dialog-close').addEventListener('click',()=>$('#dialog').close());
$('#dialog').addEventListener('click',ev=>{if(ev.target===$('#dialog'))$('#dialog').close();});
$('#year').textContent=new Date().getFullYear();
function shell(){
 document.dispatchEvent(new CustomEvent('avesta:route',{detail:{route}}));
 document.title=(pageTitles[route]||'Page not found')+' · Avesta Enterprises';
 $$('.desktop-nav a').forEach(a=>{const active=new URL(a.href).searchParams.get('page')===route.slice(1) || a.getAttribute('href')===route||(a.dataset.nav==='lending'&&lendingPaths.includes(route));a.classList.toggle('active',active);if(active)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');});
 $('#mobile-nav').hidden=true;$('.menu-toggle').setAttribute('aria-expanded','false');$('.menu-toggle').setAttribute('aria-label','Open navigation');
 $('#subnav').innerHTML=lendingPaths.includes(route)?`<nav class="subnav" aria-label="Lending navigation"><div class="wrap">${[['/lending','Overview'],['/how-it-works','How it works'],['/calculator','Loan calculator'],['/currency','Currency converter'],['/apply','Apply now']].map(([p,label])=>`<a href="${p}" class="${route===p?'active':''}" ${route===p?'aria-current="page"':''}>${label}</a>`).join('')}</div></nav>`:'';
}
function home(){return `
<div class="be-switchband"><div class="wrap be-switchband-inner"><span class="be-overline">One local team. Two kinds of support.</span><div class="be-tabs" role="tablist" aria-label="Choose Avesta division"><button id="be-tab-lending" type="button" role="tab" aria-selected="true" tabindex="0" aria-controls="be-panel-lending" data-be-tab="lending">Avesta Lending</button><button id="be-tab-it" type="button" role="tab" aria-selected="false" tabindex="-1" aria-controls="be-panel-it" data-be-tab="it">Avesta Consulting</button></div></div></div>
<section class="be-hero" aria-label="Avesta services" id="be-home">
 <div id="be-panel-lending" class="be-view" role="tabpanel" aria-labelledby="be-tab-lending"><div class="wrap be-hero-grid"><div class="be-hero-copy"><span class="be-pill">${icon('shield')} Avesta Lending · Short-term finance</span><h1>Short-term finance.<br><em>Clearly explained.</em></h1><p>Straightforward lending for people and small businesses. See Avesta's published flat rates, understand the terms, and decide what to do next.</p><div class="be-actions"><a class="button" href="/lending">View published rates <span aria-hidden="true">→</span></a><a class="be-hero-link" href="/how-it-works">How it works <span aria-hidden="true">↓</span></a></div><p class="be-hero-note">1–4 week terms · Terms confirmed in a written agreement.</p></div>${beOrbit('lending')}</div></div>
 <div id="be-panel-it" class="be-view" role="tabpanel" aria-labelledby="be-tab-it" hidden><div class="wrap be-hero-grid"><div class="be-hero-copy"><span class="be-pill">${icon('tech')} Avesta Consulting · Practical IT</span><h1>Less downtime.<br><em>More doing.</em></h1><p>Computer repairs, networks, cybersecurity and practical IT guidance. Choose the support you need from our team in Ndola, serving Zambia.</p><div class="be-actions"><a class="button" href="/it">Explore IT services <span aria-hidden="true">→</span></a><a class="be-hero-link" href="/contact">Talk to our team <span aria-hidden="true">↓</span></a></div><p class="be-hero-note">Practical advice · Service information before you commit.</p></div>${beOrbit('it')}</div></div>
</section>
<div class="be-trust"><div class="wrap be-trust-grid"><div><span aria-hidden="true">⌖</span><p><strong>Rooted in Kansenshi, Ndola</strong><small>One local team, serving Zambia</small></p></div><div><span aria-hidden="true">◷</span><p><strong>Published flat lending rates</strong><small>Choose from 1, 2, 3 or 4 weeks</small></p></div><div><span aria-hidden="true">✓</span><p><strong>Understand before you commit</strong><small>Written terms confirm the agreement</small></p></div></div></div>
<section class="section be-overview"><div class="wrap"><div class="section-head"><div><div class="eyebrow">Avesta Enterprises · Specialist services</div><h2>Two specialties.<br>One standard of care.</h2></div><p>From short-term lending to day-to-day technology support, explore the service that fits your next step.</p></div><div class="card-grid"><article class="card">${icon('file')}<h3>Understand your loan</h3><p>Review the process, documents and published terms before applying.</p><a class="link" href="/how-it-works">How lending works</a></article><article class="card">${icon('clock')}<h3>Plan your repayments</h3><p>See the full borrowing cost and compare repayment schedules.</p><a class="link" href="/calculator">Use the calculator</a></article><article class="card">${icon('tech')}<h3>Get technology support</h3><p>Explore practical IT services, support guidance and troubleshooting.</p><a class="link" href="/it#help">Find IT help</a></article></div></div></section>
<section class="section soft"><div class="wrap">${cta('Let’s work out the next step.','Speak to our team by phone, WhatsApp or email, or visit our Ndola office.','/contact','Get in touch')}</div></section>`;}
function beOrbit(kind){return `<div class="be-orbit" aria-hidden="true"><div class="be-ring be-ring-1"></div><div class="be-ring be-ring-2"></div><div class="be-ring be-ring-3"></div><div class="be-orb"><svg viewBox="0 0 64 64" width="78" height="78" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"><path d="M32 7 52 15v16c0 13-8 23-20 27C20 54 12 44 12 31V15L32 7Z"/><path d="m23 32 6 6 13-15"/></svg><small>AVESTA / NDOLA</small></div><span class="be-flag be-flag-1">${kind==='lending'?'CLEAR TERMS':'IT SUPPORT'}</span><span class="be-flag be-flag-2">LOCAL SUPPORT</span><span class="be-flag be-flag-3">${kind==='lending'?'1–4 WEEKS':'PRACTICAL HELP'}</span></div>`;}
function bindHomeTabs(){
 const buttons=$$('[data-be-tab]'), values=['lending','it'];
 const activate=(value,focus=false)=>{
  for(const key of values){const b=$('#be-tab-'+key),p=$('#be-panel-'+key),active=key===value;p.hidden=!active;b.setAttribute('aria-selected',String(active));b.tabIndex=active?0:-1;}
  if(focus)$('#be-tab-'+value).focus();
 };
 buttons.forEach(b=>{b.addEventListener('click',()=>activate(b.dataset.beTab));b.addEventListener('keydown',ev=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(ev.key))return;ev.preventDefault();const current=values.indexOf(b.dataset.beTab),next=ev.key==='Home'?0:ev.key==='End'?values.length-1:(current+(ev.key==='ArrowRight'?1:-1)+values.length)%values.length;activate(values[next],true);});});
}
function lending(){return renderLending(beOrbit('lending'));}
function how(){return renderHowItWorks();}
function calculator(){return renderCalculator();}
function about(){return head('One company. Two ways to help.','Rooted in Ndola.<br><em>Here for Zambia.</em>','Avesta Enterprises brings accessible short-term finance and practical technology support to people, homes and businesses.')+`<section class="section"><div class="wrap about-block"><div><div class="eyebrow">Our story</div><h2>Keep it simple.<br>Do it properly.</h2><p style="margin-top:23px">Avesta was founded to bridge the gap between everyday Zambians and accessible finance. Our lending approach is built around treating every client with dignity, keeping the process clear, and explaining the costs before an agreement.</p><p>Avesta Consulting keeps the technology that businesses and homes depend on running. From a single laptop to a network and camera system, we explain what the job takes and what it costs before work starts.</p><p>Our office is on Thom Avenue in Kansenshi, Ndola. Lending and remote IT support reach across Zambia, with on-site IT work on the Copperbelt and in Lusaka, and elsewhere by arrangement.</p></div><blockquote class="large-quote" style="margin:0">Good support should be something you can understand — and something you can get hold of.<small>Our approach at Avesta</small></blockquote></div></section><section class="section soft"><div class="wrap"><div class="section-head"><div><div class="eyebrow">What guides us</div><h2>Clear work. Lasting trust.</h2></div></div><div class="card-grid"><article class="card">${icon('check')}<h3>Trust & integrity</h3><p>Clear terms, honest advice and costs explained before you commit.</p></article><article class="card">${icon('shield')}<h3>Responsible support</h3><p>Careful loan assessment and practical IT diagnosis that addresses the cause.</p></article><article class="card">${icon('pin')}<h3>Community focus</h3><p>Rooted in Ndola, supporting our neighbours and businesses across Zambia.</p></article></div></div></section><section class="section"><div class="wrap">${cta('What can we help with?','Explore lending or IT support, or speak with the team about what you need.','/contact','Talk to Avesta')}</div></section>`;}
function contact(){return head('We’re ready to listen','Let’s take<br><em>the next step.</em>','A loan question, a technology problem, or a visit to the office — choose the way that works for you.')+`<section class="section"><div class="wrap"><div class="contact-grid"><article class="card contact-card">${icon('phone')}<h3>Call or WhatsApp</h3><p><a href="tel:+260971013108">0971 013 108</a></p><p><a href="tel:+260769974200">0769 974 200</a></p><div class="actions"><a class="link" href="https://wa.me/260971013108" target="_blank" rel="noopener noreferrer">Open WhatsApp</a></div></article><article class="card contact-card">${icon('mail')}<h3>Email the team</h3><p>Tell us what you need and how we can reach you.</p><a href="mailto:info@avesta.solutions">info@avesta.solutions</a></article><article class="card contact-card">${icon('clock')}<h3>Office hours</h3><p>Monday–Saturday<br><strong>08:00–17:00</strong></p><p>Closed on Sundays.</p></article></div><div class="contact-office"><div class="office-address"><div class="eyebrow">Visit our head office</div><h3>Kansenshi, Ndola</h3><p>House No. 3, Thom Avenue<br>Kansenshi, Ndola, Zambia</p><div class="actions"><a class="button white" href="https://www.google.com/maps/search/?api=1&query=Thom+Avenue+Kansenshi+Ndola+Zambia" target="_blank" rel="noopener noreferrer">Open map & directions</a><a class="button" href="https://wa.me/260971013108?text=Hello%2C%20please%20send%20me%20directions%20to%20your%20office." target="_blank" rel="noopener noreferrer">Ask for directions</a></div></div><div class="card"><div class="eyebrow">Where we work</div><h3>Support across Zambia</h3><p style="margin-top:18px"><strong>Lending:</strong> Applications, mobile-money disbursement and bank transfers are available across Zambia.</p><p style="margin-top:15px"><strong>IT services:</strong> Remote websites, databases, data work, cybersecurity, training and advice reach anywhere. On-site repairs, networks and CCTV cover the Copperbelt and Lusaka, with other areas by arrangement.</p></div></div></div></section>`;}
function it(){return renderConsulting(services, help, publicDataProblems);}
const compactMedia=window.matchMedia('(max-width:680px)');
function setCompact(button,open){
 const panel=document.getElementById(button.getAttribute('aria-controls'));if(!panel)return;
 panel.hidden=!open;button.setAttribute('aria-expanded',String(open));button.querySelector('.compact-sign').textContent=open?'−':'+';
}
function syncCompactGroups(root=document){$$('[data-compact-toggle]',root).forEach(button=>setCompact(button,!compactMedia.matches));}
function bindCompactGroups(root){
 if(!root)return;
 $$('[data-compact-toggle]',root).forEach(button=>button.onclick=()=>{
  const open=button.getAttribute('aria-expanded')!=='true';
  if(compactMedia.matches&&open)$$('[data-compact-toggle]',root).forEach(other=>{if(other!==button)setCompact(other,false);});
  setCompact(button,open);
 });
 syncCompactGroups(root);
}
compactMedia.addEventListener('change',()=>syncCompactGroups());
bindCompactGroups($('.footer-grid'));
function currency(){return head('A useful estimate','Currency, without<br><em>the guesswork.</em>','Convert amounts using a dated indicative rate, or enter an agreed rate manually.')+`<section class="section soft"><div class="wrap calculator-layout"><div class="card"><form id="fx-form"><label class="field" for="fx-amount">Amount<input id="fx-amount" type="number" inputmode="decimal" value="1000" min="0" max="1000000000" step="0.01" required></label><div class="two-col"><label class="field" for="fx-from">From<select id="fx-from">${['ZMW','USD','EUR','GBP','ZAR','BWP'].map(c=>`<option>${c}</option>`).join('')}</select></label><label class="field" for="fx-to">To<select id="fx-to">${['USD','ZMW','EUR','GBP','ZAR','BWP'].map(c=>`<option>${c}</option>`).join('')}</select></label></div><button class="button outline small" id="fx-swap" type="button">Swap currencies</button><label class="field" for="fx-mode" style="margin-top:24px">Rate source<select id="fx-mode"><option value="indicative">Indicative market rate</option><option value="manual">Enter an agreed rate</option></select></label><label class="field" for="fx-rate" id="fx-manual" hidden>Rate: 1 unit of the source currency equals<input id="fx-rate" type="number" inputmode="decimal" step="0.000001" min="0.000001" placeholder="Enter target currency amount"><small>Enter the rate you agreed with the provider.</small></label><button class="button secondary" type="submit">Convert amount</button></form><p class="updated" id="fx-source">Loading indicative rates…</p></div><div class="card"><div class="eyebrow">Conversion estimate</div><div id="fx-result" aria-live="polite"><p>Choose your currencies and convert an amount.</p></div><div class="notice">These are indicative estimates, not Bank of Zambia buy/sell quotes or a promise of a transaction rate. Fees and provider margins may change the amount you receive.</div><p>For a lending estimate in kwacha, use the <a class="link" href="/calculator">loan calculator</a>.</p></div></div></section>`;}
async function request(path,options={}){
 const response=await fetch(hostedURL(path),options);let data;try{data=await response.json();}catch{throw new Error('The service could not be reached. Please try again.');}
 if(!response.ok)throw new Error(data.error||'The service is temporarily unavailable.');return data;
}
async function bindFX(){
 let data=null;const mode=$('#fx-mode'),manual=$('#fx-manual'),source=$('#fx-source');
 mode.addEventListener('change',()=>{manual.hidden=mode.value!=='manual';$('#fx-rate').required=mode.value==='manual';});
 $('#fx-swap').addEventListener('click',()=>{const v=$('#fx-from').value;$('#fx-from').value=$('#fx-to').value;$('#fx-to').value=v;const r=Number($('#fx-rate').value);if(r>0)$('#fx-rate').value=(1/r).toFixed(6);});
 $('#fx-form').addEventListener('submit',ev=>{ev.preventDefault();try{const amount=Number($('#fx-amount').value),from=$('#fx-from').value,to=$('#fx-to').value;if(!Number.isFinite(amount)||amount<0)throw new Error('Enter a valid amount.');let rate=from===to?1:mode.value==='manual'?Number($('#fx-rate').value):data?.rates[to]/data?.rates[from];if(!Number.isFinite(rate)||rate<=0)throw new Error('Enter a valid rate manually, or try loading indicative rates again.');$('#fx-result').innerHTML=`<p>${amount.toLocaleString('en-ZM',{maximumFractionDigits:2})} ${from}</p><div class="quote-total"><strong>${(amount*rate).toLocaleString('en-ZM',{minimumFractionDigits:2,maximumFractionDigits:2})} ${to}</strong></div><p class="updated">1 ${from} = ${rate.toFixed(6)} ${to}</p><p class="updated">${mode.value==='manual'?'Manually entered rate':e('Indicative · '+(data?.source||'')+' · '+(data?dateLabel(data.asOf):''))}</p>`;}catch(err){$('#fx-result').innerHTML=`<p class="error">${e(err.message)}</p>`;}});
 try{const legacy=await request('/api.php?action=rates');if(legacy.ok===false)throw new Error(legacy.error||'Rates are unavailable.');data={rates:legacy.table,asOf:legacy.as_of,source:(legacy.feed||'Market feed')+(legacy.stale?' (cached; may be out of date)':'')};if(route!=='/currency')return;source.textContent='Indicative · '+data.source+' · updated '+dateLabel(data.asOf);$('#fx-form').requestSubmit();}catch(err){if(route!=='/currency')return;source.textContent=err.message;mode.value='manual';mode.dispatchEvent(new Event('change'));}
}
function dateLabel(value){return new Date(String(value).length===10?value+'T12:00:00Z':value).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric',timeZone:'Africa/Lusaka'});}
function render(){
 cleanupSoftware(); cleanupSoftware = () => {};
 cleanupConsulting(); cleanupConsulting = () => {};
 shell();if($('#dialog').open)$('#dialog').close();
 const views={'/software':()=>renderSoftware(software,publicDataProblems),'/' :home,'/lending':lending,'/how-it-works':how,'/calculator':calculator,'/about':about,'/contact':contact,'/it':it,'/currency':currency,};
 main.innerHTML=views[route]?views[route]():head('Avesta Enterprises','Page not found.','Use the navigation to find lending, IT support or the contact details.')+'<section class="section"><div class="wrap"><a class="button secondary" href="/">Return home</a></div></section>';
 if(route==='/')bindHomeTabs();
 if(route==='/lending'||route==='/calculator')bindLendingEstimator();
 if(route==='/it')cleanupConsulting=bindConsulting(main,services,help,publicDataProblems);
 if(route==='/software')cleanupSoftware=bindSoftware(main,software,{onSupport:product=>{navigate('/it#enquiry');window.dispatchEvent(new CustomEvent('avesta:software-support',{detail:{name:product.name}}));}});
 if(route==='/currency')bindFX();
 mapLinks();scrollToRouteAnchor();
}
async function start(){
 let embedded=null;
 try { embedded=JSON.parse($('#avesta-public-data')?.textContent || 'null'); } catch {}
 const definitions=[['services',validServices],['help',validHelp],['software',validSoftware]];
 const loaded=await Promise.all(definitions.map(async ([key,validate])=>{
  if(validate(embedded?.[key]))return embedded[key];
  try {
   const data=await request('/assets/avesta/'+key+'.json',{cache:'no-store'});
   if(validate(data))return data;
  } catch {}
  publicDataProblems.push(key);
  return [];
 }));
 [services,help,software]=loaded;
 render();
}
start();
