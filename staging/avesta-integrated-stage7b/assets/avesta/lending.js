/* Approved Balanced Enterprise Lending layout. Local estimates only. */
import {rates, frequencies, quote, schedule, money, today, escapeHTML as e} from './domain.js?v=20261003-steady-navigation';
import {calculatorOptions, STAGE_BASE} from './routing.js?v=20261018-stage7b-r1';

function sectionHead(kicker, title, description) {
  return `<div class="be-lending-heading"><div><p class="eyebrow">${kicker}</p><h2>${title}</h2></div><p>${description}</p></div>`;
}
function termCards() {
  return `<div class="be-term-cards">${Object.entries(rates).map(([weeks, rate]) => `<a class="be-term-card" href="/calculator?weeks=${weeks}" aria-label="Estimate a ${weeks}-week loan at ${rate}% flat interest"><span>${weeks} ${weeks==='1'?'week':'weeks'}</span><strong>${rate}%</strong><small>Flat interest for the full term</small><span class="be-term-link">Estimate this term <span aria-hidden="true">↗</span></span></a>`).join('')}</div>`;
}
function estimator() {
  return `<div class="be-estimator" data-lending-estimator>
  <div class="be-estimator-inputs"><p class="eyebrow">Estimate a loan</p><h3>Your repayment plan</h3>
    <label class="field" for="be-loan-amount">Principal amount<div class="input-prefix"><span>ZMW</span><input id="be-loan-amount" type="number" inputmode="decimal" min="0.01" max="1000000" step="0.01" value="1000" aria-describedby="be-amount-help be-loan-error" autocomplete="off"></div></label>
    <p class="be-field-help" id="be-amount-help">Use a sample amount. ZMW 0.01–1,000,000; up to two decimal places.</p>
    <div id="be-loan-error" class="be-loan-error" role="status" aria-live="polite"></div>
    <p class="be-field-label" id="be-loan-term-label">Choose loan term</p><div class="be-term-picker" role="group" aria-labelledby="be-loan-term-label">${Object.entries(rates).map(([weeks, rate])=>`<button type="button" data-loan-weeks="${weeks}" aria-pressed="${weeks==='2'}">${weeks} ${weeks==='1'?'week':'weeks'}<small>${rate}% flat</small></button>`).join('')}</div>
    <label class="field" for="be-loan-frequency">Illustrative repayment frequency<select id="be-loan-frequency">${frequencies.map(f=>`<option${f==='Lump sum'?' selected':''}>${e(f)}</option>`).join('')}</select></label>
    <label class="field" for="be-loan-date">Illustrative disbursement date<input id="be-loan-date" type="date" value="${e(today())}"></label>
    <p class="be-field-help">Amounts and dates are calculated in your browser. Nothing is submitted.</p>
  </div>
  <div class="be-estimator-results"><p class="eyebrow">Your illustration</p><h3>Clear from the start.</h3><p class="be-result-intro">Based on the selected term, amount and payment frequency.</p>
    <div id="be-loan-results" aria-live="polite" aria-atomic="true"></div>
    <p class="be-estimate-note">An estimate, not an offer or approval. Figures exclude charges not specified in this flat-interest model. Confirm final costs and dates in a written agreement.</p>
    <a class="button" data-application-demo href="/apply">Try the application demo <span aria-hidden="true">→</span></a>
    <p class="be-disabled-note">Fictional details only. No application is sent or stored.</p>
  </div></div><div class="be-schedule"><h3>Your illustrative payment schedule</h3><p>Dates and rounded instalments are shown for review only.</p><div id="be-loan-schedule"></div></div>`;
}
function processCards() {
  return `<ol class="be-process-cards" aria-label="Loan application process"><li><span class="be-step-number" aria-hidden="true">01</span><h3>Review the terms</h3><p>Choose a 1–4 week term, review its flat rate and estimate what you could repay.</p></li><li><span class="be-step-number" aria-hidden="true">02</span><h3>Complete the application</h3><p>On the live service, prepare the required information and documents. No application can be submitted here.</p></li><li><span class="be-step-number" aria-hidden="true">03</span><h3>Receive a decision</h3><p>Avesta assesses the application. Approved arrangements are confirmed in writing before commitment.</p></li></ol>`;
}
function questions() {
  return `<div class="be-lending-faq"><details><summary>Is this a live loan application?</summary><p>No. This is a protected staging review. It cannot submit applications, process payments or access real customer records.</p></details><details><summary>Is the displayed interest calculated weekly?</summary><p>No. Each percentage is flat interest for the selected full term, not a weekly compounding rate. Using the existing model, ZMW 1,000 over two weeks at 20% gives ZMW 200 interest and ZMW 1,200 total.</p></details><details><summary>Does the estimate mean I am approved?</summary><p>No. Approval, documentation, additional disclosed charges and repayment conditions are determined through Avesta's application assessment and written agreement.</p></details><details><summary>Why do some instalments differ by a ngwee?</summary><p>Rounding is distributed across instalments so that their sum matches the total exactly. Dates use the selected term and frequency.</p></details></div>`;
}
export function renderLending(orbit) {
  return `<div class="be-lending"><section class="be-hero be-lending-hero"><div class="wrap be-hero-grid"><div class="be-hero-copy"><span class="be-pill">Avesta Lending · Short-term finance</span><h1>Short-term finance.<br><em>Clearly explained.</em></h1><p>Understand published flat interest rates, compare repayment options, and make an informed decision before taking the next step.</p><div class="be-actions"><a class="button" href="/lending#loan-estimator">Estimate repayment <span aria-hidden="true">↗</span></a><a class="be-hero-link" href="/lending#published-terms">View published terms <span aria-hidden="true">↓</span></a></div><p class="be-hero-note">1–4 week terms · Subject to assessment and written agreement.</p></div>${orbit}</div></section>
  <div class="be-trust"><div class="wrap be-trust-grid"><div><span aria-hidden="true">⌖</span><p><strong>Based in Kansenshi, Ndola</strong><small>Local support for your questions</small></p></div><div><span aria-hidden="true">◷</span><p><strong>Published flat lending rates</strong><small>Rates based on the selected term</small></p></div><div><span aria-hidden="true">✓</span><p><strong>Understand before committing</strong><small>Confirm full costs in writing</small></p></div></div></div>
  <section class="section" id="published-terms"><div class="wrap">${sectionHead('Avesta Lending · Published terms','Four terms.<br>Clear choices.','Each rate is flat interest for the full term. The final agreement determines the approved amount and repayment conditions. Select a card to carry that term into the calculator.')}${termCards()}</div></section>
  <section class="section be-lending-soft" id="loan-estimator"><div class="wrap">${sectionHead('Loan planning','Know the numbers.<br>Before you decide.','The calculator uses the existing Avesta flat-interest model. These are illustrative figures only, not an offer or approval.')}${estimator()}</div></section>
  <section class="section" id="lending-process"><div class="wrap">${sectionHead('Straightforward process','Understand.<br>Apply. Confirm.','Review the process before you prepare an application. Real submissions remain disabled throughout this review.')}${processCards()}<div class="actions"><a class="link" href="/how-it-works">Read the process and document checklist</a></div></div></section>
  <section class="section be-lending-soft" id="lending-questions"><div class="wrap narrow">${sectionHead('Common questions','Answers before action.','')}${questions()}</div></section>
  <section class="section"><div class="wrap"><div class="cta"><div><h2>Confidence starts with clarity.</h2><p>Review the terms and your estimate before deciding what comes next.</p></div><a class="button" href="/calculator">Review your estimate <span aria-hidden="true">↗</span></a></div></div></section></div>`;
}
export function renderCalculator() {
  return `<div class="be-lending"><section class="page-head"><div class="wrap"><p class="eyebrow">Loan planning · Staging review</p><h1>See the cost.<br><em>Choose with confidence.</em></h1><p>Compare the existing Avesta terms and illustrative schedules. No personal details or applications are collected.</p></div></section><section class="section be-lending-soft"><div class="wrap">${estimator()}<div class="actions"><a class="link" href="/lending#published-terms">Back to published terms</a><a class="link" href="/how-it-works">How it works</a></div></div></section></div>`;
}
export function renderHowItWorks() {
  return `<div class="be-lending"><section class="page-head"><div class="wrap"><p class="eyebrow">Avesta Lending · The process</p><h1>A clear path from<br><em>application to decision.</em></h1><p>Understand what to prepare, what happens next and what is confirmed in writing.</p></div></section><section class="section"><div class="wrap">${processCards()}</div></section>
  <section class="section be-lending-soft"><div class="wrap two-col"><div><p class="eyebrow">Have these ready</p><h2>A little preparation helps.</h2><p class="be-checklist-intro">This is an information checklist only. Do not enter or upload these details in staging.</p></div><div class="card"><h3>Application checklist</h3><ul class="be-checklist"><li>Your full name, NRC and contact details</li><li>Clear images of your NRC front and back</li><li>Occupation, employer and monthly income</li><li>Amount, purpose and repayment plan</li><li>Applicable collateral, salary check-off, employer letter or guarantor details</li><li>Relevant supporting documents, such as a payslip</li></ul></div></div></section>
  <section class="section"><div class="wrap narrow">${sectionHead('Common questions','Before you apply','')}${questions()}<div class="actions"><a class="button secondary" href="/calculator">Estimate repayment</a><a class="link" href="/apply">Try the five-step application demo</a></div></div></section></div>`;
}
export function bindLendingEstimator(options = {}) {
  const box = document.querySelector('[data-lending-estimator]');
  if (!box) return;
  const params = calculatorOptions(new URLSearchParams(globalThis.location.search));
  const amount = box.querySelector('#be-loan-amount');
  const frequency = box.querySelector('#be-loan-frequency');
  const date = box.querySelector('#be-loan-date');
  const error = box.querySelector('#be-loan-error');
  const result = box.querySelector('#be-loan-results');
  const scheduleBox = document.querySelector('#be-loan-schedule');
  let weeks = Number(params.get('weeks') || 2);
  amount.value = params.get('amount') || '1000';
  frequency.value = params.get('frequency') || 'Lump sum';
  date.value = params.get('start') || today();
  const dateLabel = value => new Intl.DateTimeFormat('en-GB',{day:'numeric',month:'short',year:'numeric',timeZone:'Africa/Lusaka'}).format(new Date(value+'T12:00:00Z'));
  function update() {
    box.querySelectorAll('[data-loan-weeks]').forEach(button=>button.setAttribute('aria-pressed',String(Number(button.dataset.loanWeeks)===weeks)));
    amount.setAttribute('aria-invalid','false');date.setAttribute('aria-invalid','false');
    try {
      const n = Number(amount.value);
      if (amount.value.trim()==='' || !Number.isFinite(n) || n<.01 || n>1000000 || Math.abs(n*100-Math.round(n*100))>1e-6) {
        amount.setAttribute('aria-invalid','true');
        throw new Error('Enter an amount from ZMW 0.01 to ZMW 1,000,000 with at most two decimal places.');
      }
      if (!frequencies.includes(frequency.value)) throw new Error('Choose one of the displayed repayment frequencies.');
      const dateCheck = calculatorOptions(new URLSearchParams({start:date.value}));
      if (!dateCheck.has('start')) {date.setAttribute('aria-invalid','true');throw new Error('Choose a valid illustrative disbursement date.');}
      const q=quote(n,weeks,frequency.value), rows=schedule(n,weeks,frequency.value,date.value);
      error.textContent='';
      result.innerHTML=`<dl class="be-results-list"><div><dt>Principal</dt><dd data-result="principal">${e(money(q.principal/100))}</dd></div><div><dt>Flat interest (${q.rate}%)</dt><dd data-result="interest">${e(money(q.interest/100))}</dd></div><div class="be-result-total"><dt>Total to repay</dt><dd data-result="total">${e(money(q.total/100))}</dd></div><div><dt>Illustrative instalments</dt><dd data-result="count">${q.count}</dd></div></dl><p class="be-result-payments">Payment amount${q.count===1?'':'s'}: <strong data-result="payments">${q.instalments.map(c=>e(money(c/100))).join(' / ')}</strong></p>`;
      scheduleBox.innerHTML=`<div class="be-schedule-scroll" tabindex="0" role="region" aria-label="Illustrative payment schedule"><table><caption>${weeks}-week term · ${e(frequency.value)} · ${q.rate}% flat interest</caption><thead><tr><th scope="col">Payment</th><th scope="col">Estimated date</th><th scope="col">Amount</th></tr></thead><tbody>${rows.map((row,i)=>`<tr><th scope="row">${i+1}</th><td>${e(dateLabel(row.date))}</td><td data-cents="${row.amount}">${e(money(row.amount/100))}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="2" scope="row">Total</th><td>${e(money(q.total/100))}</td></tr></tfoot></table></div>`;
      const demo=box.querySelector('[data-application-demo]');
      if (demo) { demo.href=STAGE_BASE+'/apply.php?'+new URLSearchParams({amount:amount.value,weeks:String(weeks),frequency:frequency.value,start:date.value}); demo.removeAttribute('aria-disabled'); }
      if (options.onTermChange) options.onTermChange(weeks);
    } catch (err) {
      const demo=box.querySelector('[data-application-demo]');if(demo){demo.removeAttribute('href');demo.setAttribute('aria-disabled','true');}
      error.textContent=err.message;
      result.innerHTML='<p class="be-result-empty">Your estimate will appear after the highlighted details are corrected.</p>';
      scheduleBox.innerHTML='<p class="notice">No schedule is shown until the amount, term and date are valid.</p>';
    }
  }
  box.querySelectorAll('[data-loan-weeks]').forEach(button=>button.addEventListener('click',()=>{weeks=Number(button.dataset.loanWeeks);update();}));
  amount.addEventListener('input',update);frequency.addEventListener('change',update);date.addEventListener('change',update);
  update();
}
