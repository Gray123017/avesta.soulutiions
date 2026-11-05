import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* What a company sees about its own staff.
 *
 * An employer deducting at payroll needs to know who is enrolled, what comes
 * off which salary this month, and what is still owed. It does not need, and
 * must never be shown, the NRC scan, the bank statement, the home address or
 * anything else the employee gave Avesta in confidence. The employee did not
 * hand their documents to their boss; they handed them to a lender.
 *
 * So the employer view is a narrow projection built on purpose, not a loan
 * record with a few fields hidden. The test that matters is the one that
 * reads the whole response back looking for things that should not be in it.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

function lib(body) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-eportal-'));
  try {
    const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "employers-lib.php"; require "remittance-lib.php";
       av_employers_path(${JSON.stringify(join(dir, 'employers.json'))});
       av_remittances_path(${JSON.stringify(join(dir, 'remittances.json'))});
       ${body}`], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
    try { return JSON.parse(out); }
    catch { throw new Error('employers-lib.php did not return JSON:\n' + out.slice(0, 1500)); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

/* A loan record with every confidential thing on it that a real one carries. */
const SETUP = `
  $e = av_employer_create(['name'=>'Avanto Group Limited','contact_phone'=>'0970000000']);
  $eid = $e['employer']['id'];
  $bid = av_employer_add_branch($eid,'Ndola Head Office','Ndola')['branch']['id'];
  av_employer_set_status($eid,'active');
  av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda Mulenga','payroll_no'=>'AV-0012','branch_id'=>$bid]);
  av_employer_enrol($eid, ['user_id'=>'u2','name'=>'Mwila Banda','payroll_no'=>'AV-0031','branch_id'=>$bid]);

  $other = av_employer_create(['name'=>'Somebody Else Ltd']);
  $oid = $other['employer']['id'];
  $obid = av_employer_add_branch($oid,'Lusaka','Lusaka')['branch']['id'];
  av_employer_set_status($oid,'active');
  av_employer_enrol($oid, ['user_id'=>'u9','name'=>'Peter Zulu','payroll_no'=>'SE-1','branch_id'=>$obid]);

  $records = [
    ['_key'=>'a1','_owner'=>'u1','full_name'=>'Chanda Mulenga','status'=>'Disbursed',
     'disburse_date'=>'2026-10-20','loan_amt'=>1000.00,'total_repayment'=>'1200.00',
     'installments'=>'2','first_payment'=>'2026-11-07','repay_plan'=>'Bi-Weekly',
     'sel_dur'=>'4 Weeks','_payments'=>[['id'=>'p1','amount'=>200.00,'date'=>'2026-11-01',
       'method'=>'Airtel Money','reference'=>'AM-887766']],
     // None of this is the employer's business.
     'nrc_number'=>'123456/78/1','phone'=>'0966123456','email'=>'chanda@example.test',
     'address'=>'Plot 44 Kabundi Road, Ndola','doc_nrc_front_url'=>'/api.php?action=doc&key=a1',
     'doc_bank_statement_url'=>'/api.php?action=doc&key=a1','collateral_items'=>'Toyota Hilux',
     'gps_latitude'=>'-12.958870','next_of_kin'=>'Mary Mulenga'],
    ['_key'=>'a2','_owner'=>'u9','full_name'=>'Peter Zulu','status'=>'Disbursed',
     'disburse_date'=>'2026-10-20','loan_amt'=>400.00,'total_repayment'=>'500.00',
     'installments'=>'1','first_payment'=>'2026-11-10','repay_plan'=>'Lump Sum',
     'nrc_number'=>'999999/99/9','_payments'=>[]],
  ];
`;

test('a company sees its own staff, their payroll numbers and what they owe',
  { skip: !hasPhp }, () => {
  const v = lib(SETUP + `echo json_encode(av_employer_staff_view($eid, '2026-11', $records));`);

  assert.equal(v.employer.name, 'Avanto Group Limited');
  assert.equal(v.staff.length, 2);

  const chanda = v.staff.find(s => s.payroll_no === 'AV-0012');
  assert.equal(chanda.name, 'Chanda Mulenga');
  assert.equal(chanda.branch, 'Ndola Head Office');
  assert.equal(chanda.loans.length, 1);

  const loan = chanda.loans[0];
  assert.equal(loan.total_repayable_ngwee, 120000);
  assert.equal(loan.paid_ngwee, 20000);
  assert.equal(loan.outstanding_ngwee, 100000);
  assert.equal(loan.due_this_month_ngwee, 100000);   // capped at what is owed

  // Somebody with no loan is still listed, or the company cannot tell who it
  // has enrolled from who it is deducting for.
  const mwila = v.staff.find(s => s.payroll_no === 'AV-0031');
  assert.deepEqual(mwila.loans, []);

  assert.equal(v.totals.staff, 2);
  assert.equal(v.totals.with_loans, 1);
  assert.equal(v.totals.outstanding_ngwee, 100000);
  assert.equal(v.totals.due_this_month_ngwee, 100000);
});

test('THE TEST THAT MATTERS: nothing confidential reaches the employer',
  { skip: !hasPhp }, () => {
  const v = lib(SETUP + `echo json_encode(av_employer_staff_view($eid, '2026-11', $records));`);
  const text = JSON.stringify(v);

  // The employee gave these to a lender, not to their boss.
  for (const secret of [
    '123456/78/1',                  // NRC number
    '0966123456',                   // personal phone
    'chanda@example.test',
    'Kabundi Road',                 // home address
    'action=doc',                   // any link to an uploaded document
    'doc_nrc_front', 'doc_bank_statement',
    'Toyota Hilux',                 // what they put up as security
    '-12.958870',                   // where they were when they applied
    'Mary Mulenga',                 // next of kin
    'AM-887766',                    // how they paid, and from which account
    'Airtel Money',
  ]) {
    assert.ok(!text.includes(secret),
      `an employer must never be shown ${secret}\n` + text.slice(0, 600));
  }
});

test('a company sees nothing at all about another company\'s staff',
  { skip: !hasPhp }, () => {
  const v = lib(SETUP + `echo json_encode(av_employer_staff_view($eid, '2026-11', $records));`);
  const text = JSON.stringify(v);
  for (const theirs of ['Peter Zulu', 'SE-1', 'Somebody Else', '999999/99/9']) {
    assert.ok(!text.includes(theirs), `another employer's ${theirs} must not appear`);
  }
});

test('an unknown employer gets an empty view, not somebody else\'s',
  { skip: !hasPhp }, () => {
  const v = lib(SETUP + `echo json_encode(av_employer_staff_view('emp_nothing', '2026-11', $records));`);
  assert.equal(v.employer, null);
  assert.deepEqual(v.staff, []);
  assert.equal(v.totals.outstanding_ngwee, 0);
});

test('a loan that has not been paid out is not shown as collectable',
  { skip: !hasPhp }, () => {
  const v = lib(SETUP + `
    $records[0]['status'] = 'Approved';
    unset($records[0]['disburse_date']);
    echo json_encode(av_employer_staff_view($eid, '2026-11', $records));`);
  // Same rule as the payroll schedule: nothing is deducted for money that
  // never went out, so the employer is never asked about it.
  assert.deepEqual(v.staff.find(s => s.payroll_no === 'AV-0012').loans, []);
  assert.equal(v.totals.due_this_month_ngwee, 0);
});

test('somebody who has left is shown as having left, with the balance',
  { skip: !hasPhp }, () => {
  const v = lib(SETUP + `
    $emp = av_employer_find($eid);
    av_employer_mark_left($eid, $emp['employees'][0]['id'], '2026-11-15');
    echo json_encode(av_employer_staff_view($eid, '2026-11', $records));`);

  const chanda = v.staff.find(s => s.payroll_no === 'AV-0012');
  // The balance comes out of the final pay, so the company has to still see it.
  assert.equal(chanda.left, true);
  assert.equal(chanda.left_at, '2026-11-15');
  assert.equal(chanda.loans[0].outstanding_ngwee, 100000);
});

test('an overdue loan is flagged to the employer, without the penalty arithmetic',
  { skip: !hasPhp }, () => {
  const v = lib(SETUP + `
    $records[0]['disburse_date'] = date('Y-m-d', strtotime('-60 days'));
    echo json_encode(av_employer_staff_view($eid, '2026-11', $records));`);
  const loan = v.staff.find(s => s.payroll_no === 'AV-0012').loans[0];
  // A four-week loan disbursed sixty days ago is past its date. The employer
  // needs to know to chase the deduction; what Avesta charges for lateness is
  // between Avesta and the borrower.
  assert.equal(loan.overdue, true);
  assert.ok(loan.due_date);
  assert.equal(loan.penalty_ngwee ?? null, null);
});

// ── over HTTP ───────────────────────────────────────────────────────────────

import { mkdirSync, copyFileSync, cpSync, writeFileSync, readFileSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { createServer } from 'node:net';

test('a company login reaches its own payroll and nothing else',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-eportal-http-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'portal.php', 'apply.php', 'employer.php',
                     'login.php', 'logout.php', 'rates-lib.php', 'categories-lib.php',
                     'backup-lib.php', 'employers-lib.php', 'remittance-lib.php',
                     'disbursement-lib.php', 'recovery-lib.php']) {
      copyFileSync(f, join(dir, f));
    }
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    cpSync('app', join(dir, 'app'), { recursive: true });
    mkdirSync(join(dir, 'sessions'));

    writeFileSync(join(dir, 'fixture.php'), `<?php
      require __DIR__.'/auth.php'; require __DIR__.'/employers-lib.php';

      $e = av_employer_create(['name'=>'Avanto Group Limited']);
      $eid = $e['employer']['id'];
      $bid = av_employer_add_branch($eid,'Ndola','Ndola')['branch']['id'];
      av_employer_set_status($eid,'active');
      av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda Mulenga',
                               'payroll_no'=>'AV-0012','branch_id'=>$bid]);

      $o = av_employer_create(['name'=>'Somebody Else Ltd']);
      $oid = $o['employer']['id'];
      $obid = av_employer_add_branch($oid,'Lusaka','Lusaka')['branch']['id'];
      av_employer_set_status($oid,'active');
      av_employer_enrol($oid, ['user_id'=>'u9','name'=>'Peter Zulu',
                               'payroll_no'=>'SE-1','branch_id'=>$obid]);

      av_create_user('avantohr','Payroll-phrase-91','Avanto HR','employer','',$eid);
      av_create_user('adminone','Admin-phrase-91','Admin','admin');

      avStoreWrite(__DIR__.'/records_data.json', [
        ['_key'=>'a1','_owner'=>'u1','full_name'=>'Chanda Mulenga','status'=>'Disbursed',
         'disburse_date'=>'2026-10-20','loan_amt'=>1000.00,'total_repayment'=>'1200.00',
         'installments'=>'1','first_payment'=>'2026-11-07','repay_plan'=>'Lump Sum',
         'sel_dur'=>'4 Weeks','nrc_number'=>'123456/78/1','phone'=>'0966123456',
         'address'=>'Plot 44 Kabundi Road','doc_nrc_front_url'=>'/api.php?action=doc&key=a1',
         '_payments'=>[]],
        ['_key'=>'a2','_owner'=>'u9','full_name'=>'Peter Zulu','status'=>'Disbursed',
         'disburse_date'=>'2026-10-20','loan_amt'=>400.00,'total_repayment'=>'500.00',
         'installments'=>'1','first_payment'=>'2026-11-10','repay_plan'=>'Lump Sum',
         'sel_dur'=>'2 Weeks','_payments'=>[]]]);
    `);
    const config = ['-d', 'session.save_path=' + join(dir, 'sessions')];
    const fx = spawnSync(php, [...config, join(dir, 'fixture.php')], { encoding: 'utf8' });
    assert.equal(fx.status, 0, fx.stderr);

    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    const base = 'http://127.0.0.1:' + port;
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const jar = { cookie: '' };
    async function req(path, data, client = jar) {
      const res = await fetch(base + path, { redirect: 'manual',
        headers: { ...(client.cookie ? { Cookie: client.cookie } : {}),
                   ...(data ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}) },
        ...(data ? { method: 'POST', body: new URLSearchParams(data) } : {}) });
      for (const c of res.headers.getSetCookie()) {
        if (c.startsWith('AVESTASESS=')) client.cookie = c.split(';')[0];
      }
      return res;
    }
    for (let i = 0; i < 40; i++) {
      try { await req('/login.php'); break; } catch { await new Promise(r => setTimeout(r, 50)); }
    }

    // Signing in as the company lands on its own page, not the staff portal.
    let res = await req('/login.php', { do: 'in', username: 'avantohr', password: 'Payroll-phrase-91' });
    assert.equal(res.status, 302);
    assert.equal(res.headers.get('location'), 'employer.php');

    const page = await (await req('/employer.php')).text();
    assert.ok(!/Fatal error|Parse error|Warning:/.test(page), page.slice(0, 400));
    assert.ok(page.includes('employerPortal'));

    const view = await (await req('/api.php?action=employerPortal&period=2026-11')).json();
    assert.equal(view.ok, true);
    assert.equal(view.view.employer.name, 'Avanto Group Limited');
    assert.equal(view.view.staff.length, 1);
    assert.equal(view.view.staff[0].payroll_no, 'AV-0012');

    // Nothing confidential, and nothing about the other company, over the wire.
    const wire = JSON.stringify(view);
    for (const secret of ['123456/78/1', '0966123456', 'Kabundi Road', 'action=doc',
                          'Peter Zulu', 'SE-1', 'Somebody Else']) {
      assert.ok(!wire.includes(secret), `the employer response must not carry ${secret}`);
    }

    // The staff portal, the register, the loan book and the documents are shut.
    for (const action of ['employers', 'getRecords', 'accounts', 'deductionSchedule',
                          'disbursementQueue', 'backupStatus']) {
      const r = await req('/api.php?action=' + action);
      assert.equal(r.status, 403, `${action} should be closed to an employer, got ${r.status}`);
    }
    assert.equal((await req('/api.php?action=doc&key=a1&field=doc_nrc_front')).status, 403);
    assert.equal((await req('/portal.php')).status, 403);

    // And an admin cannot wander into the company's page by accident.
    const admin = { cookie: '' };
    await req('/login.php', { do: 'in', username: 'adminone', password: 'Admin-phrase-91' }, admin);
    assert.equal((await req('/employer.php', null, admin)).status, 403);

    const audit = readFileSync(join(dir, 'audit_log.jsonl'), 'utf8')
      .trim().split('\n').map(JSON.parse);
    assert.ok(audit.some(e => e.action === 'employer.portal_viewed'));
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});
