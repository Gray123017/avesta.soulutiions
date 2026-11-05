import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* The employer register.
 *
 * The check-off channel rests on this: an employer signs up, lists its
 * branches, enrols its staff, and deducts repayments at payroll. Everything
 * that can go wrong here goes wrong with somebody's wages — two people sharing
 * a payroll number means a deduction taken off the wrong salary, and two
 * employers holding the same person means it is taken twice. Those are the
 * rules these tests are mostly about.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

/** Run PHP against a throwaway register and return whatever it echoes as JSON. */
function withStore(body) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-employers-'));
  try {
    const code = `require "employers-lib.php";
      av_employers_path(${JSON.stringify(join(dir, 'employers.json'))});
      ${body}`;
    const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r', code],
      { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
    try { return { result: JSON.parse(out), dir }; }
    catch { throw new Error('employers-lib.php did not return JSON:\n' + out.slice(0, 1500)); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

/** The common opening move: one employer, one branch. */
const SETUP = `
  $e = av_employer_create(['name' => 'Avanto Group Limited', 'registration_no' => '120210000123',
                           'contact_name' => 'Gray Muyengu', 'contact_phone' => '0970000000']);
  $eid = $e['employer']['id'];
  $b = av_employer_add_branch($eid, 'Ndola Head Office', 'Ndola');
  $bid = $b['branch']['id'];
  av_employer_set_status($eid, 'active');
`;

test('an employer is created with branches and no staff, and starts inactive',
  { skip: !hasPhp }, () => {
  const { result } = withStore(`
    $e = av_employer_create(['name' => 'Avanto Group Limited']);
    echo json_encode(['ok' => $e['ok'], 'employer' => $e['employer']]);`);

  assert.equal(result.ok, true);
  assert.equal(result.employer.name, 'Avanto Group Limited');
  // Nobody can borrow against an employer until a person has agreed to it.
  assert.equal(result.employer.status, 'pending');
  assert.deepEqual(result.employer.branches, []);
  assert.deepEqual(result.employer.employees, []);
  assert.ok(result.employer.id);
});

test('an employer with no name is refused', { skip: !hasPhp }, () => {
  const { result } = withStore(`
    $a = av_employer_create(['name' => '   ']);
    $b = av_employer_create([]);
    echo json_encode([$a['ok'], $b['ok']]);`);
  assert.deepEqual(result, [false, false]);
});

test('the same employer cannot be registered twice', { skip: !hasPhp }, () => {
  const { result } = withStore(`
    av_employer_create(['name' => 'Avanto Group Limited']);
    $again = av_employer_create(['name' => '  avanto group LIMITED ']);
    echo json_encode(['ok' => $again['ok'], 'error' => $again['error'] ?? null,
                      'count' => count(av_employers_all())]);`);
  // Two records for one company would split its staff across both.
  assert.equal(result.ok, false);
  assert.equal(result.count, 1);
  assert.match(result.error, /already/i);
});

test('an employee is enrolled against a branch, with a payroll number',
  { skip: !hasPhp }, () => {
  const { result } = withStore(SETUP + `
    $r = av_employer_enrol($eid, ['user_id' => 'u1', 'name' => 'Chanda Mulenga',
                                  'payroll_no' => 'AV-0012', 'branch_id' => $bid]);
    echo json_encode(['ok' => $r['ok'], 'enrolment' => $r['enrolment'],
                      'for_user' => av_employer_for_user('u1')]);`);

  assert.equal(result.ok, true);
  assert.equal(result.enrolment.payroll_no, 'AV-0012');
  assert.equal(result.enrolment.status, 'active');
  assert.ok(result.enrolment.enrolled_at);

  // And the application form can find it from the borrower's side.
  assert.equal(result.for_user.employer.name, 'Avanto Group Limited');
  assert.equal(result.for_user.branch.name, 'Ndola Head Office');
  assert.equal(result.for_user.enrolment.payroll_no, 'AV-0012');
});

test('two people cannot share one payroll number at the same employer',
  { skip: !hasPhp }, () => {
  const { result } = withStore(SETUP + `
    av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-0012','branch_id'=>$bid]);
    $clash = av_employer_enrol($eid, ['user_id'=>'u2','name'=>'Mwila','payroll_no'=>' av-0012 ','branch_id'=>$bid]);
    echo json_encode(['ok' => $clash['ok'], 'error' => $clash['error'] ?? null,
                      'staff' => count(av_employer_find($eid)['employees'])]);`);

  // A deduction would come off the wrong person's salary.
  assert.equal(result.ok, false);
  assert.match(result.error, /payroll number/i);
  assert.equal(result.staff, 1);
});

test('a person cannot be enrolled with two employers at once', { skip: !hasPhp }, () => {
  const { result } = withStore(SETUP + `
    av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-0012','branch_id'=>$bid]);
    $two = av_employer_create(['name' => 'Second Employer Ltd']);
    av_employer_set_status($two['employer']['id'], 'active');
    $b2 = av_employer_add_branch($two['employer']['id'], 'Lusaka', 'Lusaka');
    $clash = av_employer_enrol($two['employer']['id'],
      ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'X-1','branch_id'=>$b2['branch']['id']]);
    echo json_encode(['ok' => $clash['ok'], 'error' => $clash['error'] ?? null]);`);

  // Otherwise both payrolls deduct for the same loan.
  assert.equal(result.ok, false);
  assert.match(result.error, /already enrolled/i);
});

test('someone who has left can be enrolled somewhere else, and stays on the old record',
  { skip: !hasPhp }, () => {
  const { result } = withStore(SETUP + `
    $r = av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-0012','branch_id'=>$bid]);
    av_employer_mark_left($eid, $r['enrolment']['id'], '2026-11-30');

    $two = av_employer_create(['name' => 'Second Employer Ltd']);
    av_employer_set_status($two['employer']['id'], 'active');
    $b2 = av_employer_add_branch($two['employer']['id'], 'Lusaka', 'Lusaka');
    $moved = av_employer_enrol($two['employer']['id'],
      ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'X-1','branch_id'=>$b2['branch']['id']]);

    $old = av_employer_find($eid)['employees'][0];
    echo json_encode(['moved' => $moved['ok'], 'old_status' => $old['status'],
                      'left_at' => $old['left_at'], 'old_kept' => count(av_employer_find($eid)['employees']),
                      'now' => av_employer_for_user('u1')['employer']['name']]);`);

  assert.equal(result.moved, true);
  assert.equal(result.old_status, 'left');
  assert.equal(result.left_at, '2026-11-30');
  // The old record stays: there may be a balance to settle from the final pay.
  assert.equal(result.old_kept, 1);
  assert.equal(result.now, 'Second Employer Ltd');
});

test('an employee cannot be enrolled against another employer\'s branch',
  { skip: !hasPhp }, () => {
  const { result } = withStore(SETUP + `
    $two = av_employer_create(['name' => 'Second Employer Ltd']);
    $b2 = av_employer_add_branch($two['employer']['id'], 'Lusaka', 'Lusaka');
    $r = av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-1',
                                  'branch_id'=>$b2['branch']['id']]);
    echo json_encode(['ok' => $r['ok'], 'error' => $r['error'] ?? null]);`);
  assert.equal(result.ok, false);
  assert.match(result.error, /branch/i);
});

test('a branch with staff on it cannot be removed', { skip: !hasPhp }, () => {
  const { result } = withStore(SETUP + `
    av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-1','branch_id'=>$bid]);
    $blocked = av_employer_remove_branch($eid, $bid);

    $empty = av_employer_add_branch($eid, 'Kitwe', 'Kitwe');
    $gone  = av_employer_remove_branch($eid, $empty['branch']['id']);
    echo json_encode(['blocked' => $blocked['ok'], 'error' => $blocked['error'] ?? null,
                      'gone' => $gone['ok'], 'left' => count(av_employer_find($eid)['branches'])]);`);

  // Orphaning staff would leave deductions with nowhere to be collected.
  assert.equal(result.blocked, false);
  assert.match(result.error, /still/i);
  assert.equal(result.gone, true);
  assert.equal(result.left, 1);
});

test('only an active employer can be chosen on an application', { skip: !hasPhp }, () => {
  const { result } = withStore(`
    $pending = av_employer_create(['name' => 'Not Signed Yet Ltd']);
    av_employer_add_branch($pending['employer']['id'], 'Ndola', 'Ndola');

    $live = av_employer_create(['name' => 'Avanto Group Limited']);
    av_employer_add_branch($live['employer']['id'], 'Ndola Head Office', 'Ndola');
    av_employer_set_status($live['employer']['id'], 'active');

    $suspended = av_employer_create(['name' => 'Stopped Paying Ltd']);
    av_employer_set_status($suspended['employer']['id'], 'suspended');

    echo json_encode(av_employers_choosable());`);

  assert.equal(result.length, 1);
  assert.equal(result[0].name, 'Avanto Group Limited');
  assert.equal(result[0].branches.length, 1);
});

test('the list the form is given carries no staff and no contact details',
  { skip: !hasPhp }, () => {
  const { result } = withStore(SETUP + `
    av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda Mulenga',
                             'payroll_no'=>'AV-0012','branch_id'=>$bid]);
    echo json_encode(av_employers_choosable());`);

  const asText = JSON.stringify(result);
  // The picker needs a name and its branches. Everything else about an
  // employer — who works there, on what payroll number, who to ring — is
  // nobody else's business, and this list goes to every applicant.
  assert.deepEqual(Object.keys(result[0]).sort(), ['branches', 'id', 'name']);
  assert.deepEqual(Object.keys(result[0].branches[0]).sort(), ['id', 'name', 'town']);
  for (const leak of ['Chanda', 'AV-0012', 'employees', '0970000000', 'Gray Muyengu',
                      'contact_phone', 'registration_no']) {
    assert.ok(!asText.includes(leak), `the employer picker must not carry ${leak}`);
  }
});

test('the register survives being written and read back', { skip: !hasPhp }, () => {
  const { result } = withStore(SETUP + `
    av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-1','branch_id'=>$bid]);
    // A second process reading the same file sees the same thing.
    av_employers_reset_cache();
    $again = av_employer_find($eid);
    echo json_encode(['name' => $again['name'], 'branches' => count($again['branches']),
                      'staff' => count($again['employees']),
                      'guarded' => str_starts_with(file_get_contents(av_employers_store()), '<?php')]);`);

  assert.equal(result.name, 'Avanto Group Limited');
  assert.equal(result.branches, 1);
  assert.equal(result.staff, 1);
  // Written through the same guarded store as every other data file, so
  // fetching it over the web returns 404 rather than the staff register.
  assert.equal(result.guarded, true);
});

test('the new store is backed up, denied by the web server and kept out of Git',
  { skip: !hasPhp }, () => {
  /* The rule this project already has: anything that stores personal data goes
   * on all three lists at once. A staff register that is served to the web, or
   * committed to a public repository, or silently left out of the backup, is
   * the same class of mistake in three different directions. */
  const allow = execFileSync(php, ['-r',
    'require "backup-lib.php"; echo json_encode(array_keys(av_backup_allowlist()));'],
    { encoding: 'utf8' });
  assert.ok(JSON.parse(allow).includes('employers.php'),
    'employers.php is not in av_backup_allowlist()');

  const htaccess = readFileSync('.htaccess', 'utf8');
  assert.match(htaccess, /\^\([^)]*\bemployers\b[^)]*\)\\\.\(json\|jsonl\)\$/,
    '.htaccess would serve employers.json to anyone who asked');

  const ignored = readFileSync('.gitignore', 'utf8');
  assert.match(ignored, /^\/employers\.(php|json)$/m, 'employers store is not gitignored');
  assert.ok(!existsSync('employers.php') || false,
    'a real employer register must never sit in the working tree');
});
