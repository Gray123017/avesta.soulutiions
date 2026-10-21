/* Stage 4 catalogue UI. Source destinations are displayed as text, never executed.
   Native tabs/disclosures/dialog with per-route cleanup; no data persistence. */
import {escapeHTML as e} from './domain.js?v=20261003-steady-navigation';
import {STAGE_BASE} from './routing.js?v=20261018-stage7b-r1';
import {validSoftware,selectedProduct,actionExplanation} from './software-model.js?v=20261018-stage7b-r1';
const art = `<div class="sw4-art" aria-hidden="true"><span class="sw4-ring"></span><span class="sw4-ring inner"></span><div class="sw4-seal"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="9" y="10" width="19" height="19" rx="3"/><rect x="36" y="10" width="19" height="19" rx="3"/><rect x="9" y="37" width="19" height="19" rx="3"/><path d="M38 40h15v15H38zM18 16v7m-3-3h6M41 20h9M15 46h7M45 41v9m-3-4h6"/></svg><span>AVESTA SOFTWARE</span></div><span class="sw4-art-label a">TRACK</span><span class="sw4-art-label b">MONITOR</span><span class="sw4-art-label c">MANAGE</span></div>`;
function tab(p,selected){return `<button type="button" class="sw4-tab" role="tab" id="sw4-tab-${e(p.id)}" aria-controls="product-${e(p.id)}" aria-selected="${selected}" tabindex="${selected?'0':'-1'}" data-sw4-product="${e(p.id)}"><span class="sw4-tab-icon" aria-hidden="true">${e(p.icon)}</span><span class="sw4-tab-copy"><strong>${e(p.short)}</strong><small>${e(p.badge)}</small></span><span class="sw4-selected-mark" aria-hidden="true">${selected?'✓':'→'}</span></button>`;}
function panel(p,selected){return `<section class="sw4-panel" id="product-${e(p.id)}" role="tabpanel" aria-labelledby="sw4-tab-${e(p.id)}" tabindex="0" ${selected?'':'hidden'}>
 <div class="sw4-product-top"><span class="sw4-product-icon" aria-hidden="true">${e(p.icon)}</span><span class="sw4-badge">${e(p.badge)}</span></div>
 <p class="sw4-kicker">Avesta application</p><h3>${e(p.name)}</h3><p class="sw4-description">${e(p.description)}</p>
 <div class="sw4-meta">${p.meta.map(v=>`<span>${e(v)}</span>`).join('')}</div>
 <h4>What the catalogue lists</h4><ul class="sw4-features">${p.features.map(v=>`<li>${e(v)}</li>`).join('')}</ul>
 <details class="sw4-details"><summary>Platform & release information</summary><div><dl><div><dt>Catalogue label</dt><dd>${e(p.badge)}</dd></div><div><dt>Platform / categories</dt><dd>${e(p.meta.join(' · '))}</dd></div></dl><p>Detailed hardware requirements, package size, signatures and checksums are not specified in this catalogue snapshot. Verify them against the actual release before installation.</p>${p.id==='device-health'?'<p class="sw4-warning"><strong>Test build:</strong> the source labels this as a test build. This page does not establish production readiness or installation safety.</p>':''}</div></details>
 <details class="sw4-details"><summary>Setup & support</summary><div><p>Review the intended action below, or prepare a fictional setup enquiry with Avesta. No remote session, installation or support booking is started here.</p><button type="button" class="button outline" data-sw4-support="${e(p.id)}">Prepare sample setup request</button></div></details>
 <div class="sw4-product-actions">${p.actions.map((a,i)=>`<button type="button" class="button ${i?'outline':''}" data-sw4-action="${i}" data-sw4-id="${e(p.id)}">Preview: ${e(a.label)} <span aria-hidden="true">↗</span></button>`).join('')}</div>
 <p class="sw4-caption">Demonstration only. The version labels and capabilities above come from the repository catalogue, not an independent installer test.</p>
 <button type="button" class="sw4-back" data-sw4-back="${e(p.id)}">↑ Back to product selectors</button>
 </section>`;}
export function renderSoftware(products,problems=[]){
 const ready=validSoftware(products)&&!problems.includes('software');
 const chosen=ready?selectedProduct(products,location.hash):null;
 return `<div id="sw4-root">
 <section class="sw4-hero"><div class="wrap sw4-hero-grid"><div><span class="sw4-pill">Balanced Enterprise · Software Centre</span><h1>Useful software.<br><em>Better visibility.</em></h1><p>Explore tools for asset tracking, stock operations, device health and everyday financial records. Choose a product and see the details that matter.</p><div class="sw4-hero-actions"><button type="button" class="button" data-sw4-scroll="sw4-products">Explore applications <span aria-hidden="true">↓</span></button><a href="${STAGE_BASE}/index.php?page=it#enquiry" class="sw4-hero-link">Try a support enquiry →</a></div><p class="sw4-hero-note">Private staging review · No live downloads, installations or outgoing messages.</p></div>${art}</div></section>
 <section class="sw4-strip" aria-label="How to use this catalogue"><div class="wrap"><div><b>${ready?products.length+' applications':'Catalogue unavailable'}</b><span>${ready?'From the repository catalogue':'Check the public catalogue file'}</span></div><div><b>One focused view</b><span>Select a product to explore</span></div><div><b>Clear before you install</b><span>Release verification remains separate</span></div></div></section>
 <section class="sw4-section" id="sw4-products" tabindex="-1"><div class="wrap"><div class="sw4-section-head"><div><p class="sw4-kicker">Explore the catalogue</p><h2>Tools for<br>how you work.</h2></div><p>Select an application, open its information and preview the intended download or support action. Everything stays in this demonstration.</p></div>
 ${ready?`<div class="sw4-catalogue"><aside class="sw4-chooser"><p class="sw4-choose-label" id="sw4-choose-label">Choose an application</p><div class="sw4-tabs" role="tablist" aria-labelledby="sw4-choose-label" aria-orientation="vertical">${products.map(p=>tab(p,p.id===chosen.id)).join('')}</div><p class="sw4-key-help">Keyboard: ↑ / ↓ to focus, Enter or Space to select. Home and End move to the first and last products.</p><p id="sw4-selection-status" class="sr-only" role="status" aria-live="polite">Selected ${e(chosen.name)}</p></aside><div class="sw4-panels">${products.map(p=>panel(p,p.id===chosen.id)).join('')}</div></div>`:`<div class="sw4-warning" role="alert"><h3>Software information is unavailable</h3><p>The public software catalogue is missing, empty or invalid. Product actions are disabled; other sections remain available.</p><a class="button outline" href="${STAGE_BASE}/index.php?page=it">Return to IT Consulting</a></div>`}
 <div class="sw4-boundary"><strong>Before a production release</strong><p>Check the real installer’s availability, version, system requirements, signature and checksum separately. These buttons deliberately do not retrieve files or prove that a release works.</p></div></div></section>
 <section class="sw4-section sw4-support"><div class="wrap"><div class="sw4-support-box"><div><p class="sw4-kicker">Practical setup guidance</p><h2>Choose with clarity.<br>Start with support.</h2><p>Try the existing fictional IT enquiry journey before we connect real product support.</p></div><a class="button" href="${STAGE_BASE}/index.php?page=it#enquiry">Open enquiry demo →</a></div></div></section>
 <dialog id="sw4-dialog" class="sw4-dialog" aria-labelledby="sw4-action-title" aria-describedby="sw4-action-description"><div class="sw4-dialog-top"><span class="sw4-kicker">Staging action preview</span><button type="button" class="sw4-close" data-sw4-close aria-label="Close action preview">×</button></div><h2 id="sw4-action-title">Action preview</h2><p id="sw4-action-description"></p><p class="sw4-caption">Intended production destination — shown as text only:</p><code id="sw4-action-destination"></code><div class="sw4-boundary"><strong>No live action performed</strong><p>This control does not fetch a file, run software, launch an email application or contact a provider.</p></div><div class="sw4-product-actions"><button type="button" class="button" data-sw4-dialog-support>Prepare sample setup request</button><button type="button" class="button outline" data-sw4-close>Back to product</button></div></dialog>
 </div>`;
}
export function bindSoftware(main,products,{onSupport=()=>{}}={}) {
 const root=main.querySelector('#sw4-root');if(!root)return()=>{};
 const controller=new AbortController(),options={signal:controller.signal};
 const on=(el,event,fn)=>el.addEventListener(event,fn,options);
 const tabs=[...root.querySelectorAll('[data-sw4-product]')];
 const panels=[...root.querySelectorAll('[role="tabpanel"]')];
 const dialog=root.querySelector('#sw4-dialog');let opener=null,actionProduct=null;
 const ready=validSoftware(products);
 const focusProduct=id=>root.querySelector('#sw4-tab-'+id)?.focus();
 function scrollTo(el){el?.scrollIntoView({behavior:window.avestaScrollBehavior?.()||'auto',block:'start'});}
 function select(id,{updateAddress=false,scroll=false}={}){
  const p=ready?products.find(item=>item.id===id):null;if(!p)return;
  tabs.forEach(t=>{const active=t.dataset.sw4Product===id;t.setAttribute('aria-selected',String(active));t.tabIndex=active?0:-1;t.querySelector('.sw4-selected-mark').textContent=active?'✓':'→';});
  panels.forEach(panel=>panel.hidden=panel.id!=='product-'+id);
  root.querySelector('#sw4-selection-status').textContent='Selected '+p.name;
  if(updateAddress)try{history.replaceState({},'',STAGE_BASE+'/index.php?page=software#product-'+id);}catch{/* Selection must work even if browser history is unavailable. */}
  if(scroll){const p=root.querySelector('#product-'+id);scrollTo(p);p.focus({preventScroll:true});}
 }
 function close(){if(dialog.open)dialog.close();}
 function support(p){if(!p)return;close();onSupport(p);}
 on(root,'click',ev=>{
  const b=ev.target instanceof Element?ev.target.closest('button'):null;if(!b)return;
  if(b.dataset.sw4Scroll){const el=root.querySelector('#'+b.dataset.sw4Scroll);scrollTo(el);el?.focus({preventScroll:true});}
  if(b.dataset.sw4Product)select(b.dataset.sw4Product,{updateAddress:true,scroll:ev.detail>0&&window.matchMedia('(max-width:820px)').matches});
  if(b.dataset.sw4Back){scrollTo(root.querySelector('.sw4-chooser'));focusProduct(b.dataset.sw4Back);}
  if(b.hasAttribute('data-sw4-close'))close();
  if(b.dataset.sw4Support)support(products.find(p=>p.id===b.dataset.sw4Support));
  if(b.hasAttribute('data-sw4-dialog-support'))support(actionProduct);
  if(b.hasAttribute('data-sw4-action')){
   const p=ready?products.find(p=>p.id===b.dataset.sw4Id):null;const a=p?.actions[Number(b.dataset.sw4Action)];if(!a)return;
   opener=b;actionProduct=p;
   root.querySelector('#sw4-action-title').textContent=p.name+' — '+a.label;
   root.querySelector('#sw4-action-description').textContent=actionExplanation(a.kind);
   root.querySelector('#sw4-action-destination').textContent=a.destination;
   if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','');
   dialog.querySelector('[data-sw4-close]').focus();
  }
 });
 on(dialog,'close',()=>{opener?.isConnected&&opener.focus({preventScroll:true});opener=null;actionProduct=null;});
 on(dialog,'click',ev=>{if(ev.target===dialog){const r=dialog.getBoundingClientRect();if(ev.clientX<r.left||ev.clientX>r.right||ev.clientY<r.top||ev.clientY>r.bottom)close();}});
 tabs.forEach((t,i)=>on(t,'keydown',ev=>{
  let n;if(ev.key==='ArrowDown')n=(i+1)%tabs.length;else if(ev.key==='ArrowUp')n=(i+tabs.length-1)%tabs.length;else if(ev.key==='Home')n=0;else if(ev.key==='End')n=tabs.length-1;else return;
  ev.preventDefault();tabs[n].focus();
 }));
 on(window,'hashchange',()=>{if(ready)select(selectedProduct(products,location.hash).id);});
 on(window,'pagehide',close);
 return()=>{controller.abort();if(dialog.open)dialog.close();opener=null;actionProduct=null;};
}
