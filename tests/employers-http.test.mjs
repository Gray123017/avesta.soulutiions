import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, cpSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

/* The employer register over real HTTP.
 *
 * The register says who works where and on which payroll number. A borrower
 * must be able to read the list of employers to pick one, and must not be able
 * to read anybody's payroll number — including their own colleagues'. That line
 * is tested here from the outside, with real sessions.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

test('the register is staff work; the picker is all a borrower may see',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-employers-http-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php']) {
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
        $_SESSION = ['uid'=>$key,'username'=>$key,'name'=>'Test',
          'role'=>$role,'seen'=>time(),'started'=>time()];
        session_write_close();
      }
    `);
    const config = ['-d', 'session.save_path=' + join(dir, 'sessions')];
    const fx = spawnSync(php, [...config, join(dir, 'fixture.php')], { encoding: 'utf8' });
    assert.equal(fx.status, 0, fx.stderr);

    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const call = async (action, role, body) => {
      const res = await fetch(`http://127.0.0.1:${port}/api.php?action=${action}`, {
        headers: { ...(role ? { Cookie: 'AVESTASESS=test-' + role } : {}),
                   ...(body ? { 'Content-Type': 'application/json' } : {}) },
        ...(body ? { method: 'POST', body: JSON.stringify(body) } : {}),
      });
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch { /* keep the text for the message */ }
      return { status: res.status, json, text };
    };
    for (let i = 0; i < 40; i++) {
      try { await call('ping'); break; } catch { await new Promise(r => setTimeout(r, 50)); }
    }

    // ── only staff keep the register ──────────────────────────────────────
    assert.equal((await call('employers')).status, 401);
    assert.equal((await call('employers', 'borrower')).status, 403);
    assert.equal((await call('employers', 'staff')).status, 200);

    // Registering a company, suspending one, or editing a branch is admin work.
    for (const action of ['employerCreate', 'employerStatus', 'employerBranchAdd']) {
      const r = await call(action, 'staff', { name: 'Sneaky Ltd' });
      assert.equal(r.status, 403, `${action} should be admin-only, got ${r.status}`);
    }

    // ── the happy path ────────────────────────────────────────────────────
    let r = await call('employerCreate', 'admin',
      { name: 'Avanto Group Limited', contact_name: 'Gray Muyengu', contact_phone: '0970000000' });
    assert.equal(r.status, 200, r.text);
    const eid = r.json.employer.id;
    assert.equal(r.json.employer.status, 'pending');

    r = await call('employerBranchAdd', 'admin', { employer_id: eid, name: 'Ndola Head Office', town: 'Ndola' });
    assert.equal(r.status, 200, r.text);
    const bid = r.json.branch.id;

    // Still pending, so nothing can be borrowed against it yet.
    assert.deepEqual((await call('employersChoosable', 'borrower')).json.employers, []);

    assert.equal((await call('employerStatus', 'admin', { employer_id: eid, status: 'active' })).status, 200);

    r = await call('employerEnrol', 'staff',
      { employer_id: eid, branch_id: bid, user_id: 'borrower', name: 'Test Borrower', payroll_no: 'AV-0012' });
    assert.equal(r.status, 200, r.text);

    // The same payroll number again is refused, with a reason a person can act on.
    r = await call('employerEnrol', 'staff',
      { employer_id: eid, branch_id: bid, user_id: 'other', name: 'Someone Else', payroll_no: 'av-0012' });
    assert.equal(r.status, 409, r.text);
    assert.match(r.json.error, /payroll number/i);

    // ── what a borrower can read ──────────────────────────────────────────
    const picker = await call('employersChoosable', 'borrower');
    assert.equal(picker.status, 200);
    assert.equal(picker.json.employers.length, 1);
    assert.equal(picker.json.employers[0].name, 'Avanto Group Limited');
    assert.equal(picker.json.employers[0].branches[0].name, 'Ndola Head Office');

    for (const leak of ['AV-0012', 'payroll_no', 'employees', '0970000000', 'Gray Muyengu']) {
      assert.ok(!picker.text.includes(leak),
        `the picker a borrower sees must not carry ${leak}`);
    }

    // Their own enrolment, though, is theirs to see — the form fills itself in.
    const mine = await call('myEmployer', 'borrower');
    assert.equal(mine.status, 200);
    assert.equal(mine.json.enrolment.payroll_no, 'AV-0012');
    assert.equal(mine.json.employer.name, 'Avanto Group Limited');

    // And somebody with no enrolment is told so, rather than given an error.
    const none = await call('myEmployer', 'staff');
    assert.equal(none.status, 200);
    assert.equal(none.json.enrolment, null);

    // ── leaving ───────────────────────────────────────────────────────────
    const full = await call('employers', 'staff');
    const enrolment = full.json.employers[0].employees[0];
    r = await call('employerLeft', 'staff',
      { employer_id: eid, enrolment_id: enrolment.id, left_at: '2026-11-30' });
    assert.equal(r.status, 200, r.text);

    const after = await call('employers', 'staff');
    assert.equal(after.json.employers[0].employees.length, 1, 'a leaver is kept, never deleted');
    assert.equal(after.json.employers[0].employees[0].status, 'left');
    assert.equal((await call('myEmployer', 'borrower')).json.enrolment, null);

    // ── the portal page exists and its script compiles ────────────────────
    const portal = await (await fetch(`http://127.0.0.1:${port}/portal.php`,
      { headers: { Cookie: 'AVESTASESS=test-staff' } })).text();
    assert.ok(!/Fatal error|Parse error|Warning:/.test(portal), portal.slice(0, 400));
    assert.ok(portal.includes('id="page-employers"'));
    assert.ok(portal.includes('id="emp-refresh"'));
    // Registering a company is kept out of a staff member's way in the page as
    // well as refused by the server; the card ships hidden and only an admin
    // reveals it.
    assert.match(portal, /id="emp-new-card" hidden/);
    assert.ok(portal.includes("if (isAdmin) $('emp-new-card').hidden = false;"));

    // ── it is written down ────────────────────────────────────────────────
    const audit = readFileSync(join(dir, 'audit_log.jsonl'), 'utf8')
      .trim().split('\n').map(JSON.parse).map(e => e.action);
    for (const action of ['employer.created', 'employer.status_changed',
                          'employer.enrolled', 'employer.left']) {
      assert.ok(audit.includes(action), `${action} is missing from the audit log`);
    }
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});
