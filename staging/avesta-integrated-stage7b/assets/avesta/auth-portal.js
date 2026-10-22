/* Presentation only. No account or record data requests, storage or identity selection. */
(() => {
 'use strict';
 const toggle=document.getElementById('ab-menu-toggle'), menu=document.getElementById('ab-menu');
 function closeMenu(focus=false){if(!menu||!toggle)return;menu.hidden=true;toggle.setAttribute('aria-expanded','false');if(focus)toggle.focus({preventScroll:true});}
 toggle?.addEventListener('click',()=>{const opening=toggle.getAttribute('aria-expanded')!=='true';menu.hidden=!opening;toggle.setAttribute('aria-expanded',String(opening));});
 menu?.addEventListener('click',event=>{if(event.target.closest('a'))closeMenu();});
 document.addEventListener('click',event=>{if(menu&&!menu.hidden&&!menu.contains(event.target)&&!toggle.contains(event.target))closeMenu();});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu&&!menu.hidden)closeMenu(true);});
 const search=document.getElementById('ab-search'),status=document.getElementById('ab-filter-status'),clear=document.getElementById('ab-clear-filters');
 const cards=[...document.querySelectorAll('[data-record-id]')],count=document.getElementById('ab-result-count'),empty=document.getElementById('ab-empty');
 function filter(){const q=search.value.trim().toLocaleLowerCase().slice(0,100),s=status.value;let visible=0;cards.forEach(card=>{card.hidden=!(card.textContent.toLocaleLowerCase().includes(q)&&(s==='all'||card.dataset.recordStatus===s));if(!card.hidden)visible++;});count.textContent=`${visible} of ${cards.length} permitted sample records shown`;empty.hidden=visible>0;}
 if(search&&status&&clear){[search,status,clear].forEach(n=>n.disabled=false);search.addEventListener('input',filter);status.addEventListener('change',filter);clear.addEventListener('click',()=>{search.value='';status.value='all';filter();search.focus();});filter();}
 // Clear already-rendered protected data before a page is put into history. This is
 // a privacy aid, not authentication. Each fresh request still checks the PHP session.
 const protectedView=document.querySelector('[data-protected-view]');
 function conceal(){if(!protectedView)return;protectedView.replaceChildren();const note=document.createElement('p');note.textContent='Reloading the protected workspace requires the server to recheck access.';protectedView.append(note);}
 window.addEventListener('pagehide',conceal);
 window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});
 document.querySelector('[data-signout]')?.addEventListener('submit',conceal);
})();
