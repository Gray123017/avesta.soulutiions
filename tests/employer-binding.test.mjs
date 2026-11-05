import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* Tying an application to the employer register.
 *
 * Someone applying through check-off names an employer. Until now that was a
 * line of free text, which is a wish rather than a fact — Avesta would have
 * been deducting from a payroll it had no agreement with. This is the check
 * that turns the name into a record, and the rule that an applicant does not
 * get to choose which employer they work for when the register already says.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;
const cfg = JSON.parse(readFileSync('assets/avesta/categories.json', 'utf8'));

function bind(body) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-binding-'));
  try {
    const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "employers-lib.php";
       av_employers_path(${JSON.stringify(join(dir, 'employers.json'))});
       ${body}`], { encoding: 'utf8' });
    try { return JSON.parse(out); }
    catch { throw new Error('employers-lib.php did not return JSON:\n' + out.slice(0, 1200)); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

/** One active employer with one branch, plus a second branch elsewhere. */
const SETUP = `
  $e = av_employer_create(['name' => 'Avanto Group Limited']);
  $eid = $e['employer']['id'];
  $bid = av_employer_add_branch($eid, 'Ndola Head Office', 'Ndola')['branch']['id'];
  av_employer_set_status($eid, 'active');

  $other = av_employer_create(['name' => 'Someone Else Ltd']);
  $oid = $other['employer']['id'];
  $obid = av_employer_add_branch($oid, 'Lusaka', 'Lusaka')['branch']['id'];
  av_employer_set_status($oid, 'active');
`;

test('the channel is published as requiring a registered employer, or not', () => {
  // Same rollout handle as requireCategory: a switch in the published file, so
  // it can be turned on when the register has been filled in, with no code
  // change and nothing to deploy.
  assert.equal(typeof cfg.categories.company_employee.requireRegisteredEmployer, 'boolean');
});

test('an application in another channel is left completely alone',
  { skip: !hasPhp }, () => {
  const r = bind(SETUP + `
    $in = ['applicant_category' => 'self_employed', 'checkoff_employer' => 'Anything At All'];
    echo json_encode(av_employer_bind($in, 'u1'));`);
  assert.equal(r.ok, true);
  assert.equal(r.payload.checkoff_employer, 'Anything At All');
  assert.equal(r.payload.employer_id ?? null, null);
});

test('an enrolled employee is bound to the employer the register says, not the one they typed',
  { skip: !hasPhp }, () => {
  const r = bind(SETUP + `
    av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda Mulenga',
                             'payroll_no'=>'AV-0012','branch_id'=>$bid]);
    // They claim to work somewhere else, on a different payroll number.
    $in = ['applicant_category' => 'company_employee',
           'employer_id' => $oid, 'branch_id' => $obid,
           'checkoff_employer' => 'Someone Else Ltd', 'checkoff_payroll_no' => 'FAKE-1'];
    echo json_encode(av_employer_bind($in, 'u1'));`);

  assert.equal(r.ok, true);
  // The register is the fact. Nothing the browser sent survives it.
  assert.equal(r.payload.checkoff_employer, 'Avanto Group Limited');
  assert.equal(r.payload.checkoff_payroll_no, 'AV-0012');
  assert.equal(r.payload.employer_branch, 'Ndola Head Office');
  assert.equal(r.payload.employer_source, 'register');
  assert.ok(r.payload.employer_id);
  assert.ok(r.payload.enrolment_id);
});

test('an applicant who is not enrolled may still name a registered employer',
  { skip: !hasPhp }, () => {
  const r = bind(SETUP + `
    $in = ['applicant_category' => 'company_employee', 'employer_id' => $eid,
           'branch_id' => $bid, 'checkoff_payroll_no' => 'AV-0099'];
    echo json_encode(av_employer_bind($in, 'newcomer'));`);

  assert.equal(r.ok, true);
  assert.equal(r.payload.checkoff_employer, 'Avanto Group Limited');
  assert.equal(r.payload.employer_branch, 'Ndola Head Office');
  // Claimed, not confirmed: nobody has checked this against the payroll yet.
  assert.equal(r.payload.employer_source, 'claimed');
  assert.equal(r.payload.checkoff_payroll_no, 'AV-0099');
  assert.equal(r.payload.enrolment_id ?? null, null);
});

test('a branch that belongs to another employer is refused', { skip: !hasPhp }, () => {
  const r = bind(SETUP + `
    $in = ['applicant_category' => 'company_employee',
           'employer_id' => $eid, 'branch_id' => $obid];
    echo json_encode(av_employer_bind($in, 'newcomer', true));`);
  assert.equal(r.ok, false);
  assert.match(r.error, /branch/i);
});

test('an employer that is not active cannot be named', { skip: !hasPhp }, () => {
  const r = bind(SETUP + `
    av_employer_set_status($eid, 'suspended');
    $in = ['applicant_category' => 'company_employee', 'employer_id' => $eid, 'branch_id' => $bid];
    echo json_encode(av_employer_bind($in, 'newcomer', true));`);
  // A check-off loan against a company that has stopped remitting cannot be
  // collected, so it must not be taken in the first place.
  assert.equal(r.ok, false);
  assert.match(r.error, /not one we/i);
});

test('with the requirement on, free text alone is no longer enough',
  { skip: !hasPhp }, () => {
  const r = bind(SETUP + `
    $in = ['applicant_category' => 'company_employee',
           'checkoff_employer' => 'A Company We Have Never Heard Of'];
    echo json_encode(av_employer_bind($in, 'newcomer', true));`);
  assert.equal(r.ok, false);
  assert.match(r.error, /choose your employer/i);
});

test('with the requirement off, free text still goes through', { skip: !hasPhp }, () => {
  // The rollout position: the register is being filled in, and applications
  // started before the picker shipped must not be thrown away.
  const r = bind(SETUP + `
    $in = ['applicant_category' => 'company_employee',
           'checkoff_employer' => 'A Company We Have Never Heard Of'];
    echo json_encode(av_employer_bind($in, 'newcomer', false));`);
  assert.equal(r.ok, true);
  assert.equal(r.payload.checkoff_employer, 'A Company We Have Never Heard Of');
  assert.equal(r.payload.employer_source, 'typed');
});

test('an enrolled employee is bound even while the requirement is off',
  { skip: !hasPhp }, () => {
  const r = bind(SETUP + `
    av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-0012','branch_id'=>$bid]);
    $in = ['applicant_category' => 'company_employee', 'checkoff_employer' => 'Made Up Ltd'];
    echo json_encode(av_employer_bind($in, 'u1', false));`);
  // The switch governs whether a stranger must pick from the register. It does
  // not make the register optional for somebody who is in it.
  assert.equal(r.ok, true);
  assert.equal(r.payload.checkoff_employer, 'Avanto Group Limited');
  assert.equal(r.payload.employer_source, 'register');
});

test('somebody who has left is not bound to their old employer', { skip: !hasPhp }, () => {
  const r = bind(SETUP + `
    $enr = av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda',
                                    'payroll_no'=>'AV-0012','branch_id'=>$bid]);
    av_employer_mark_left($eid, $enr['enrolment']['id'], '2026-10-31');
    $in = ['applicant_category' => 'company_employee', 'employer_id' => $oid, 'branch_id' => $obid];
    echo json_encode(av_employer_bind($in, 'u1', true));`);

  assert.equal(r.ok, true);
  assert.equal(r.payload.checkoff_employer, 'Someone Else Ltd');
  assert.equal(r.payload.employer_source, 'claimed');
});
