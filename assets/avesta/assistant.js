import {createGuidedHelp} from './guided.js';
import {guideAnswer} from './guide.js?v=20261003-site-scope';
let guidePromise;
const chatScope=()=>location.pathname==='/it.php'||location.pathname==='/it'||new URLSearchParams(location.search).get('page')==='it'?'it':'site';
let scope=chatScope();
const escapeChat=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function chatURL(value){try{const u=new URL(value,location.origin);return (u.origin===location.origin&&value.startsWith('/')&&!value.startsWith('//'))||u.protocol==='https:'?u.href:null;}catch{return null;}}
function chatLink(url,label){
 const safe=chatURL(url);if(!safe)return escapeChat(label);
 const internal=new URL(safe).origin===location.origin;
 return '<a href="'+escapeChat(safe)+'"'+(internal?'':' target="_blank" rel="noopener noreferrer"')+'>'+escapeChat(label)+'</a>';
}
function linkedChatText(text){
 // Escape all text; only validated HTTP(S)/site URLs become links.
 const pattern=/\[([^\]\n]+)\]\((https:\/\/[^\s)]+|\/(?!\/)[^\s)]+)\)|https:\/\/[^\s<>"']+|\/(?:index|apply|login|loans|it|downloads|recovery|portal|legal)\.php(?:\?[^\s<>"')\]]+)?/g;
 let out='',at=0;
 for(const match of text.matchAll(pattern)){
  out+=escapeChat(text.slice(at,match.index));
  const raw=match[2]||match[0],url=raw.replace(/[.,;!]+$/,'');
  out+=chatLink(url,match[1]||url)+(match[2]?'':escapeChat(raw.slice(url.length)));
  at=match.index+match[0].length;
 }
 return out+escapeChat(text.slice(at));
}
function answerHTML(data){
 const text=String(data.answer||'');let out='',at=0;
 for(const c of [...(data.citations||[])].sort((a,b)=>a.start-b.start)){
  const url=chatURL(c.url);if(!url||!Number.isInteger(c.start)||!Number.isInteger(c.end)||c.start<at||c.end<c.start||c.end>text.length)continue;
  out+=linkedChatText(text.slice(at,c.start))+escapeChat(text.slice(c.start,c.end))+' '+chatLink(url,'['+String(c.title)+']');at=c.end;
 }
 out+=linkedChatText(text.slice(at));
 const sources=(data.sources||[]).filter(s=>chatURL(s.url));
 return '<p class="chat-answer">'+out+'</p>'+(sources.length?'<ul class="chat-sources">'+sources.map(s=>'<li>'+chatLink(s.url,s.title)+'</li>').join('')+'</ul>':'');
}
const launcher=document.createElement('button');launcher.id='chat-launch';launcher.className='button secondary chat-launch';launcher.type='button';launcher.innerHTML='<img class="git-assistant-logo" src="/icons/git-assistant.svg" width="32" height="32" alt=""><span>Ask G.I.T</span>';launcher.setAttribute('aria-expanded','false');launcher.setAttribute('aria-controls','avesta-chat');document.body.append(launcher);
const panel=document.createElement('section');panel.id='avesta-chat';panel.className='chat-panel';panel.hidden=true;panel.setAttribute('aria-label','Avesta assistant');
panel.innerHTML=`<div class="chat-header"><div class="chat-brand"><img class="git-assistant-logo" src="/icons/git-assistant.svg" width="40" height="40" alt=""><div><strong>G.I.T · Avesta assistant</strong><p id="chat-mode">Saved help guide</p></div></div><div class="chat-window-controls"><button type="button" id="chat-minimize" aria-label="Minimize assistant and keep conversation" title="Minimize — keep conversation">−</button><button type="button" id="chat-close" aria-label="Close assistant">×</button></div></div><div id="chat-log" class="chat-log" role="log" aria-live="polite" aria-relevant="additions"><div class="chat-message"><p>How can I help? Ask about Avesta services, loans or an IT problem.</p></div></div><div class="chat-chips"><button type="button" data-chat="How do I apply for a loan?">Loan questions</button><button type="button" data-chat="My printer is offline">Printer help</button><button type="button" data-chat="How do I contact Avesta?">Contact us</button></div><form id="chat-form"><label for="chat-question">Your question</label><textarea id="chat-question" maxlength="1000" rows="2" required placeholder="Describe the problem and device model"></textarea><label class="chat-web" for="chat-web"><input id="chat-web" type="checkbox" disabled> Use AI and online sources</label><p class="chat-note" id="chat-availability">Live AI web search is not connected. Saved guidance is available.</p><p class="chat-note">Do not include passwords, NRCs or account details. Saved guidance stays on your device. Choosing AI sends your question to OpenAI for an answer and web search.</p><div class="chat-actions"><button type="submit" class="button secondary small" id="chat-send">Send</button><button type="button" class="record-button" id="chat-clear">Clear chat</button><a href="/index.php?page=contact" class="link">Talk to the team</a></div><p id="chat-error" class="error" role="alert"></p></form>`;
document.body.append(panel);
const el=id=>document.getElementById(id);let history=[],busy=false,controller=null;
function showChat(open){panel.hidden=!open;launcher.setAttribute('aria-expanded',String(open));launcher.setAttribute('aria-label',open?'Minimize G.I.T assistant':'Open G.I.T assistant and continue conversation');if(open)el('chat-question').focus();else launcher.focus();}
launcher.onclick=()=>showChat(panel.hidden);el('chat-close').onclick=()=>showChat(false);el('chat-minimize').onclick=()=>showChat(false);
panel.addEventListener('click',event=>{const a=event.target.closest('a');if(!a||event.defaultPrevented||event.button>0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||a.target==='_blank'||a.hasAttribute('download'))return;try{if(new URL(a.href).origin===location.origin)showChat(false);}catch{}});
panel.addEventListener('keydown',event=>{if(event.key==='Escape'){showChat(false);event.stopPropagation();}});
function addMessage(role,content){const div=document.createElement('div');div.className='chat-message '+(role==='user'?'chat-user':'');if(role==='user')div.textContent=content;else div.innerHTML=answerHTML(content)+'<p class="chat-note">'+escapeChat(content.notice)+'</p>';el('chat-log').append(div);while(el('chat-log').children.length>20)el('chat-log').firstChild.remove();el('chat-log').scrollTop=el('chat-log').scrollHeight;}
function setBusy(value){busy=value;el('chat-send').disabled=value;el('chat-send').textContent=value?'Finding an answer…':'Send';el('chat-question').disabled=value;panel.querySelectorAll('[data-chat]').forEach(b=>b.disabled=value);}
const guided=createGuidedHelp(panel,addMessage);
el('chat-clear').onclick=()=>{guided.clear();controller?.abort();controller=null;history=[];el('chat-log').replaceChildren();el('chat-question').value='';el('chat-error').textContent='';setBusy(false);};
panel.querySelectorAll('[data-chat]').forEach(b=>b.onclick=()=>{el('chat-question').value=b.dataset.chat;el('chat-form').requestSubmit();});
el('chat-form').onsubmit=async event=>{
 event.preventDefault();if(busy)return;const message=el('chat-question').value.trim();if(!message)return;
 el('chat-error').textContent='';addMessage('user',message);setBusy(true);const current=new AbortController();controller=current;const timer=setTimeout(()=>current.abort(),35000);
 try{if(el('chat-web').checked){guided.clear();const res=await fetch('/assistant-api.php',{method:'POST',headers:{'Content-Type':'application/json','X-Avesta-Chat':'1'},signal:current.signal,body:JSON.stringify({message,web:true,scope})});const data=await res.json();if(!res.ok)throw new Error(data.error||'AI could not respond.');if(controller!==current)return;addMessage('assistant',data);el('chat-question').value='';return;}if(/\b\d{6}\/\d{2}\/\d\b/.test(message))throw new Error('Please remove your NRC number. Use the secure application form for personal documents.');guidePromise ||= fetch('/assets/avesta/help.json').then(r=>{if(!r.ok)throw new Error('Help guide is unavailable.');return r.json();}).catch(err=>{guidePromise=null;throw err;});const guides=await guidePromise;if(controller!==current)return;const business=/\b(loan|borrow|interest|repay|lending|guarantor|collateral|salary check|finance|contact|hours|address|services|consulting|consultation|offer|software|download|asset tracker|device health)\b/i.test(message);if(business)guided.clear();if(!business&&guided.start(message,guides)){el('chat-question').value='';return;}const data={...guideAnswer(message,guides,scope),notice:'Saved help guide · not a live web search'};
 addMessage('assistant',data);history=[...history,{role:'user',content:message},{role:'assistant',content:data.answer}].slice(-6);el('chat-question').value='';
 }catch(err){if(controller===current)el('chat-error').textContent=current.signal.aborted?'The request timed out. Please retry or contact the team.':err.message;}
 finally{clearTimeout(timer);if(controller===current){setBusy(false);controller=null;}}
};
document.addEventListener('click',event=>{if(event.target.closest('[data-open-assistant]'))showChat(true);});

function updateScope(){
 const next=chatScope();
 if(next!==scope){guided.clear();controller?.abort();controller=null;history=[];el('chat-log').replaceChildren();el('chat-error').textContent='';el('chat-question').value='';setBusy(false);scope=next;}
 const it=scope==='it';
 el('chat-mode').textContent=it?'IT consultation only':'Avesta loans & IT services';
 el('chat-question').placeholder=it?'Describe your IT problem and device model':'Ask about loans, IT services or using this website';
 panel.querySelector('.chat-chips').innerHTML=it?'<button type="button" data-chat="My printer is offline">Printer help</button><button type="button" data-chat="What IT services do you offer?">IT services</button><button type="button" data-chat="How do I contact IT support?">IT support</button>':'<button type="button" data-chat="How do I apply for a loan?">Loan questions</button><button type="button" data-chat="My printer is offline">Printer help</button><button type="button" data-chat="How do I contact Avesta?">Contact us</button>';
 panel.querySelectorAll('[data-chat]').forEach(b=>b.onclick=()=>{el('chat-question').value=b.dataset.chat;el('chat-form').requestSubmit();});
 if(el('chat-log').children.length<=1){el('chat-log').replaceChildren();addMessage('assistant',{answer:it?'Ask about IT services, software or a technology problem. For loans, use the homepage assistant or Lending page.':'Ask about Avesta loans, applications, IT services, software or using this website.',notice:it?'IT consultation only':'Avesta loans & IT services'});}
}
updateScope();
document.addEventListener('avesta:route',updateScope);
window.addEventListener('popstate',updateScope);

fetch('/assistant-api.php?action=status',{cache:'no-store'}).then(r=>r.ok?r.json():null).then(data=>{if(data?.configured){el('chat-web').disabled=false;el('chat-availability').textContent='AI setup ready. Choose AI and online sources to send this question to OpenAI.';}else if(data?.reason==='missing_curl'){el('chat-availability').textContent='AI needs the PHP cURL extension enabled. Saved guidance is available.';}}).catch(()=>{});
