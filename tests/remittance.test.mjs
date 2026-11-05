import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* Payroll deduction and remittance.
 *
 * An employer deducts repayments from its staff's salaries and sends Avesta
 * one payment. Two numbers have to agree: what should have been deducted, and
 * what actually arrived. Where they do not, somebody's loan is short and it
 * has to be obvious which.
 *
 * Everything here is in ngwee — whole hundredths of a kwacha — for the reason
 * rates-lib.php already is: splitting money between people in floating point
 * loses a ngwee here and there, and the loss turns up as a balance that never
 * quite clears.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

function lib(body) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-remit-'));
  try {
    const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "remittance-lib.php";
       av_employers_path(${JSON.stringify(join(dir, 'employers.json'))});
       av_remittances_path(${JSON.stringify(join(dir, 'remittances.json'))});
       ${body}`], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
    try { return JSON.parse(out); }
    catch { throw new Error('remittance-lib.php did not return JSON:\n' + out.slice(0, 1500)); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

// ── what is due, and when ───────────────────────────────────────────────────

test('a weekly plan puts its instalments a week apart, to the ngwee',
  { skip: !hasPhp }, () => {
  const r = lib(`
    $rec = ['total_repayment' => '1250.00', 'installments' => '3',
            'first_payment' => '2026-11-07', 'repay_plan' => 'Weekly'];
    echo json_encode(av_loan_due_dates($rec));`);

  assert.deepEqual(r.map(d => d.date), ['2026-11-07', '2026-11-14', '2026-11-21']);
  // K1,250 over three does not divide. The odd ngwee go to the earliest ones,
  // and the three still add up to exactly the total.
  assert.deepEqual(r.map(d => d.ngwee), [41667, 41667, 41666]);
  assert.equal(r.reduce((a, d) => a + d.ngwee, 0), 125000);
});

test('a monthly plan lands on the same day each month, and a lump sum is one date',
  { skip: !hasPhp }, () => {
  const monthly = lib(`
    echo json_encode(av_loan_due_dates(['total_repayment' => '900.00', 'installments' => '3',
      'first_payment' => '2026-11-30', 'repay_plan' => 'Monthly']));`);
  // 30 November, then the end of each following month rather than spilling
  // into the next one: 31 Dec and 31 Jan, never 30 Feb.
  assert.deepEqual(monthly.map(d => d.date), ['2026-11-30', '2026-12-30', '2027-01-30']);

  const lump = lib(`
    echo json_encode(av_loan_due_dates(['total_repayment' => '2600.00', 'installments' => '1',
      'first_payment' => '2026-11-28', 'repay_plan' => 'Lump Sum']));`);
  assert.deepEqual(lump, [{ date: '2026-11-28', ngwee: 260000 }]);
});

test('a record with no usable schedule expects nothing, and says so',
  { skip: !hasPhp }, () => {
  // Never invent an amount to take off somebody's salary. A record that does
  // not say when it is due is a question for staff, not a guess.
  for (const rec of [
    `['total_repayment' => '1000.00', 'installments' => '2', 'repay_plan' => 'Weekly']`,
    `['total_repayment' => '1000.00', 'first_payment' => '2026-11-07', 'installments' => '0']`,
    `['installments' => '2', 'first_payment' => '2026-11-07']`,
  ]) {
    assert.equal(lib(`echo json_encode(av_loan_due_dates(${rec}));`), null);
  }
});

// ── the schedule ────────────────────────────────────────────────────────────

const PAYROLL = `
  $e = av_employer_create(['name' => 'Avanto Group Limited']);
  $eid = $e['employer']['id'];
  $bid = av_employer_add_branch($eid, 'Ndola Head Office', 'Ndola')['branch']['id'];
  av_employer_set_status($eid, 'active');
  av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda Mulenga','payroll_no'=>'AV-0012','branch_id'=>$bid]);
  av_employer_enrol($eid, ['user_id'=>'u2','name'=>'Mwila Banda','payroll_no'=>'AV-0031','branch_id'=>$bid]);

  $records = [
    ['_key'=>'a1','_owner'=>'u1','full_name'=>'Chanda Mulenga','status'=>'Active','disburse_date'=>'2026-10-20',
     'total_repayment'=>'1200.00','installments'=>'2','first_payment'=>'2026-11-07',
     'repay_plan'=>'Bi-Weekly','_payments'=>[]],
    ['_key'=>'a2','_owner'=>'u2','full_name'=>'Mwila Banda','status'=>'Active','disburse_date'=>'2026-10-20',
     'total_repayment'=>'600.00','installments'=>'1','first_payment'=>'2026-11-25',
     'repay_plan'=>'Lump Sum','_payments'=>[]],
  ];
`;

test('the schedule is what each payroll number owes that month', { skip: !hasPhp }, () => {
  const s = lib(PAYROLL + `echo json_encode(av_deduction_schedule($eid, '2026-11', $records));`);

  assert.equal(s.period, '2026-11');
  assert.equal(s.lines.length, 2);

  const chanda = s.lines.find(l => l.payroll_no === 'AV-0012');
  // Two bi-weekly instalments, 7 and 21 November: both fall in the month.
  assert.equal(chanda.expected_ngwee, 120000);
  assert.equal(chanda.name, 'Chanda Mulenga');
  assert.equal(chanda.loan_key, 'a1');

  const mwila = s.lines.find(l => l.payroll_no === 'AV-0031');
  assert.equal(mwila.expected_ngwee, 60000);

  assert.equal(s.expected_ngwee, 180000);
});

test('an instalment in a later month is not deducted early', { skip: !hasPhp }, () => {
  const s = lib(PAYROLL + `
    $records[0]['first_payment'] = '2026-11-25';   // second one falls in December
    echo json_encode(av_deduction_schedule($eid, '2026-11', $records));`);
  const chanda = s.lines.find(l => l.payroll_no === 'AV-0012');
  assert.equal(chanda.expected_ngwee, 60000, 'only the November instalment');
});

test('a loan already paid down is never over-deducted', { skip: !hasPhp }, () => {
  const s = lib(PAYROLL + `
    $records[0]['_payments'] = [['id'=>'p1','amount'=>1150.00,'date'=>'2026-11-02']];
    echo json_encode(av_deduction_schedule($eid, '2026-11', $records));`);
  const chanda = s.lines.find(l => l.payroll_no === 'AV-0012');
  // K1,200 due this month but only K50 left owing. Take the K50.
  assert.equal(chanda.expected_ngwee, 5000);
});

test('a settled or rejected loan drops off the schedule entirely', { skip: !hasPhp }, () => {
  for (const status of ['Repaid', 'Rejected', 'Cancelled']) {
    const s = lib(PAYROLL + `
      $records[0]['status'] = ${JSON.stringify(status)};
      echo json_encode(av_deduction_schedule($eid, '2026-11', $records));`);
    assert.equal(s.lines.length, 1, status + ' should not be deducted for');
    assert.equal(s.expected_ngwee, 60000);
  }
});

test('a loan whose schedule cannot be worked out is flagged, not guessed',
  { skip: !hasPhp }, () => {
  const s = lib(PAYROLL + `
    unset($records[0]['first_payment']);
    echo json_encode(av_deduction_schedule($eid, '2026-11', $records));`);

  const chanda = s.lines.find(l => l.payroll_no === 'AV-0012');
  assert.equal(chanda.expected_ngwee, 0);
  assert.equal(chanda.needs_attention, true);
  assert.match(chanda.problem, /when it is due/i);
  // And it is not quietly folded into the total the employer is asked for.
  assert.equal(s.expected_ngwee, 60000);
  assert.equal(s.needs_attention, 1);
});

test('somebody who has left the employer is still on the schedule',
  { skip: !hasPhp }, () => {
  const s = lib(PAYROLL + `
    $emp = av_employer_find($eid);
    av_employer_mark_left($eid, $emp['employees'][0]['id'], '2026-11-15');
    echo json_encode(av_deduction_schedule($eid, '2026-11', $records));`);

  const chanda = s.lines.find(l => l.payroll_no === 'AV-0012');
  // The balance is settled out of the final pay, so it has to be asked for.
  assert.equal(chanda.left, true);
  assert.equal(chanda.left_at, '2026-11-15');
  assert.ok(chanda.expected_ngwee > 0);
  assert.match(chanda.problem, /final pay/i);
});

// ── what arrived ────────────────────────────────────────────────────────────

test('a breakdown that does not add up to the payment is refused', { skip: !hasPhp }, () => {
  const r = lib(PAYROLL + `
    $lines = [['loan_key'=>'a1','ngwee'=>120000], ['loan_key'=>'a2','ngwee'=>55000]];
    echo json_encode(av_remittance_record($eid, '2026-11', 180000, $lines, $records));`);

  // K1,750 of breakdown against a K1,800 payment. Recording it would put the
  // loan book K50 out, quietly, for good.
  assert.equal(r.ok, false);
  assert.match(r.error, /do not add up/i);
  assert.equal(r.difference_ngwee, 5000);
});

test('an exact payment with no breakdown is allocated from the schedule',
  { skip: !hasPhp }, () => {
  const r = lib(PAYROLL + `
    echo json_encode(av_remittance_record($eid, '2026-11', 180000, null, $records));`);

  // Unambiguous: the total is exactly what was asked for, so there is only one
  // way it can be split.
  assert.equal(r.ok, true);
  assert.equal(r.remittance.status, 'allocated');
  assert.equal(r.remittance.allocated_ngwee, 180000);
  assert.deepEqual(r.remittance.lines.map(l => [l.loan_key, l.ngwee]).sort(),
    [['a1', 120000], ['a2', 60000]]);
});

test('a short payment with no breakdown waits for a person', { skip: !hasPhp }, () => {
  const r = lib(PAYROLL + `
    echo json_encode(av_remittance_record($eid, '2026-11', 150000, null, $records));`);

  // K1,500 against K1,800 expected. Avesta cannot know whose deduction was
  // missed, and guessing would credit the wrong person's loan.
  assert.equal(r.ok, true);
  assert.equal(r.remittance.status, 'needs_allocation');
  assert.equal(r.remittance.allocated_ngwee, 0);
  assert.deepEqual(r.remittance.lines, []);
  assert.equal(r.remittance.shortfall_ngwee, 30000);
});

test('an allocation is posted to the loan book, in the loan book\'s own shape',
  { skip: !hasPhp }, () => {
  const r = lib(PAYROLL + `
    $out = av_remittance_record($eid, '2026-11', 180000, null, $records);
    echo json_encode(['records' => $out['records'], 'posted' => $out['posted']]);`);

  assert.equal(r.posted, 2);
  const a1 = r.records.find(x => x._key === 'a1');
  assert.equal(a1._payments.length, 1);
  // Written as the loan book already writes payments — one ledger, not two.
  assert.equal(a1._payments[0].amount, 1200);
  assert.equal(a1._payments[0].method, 'Payroll deduction');
  assert.ok(a1._payments[0].remittance_id);
  assert.match(a1._payments[0].note, /2026-11/);
});

test('splitting a payment never loses or invents a ngwee', { skip: !hasPhp }, () => {
  // Three people, a total that does not divide by three. This is the exact
  // shape of bug that leaves a loan showing one ngwee outstanding forever.
  const r = lib(`
    $expected = [['loan_key'=>'a','expected_ngwee'=>33333],
                 ['loan_key'=>'b','expected_ngwee'=>33333],
                 ['loan_key'=>'c','expected_ngwee'=>33334]];
    $split = av_remittance_allocate(100000, $expected);
    echo json_encode(['split' => $split, 'sum' => array_sum(array_column($split, 'ngwee'))]);`);

  assert.equal(r.sum, 100000);
  assert.deepEqual(r.split.map(l => l.ngwee), [33333, 33333, 33334]);
});

test('the reconciliation says who is short, by how much', { skip: !hasPhp }, () => {
  const r = lib(PAYROLL + `
    // The employer deducted Chanda in full and missed Mwila entirely.
    $lines = [['loan_key'=>'a1','ngwee'=>120000]];
    $out = av_remittance_record($eid, '2026-11', 120000, $lines, $records);
    echo json_encode(av_remittance_reconcile($eid, '2026-11', $out['records']));`);

  assert.equal(r.expected_ngwee, 180000);
  assert.equal(r.received_ngwee, 120000);
  assert.equal(r.shortfall_ngwee, 60000);

  const short = r.lines.filter(l => l.shortfall_ngwee > 0);
  assert.equal(short.length, 1);
  assert.equal(short[0].payroll_no, 'AV-0031');
  assert.equal(short[0].shortfall_ngwee, 60000);
});

test('the register is kept out of harm: remittances are their own store',
  { skip: !hasPhp }, () => {
  const r = lib(PAYROLL + `
    av_remittance_record($eid, '2026-11', 180000, null, $records);
    av_remittances_reset_cache();
    $all = av_remittances_for($eid);
    echo json_encode(['count' => count($all), 'guarded' =>
      str_starts_with(file_get_contents(av_remittances_store()), '<?php')]);`);
  assert.equal(r.count, 1);
  assert.equal(r.guarded, true);
});

test('a borrower who paid in full still appears, as paid, not vanished',
  { skip: !hasPhp }, () => {
  /* The bug this guards: once a remittance is posted, the loan book shows that
   * borrower owing nothing, so a schedule rebuilt from the records drops them
   * — and the reconciliation quietly loses the one line that should read
   * "asked for K1,200, received K1,200". */
  const r = lib(PAYROLL + `
    $out = av_remittance_record($eid, '2026-11', 180000, null, $records);
    echo json_encode(av_remittance_reconcile($eid, '2026-11', $out['records']));`);

  assert.equal(r.lines.length, 2, 'both people stay on the reconciliation');
  assert.equal(r.expected_ngwee, 180000);
  assert.equal(r.received_ngwee, 180000);
  assert.equal(r.shortfall_ngwee, 0);

  const chanda = r.lines.find(l => l.payroll_no === 'AV-0012');
  assert.equal(chanda.expected_ngwee, 120000);
  assert.equal(chanda.received_ngwee, 120000);
  assert.equal(chanda.shortfall_ngwee, 0);
});

test('the same remittance cannot be posted twice', { skip: !hasPhp }, () => {
  // Posting it again would clear a loan nobody has paid down.
  const r = lib(PAYROLL + `
    $out = av_remittance_record($eid, '2026-11', 180000, null, $records);
    [$again, $posted] = av_remittance_post($out['remittance'], $out['records']);
    $a1 = null; foreach ($again as $x) if ($x['_key'] === 'a1') $a1 = $x;
    echo json_encode(['posted_again' => $posted, 'payments' => count($a1['_payments'])]);`);
  assert.equal(r.posted_again, 0);
  assert.equal(r.payments, 1);
});

test('the remittance store is backed up, web-denied and kept out of Git',
  { skip: !hasPhp }, () => {
  const allow = execFileSync(php, ['-r',
    'require "backup-lib.php"; echo json_encode(array_keys(av_backup_allowlist()));'],
    { encoding: 'utf8' });
  assert.ok(JSON.parse(allow).includes('remittances.php'),
    'remittances.php is not in av_backup_allowlist()');

  const htaccess = readFileSync('.htaccess', 'utf8');
  assert.match(htaccess, /\^\([^)]*\bremittances\b[^)]*\)\\\.\(json\|jsonl\)\$/,
    '.htaccess would serve remittances.json to anyone who asked');

  assert.match(readFileSync('.gitignore', 'utf8'), /^\/remittances\.(php|json)$/m,
    'the remittance store is not gitignored');
});

// ── splitting a held payment ────────────────────────────────────────────────

const HELD = PAYROLL + `
  $out = av_remittance_record($eid, '2026-11', 150000, null, $records);
  $rid = $out['remittance']['id'];
  $records = $out['records'];
`;

test('a held payment is split by hand, and only if the parts match it exactly',
  { skip: !hasPhp }, () => {
  const short = lib(HELD + `
    // K1,500 arrived against K1,800 asked for. Chanda was deducted in full,
    // Mwila was missed — but these lines only come to K1,200.
    $lines = [['loan_key'=>'a1','ngwee'=>120000]];
    echo json_encode(av_remittance_allocate_held($rid, $lines, $records));`);
  assert.equal(short.ok, false);
  assert.match(short.error, /do not add up/i);
  assert.equal(short.difference_ngwee, 30000);

  const over = lib(HELD + `
    $lines = [['loan_key'=>'a1','ngwee'=>120000], ['loan_key'=>'a2','ngwee'=>60000]];
    echo json_encode(av_remittance_allocate_held($rid, $lines, $records));`);
  assert.equal(over.ok, false);
  assert.equal(over.difference_ngwee, 30000);
});

test('a split that matches is posted and the payment stops being held',
  { skip: !hasPhp }, () => {
  const r = lib(HELD + `
    // Chanda in full, Mwila short by K300 — which is the real shape of it.
    $lines = [['loan_key'=>'a1','ngwee'=>120000], ['loan_key'=>'a2','ngwee'=>30000]];
    $done = av_remittance_allocate_held($rid, $lines, $records);
    $a1 = null; $a2 = null;
    foreach ($done['records'] as $x) { if ($x['_key']==='a1') $a1 = $x; if ($x['_key']==='a2') $a2 = $x; }
    echo json_encode(['ok'=>$done['ok'], 'status'=>$done['remittance']['status'],
      'allocated'=>$done['remittance']['allocated_ngwee'], 'posted'=>$done['posted'],
      'a1'=>$a1['_payments'][0]['amount'], 'a2'=>$a2['_payments'][0]['amount'],
      'stored'=>av_remittances_for($eid,'2026-11')[0]['status']]);`);

  assert.equal(r.ok, true);
  assert.equal(r.status, 'allocated');
  assert.equal(r.allocated, 150000);
  assert.equal(r.posted, 2);
  assert.equal(r.a1, 1200);
  assert.equal(r.a2, 300);
  // The stored remittance is updated too, or it would be split again tomorrow.
  assert.equal(r.stored, 'allocated');
});

test('a payment that has already been split cannot be split again',
  { skip: !hasPhp }, () => {
  // Doing it twice would credit loans with money that never arrived.
  const r = lib(HELD + `
    $lines = [['loan_key'=>'a1','ngwee'=>120000], ['loan_key'=>'a2','ngwee'=>30000]];
    $first = av_remittance_allocate_held($rid, $lines, $records);
    $again = av_remittance_allocate_held($rid, $lines, $first['records']);
    echo json_encode(['first'=>$first['ok'], 'again'=>$again['ok'], 'error'=>$again['error'] ?? null]);`);
  assert.equal(r.first, true);
  assert.equal(r.again, false);
  assert.match(r.error, /already been split/i);
});

test('a split cannot credit a loan that is not on this employer\'s schedule',
  { skip: !hasPhp }, () => {
  const r = lib(HELD + `
    $records[] = ['_key'=>'stranger','_owner'=>'nobody','status'=>'Active','disburse_date'=>'2026-10-20',
                  'total_repayment'=>'500.00','installments'=>'1',
                  'first_payment'=>'2026-11-10','repay_plan'=>'Lump Sum','_payments'=>[]];
    $lines = [['loan_key'=>'a1','ngwee'=>120000], ['loan_key'=>'stranger','ngwee'=>30000]];
    echo json_encode(av_remittance_allocate_held($rid, $lines, $records));`);
  assert.equal(r.ok, false);
  assert.match(r.error, /not on this employer/i);
});

test('an unknown payment cannot be split', { skip: !hasPhp }, () => {
  const r = lib(PAYROLL + `
    echo json_encode(av_remittance_allocate_held('rem_nothing',
      [['loan_key'=>'a1','ngwee'=>100]], $records));`);
  assert.equal(r.ok, false);
  assert.match(r.error, /not found/i);
});
