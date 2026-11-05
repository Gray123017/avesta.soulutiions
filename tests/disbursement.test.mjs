import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* Recording that money went out.
 *
 * This does not send anybody any money. Avesta is not licensed to lend yet,
 * and wiring a mobile-money API to an unlicensed lender is the one thing in
 * this codebase that could do real harm. What this does is record a payout
 * that a person made by whatever means they actually used — the MoMo app, a
 * bank portal, cash across a counter — and refuse the ways that recording goes
 * wrong.
 *
 * It also closes a gap that was already live: a loan could be marked Disbursed
 * with no disbursement date, which leaves the rate engine unable to work out
 * when it is due, so it never goes overdue and never attracts the penalty.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

function lib(body) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-disb-'));
  try {
    const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "disbursement-lib.php";
       av_employers_path(${JSON.stringify(join(dir, 'employers.json'))});
       av_remittances_path(${JSON.stringify(join(dir, 'remittances.json'))});
       ${body}`], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
    try { return JSON.parse(out); }
    catch { throw new Error('disbursement-lib.php did not return JSON:\n' + out.slice(0, 1500)); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

const APPROVED = `
  $rec = ['_key'=>'a1','_owner'=>'u1','full_name'=>'Chanda Mulenga','status'=>'Approved',
          'loan_amt'=>2000.00,'total_repayment'=>'2600.00','sel_dur'=>'4 Weeks',
          'disburse'=>'mobile_mtn'];
`;

test('a payout is recorded, dated, and the loan becomes disbursed', { skip: !hasPhp }, () => {
  const r = lib(APPROVED + `
    echo json_encode(av_disbursement_record($rec, ['amount'=>2000.00,'method'=>'mobile_mtn',
      'reference'=>'MP261105.1423.A45678','date'=>'2026-11-05']));`);

  assert.equal(r.ok, true);
  assert.equal(r.record.status, 'Disbursed');
  assert.equal(r.record.disburse_date, '2026-11-05');
  assert.equal(r.record.disbursed_amount, 2000);
  assert.equal(r.record.disburse_method, 'mobile_mtn');
  assert.equal(r.record.disburse_reference, 'MP261105.1423.A45678');
  assert.ok(r.record.disbursed_at);
});

test('the disbursement date is what makes a loan ageable at all', { skip: !hasPhp }, () => {
  // The gap this closes: without a date the rate engine cannot work out when
  // the loan is due, so it never goes overdue and the penalty never applies.
  const r = lib(APPROVED + `
    $out = av_disbursement_record($rec, ['amount'=>2000.00,'method'=>'cash','date'=>'2026-11-05']);
    echo json_encode(['before' => isset($rec['disburse_date']),
                      'after'  => $out['record']['disburse_date']]);`);
  assert.equal(r.before, false);
  assert.equal(r.after, '2026-11-05');
});

test('a loan cannot be paid out twice', { skip: !hasPhp }, () => {
  const r = lib(APPROVED + `
    $first = av_disbursement_record($rec, ['amount'=>2000.00,'method'=>'cash','date'=>'2026-11-05']);
    $again = av_disbursement_record($first['record'],
      ['amount'=>2000.00,'method'=>'cash','date'=>'2026-11-06']);
    echo json_encode(['first'=>$first['ok'],'again'=>$again['ok'],'error'=>$again['error'] ?? null]);`);
  assert.equal(r.first, true);
  assert.equal(r.again, false);
  assert.match(r.error, /already been paid out/i);
});

test('only an approved loan can be paid out', { skip: !hasPhp }, () => {
  for (const status of ['New Application', 'Under Review', 'Rejected', 'Repaid']) {
    const r = lib(APPROVED + `
      $rec['status'] = ${JSON.stringify(status)};
      echo json_encode(av_disbursement_record($rec,
        ['amount'=>2000.00,'method'=>'cash','date'=>'2026-11-05']));`);
    assert.equal(r.ok, false, status + ' should not be payable');
    assert.match(r.error, /approved/i);
  }
});

test('an amount that is not the approved one is refused unless a reason is given',
  { skip: !hasPhp }, () => {
  const wrong = lib(APPROVED + `
    echo json_encode(av_disbursement_record($rec,
      ['amount'=>1800.00,'method'=>'cash','date'=>'2026-11-05']));`);
  // Paying out less than was agreed, with interest charged on the full sum,
  // is the kind of mistake that is only noticed by the borrower.
  assert.equal(wrong.ok, false);
  assert.match(wrong.error, /K\s?2,000\.00/);
  assert.equal(wrong.difference_ngwee, 20000);

  const deliberate = lib(APPROVED + `
    echo json_encode(av_disbursement_record($rec, ['amount'=>1800.00,'method'=>'cash',
      'date'=>'2026-11-05','override_reason'=>'K200 arrangement fee deducted, agreed in writing']));`);
  assert.equal(deliberate.ok, true);
  assert.equal(deliberate.record.disbursed_amount, 1800);
  assert.match(deliberate.record.disburse_note, /arrangement fee/);
});

test('a payout cannot be dated in the future', { skip: !hasPhp }, () => {
  const r = lib(APPROVED + `
    $tomorrow = date('Y-m-d', strtotime('+2 days'));
    echo json_encode(av_disbursement_record($rec,
      ['amount'=>2000.00,'method'=>'cash','date'=>$tomorrow]));`);
  // The due date is counted from this, so a date that has not happened yet
  // buys the borrower extra time nobody agreed to.
  assert.equal(r.ok, false);
  assert.match(r.error, /future/i);
});

test('anything but cash needs a reference it can be traced by', { skip: !hasPhp }, () => {
  const noRef = lib(APPROVED + `
    echo json_encode(av_disbursement_record($rec,
      ['amount'=>2000.00,'method'=>'mobile_mtn','date'=>'2026-11-05']));`);
  assert.equal(noRef.ok, false);
  assert.match(noRef.error, /reference/i);

  // Cash across a counter has no transaction id to give.
  const cash = lib(APPROVED + `
    echo json_encode(av_disbursement_record($rec,
      ['amount'=>2000.00,'method'=>'cash','date'=>'2026-11-05']));`);
  assert.equal(cash.ok, true);
});

test('a method has to be said', { skip: !hasPhp }, () => {
  const r = lib(APPROVED + `
    echo json_encode(av_disbursement_record($rec, ['amount'=>2000.00,'date'=>'2026-11-05']));`);
  assert.equal(r.ok, false);
  assert.match(r.error, /how the money/i);
});

// ── the queues ──────────────────────────────────────────────────────────────

const BOOK = `
  $records = [
    ['_key'=>'a1','status'=>'Approved','full_name'=>'Chanda','loan_amt'=>2000.00,'disburse'=>'cash'],
    ['_key'=>'a2','status'=>'Approved','full_name'=>'Mwila','loan_amt'=>500.00,
     'disburse_date'=>'2026-11-01'],
    ['_key'=>'a3','status'=>'Disbursed','full_name'=>'Joseph','loan_amt'=>800.00],
    ['_key'=>'a4','status'=>'Rejected','full_name'=>'Peter','loan_amt'=>900.00],
  ];
`;

test('the payout queue is approved loans that have not gone out', { skip: !hasPhp }, () => {
  const r = lib(BOOK + `echo json_encode(av_disbursement_pending($records));`);
  assert.deepEqual(r.map(x => x._key), ['a1']);
});

test('loans marked disbursed with no date are listed, because they cannot be aged',
  { skip: !hasPhp }, () => {
  /* This is the live data problem, not a hypothetical: a loan marked Disbursed
   * through the status dropdown never got a date, so the rate engine cannot
   * work out when it is due, it never goes overdue, and the 35% never applies. */
  const r = lib(BOOK + `echo json_encode(av_disbursement_undated($records));`);
  assert.deepEqual(r.map(x => x._key), ['a3']);
});

// ── the payroll bug this turned up ──────────────────────────────────────────

test('payroll is never asked to deduct for a loan that has not been paid out',
  { skip: !hasPhp }, () => {
  const r = lib(`
    $e = av_employer_create(['name'=>'Avanto Group Limited']);
    $eid = $e['employer']['id'];
    $bid = av_employer_add_branch($eid,'Ndola','Ndola')['branch']['id'];
    av_employer_set_status($eid,'active');
    av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-0012','branch_id'=>$bid]);

    $base = ['_key'=>'a1','_owner'=>'u1','total_repayment'=>'1200.00','installments'=>'1',
             'first_payment'=>'2026-11-10','repay_plan'=>'Lump Sum','_payments'=>[]];

    $approved  = av_deduction_schedule($eid, '2026-11', [$base + ['status'=>'Approved']]);
    $disbursed = av_deduction_schedule($eid, '2026-11',
      [$base + ['status'=>'Disbursed','disburse_date'=>'2026-11-01']]);

    echo json_encode(['approved' => $approved['expected_ngwee'],
                      'approved_lines' => count($approved['lines']),
                      'disbursed' => $disbursed['expected_ngwee']]);`);

  // Asking an employer to deduct from somebody's salary for money that was
  // never sent to them is the worst thing this schedule can do.
  assert.equal(r.approved, 0);
  assert.equal(r.approved_lines, 0);
  assert.equal(r.disbursed, 120000);
});

// ── over HTTP ───────────────────────────────────────────────────────────────

import { mkdirSync, copyFileSync, cpSync, writeFileSync, readFileSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { createServer } from 'node:net';

test('recording a payout is admin work, and the refusals hold over HTTP',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-disb-http-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'remittance-lib.php', 'disbursement-lib.php']) copyFileSync(f, join(dir, f));
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    cpSync('app', join(dir, 'app'), { recursive: true });
    mkdirSync(join(dir, 'sessions'));

    writeFileSync(join(dir, 'fixture.php'), `<?php
      require __DIR__.'/auth.php';
      foreach (['admin'=>'admin','staff'=>'staff'] as $key => $role) {
        session_id('test-'.$key); av_session_start();
        $_SESSION = ['uid'=>$key,'username'=>$key,'name'=>'Test','role'=>$role,
                     'seen'=>time(),'started'=>time()];
        session_write_close();
      }
      avStoreWrite(__DIR__.'/records_data.json', [
        ['_key'=>'a1','_owner'=>'u1','full_name'=>'Chanda Mulenga','status'=>'Approved',
         'loan_amt'=>2000.00,'sel_dur'=>'4 Weeks','disburse'=>'mobile_mtn'],
        ['_key'=>'a2','_owner'=>'u2','full_name'=>'Joseph Phiri','status'=>'Disbursed',
         'loan_amt'=>800.00]]);
    `);
    const config = ['-d', 'session.save_path=' + join(dir, 'sessions')];
    assert.equal(spawnSync(php, [...config, join(dir, 'fixture.php')], { encoding: 'utf8' }).status, 0);

    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const call = async (action, role, body) => {
      const res = await fetch(`http://127.0.0.1:${port}/api.php?action=${action}`, {
        headers: { Cookie: 'AVESTASESS=test-' + role,
                   ...(body ? { 'Content-Type': 'application/json' } : {}) },
        ...(body ? { method: 'POST', body: JSON.stringify(body) } : {}),
      });
      const text = await res.text();
      let json = null; try { json = JSON.parse(text); } catch {}
      return { status: res.status, json, text };
    };
    for (let i = 0; i < 40; i++) {
      try { await call('ping', 'admin'); break; } catch { await new Promise(r => setTimeout(r, 50)); }
    }

    // Staff can see what is waiting to go out, and what is already broken.
    const queue = await call('disbursementQueue', 'staff');
    assert.equal(queue.status, 200, queue.text);
    assert.deepEqual(queue.json.pending.map(r => r._key), ['a1']);
    assert.deepEqual(queue.json.undated.map(r => r._key), ['a2']);

    const payout = { _key: 'a1', amount: 2000, method: 'mobile_mtn',
                     reference: 'MP261105.1423.A45678', date: '2026-11-05' };

    // But sending it is an assertion that money left the business.
    assert.equal((await call('disburse', 'staff', payout)).status, 403);

    // A mobile transfer with no reference cannot be traced later.
    const noRef = await call('disburse', 'admin', { ...payout, reference: '' });
    assert.equal(noRef.status, 422);
    assert.match(noRef.json.error, /reference/i);

    // An amount that is not the approved one needs a reason.
    const wrong = await call('disburse', 'admin', { ...payout, amount: 1800 });
    assert.equal(wrong.status, 422);
    assert.equal(wrong.json.difference_ngwee, 20000);

    const done = await call('disburse', 'admin', payout);
    assert.equal(done.status, 200, done.text);
    assert.equal(done.json.record.status, 'Disbursed');
    assert.equal(done.json.record.disburse_date, '2026-11-05');

    // Twice is money gone with nothing to show for it.
    const twice = await call('disburse', 'admin', payout);
    assert.equal(twice.status, 422);
    assert.match(twice.json.error, /already been paid out/i);

    // It left the queue, and it is written down.
    assert.deepEqual((await call('disbursementQueue', 'staff')).json.pending, []);
    const audit = readFileSync(join(dir, 'audit_log.jsonl'), 'utf8')
      .trim().split('\n').map(JSON.parse);
    const entry = audit.find(e => e.action === 'loan.disbursed');
    assert.ok(entry);
    assert.equal(entry.detail.reference, 'MP261105.1423.A45678');
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});
