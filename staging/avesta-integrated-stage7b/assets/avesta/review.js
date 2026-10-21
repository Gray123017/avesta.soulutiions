/* A manual, in-memory checklist; not an automated test runner or access-control report. */
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
function update(){const values=$$('[data-review-state]').map(s=>s.value);$('#rv-summary').textContent=`${values.filter(v=>v==='pending').length} not checked · ${values.filter(v=>v==='checked').length} checked by you · ${values.filter(v=>v==='issue').length} need attention`;}
function reset(){ $$('[data-review-state]').forEach(s=>s.value='pending');update(); }
$$('[data-review-state]').forEach(s=>s.addEventListener('change',update));
$('#rv-reset').addEventListener('click',reset);
window.addEventListener('pagehide',reset);window.addEventListener('pageshow',e=>{if(e.persisted)reset();});
// Shared header/footer interaction; portal does not load the public-page router.
const toggle=$('.menu-toggle'),menu=$('#mobile-nav');
function closeMenu(focus=false){menu.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Open navigation');if(focus)toggle.focus({preventScroll:true});}
toggle.addEventListener('click',()=>{const open=toggle.getAttribute('aria-expanded')!=='true';menu.hidden=!open;toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Close navigation':'Open navigation');});
menu.addEventListener('click',ev=>{if(ev.target.closest('a'))closeMenu();});
document.addEventListener('click',ev=>{if(!menu.hidden&&!menu.contains(ev.target)&&!toggle.contains(ev.target))closeMenu();});
document.addEventListener('keydown',ev=>{if(ev.key==='Escape'&&!menu.hidden)closeMenu(true);});
const compact=matchMedia('(max-width:680px)');
function foldFooter(){ $$('[data-compact-toggle]').forEach(b=>{const p=document.getElementById(b.getAttribute('aria-controls'));if(!p)return;p.hidden=compact.matches;b.setAttribute('aria-expanded',String(!compact.matches));b.querySelector('.compact-sign').textContent=compact.matches?'+':'−';});}
$$('[data-compact-toggle]').forEach(b=>b.addEventListener('click',()=>{const p=document.getElementById(b.getAttribute('aria-controls'));p.hidden=!p.hidden;b.setAttribute('aria-expanded',String(!p.hidden));b.querySelector('.compact-sign').textContent=p.hidden?'+':'−';}));
compact.addEventListener('change',foldFooter);foldFooter();$('#year').textContent=new Date().getFullYear();

reset();
