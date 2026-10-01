const escapeChat=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function chatURL(value){try{const u=new URL(value,location.origin);return (u.origin===location.origin&&value.startsWith('/')&&!value.startsWith('//'))||u.protocol==='https:'?u.href:null;}catch{return null;}}
function answerHTML(data){
 const text=String(data.answer||'');let out='',at=0;
 for(const c of [...(data.citations||[])].sort((a,b)=>a.start-b.start)){
  const url=chatURL(c.url);if(!url||!Number.isInteger(c.start)||!Number.isInteger(c.end)||c.start<at||c.end<c.start||c.end>text.length)continue;
  out+=escapeChat(text.slice(at,c.start))+escapeChat(text.slice(c.start,c.end))+' <a target="_blank" rel="noopener noreferrer" href="'+escapeChat(url)+'">['+escapeChat(c.title)+']</a>';at=c.end;
 }
 out+=escapeChat(text.slice(at));
 const sources=(data.sources||[]).filter(s=>chatURL(s.url));
 return '<p class="chat-answer">'+out+'</p>'+(sources.length?'<ul class="chat-sources">'+sources.map(s=>'<li><a href="'+escapeChat(chatURL(s.url))+'" target="_blank" rel="noopener noreferrer">'+escapeChat(s.title)+'</a></li>').join('')+'</ul>':'');
}
const launcher=document.createElement('button');launcher.id='chat-launch';launcher.className='button secondary chat-launch';launcher.type='button';launcher.textContent='Ask Avesta';launcher.setAttribute('aria-expanded','false');launcher.setAttribute('aria-controls','avesta-chat');document.body.append(launcher);
const panel=document.createElement('section');panel.id='avesta-chat';panel.className='chat-panel';panel.hidden=true;panel.setAttribute('aria-label','Avesta assistant');
panel.innerHTML=`<div class="chat-header"><div><strong>Avesta assistant</strong><p id="chat-mode">Saved help guide</p></div><button type="button" id="chat-close" aria-label="Close assistant">×</button></div><div id="chat-log" class="chat-log" role="log" aria-live="polite" aria-relevant="additions"><div class="chat-message"><p>How can I help? Ask about Avesta services, loans or an IT problem.</p></div></div><div class="chat-chips"><button type="button" data-chat="How do I apply for a loan?">Loan questions</button><button type="button" data-chat="My printer is offline">Printer help</button><button type="button" data-chat="How do I contact Avesta?">Contact us</button></div><form id="chat-form"><label for="chat-question">Your question</label><textarea id="chat-question" maxlength="1000" rows="2" required placeholder="Describe the problem and device model"></textarea><label class="chat-web" for="chat-web"><input id="chat-web" type="checkbox" disabled> Use AI and online sources</label><p class="chat-note" id="chat-availability">Live AI web search is not connected. Saved guidance is available.</p><p class="chat-note">Do not include passwords, NRCs or account details. When enabled, AI sends your chat to OpenAI and may search the web.</p><div class="chat-actions"><button type="submit" class="button secondary small" id="chat-send">Send</button><button type="button" class="record-button" id="chat-clear">Clear chat</button><a href="/contact" class="link">Talk to the team</a></div><p id="chat-error" class="error" role="alert"></p></form>`;
document.body.append(panel);
const el=id=>document.getElementById(id);let history=[],busy=false,controller=null;
function showChat(open){panel.hidden=!open;launcher.setAttribute('aria-expanded',String(open));if(open)el('chat-question').focus();else launcher.focus();}
launcher.onclick=()=>showChat(panel.hidden);el('chat-close').onclick=()=>showChat(false);panel.addEventListener('keydown',event=>{if(event.key==='Escape'){showChat(false);event.stopPropagation();}});
function addMessage(role,content){const div=document.createElement('div');div.className='chat-message '+(role==='user'?'chat-user':'');if(role==='user')div.textContent=content;else div.innerHTML=answerHTML(content)+'<p class="chat-note">'+escapeChat(content.notice)+'</p>';el('chat-log').append(div);while(el('chat-log').children.length>20)el('chat-log').firstChild.remove();el('chat-log').scrollTop=el('chat-log').scrollHeight;}
function setBusy(value){busy=value;el('chat-send').disabled=value;el('chat-send').textContent=value?'Finding an answer…':'Send';el('chat-question').disabled=value;panel.querySelectorAll('[data-chat]').forEach(b=>b.disabled=value);}
el('chat-clear').onclick=()=>{controller?.abort();controller=null;history=[];el('chat-log').replaceChildren();el('chat-question').value='';el('chat-error').textContent='';setBusy(false);};
panel.querySelectorAll('[data-chat]').forEach(b=>b.onclick=()=>{el('chat-question').value=b.dataset.chat;el('chat-form').requestSubmit();});
el('chat-form').onsubmit=async event=>{
 event.preventDefault();if(busy)return;const message=el('chat-question').value.trim();if(!message)return;
 el('chat-error').textContent='';addMessage('user',message);setBusy(true);const current=new AbortController();controller=current;const timer=setTimeout(()=>current.abort(),35000);
 try{const res=await fetch('/api/assistant',{method:'POST',headers:{'Content-Type':'application/json'},signal:current.signal,body:JSON.stringify({message,history,web:el('chat-web').checked})});const data=await res.json();if(!res.ok)throw new Error(data.error||'The assistant could not respond.');if(controller!==current)return;
 addMessage('assistant',data);history=[...history,{role:'user',content:message},{role:'assistant',content:data.answer}].slice(-6);el('chat-question').value='';
 }catch(err){if(controller===current)el('chat-error').textContent=current.signal.aborted?'The request timed out. Please retry or contact the team.':err.message;}
 finally{clearTimeout(timer);if(controller===current){setBusy(false);controller=null;}}
};
fetch('/api/assistant/status').then(r=>r.ok?r.json():null).then(data=>{if(data?.webAvailable){el('chat-web').disabled=false;el('chat-availability').textContent='Choose AI and online sources for a web-informed answer.';el('chat-mode').textContent='Help guide · AI available';}}).catch(()=>{});
document.addEventListener('click',event=>{if(event.target.closest('[data-open-assistant]'))showChat(true);});
