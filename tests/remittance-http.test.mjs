import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, cpSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

/* Recording a remittance over real HTTP.
 *
 * This is the endpoint that moves money in the loan book, so it is admin work
 * and the refusals are tested from the outside: a breakdown that does not add
 * up must come back as a refusal with the difference named, not as a quietly
 * unbalanced ledger.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

test('staff may read the schedule; only an admin may record what arrived',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-remit-http-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'remittance-lib.php']) {
      copyFileSync(f, join(dir, f));
    }
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    cpSync('app', join(dir, 'app'), { recursive: true });
    mkdirSync(join(dir, 'sessions'));

    writeFileSync(join(dir, 'fixture.php'), `<?php
      require __DIR__.'/auth.php';
      foreach (['admin'=>'admin','staff'=>'staff','borrower'=>'borrower'] as $key => $role) {
        session_id('test-'.$key); av_session_start();
        $_SESSION = ['uid'=>$key,'username'=>$key,'name'=>'Test','role'=>$role,
                     'seen'=>time(),'started'=>time()];
        session_write_close();
      }
      require __DIR__.'/employers-lib.php';
      $e = av_employer_create(['name' => 'Avanto Group Limited']);
      $eid = $e['employer']['id'];
      $bid = av_employer_add_branch($eid, 'Ndola Head Office', 'Ndola')['branch']['id'];
      av_employer_set_status($eid, 'active');
      av_employer_enrol($eid, ['user_id'=>'borrower','name'=>'Chanda Mulenga',
                               'payroll_no'=>'AV-0012','branch_id'=>$bid]);
      // One live loan, K1,200 over two bi-weekly instalments in November.
      avStoreWrite(__DIR__.'/records_data.json', [[
        '_key'=>'a1','_owner'=>'borrower','full_name'=>'Chanda Mulenga','status'=>'Active',
        'total_repayment'=>'1200.00','installments'=>'2','first_payment'=>'2026-11-07',
        'repay_plan'=>'Bi-Weekly','_payments'=>[]]]);
      file_put_contents(__DIR__.'/eid.txt', $eid);
    `);
    const config = ['-d', 'session.save_path=' + join(dir, 'sessions')];
    const fx = spawnSync(php, [...config, join(dir, 'fixture.php')], { encoding: 'utf8' });
    assert.equal(fx.status, 0, fx.stderr);
    const eid = readFileSync(join(dir, 'eid.txt'), 'utf8').trim();

    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const call = async (action, role, body, query = '') => {
      const res = await fetch(`http://127.0.0.1:${port}/api.php?action=${action}${query}`, {
        headers: { ...(role ? { Cookie: 'AVESTASESS=test-' + role } : {}),
                   ...(body ? { 'Content-Type': 'application/json' } : {}) },
        ...(body ? { method: 'POST', body: JSON.stringify(body) } : {}),
      });
      const text = await res.text();
      let json = null; try { json = JSON.parse(text); } catch {}
      return { status: res.status, json, text };
    };
    for (let i = 0; i < 40; i++) {
      try { await call('ping'); break; } catch { await new Promise(r => setTimeout(r, 50)); }
    }

    const q = `&employer_id=${eid}&period=2026-11`;

    // ── who may look, who may act ─────────────────────────────────────────
    assert.equal((await call('deductionSchedule', 'borrower', null, q)).status, 403);
    const sched = await call('deductionSchedule', 'staff', null, q);
    assert.equal(sched.status, 200, sched.text);
    assert.equal(sched.json.schedule.expected_ngwee, 120000);
    assert.equal(sched.json.schedule.lines[0].payroll_no, 'AV-0012');

    // Recording moves money in the loan book, so staff cannot.
    assert.equal((await call('remittanceRecord', 'staff',
      { employer_id: eid, period: '2026-11', received: 1200 })).status, 403);

    // ── a breakdown that does not add up ──────────────────────────────────
    const bad = await call('remittanceRecord', 'admin', {
      employer_id: eid, period: '2026-11', received: 1200,
      lines: [{ loan_key: 'a1', amount: 1150 }],
    });
    assert.equal(bad.status, 422);
    assert.match(bad.json.error, /do not add up/i);
    assert.equal(bad.json.difference_ngwee, 5000);
    // And nothing was written: the loan book is untouched by a refusal.
    assert.equal((await call('remittances', 'staff', null, q)).json.remittances.length, 0);

    // ── the exact amount, allocated and posted ────────────────────────────
    const good = await call('remittanceRecord', 'admin',
      { employer_id: eid, period: '2026-11', received: 1200 });
    assert.equal(good.status, 200, good.text);
    assert.equal(good.json.remittance.status, 'allocated');
    assert.equal(good.json.posted, 1);

    // It reached the loan book, in the loan book's own shape.
    const book = await call('getRecords', 'admin');
    const loan = book.json.records.find(r => r._key === 'a1');
    assert.equal(loan._payments.length, 1);
    assert.equal(loan._payments[0].amount, 1200);
    assert.equal(loan._payments[0].method, 'Payroll deduction');

    // ── the reconciliation still shows the ask, not what is left ──────────
    const rec = await call('remittanceReconcile', 'staff', null, q);
    assert.equal(rec.json.reconciliation.expected_ngwee, 120000);
    assert.equal(rec.json.reconciliation.received_ngwee, 120000);
    assert.equal(rec.json.reconciliation.shortfall_ngwee, 0);
    assert.equal(rec.json.reconciliation.lines.length, 1);

    // ── it is written down ────────────────────────────────────────────────
    const audit = readFileSync(join(dir, 'audit_log.jsonl'), 'utf8')
      .trim().split('\n').map(JSON.parse);
    const entry = audit.find(e => e.action === 'remittance.recorded');
    assert.ok(entry, 'recording a remittance must be in the audit log');
    assert.equal(entry.detail.received, 1200);
    assert.equal(entry.detail.posted, 1);
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});

test('the payroll page is in the portal and its script compiles',
  { skip: !hasPhp, timeout: 20000 }, async () => {
  // The whole portal's inline scripts are compiled by the recovery and
  // documents tests; this checks the page itself is wired in and that
  // recording is kept to admins in the markup as well as on the server.
  const admin = readFileSync('app/admin.php', 'utf8');
  assert.ok(admin.includes('id="page-payroll"'));
  assert.ok(admin.includes("showPage('payroll')"));
  assert.match(admin, /id="pay-record-card" hidden/);
  assert.ok(admin.includes("if (isAdmin) $('pay-record-card').hidden = false;"));
  // The schedule a clerk emails to payroll has to be copyable as plain text.
  assert.ok(admin.includes('payroll deduction schedule'));
});

test('splitting a held payment is admin work, and must balance',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-split-http-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'remittance-lib.php']) copyFileSync(f, join(dir, f));
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
      require __DIR__.'/employers-lib.php';
      $e = av_employer_create(['name' => 'Avanto Group Limited']);
      $eid = $e['employer']['id'];
      $bid = av_employer_add_branch($eid, 'Ndola', 'Ndola')['branch']['id'];
      av_employer_set_status($eid, 'active');
      av_employer_enrol($eid, ['user_id'=>'u1','name'=>'Chanda','payroll_no'=>'AV-0012','branch_id'=>$bid]);
      av_employer_enrol($eid, ['user_id'=>'u2','name'=>'Mwila','payroll_no'=>'AV-0031','branch_id'=>$bid]);
      avStoreWrite(__DIR__.'/records_data.json', [
        ['_key'=>'a1','_owner'=>'u1','full_name'=>'Chanda','status'=>'Active',
         'total_repayment'=>'1200.00','installments'=>'1','first_payment'=>'2026-11-07',
         'repay_plan'=>'Lump Sum','_payments'=>[]],
        ['_key'=>'a2','_owner'=>'u2','full_name'=>'Mwila','status'=>'Active',
         'total_repayment'=>'600.00','installments'=>'1','first_payment'=>'2026-11-25',
         'repay_plan'=>'Lump Sum','_payments'=>[]]]);
      file_put_contents(__DIR__.'/eid.txt', $eid);
    `);
    const config = ['-d', 'session.save_path=' + join(dir, 'sessions')];
    assert.equal(spawnSync(php, [...config, join(dir, 'fixture.php')], { encoding: 'utf8' }).status, 0);
    const eid = readFileSync(join(dir, 'eid.txt'), 'utf8').trim();

    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const call = async (action, role, body, query = '') => {
      const res = await fetch(`http://127.0.0.1:${port}/api.php?action=${action}${query}`, {
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

    // K1,500 against K1,800 asked for: held, nobody credited.
    const held = await call('remittanceRecord', 'admin',
      { employer_id: eid, period: '2026-11', received: 1500 });
    assert.equal(held.json.remittance.status, 'needs_allocation');
    assert.equal(held.json.posted, 0);
    const rid = held.json.remittance.id;

    const split = [{ loan_key: 'a1', amount: 1200 }, { loan_key: 'a2', amount: 300 }];

    // Staff may see the shortfall but not decide who it belongs to.
    assert.equal((await call('remittanceAllocate', 'staff',
      { remittance_id: rid, lines: split })).status, 403);

    // A split that does not balance is refused, with the difference named.
    const off = await call('remittanceAllocate', 'admin',
      { remittance_id: rid, lines: [{ loan_key: 'a1', amount: 1200 }] });
    assert.equal(off.status, 422);
    assert.equal(off.json.difference_ngwee, 30000);

    const ok = await call('remittanceAllocate', 'admin', { remittance_id: rid, lines: split });
    assert.equal(ok.status, 200, ok.text);
    assert.equal(ok.json.posted, 2);

    const book = await call('getRecords', 'admin');
    assert.equal(book.json.records.find(r => r._key === 'a1')._payments[0].amount, 1200);
    assert.equal(book.json.records.find(r => r._key === 'a2')._payments[0].amount, 300);

    // Twice would credit money that never arrived.
    assert.equal((await call('remittanceAllocate', 'admin',
      { remittance_id: rid, lines: split })).status, 422);

    const rec = await call('remittanceReconcile', 'staff', null,
      `&employer_id=${eid}&period=2026-11`);
    const mwila = rec.json.reconciliation.lines.find(l => l.payroll_no === 'AV-0031');
    assert.equal(mwila.received_ngwee, 30000);
    assert.equal(mwila.shortfall_ngwee, 30000);

    const audit = readFileSync(join(dir, 'audit_log.jsonl'), 'utf8')
      .trim().split('\n').map(JSON.parse);
    assert.ok(audit.some(e => e.action === 'remittance.allocated'));
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});
