import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, cpSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

/* Recording the same payment twice.
 *
 * The portal gives up on the request after twelve seconds and tells the staff
 * member "Could not record the payment". On shared hosting, behind a lock,
 * past twelve seconds is not unusual — and the request it gave up on may have
 * finished perfectly well on the server. So the person who just took K500 in
 * cash is told it failed, and does the sensible thing, which is to do it
 * again.
 *
 * Nothing stopped the second one. Two payments, same amount, same day, and a
 * borrower credited twice for money they handed over once. The loan shows
 * settled when it is not, or overpaid when it is not.
 *
 * A double-click does the same thing, more quickly.
 *
 * So an attempt carries an id, and an attempt already carried out is answered
 * with what happened the first time rather than done again.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

async function portal(fn) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-idem-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'remittance-lib.php', 'disbursement-lib.php', 'notify-lib.php']) {
      copyFileSync(f, join(dir, f));
    }
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    cpSync('app', join(dir, 'app'), { recursive: true });
    mkdirSync(join(dir, 'sessions'));

    writeFileSync(join(dir, 'records_data.php'),
      '<?php http_response_code(404); exit; ?>\n' +
      JSON.stringify([{ _key: 'AV-1', full_name: 'Chanda Mulenga', status: 'Disbursed',
                        loan_amt: 4000, total_repay: '5200.00', int_rate: 30,
                        sel_dur: '4 Weeks (30% interest)', disburse_date: '2026-11-01',
                        terms_version: '2026-11-27', _payments: [] }]));

    writeFileSync(join(dir, 'fixture.php'), `<?php
      require __DIR__.'/auth.php';
      session_id('test-staff'); av_session_start();
      $_SESSION = ['uid'=>'s','username'=>'staffone','name'=>'Staff',
        'role'=>'staff','seen'=>time(),'started'=>time()];
      session_write_close();
    `);
    const config = ['-d', 'session.save_path=' + join(dir, 'sessions')];
    const fx = spawnSync(php, [...config, join(dir, 'fixture.php')], { encoding: 'utf8' });
    assert.equal(fx.status, 0, fx.stderr);

    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const pay = async (body) => {
      const res = await fetch(`http://127.0.0.1:${port}/api.php?action=addPayment`, {
        method: 'POST',
        headers: { Cookie: 'AVESTASESS=test-staff', 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch { /* keep the text */ }
      return { status: res.status, json, text };
    };
    for (let i = 0; i < 40; i++) {
      try { await fetch(`http://127.0.0.1:${port}/api.php?action=ping`); break; }
      catch { await new Promise(r => setTimeout(r, 50)); }
    }

    const book = () => JSON.parse(spawnSync(php, ['-r',
      `require "${join(dir, 'auth.php')}";
       $d = av_guarded_decode(file_get_contents("${join(dir, 'records_data.php')}"));
       echo json_encode($d[0]['_payments'] ?? []);`], { encoding: 'utf8' }).stdout);

    await fn({ pay, book });
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
}

test('THE ONE THAT MATTERS: the same attempt sent twice records one payment',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  await portal(async ({ pay, book }) => {
    const attempt = { key: 'AV-1', amount: 500, method: 'Cash',
                      request_id: 'req-abc-123' };

    const first = await pay(attempt);
    assert.equal(first.status, 200, first.text);
    assert.equal(first.json.ok, true);

    // The portal gave up at twelve seconds and the staff member tried again.
    const again = await pay(attempt);
    assert.equal(again.status, 200,
      `a retry must not be an error — the staff member would try a third time: ${again.text}`);
    assert.equal(again.json.ok, true);

    const paid = book();
    assert.equal(paid.length, 1,
      `one payment was taken, so one must be recorded. Book has: ${JSON.stringify(paid)}`);
    assert.equal(paid[0].amount, 500);

    // And the retry says it was already done, so nothing lies about it.
    assert.equal(again.json.already_recorded, true, again.text);
    assert.equal(again.json.payment.id, first.json.payment.id,
      'the retry has to answer with the payment that was already made');
  });
});

test('a borrower paying the same amount twice gets two payments',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  // Two genuine instalments of K500 on the same day is an ordinary thing, so
  // matching on amount and date would refuse real money. The id is what tells
  // a retry from a second payment.
  await portal(async ({ pay, book }) => {
    await pay({ key: 'AV-1', amount: 500, method: 'Cash', request_id: 'req-one' });
    await pay({ key: 'AV-1', amount: 500, method: 'Cash', request_id: 'req-two' });

    const paid = book();
    assert.equal(paid.length, 2, JSON.stringify(paid));
    assert.equal(paid[0].amount + paid[1].amount, 1000);
  });
});

test('the id is kept on the payment, so a later retry still matches',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  await portal(async ({ pay, book }) => {
    await pay({ key: 'AV-1', amount: 250, method: 'Cash', request_id: 'req-keep-me' });
    const paid = book();
    assert.equal(paid[0].request_id, 'req-keep-me',
      `the id has to be stored or the protection lasts one request: ${JSON.stringify(paid[0])}`);
  });
});

test('a payment sent with no id is still recorded', { skip: !hasPhp, timeout: 30000 }, async () => {
  // Nothing else posts to this endpoint today, but refusing a request for
  // want of an id would break anything that did, and a recorded payment
  // matters more than the protection.
  await portal(async ({ pay, book }) => {
    const r = await pay({ key: 'AV-1', amount: 100, method: 'Cash' });
    assert.equal(r.json.ok, true, r.text);
    assert.equal(book().length, 1);
  });
});

test('an id already used on another loan does not block this one',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  // The id is scoped to the loan. A reused id across loans would silently
  // swallow a real payment on the second one.
  await portal(async ({ pay, book }) => {
    await pay({ key: 'AV-1', amount: 300, method: 'Cash', request_id: 'shared' });
    const r = await pay({ key: 'AV-NOPE', amount: 300, method: 'Cash', request_id: 'shared' });
    assert.notEqual(r.json.ok, true, 'that loan does not exist, so this is still an error');
    assert.equal(book().length, 1);
  });
});

test('the portal sends an id and retries once with the same one', () => {
  /* The server half is useless on its own: the staff member is told it failed
   * and clicks again, which is a fresh attempt with a fresh id unless the
   * client reuses it. So the client retries the timeout itself, with the id it
   * already sent, and only reports a failure after that. */
  const src = readFileSync('app/admin.php', 'utf8');
  const fn = src.slice(src.indexOf('window.recordPayment = async function'),
                       src.indexOf('// Load once the dashboard is on screen'));

  assert.match(fn, /request_id/, 'the payment request must carry an id');
  assert.match(fn, /avRequestId|crypto\.randomUUID|Math\.random/,
    'and the id has to come from somewhere');
  assert.match(fn, /for \(|while \(|attempt|retry/i,
    'the timeout has to be retried with the same id, not reported as a failure');
  // The id is made once, outside whatever retries, or a retry is a new attempt.
  const madeAt = fn.search(/request_id\s*:/);
  const idMadeAt = fn.search(/var\s+requestId|const\s+requestId/);
  assert.ok(idMadeAt >= 0 && idMadeAt < madeAt,
    'the id must be made before the request that sends it, once per attempt');
});
