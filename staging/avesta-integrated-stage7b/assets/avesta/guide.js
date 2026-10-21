/* Guided interface only. No fetch, account inspection, provider, transcript storage or telemetry. */
import {guideReply,ACTIONS,LIMIT} from './guide-model.js?v=20261018-stage7b-r1';
const root=document.getElementById('av-guide');
if(root){
 const $=s=>root.querySelector(s),panel=$('#ag-panel'),launch=$('#ag-launch'),log=$('#ag-log'),query=$('#ag-query'),scroll=$('#ag-scroll'),status=$('#ag-status');
 let data={};try{data=JSON.parse(document.getElementById('av-guide-data')?.textContent||'{}')||{};}catch{}
 const base='/avesta-integrated-stage7b/';
 let turns=0;
 const el=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=text;return n;};
 function count(){$('#ag-count').textContent=`${query.value.length} / ${LIMIT}`;}
 function hello(){const n=el('article','ag-answer');n.append(el('span','ag-badge','LOCAL GUIDE'),el('h3','', 'Where would you like to start?'),el('p','','Explore Lending, software and portal demonstrations, or read a saved IT-help example. This is not a live conversation with AI or a technician.'));log.append(n);}
 function clear(){query.value='';turns=0;log.replaceChildren();hello();count();status.textContent='';scroll.scrollTop=0;}
 function hide(erase=false,focus=true){panel.hidden=true;launch.setAttribute('aria-expanded','false');if(erase)clear();if(focus)launch.focus({preventScroll:true});}
 function open(){
  if(document.querySelector('dialog[open]'))return;
  document.querySelectorAll('[data-appearance-control][open]').forEach(c=>c.open=false);
  const menu=document.getElementById('mobile-nav'),toggle=document.querySelector('.menu-toggle');
  if(menu&&toggle){menu.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Open navigation');}
  panel.hidden=false;launch.setAttribute('aria-expanded','true');$('#ag-title').focus({preventScroll:true});
 }
 function showReply(value){
  const text=typeof value==='string'?value.trim():'';
  if(!text){status.textContent='Choose a sample topic or enter a fictional example.';query.focus();return;}
  if(text.length>LIMIT){status.textContent='Keep the fictional example within 300 characters.';query.focus();return;}
  status.textContent='';
  const answer=guideReply(text,data);
  if(turns>=6){clear();status.textContent='Earlier examples were cleared to keep this conversation short.';}
  const user=el('article','ag-user');user.append(el('span','ag-badge','YOUR EXAMPLE'),el('p','',text));
  const msg=el('article','ag-answer');msg.append(el('span','ag-badge',answer.kind==='saved-help'?'SAVED AVESTA GUIDANCE':'LOCAL GUIDE'),el('h3','',answer.title),el('p','',answer.text));
  if(answer.note)msg.append(el('p','ag-source',answer.note));
  if(answer.steps?.length){const list=el('ol');for(const step of answer.steps)list.append(el('li','',step));msg.append(list);}
  if(answer.references?.length)msg.append(el('p','ag-source','Catalogue reference labels: '+answer.references.join(' · ')+'. No external page was opened.'));
  const actionbar=el('div','ag-actions');
  for(const a of answer.actions||[]){if(!Object.hasOwn(ACTIONS,a.id))continue;const link=el('a','ag-link',a.label);link.href=base+ACTIONS[a.id];actionbar.append(link);}
  if(answer.topicId&&document.getElementById('it3-enquiry-form')){
   const b=el('button','ag-link','Use this topic in a sample request');b.type='button';
   b.addEventListener('click',()=>{const id=answer.topicId;hide(true,false);window.dispatchEvent(new CustomEvent('avesta:guide-topic',{detail:{id}}));});actionbar.prepend(b);
  }
  if(actionbar.children.length){msg.append(actionbar,el('p','ag-source','Opening another page clears current form/demo changes. Only this topic is handed over on the IT page, never your typed question.'));}
  log.append(user,msg);turns++;query.value='';count();
  // Start of the new answer stays readable; never fake a typing delay or progress bar.
  scroll.scrollTop=Math.max(0,msg.offsetTop-scroll.offsetTop-12);
 }
 launch.hidden=false;clear();
 launch.addEventListener('click',()=>panel.hidden?open():hide(false));
 $('#ag-minimize').addEventListener('click',()=>hide(false));
 $('#ag-close').addEventListener('click',()=>hide(true));
 $('#ag-clear').addEventListener('click',()=>{clear();status.textContent='Conversation cleared. Nothing was sent.';query.focus();});
 query.addEventListener('input',()=>{count();status.textContent='';});
 $('#ag-form').addEventListener('submit',event=>{event.preventDefault();showReply(query.value);});
 root.querySelectorAll('[data-ag-query]').forEach(b=>b.addEventListener('click',()=>showReply(b.dataset.agQuery)));
 log.addEventListener('click',ev=>{if(ev.target.closest('a'))hide(true,false);});
 document.addEventListener('keydown',ev=>{if(ev.key==='Escape'&&!panel.hidden&&!document.querySelector('dialog[open]')){ev.preventDefault();hide(false);}});
 // Non-modal disclosure: permit keyboard access to the page; collapse before obscuring its focus.
 document.addEventListener('focusin',ev=>{if(!panel.hidden&&!root.contains(ev.target))hide(false,false);});
 document.addEventListener('pointerdown',ev=>{if(!panel.hidden&&!root.contains(ev.target))hide(false,false);});
 document.addEventListener('avesta:route',()=>hide(true,false));
 window.addEventListener('pagehide',()=>hide(true,false));
 window.addEventListener('pageshow',ev=>{if(ev.persisted)hide(true,false);});
}
