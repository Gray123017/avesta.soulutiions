/* Stage 2B: local-only demo. No fetch, submit API, browser storage, real signature or user account. */
import {money,escapeHTML as e} from './domain.js?v=20261003-steady-navigation';
import {steps,methods,securityOptions,initialState,fictionalExample,estimate,validateStep,firstInvalidStep,requiredDocs,visibleDocs,allDocuments,classifyFile} from './application-model.js?v=20261018-stage7b-r1';
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
const initialSearch=location.search;
let state=initialState(initialSearch), current=0, furthest=0, generation=0;
let documentTokens=new Map(), pendingFiles=new Set(), generatingSamples=false;
let strokes=[],redoStrokes=[],activeStroke=null;
const form=$('#demo-form');
const canvas=$('#signature-canvas'),ctx=canvas.getContext('2d');
function updateState(){
 $$('[data-field]').forEach(el=>{state[el.dataset.field]=el.type==='checkbox'?el.checked:el.value;});
 state.collateral=$$('[data-collateral-row]').map(row=>({description:row.querySelector('[data-description]').value,value:row.querySelector('[data-value]').value}));
}
function writeState(){
 $$('[data-field]').forEach(el=>{if(el.type==='checkbox')el.checked=Boolean(state[el.dataset.field]);else el.value=state[el.dataset.field]??'';});
 renderCollateral();toggleSecurity(false);renderDocuments();updateEstimate();
}
function invalidateSignoff(){state.reviewed=false;$('#reviewed').checked=false;}
function clearErrors(){
 $('#error-summary').hidden=true;$('#error-summary').replaceChildren();
 $$('.aj-field-error').forEach(el=>el.textContent='');$$('[aria-invalid="true"]').forEach(el=>el.removeAttribute('aria-invalid'));
}
function showErrors(errors){
 clearErrors();
 const summary=$('#error-summary');
 summary.innerHTML='<strong>Please check these demo details:</strong><ul>'+errors.map(x=>`<li><button type="button" data-error-target="${e(x.field)}">${e(x.message)}</button></li>`).join('')+'</ul>';
 for(const error of errors){const field=document.getElementById(error.field), msg=document.getElementById(error.field+'-error');if(field)field.setAttribute('aria-invalid','true');if(msg)msg.textContent=error.message;}
 summary.hidden=false;summary.focus();
}
$('#error-summary').addEventListener('click',event=>{const button=event.target.closest('[data-error-target]');if(button)document.getElementById(button.dataset.errorTarget)?.focus();});
function showStep(step,focus=true){
 current=step;furthest=Math.max(furthest,current);clearErrors();
 $$('[data-panel]').forEach(panel=>panel.hidden=Number(panel.dataset.panel)!==current);
 $$('[data-step]').forEach(button=>{const number=Number(button.dataset.step);button.disabled=number>furthest;if(number===current)button.setAttribute('aria-current','step');else button.removeAttribute('aria-current');});
 $('#progress-note').textContent=`Step ${current+1} of 5 · ${steps[current]}`;
 $('#previous-step').hidden=current===0;
 $('#next-step').textContent=current===4?'Complete demonstration →':`Continue to ${steps[current+1]} →`;
 if(current===3)renderDocuments();if(current===4)renderReview();updateEstimate();updateBusy();
 if(focus)document.getElementById('step-title-'+current).focus();
}
function moveForward(target=current+1){
 updateState();
 if(pendingFiles.size||generatingSamples){$('#document-status').textContent='Please wait for the dummy file checks to finish.';return;}
 if(current===4&&target===5){const invalid=firstInvalidStep(state);if(invalid){showStep(invalid.step);showErrors(invalid.errors);return;}completeDemo();return;}
 for(let i=0;i<target;i++){const errors=validateStep(state,i);if(errors.length){showStep(i,false);showErrors(errors);return;}}
 showStep(target);
}
$('#next-step').addEventListener('click',()=>moveForward());
$('#previous-step').addEventListener('click',()=>{updateState();showStep(Math.max(0,current-1));});
$$('[data-step]').forEach(button=>button.addEventListener('click',()=>{const step=Number(button.dataset.step);updateState();if(step>current)moveForward(step);else showStep(step);}));
form.addEventListener('submit',event=>event.preventDefault());
form.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.isComposing&&event.target instanceof HTMLInputElement&&event.target.type!=='file'&&event.target.type!=='checkbox'){event.preventDefault();moveForward();}});
form.addEventListener('input',event=>{
 if(event.target.type==='file')return;
 updateState();
 if(!['reviewed','signatureName'].includes(event.target.id))invalidateSignoff();
 const msg=document.getElementById(event.target.id+'-error');if(msg)msg.textContent='';event.target.removeAttribute('aria-invalid');
 updateEstimate();
});
form.addEventListener('change',event=>{
 if(event.target.type==='file')return;
 updateState();
 if(event.target.id==='security'){toggleSecurity(true);renderDocuments();}
 if(!['reviewed','signatureName'].includes(event.target.id))invalidateSignoff();
 updateEstimate();
});
function renderCollateral(){
 $('#collateral-items').innerHTML=state.collateral.map((item,i)=>`<div class="aj-collateral-row" data-collateral-row="${i}"><div class="aj-field"><label for="collateral-description-${i}">Item ${i+1} · description *</label><input id="collateral-description-${i}" data-description maxlength="160" autocomplete="off" value="${e(item.description)}" aria-describedby="collateral-description-${i}-error"><p id="collateral-description-${i}-error" class="aj-field-error"></p></div><div class="aj-field"><label for="collateral-value-${i}">Value (ZMW) *</label><input id="collateral-value-${i}" data-value type="number" min="0.01" max="1000000" step="0.01" inputmode="decimal" autocomplete="off" value="${e(item.value)}" aria-describedby="collateral-value-${i}-error"><p id="collateral-value-${i}-error" class="aj-field-error"></p></div><button type="button" class="aj-remove" data-remove-collateral="${i}" ${state.collateral.length===1?'disabled':''}>Remove item ${i+1}</button></div>`).join('');
 $('#add-collateral').disabled=state.collateral.length>=5;
}
$('#add-collateral').addEventListener('click',()=>{updateState();if(state.collateral.length<5){state.collateral.push({description:'',value:''});renderCollateral();invalidateSignoff();$(`#collateral-description-${state.collateral.length-1}`).focus();}});
$('#collateral-items').addEventListener('click',event=>{const button=event.target.closest('[data-remove-collateral]');if(!button||state.collateral.length<=1)return;updateState();state.collateral.splice(Number(button.dataset.removeCollateral),1);renderCollateral();invalidateSignoff();$('#add-collateral').focus();});
function toggleSecurity(removeIrrelevant){
 $('#collateral-section').hidden=state.security!=='collateral';
 $('#employer-section').hidden=!['checkoff','employer_letter'].includes(state.security);
 $('#payroll-section').hidden=state.security!=='checkoff';$('#letter-hint').hidden=state.security!=='employer_letter';
 $('#guarantor-section').hidden=state.security!=='guarantor';
 if(removeIrrelevant){const keep=visibleDocs(state.security);Object.keys(state.documents).forEach(id=>{if(!keep.includes(id))removeDocument(id);});}
}
const dateLabel=value=>new Intl.DateTimeFormat('en-GB',{day:'numeric',month:'short',year:'numeric',timeZone:'Africa/Lusaka'}).format(new Date(value+'T12:00:00Z'));
function updateEstimate(){
 try{
  const {quote:q,schedule:rows}=estimate(state);
  $('#sidebar-estimate').innerHTML=`<dl><div><dt>Principal</dt><dd>${e(money(q.principal/100))}</dd></div><div><dt>Term</dt><dd>${Number(state.weeks)} ${Number(state.weeks)===1?'week':'weeks'}</dd></div><div><dt>Flat interest</dt><dd>${q.rate}% · ${e(money(q.interest/100))}</dd></div><div class="aj-total"><dt>Total to repay</dt><dd data-demo-total>${e(money(q.total/100))}</dd></div></dl>`;
  $('#repayment-estimate').innerHTML=`<dl class="aj-inline-estimate"><div><dt>Principal</dt><dd>${e(money(q.principal/100))}</dd></div><div><dt>Interest (${q.rate}%)</dt><dd>${e(money(q.interest/100))}</dd></div><div><dt>Total to repay</dt><dd>${e(money(q.total/100))}</dd></div></dl>`;
  $('#repayment-schedule').innerHTML=`<div class="aj-schedule-scroll" tabindex="0" role="region" aria-label="Illustrative repayment schedule"><table><caption>${q.count} illustrative payment${q.count===1?'':'s'} · ${e(state.frequency)}</caption><thead><tr><th scope="col">No.</th><th scope="col">Estimated date</th><th scope="col">Amount</th></tr></thead><tbody>${rows.map((row,i)=>`<tr><th scope="row">${i+1}</th><td>${e(dateLabel(row.date))}</td><td data-cents="${row.amount}">${e(money(row.amount/100))}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="2" scope="row">Total</th><td>${e(money(q.total/100))}</td></tr></tfoot></table></div>`;
 }catch(error){$('#sidebar-estimate').innerHTML=`<p class="aj-estimate-error">${e(error.message)}</p>`;$('#repayment-estimate').innerHTML=`<p class="aj-info">${e(error.message)}</p>`;$('#repayment-schedule').replaceChildren();}
}
function removeDocument(id){
 documentTokens.set(id,(documentTokens.get(id)||0)+1);pendingFiles.delete(id);
 const doc=state.documents[id];if(doc?.url)URL.revokeObjectURL(doc.url);delete state.documents[id];invalidateSignoff();
}
function renderDocuments(){
 const required=requiredDocs(state.security);
 $('#document-list').innerHTML=visibleDocs(state.security).map(id=>{const doc=state.documents[id];return `<article class="aj-doc"><label for="doc-${id}">${e(allDocuments[id])}</label><span class="aj-doc-status">${required.includes(id)?'Needed for this demo':'Optional'}</span><input type="file" id="doc-${id}" data-doc="${id}" aria-required="${required.includes(id)}" accept=".png,.jpg,.jpeg,.pdf" aria-describedby="doc-${id}-error"><div class="aj-doc-preview">${doc?(doc.url?`<img src="${e(doc.url)}" alt="Local dummy preview: ${e(allDocuments[id])}">`:'<div class="aj-doc-pdf">PDF selected · content not rendered</div>')+`<p class="aj-doc-filename">${e(doc.name)} · ${(doc.size/1024).toFixed(1)} KB</p><button type="button" class="aj-remove" data-remove-doc="${id}">Remove dummy file</button>`:'<p class="aj-doc-filename">No dummy file selected.</p>'}</div><p id="doc-${id}-error" class="aj-field-error"></p></article>`;}).join('');
 const ready=required.filter(id=>state.documents[id]?.ready).length;
 $('#document-count').textContent=`${ready} of ${required.length} required demo files ready · ${Object.keys(state.documents).length} selected in this tab`;
}
function updateBusy(){
 $('#sample-docs').disabled=pendingFiles.size>0||generatingSamples;
 $('#next-step').disabled=pendingFiles.size>0||generatingSamples;
 if(current===3&&pendingFiles.size)$('#document-status').textContent='Checking dummy file type and preview…';
}
async function acceptFile(id,file){
 const pageGeneration=generation;removeDocument(id);const token=documentTokens.get(id);pendingFiles.add(id);updateBusy();
 let url=null;
 try{
  // Validate only for demo preview; this is not an upload-security or malware scanner.
  const kind=classifyFile(file.name,new Uint8Array(await file.slice(0,12).arrayBuffer()),file.size,file.type);
  if(kind!=='application/pdf'){
   const bitmap=await createImageBitmap(file);
   const good=bitmap.width>0&&bitmap.height>0&&bitmap.width*bitmap.height<=16000000;bitmap.close();
   if(!good)throw new Error('For the preview, choose a dummy image no larger than 16 megapixels.');
   url=URL.createObjectURL(file);
  }
  if(pageGeneration!==generation||documentTokens.get(id)!==token||!visibleDocs(state.security).includes(id)){if(url)URL.revokeObjectURL(url);return;}
  state.documents[id]={name:file.name,size:file.size,type:kind,url,ready:true};renderDocuments();$('#document-status').textContent='Dummy file ready for local review. Nothing uploaded.';
 }catch(error){
  if(url)URL.revokeObjectURL(url);
  if(pageGeneration!==generation||documentTokens.get(id)!==token)return;
  renderDocuments();const msg=document.getElementById(`doc-${id}-error`);if(msg)msg.textContent=error.message||'The dummy image could not be decoded.';document.getElementById(`doc-${id}`)?.setAttribute('aria-invalid','true');$('#document-status').textContent='Dummy file rejected. No file has been retained for this slot.';
 }finally{if(pageGeneration===generation&&documentTokens.get(id)===token)pendingFiles.delete(id);updateBusy();}
}
$('#document-list').addEventListener('change',event=>{const input=event.target.closest('[data-doc]');if(input&&input.files[0])void acceptFile(input.dataset.doc,input.files[0]);});
$('#document-list').addEventListener('click',event=>{const button=event.target.closest('[data-remove-doc]');if(button){const id=button.dataset.removeDoc;removeDocument(id);renderDocuments();$('#document-status').textContent='Dummy file reference removed.';document.getElementById('doc-'+id)?.focus();updateBusy();}});
async function createSample(id){
 const c=document.createElement('canvas');c.width=760;c.height=420;const cx=c.getContext('2d');
 cx.fillStyle='#faf7f1';cx.fillRect(0,0,c.width,c.height);cx.fillStyle='#163e33';cx.fillRect(0,0,760,66);cx.fillStyle='#f0bd81';cx.font='22px sans-serif';cx.fillText('AVESTA · STAGING TEST DOCUMENT',25,42);
 cx.fillStyle='#163e33';cx.font='bold 32px sans-serif';cx.fillText('SAMPLE — NOT VALID',28,145);cx.font='20px sans-serif';
 const title=allDocuments[id].replace(' (both sides in one dummy file)','');cx.fillText(title,28,200);cx.fillText('Fictional placeholder. No identity or financial data.',28,249);cx.fillStyle='#d98e3b';cx.fillRect(28,297,704,3);cx.fillStyle='#52675d';cx.font='17px sans-serif';cx.fillText('For interface testing only. Do not use as evidence.',28,345);
 const blob=await new Promise(resolve=>c.toBlob(resolve,'image/png'));if(!blob)throw new Error('Sample image could not be generated.');return new File([blob],`SAMPLE-${id}-NOT-VALID.png`,{type:'image/png'});
}
$('#sample-docs').addEventListener('click',async()=>{
 if(generatingSamples)return;
 const myGeneration=generation,security=state.security;generatingSamples=true;updateBusy();
 try{for(const id of requiredDocs(security)){const file=await createSample(id);if(myGeneration!==generation||security!==state.security)return;await acceptFile(id,file);}}
 catch{if(myGeneration===generation)$('#document-status').textContent='Could not generate the sample image. Please choose a dummy file instead.';}
 finally{if(myGeneration===generation)generatingSamples=false;updateBusy();}
});
function reviewGroup(step,title,rows){return `<section class="aj-review-group"><div class="aj-review-head"><h3>${e(title)}</h3><button type="button" class="aj-text-button" data-review-step="${step}">Edit ${e(steps[step])}</button></div><dl>${rows.map(([label,value])=>`<div><dt>${e(label)}</dt><dd>${e(value||'Not provided')}</dd></div>`).join('')}</dl></section>`;}
function renderReview(){
 const amount=estimate(state),q=amount.quote;
 const borrower=[['Fictional name',state.name],['Demo identity reference',state.identity],['Dummy phone',state.phone],['Fictional address',state.address],['Dummy email',state.email],['Fictional occupation / employer',state.occupation],['Fictional monthly income',money(Number(state.income))],['Security to preview',securityOptions[state.security]]];
 if(state.dob)borrower.push(['Fictional date of birth',dateLabel(state.dob)]);
 if(state.security==='collateral')state.collateral.forEach((x,i)=>borrower.push([`Fictional item ${i+1}`,`${x.description} · ${money(Number(x.value))}`]));
 if(['checkoff','employer_letter'].includes(state.security))borrower.push(['Fictional employer',state.employer]);
 if(state.security==='checkoff')borrower.push(['Fictional payroll contact',state.payrollContact],['Dummy payroll number',state.payrollNumber],['Dummy payroll phone',state.payrollPhone]);
 if(state.security==='guarantor')borrower.push(['Fictional guarantor',state.guarantorName],['Guarantor demo ID',state.guarantorIdentity],['Dummy guarantor phone',state.guarantorPhone],['Fictional relationship',state.guarantorRelationship],['Fictional guarantor occupation',state.guarantorOccupation],['Fictional guarantor income',state.guarantorIncome?money(Number(state.guarantorIncome)):'']);
 if(state.referenceName||state.referencePhone||state.referenceRelationship)borrower.push(['Fictional reference',state.referenceName],['Dummy reference phone',state.referencePhone],['Fictional relationship',state.referenceRelationship]);
 $('#review-content').innerHTML=reviewGroup(0,'Borrower & security',borrower)+reviewGroup(1,'Loan details',[['Sample principal',money(q.principal/100)],['Fictional purpose',state.purpose],['Illustrative disbursement date',dateLabel(state.start)],['Receipt method (not connected)',methods[state.receive]]])+reviewGroup(2,'Repayment illustration',[['Term',state.weeks+' weeks'],['Flat interest',q.rate+'% · '+money(q.interest/100)],['Total',money(q.total/100)],['Schedule',state.frequency],['Repayment method (not connected)',methods[state.repay]]])+reviewGroup(3,'Dummy documents',Object.entries(state.documents).map(([id,doc])=>[allDocuments[id],doc.name+' · local preview only']));
}
$('#review-content').addEventListener('click',event=>{const button=event.target.closest('[data-review-step]');if(button)showStep(Number(button.dataset.reviewStep));});
function drawSignature(){
 const ratio=devicePixelRatio||1;const rect=canvas.getBoundingClientRect();if(!rect.width||!rect.height)return;
 canvas.width=Math.round(rect.width*ratio);canvas.height=Math.round(rect.height*ratio);ctx.setTransform(ratio,0,0,ratio,0,0);ctx.clearRect(0,0,rect.width,rect.height);ctx.strokeStyle='#163e33';ctx.lineWidth=2;ctx.lineCap='round';ctx.lineJoin='round';
 for(const line of [...strokes,...(activeStroke?[activeStroke]:[])]){if(line.length<2)continue;ctx.beginPath();line.forEach((p,i)=>i?ctx.lineTo(p.x*rect.width,p.y*rect.height):ctx.moveTo(p.x*rect.width,p.y*rect.height));ctx.stroke();}
 $('#signature-undo').disabled=strokes.length===0;$('#signature-redo').disabled=redoStrokes.length===0;$('#signature-status').textContent=strokes.length?`${strokes.length} demo stroke${strokes.length===1?'':'s'} in this tab only.`:'No demo drawing.';
}
const point=event=>{const r=canvas.getBoundingClientRect();return{x:Math.max(0,Math.min(1,(event.clientX-r.left)/r.width)),y:Math.max(0,Math.min(1,(event.clientY-r.top)/r.height))};};
canvas.addEventListener('pointerdown',event=>{if(event.button!==0||!event.isPrimary||strokes.length>=100)return;activeStroke=[point(event)];canvas.setPointerCapture(event.pointerId);invalidateSignoff();event.preventDefault();});
canvas.addEventListener('pointermove',event=>{if(activeStroke&&activeStroke.length<1000){activeStroke.push(point(event));drawSignature();}});
function finishStroke(){if(activeStroke?.length>1){strokes.push(activeStroke);redoStrokes=[];}activeStroke=null;drawSignature();}
canvas.addEventListener('pointerup',finishStroke);canvas.addEventListener('pointercancel',finishStroke);
$('#signature-undo').addEventListener('click',()=>{if(strokes.length)redoStrokes.push(strokes.pop());invalidateSignoff();drawSignature();});
$('#signature-redo').addEventListener('click',()=>{if(redoStrokes.length)strokes.push(redoStrokes.pop());invalidateSignoff();drawSignature();});
$('#signature-clear').addEventListener('click',()=>{strokes=[];redoStrokes=[];activeStroke=null;invalidateSignoff();drawSignature();});
new ResizeObserver(drawSignature).observe(canvas);$('#draw-area').addEventListener('toggle',drawSignature);
function discardWorkingState(){
 generation++;for(const doc of Object.values(state.documents))if(doc.url)URL.revokeObjectURL(doc.url);
 documentTokens.clear();pendingFiles.clear();generatingSamples=false;strokes=[];redoStrokes=[];activeStroke=null;state=initialState();form.reset();
 $$('[data-field]').forEach(el=>{if(el.type==='checkbox')el.checked=false;else el.value='';});
 $('#collateral-items').replaceChildren();$('#document-list').replaceChildren();$('#review-content').replaceChildren();$('#repayment-estimate').replaceChildren();$('#repayment-schedule').replaceChildren();$('#sidebar-estimate').replaceChildren();
 ctx.clearRect(0,0,canvas.width,canvas.height);$('#document-status').textContent='';clearErrors();
}
function startNew(useExample=false){
 discardWorkingState();state=useExample?fictionalExample(initialSearch):initialState();current=0;furthest=0;$('#confirmation').hidden=true;$('#journey-shell').hidden=false;$('#fill-demo').disabled=false;$('#draw-area').open=false;writeState();showStep(0);
}
function completeDemo(){
 discardWorkingState();$('#journey-shell').hidden=true;$('#confirmation').hidden=false;$('#fill-demo').disabled=true;$('#confirmation').focus();
}
$('#fill-demo').addEventListener('click',()=>startNew(true));
$('#start-again').addEventListener('click',()=>startNew(false));
$('#clear-demo').addEventListener('click',()=>$('#clear-dialog').showModal());
$('#cancel-clear').addEventListener('click',()=>$('#clear-dialog').close());
$('#confirm-clear').addEventListener('click',()=>{$('#clear-dialog').close();startNew(false);});
window.addEventListener('pagehide',()=>discardWorkingState());
window.addEventListener('pageshow',event=>{if(event.persisted)startNew(false);});
// The parsed query contains numeric planning inputs only; form values never enter URLs.
$('#journey-shell').hidden=false;writeState();showStep(0,false);
