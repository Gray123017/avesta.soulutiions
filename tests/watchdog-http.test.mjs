import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, cpSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

/* The watchdog over real HTTP.
 *
 * Two things are being checked from the outside, because reading the code and
 * agreeing with it is not the same as knowing:
 *
 *   The digest is admin work. It names the account that opened identity
 *   documents at two in the morning, so the person it is about is the last one
 *   who should be able to read it.
 *
 *   watchdog-cron.php must not run over the web. The repository root is the
 *   document root, so every .php file in it is reachable by anybody who asks.
 *   Nothing secret is in that file, but a stranger being able to make the
 *   server send mail empties a mail quota, and the digest is not theirs.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

test('the digest is for admins, and the cron script is not on the web',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-watchdog-http-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'asset-version.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'remittance-lib.php', 'disbursement-lib.php', 'notify-lib.php',
                     'watchdog-lib.php', 'watchdog-cron.php']) {
      copyFileSync(f, join(dir, f));
    }
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    cpSync('app', join(dir, 'app'), { recursive: true });
    mkdirSync(join(dir, 'sessions'));

    // One loan long past its date, so the digest has something real to find.
    writeFileSync(join(dir, 'records_data.php'),
      '<?php http_response_code(404); exit; ?>\n' +
      JSON.stringify([{ _key: 'AV-2026-0041', full_name: 'Chanda Mulenga',
                        nrc_number: '123456/78/1', status: 'Disbursed',
                        disburse_date: '2026-01-05', loan_amt: 2000,
                        total_repay: '2600.00', sel_dur: '4 Weeks', int_rate: 30,
                        _payments: [] }]));

    writeFileSync(join(dir, 'fixture.php'), `<?php
      require __DIR__.'/auth.php';
      foreach (['superadmin'=>'super_admin','admin'=>'admin',
                'staff'=>'staff','borrower'=>'borrower'] as $key => $role) {
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
    const base = 'http://127.0.0.1:' + port;
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const get = async (path, role) => {
      const res = await fetch(base + path, {
        headers: role ? { Cookie: 'AVESTASESS=test-' + role } : {},
      });
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch { /* keep the text for the message */ }
      return { status: res.status, json, text };
    };
    for (let i = 0; i < 40; i++) {
      try { await get('/api.php?action=ping'); break; }
      catch { await new Promise(r => setTimeout(r, 50)); }
    }

    // ── who may read it ──────────────────────────────────────────────────
    assert.equal((await get('/api.php?action=watchdogDigest')).status, 401);
    assert.equal((await get('/api.php?action=watchdogDigest', 'borrower')).status, 403);
    assert.equal((await get('/api.php?action=watchdogDigest', 'staff')).status, 403,
      'a staff member must not read a digest that reports on staff');
    assert.equal((await get('/api.php?action=watchdogDigest', 'admin')).status, 200);
    assert.equal((await get('/api.php?action=watchdogDigest', 'superadmin')).status, 200);

    // ── what it says ─────────────────────────────────────────────────────
    const digest = await get('/api.php?action=watchdogDigest', 'admin');
    const overdue = digest.json.findings.find(f => f.kind === 'overdue_loans');
    assert.ok(overdue, `expected the overdue loan to be found: ${digest.text.slice(0, 400)}`);
    assert.match(overdue.headline, /1 loan overdue/);

    // The message is the thing that would be sent, so the privacy rule has to
    // hold here too, over the wire, with a real record behind it.
    const message = digest.json.message.subject + '\n' + digest.json.message.text;
    for (const secret of ['Chanda Mulenga', '123456/78/1']) {
      assert.ok(!message.includes(secret),
        `the digest must not carry ${secret}\n${message}`);
    }

    // ── reading it changes nothing ───────────────────────────────────────
    // Opening the page must not count as having been told, or a problem could
    // be silenced by looking at it and never reach the digest.
    assert.equal(digest.json.last_run, '',
      'the preview must not record a run');
    const again = await get('/api.php?action=watchdogDigest', 'admin');
    assert.equal(again.json.findings.length, digest.json.findings.length,
      'the same problem has to still be there the second time it is looked at');

    // ── the cron script is not a web page ────────────────────────────────
    const cron = await get('/watchdog-cron.php');
    assert.equal(cron.status, 404,
      'watchdog-cron.php must refuse to run over the web');
    assert.equal(cron.text.trim(), '',
      'and it must not say anything while refusing');
    assert.equal((await get('/watchdog-cron.php', 'superadmin')).status, 404,
      'not even for a super admin — the way to run it is the scheduled job');

    // ── the portal page is there, and only for admins ────────────────────
    const portal = await (await fetch(base + '/portal.php',
      { headers: { Cookie: 'AVESTASESS=test-admin' } })).text();
    assert.ok(!/Fatal error|Parse error|Warning:/.test(portal), portal.slice(0, 400));
    assert.ok(portal.includes('id="page-watchdog"'));
    assert.ok(portal.includes('id="wd-refresh"'));
    assert.ok(portal.includes('action=watchdogDigest'));
    // The markup ships to everybody and the script takes it out again, the
    // same way the backup card does.
    assert.match(portal, /id="nav-watchdog" hidden/);
    assert.match(portal, /role !== 'admin' && role !== 'super_admin'[\s\S]{0,400}removeChild/,
      'the page must be removed from the document for anybody below admin');
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});

test('the cron script runs from the command line and says what it did',
  { skip: !hasPhp, timeout: 30000 }, () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-watchdog-cli-'));
  try {
    for (const f of ['api.php', 'auth.php', 'asset-version.php', 'rates-lib.php', 'categories-lib.php',
                     'backup-lib.php', 'employers-lib.php', 'remittance-lib.php',
                     'disbursement-lib.php', 'notify-lib.php', 'watchdog-lib.php',
                     'watchdog-cron.php']) {
      copyFileSync(f, join(dir, f));
    }
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    mkdirSync(join(dir, 'app'));
    writeFileSync(join(dir, 'records_data.php'),
      '<?php http_response_code(404); exit; ?>\n' +
      JSON.stringify([{ _key: 'AV-1', status: 'Disbursed', disburse_date: '',
                        loan_amt: 900, total_repay: '1080.00', sel_dur: '2 Weeks',
                        _payments: [] }]));

    const run = spawnSync(php, [join(dir, 'watchdog-cron.php')],
      { encoding: 'utf8', cwd: dir });

    assert.equal(run.status, 0,
      `a quiet night is not a failure, or cron emails somebody every day: ${run.stderr}`);
    assert.match(run.stdout, /watchdog: \d+ finding/, run.stdout + run.stderr);
    // The undated disbursement is the finding it should have made.
    assert.match(run.stdout, /\[high\] 1 loan marked paid out with no date/,
      run.stdout + run.stderr);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});
