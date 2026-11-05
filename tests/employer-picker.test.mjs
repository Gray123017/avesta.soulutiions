import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Window } from 'happy-dom';

/* The employer picker on the application form.
 *
 * Three states, and the form has to be right in all of them: somebody already
 * on an employer's register, somebody applying to an employer that is on it,
 * and the rollout position where the register is still empty and the old free
 * text has to keep working.
 */

const source = readFileSync('app/index.php', 'utf8');

function section(start, end) {
  const a = source.indexOf(start), b = source.indexOf(end, a + start.length);
  assert.ok(a >= 0 && b > a, 'could not find section: ' + start);
  return source.slice(a, b);
}

const CHECKOFF = section('<div class="f-section" id="checkoff-section"', '<!-- EMPLOYER LETTER DETAILS -->');
const JS = section('// ── EMPLOYER PICKER ───', '// ── END EMPLOYER PICKER');

const EMPLOYERS = [
  { id: 'emp_a', name: 'Avanto Group Limited', branches: [
    { id: 'br_1', name: 'Ndola Head Office', town: 'Ndola' },
    { id: 'br_2', name: 'Kitwe Plant', town: 'Kitwe' }] },
  { id: 'emp_b', name: 'Copperfields Mining Plc', branches: [
    { id: 'br_3', name: 'Chingola', town: 'Chingola' }] },
];

function boot(employers, mine) {
  const w = new Window({ settings: {
    enableJavaScriptEvaluation: true, suppressInsecureJavaScriptEnvironmentWarning: true } });
  w.document.body.innerHTML = CHECKOFF;
  w.AV_EMPLOYERS = employers;
  w.AV_MY_EMPLOYER = mine;
  w.eval('function saveProgress(){}' + JS);
  w.avEmployerInit();
  return w;
}

const $ = (w, id) => w.document.getElementById(id);

test('somebody already on a register has it filled in, and cannot change it', async () => {
  const w = boot(EMPLOYERS, {
    employer: { id: 'emp_a', name: 'Avanto Group Limited', status: 'active' },
    branch: { id: 'br_1', name: 'Ndola Head Office', town: 'Ndola' },
    enrolment: { id: 'enr_1', payroll_no: 'AV-0012' },
  });

  assert.equal($(w, 'co_employer').value, 'Avanto Group Limited');
  assert.equal($(w, 'co_payroll_no').value, 'AV-0012');
  assert.equal($(w, 'emp_id').value, 'emp_a');
  assert.equal($(w, 'emp_branch_id').value, 'br_1');

  // Read-only rather than disabled: a disabled field is not submitted at all,
  // and these have to travel with the application.
  assert.equal($(w, 'co_employer').readOnly, true);
  assert.equal($(w, 'co_payroll_no').readOnly, true);

  // And they are told why they cannot edit it.
  assert.notEqual($(w, 'co-employer-note').style.display, 'none');
  assert.match($(w, 'co-employer-note').textContent, /Avanto Group Limited/);

  // No picker: there is nothing to pick.
  assert.equal($(w, 'co-employer-pick').style.display, 'none');
  await w.happyDOM.close();
});

test('somebody not yet enrolled picks from the register, branches and all', async () => {
  const w = boot(EMPLOYERS, null);

  const employer = $(w, 'co_employer_select');
  assert.notEqual($(w, 'co-employer-pick').style.display, 'none');
  // A blank first option, so nothing is chosen by accident.
  assert.deepEqual(Array.from(employer.options).map(o => o.value),
    ['', 'emp_a', 'emp_b']);

  // The free-text box is out of the way while the register is in use.
  assert.equal($(w, 'co-employer-typed').style.display, 'none');

  // Branches only appear once an employer is chosen, and only that employer's.
  assert.equal($(w, 'co-branch-group').style.display, 'none');
  employer.value = 'emp_a';
  employer.dispatchEvent(new w.Event('change'));

  assert.notEqual($(w, 'co-branch-group').style.display, 'none');
  assert.deepEqual(Array.from($(w, 'co_branch').options).map(o => o.textContent),
    ['', 'Ndola Head Office (Ndola)', 'Kitwe Plant (Kitwe)']);
  assert.equal($(w, 'emp_id').value, 'emp_a');
  assert.equal($(w, 'co_employer').value, 'Avanto Group Limited');

  // Choosing a branch records it; switching employer clears the stale branch.
  $(w, 'co_branch').value = 'br_2';
  $(w, 'co_branch').dispatchEvent(new w.Event('change'));
  assert.equal($(w, 'emp_branch_id').value, 'br_2');

  employer.value = 'emp_b';
  employer.dispatchEvent(new w.Event('change'));
  assert.equal($(w, 'emp_branch_id').value, '',
    'a branch from the previous employer must not survive the switch');
  assert.deepEqual(Array.from($(w, 'co_branch').options).map(o => o.textContent),
    ['', 'Chingola (Chingola)']);
  await w.happyDOM.close();
});

test('with an empty register the form works exactly as it did before', async () => {
  const w = boot([], null);

  // The rollout position: nobody is registered yet, so a typed name is still
  // how this channel works, and nothing about the form has changed.
  assert.equal($(w, 'co-employer-pick').style.display, 'none');
  assert.notEqual($(w, 'co-employer-typed').style.display, 'none');
  assert.equal($(w, 'co_employer').readOnly, false);
  assert.equal($(w, 'emp_id').value, '');
  await w.happyDOM.close();
});

test('a register that could not be read leaves the form alone', async () => {
  const w = boot(null, null);
  assert.equal($(w, 'co-employer-pick').style.display, 'none');
  assert.equal($(w, 'co_employer').readOnly, false);
  await w.happyDOM.close();
});

test('the application carries the employer and branch it was bound to', () => {
  const payload = section('const data = {', 'security_type:');
  assert.match(payload, /employer_id:\s*gv\('emp_id'\)/);
  assert.match(payload, /branch_id:\s*gv\('emp_branch_id'\)/);
});
