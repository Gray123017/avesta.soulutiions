import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';

const source = readFileSync('app/index.php', 'utf8');
function section(start, end) {
  const a = source.indexOf(start), b = source.indexOf(end, a + start.length);
  assert.ok(a >= 0 && b > a, start);
  return source.slice(a, b);
}
function boot(weeks=4) {
  const w = new Window({settings:{enableJavaScriptEvaluation:true,suppressInsecureJavaScriptEnvironmentWarning:true}});
  w.document.body.innerHTML = '<input id="disburse_date" value="2026-10-03">' + section('<div class="wiz-panel" id="wiz-3">', '<!-- ═══ STEP 4: DOCUMENTS');
  w.eval('let selectedDurationWeeks='+weeks+'; const userEditedDates={first_payment:false,last_payment:false}; window.__userEditedDates=userEditedDates; function saveProgress(){} function updateFieldCounter(){}' + section('function addWeeksToDate(', 'const LATE_RATE_PER_WEEK') + section('function updateInstallment()', 'async function clearForm()') + section('function getCheckboxes(', '\nfunction '));
  w.document.getElementById('total_repay').value = '2600.00';
  return w;
}
test('Application schedule fills count, amounts and correct first/final dates; only one plan is selected', async()=>{
  const w=boot(), d=w.document;
  for(const [value,count,first,amount] of [['Weekly',4,'2026-10-10','650.00'],['Bi-Weekly',2,'2026-10-17','1300.00'],['Monthly',1,'2026-10-31','2600.00'],['Lump Sum',1,'2026-10-31','2600.00']]) {
    const choice=d.querySelector(`.check-pills input[value="${value}"]`);choice.checked=true;w.updateRepaymentSchedule(choice);
    assert.equal(d.querySelectorAll('.check-pills input:checked').length,1);
    assert.equal(d.getElementById('num_inst').value,String(count));
    assert.equal(d.getElementById('inst_amt').value,amount);
    assert.equal(d.getElementById('first_payment').value,first);
    assert.equal(d.getElementById('last_payment').value,'2026-10-31');
    assert.notEqual(w.getCheckboxes('#wiz-3 .check-pills'),'—');
  }
  assert.equal(d.getElementById('num_inst').readOnly,true);
  await w.happyDOM.close();
});
test('Rounding keeps the instalments equal to the total; missing choices stay incomplete',async()=>{
  const w=boot(3),d=w.document;d.getElementById('total_repay').value='1250.00';
  const choice=d.querySelector('.check-pills input[value="Weekly"]');choice.checked=true;w.updateRepaymentSchedule(choice);
  assert.equal(d.getElementById('inst_amt').value,'416.66');
  assert.match(d.getElementById('repayment-plan-summary').textContent,/2 payments of K416.66 and a final payment of K416.68/);
  assert.equal(41666*2+41668,125000);
  choice.checked=false;w.updateRepaymentSchedule(choice);
  assert.equal(d.getElementById('num_inst').value,'');assert.equal(d.getElementById('inst_amt').value,'');assert.equal(d.getElementById('first_payment').value,'');
  assert.ok(source.includes("errorMessages.push('Choose one repayment schedule')"));
  assert.equal(source.includes("getCheckboxes('#wiz-3 .check-row')"),false);
  await w.happyDOM.close();
});
test('Long-term partial intervals end on the term date; explicit manual dates are preserved',async()=>{
  const w=boot(5),d=w.document;const choice=d.querySelector('.check-pills input[value="Bi-Weekly"]');choice.checked=true;w.updateRepaymentSchedule(choice);
  assert.equal(d.getElementById('num_inst').value,'3');assert.equal(d.getElementById('last_payment').value,'2026-11-07');
  d.getElementById('first_payment').value='2026-10-20';w.__userEditedDates.first_payment=true;w.updateRepaymentDates();assert.equal(d.getElementById('first_payment').value,'2026-10-20');
  await w.happyDOM.close();
});
test('Only required NRC uploads are shown upfront; optional evidence stays accessible',async()=>{
  const w=new Window();w.document.body.innerHTML=section('<div class="wiz-panel" id="wiz-4">','<!-- ═══ STEP 5: REVIEW');
  const d=w.document,optional=d.getElementById('optional-supporting-documents');assert.equal(optional.open,false);
  for(const id of ['doc_nrc_front','doc_nrc_back'])assert.equal(optional.contains(d.getElementById(id)),false);
  for(const id of ['doc_passport','doc_salary1','doc_salary2','doc_payslip','doc_bank_statement','doc_utility_bill'])assert.ok(optional.contains(d.getElementById(id)),id);
  assert.match(optional.textContent,/do not need to upload the same document twice/);optional.open=true;assert.ok(d.getElementById('doc_payslip'));
  await w.happyDOM.close();
});
