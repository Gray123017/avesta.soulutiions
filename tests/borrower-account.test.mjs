import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, cpSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

/* A borrower's own account.
 *
 * Two things have to be true. They see their own loans and absolutely nobody
 * else's — that is the obvious one. And they see the full working on what they
 * owe, including the late charge and what it did: the price of credit was
 * published before they borrowed, so the arithmetic afterwards should not be
 * something they have to ring up and ask about.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

test('a borrower sees their own loans, with the working, and nobody else\'s',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-account-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'portal.php', 'apply.php', 'account.php',
                     'employer.php', 'login.php', 'logout.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'remittance-lib.php', 'disbursement-lib.php', 'recovery-lib.php']) {
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
      $a = av_create_user('chanda','Borrower-phrase-91','Chanda Mulenga','borrower');
      $b = av_create_user('mwila','Borrower-phrase-92','Mwila Banda','borrower');
      av_create_user('adminone','Admin-phrase-91','Admin','admin');

      // Chanda: a four-week loan disbursed sixty days ago, so it is overdue and
      // the flat late charge has taken hold.
      avStoreWrite(__DIR__.'/records_data.json', [
        ['_key'=>'a1','_owner'=>$a['user']['id'],'full_name'=>'Chanda Mulenga',
         'status'=>'Disbursed','disburse_date'=>date('Y-m-d', strtotime('-60 days')),
         'loan_amt'=>2000.00,'total_repay'=>'2600.00','sel_dur'=>'4 Weeks',
         'terms_version'=>'2026-11-03','int_rate'=>30,
         '_payments'=>[['id'=>'p1','amount'=>600.00,'date'=>date('Y-m-d')]]],
        ['_key'=>'b1','_owner'=>$b['user']['id'],'full_name'=>'Mwila Banda',
         'status'=>'Disbursed','disburse_date'=>date('Y-m-d', strtotime('-7 days')),
         'loan_amt'=>900.00,'total_repay'=>'1080.00','sel_dur'=>'2 Weeks',
         'nrc_number'=>'999999/99/9','_payments'=>[]]]);
      file_put_contents(__DIR__.'/ids.txt', $a['user']['id'].\"\\n\".$b['user']['id']);
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

    // Nobody who is not signed in gets near it.
    let res = await req('/account.php');
    assert.equal(res.status, 302);
    assert.match(res.headers.get('location'), /login\.php/);

    assert.equal((await req('/login.php',
      { do: 'in', username: 'chanda', password: 'Borrower-phrase-91' })).status, 302);

    const page = await (await req('/account.php')).text();
    assert.ok(!/Fatal error|Parse error|Warning:/.test(page), page.slice(0, 400));
    assert.ok(page.includes('getRecords'));

    const mine = await (await req('/api.php?action=getRecords')).json();
    assert.equal(mine.total, 1);
    assert.equal(mine.records[0]._key, 'a1');

    // Nothing of the other borrower's, not even over the wire.
    const wire = JSON.stringify(mine);
    for (const theirs of ['Mwila', 'b1', '999999/99/9']) {
      assert.ok(!wire.includes(theirs), `a borrower must not be sent ${theirs}`);
    }

    // The working, in full. They were told the price before borrowing; the
    // arithmetic afterwards should not need a phone call.
    const s = mine.records[0]._summary;
    assert.equal(s.penalty_applied, true);
    assert.equal(s.signed_rate, 30);
    assert.equal(s.penalty_rate, 35);
    assert.equal(s.signed_total, 2600);
    assert.equal(s.total_due, 2700);          // K2,000 at 35%
    assert.equal(s.penalty_amount, 100);
    assert.equal(s.paid, 600);
    assert.equal(s.balance, 2100);
    assert.ok(s.days_overdue > 0);

    // The page actually renders the late charge rather than quietly folding it
    // into a bigger number.
    assert.match(page, /penalty_amount|penalty_applied/);

    // Staff have the portal; this page is the borrower's.
    const admin = { cookie: '' };
    await req('/login.php', { do: 'in', username: 'adminone', password: 'Admin-phrase-91' }, admin);
    assert.equal((await req('/account.php', null, admin)).status, 403);

    // And signing in as a borrower lands somewhere they can use.
    const fresh = { cookie: '' };
    res = await req('/login.php', { do: 'in', username: 'mwila', password: 'Borrower-phrase-92' }, fresh);
    assert.equal(res.status, 302);
    assert.ok(['index.php', 'account.php'].includes(res.headers.get('location')),
      'got ' + res.headers.get('location'));

    const theirs = await (await req('/api.php?action=getRecords', null, fresh)).json();
    assert.equal(theirs.total, 1);
    assert.equal(theirs.records[0]._key, 'b1');
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});

test('the link to it appears for a borrower and nobody else', () => {
  const src = readFileSync('app/index.php', 'utf8');
  assert.ok(src.includes('id="my-account-link"'));
  assert.ok(src.includes('href="account.php"'));
  // Shown by role, not to everyone who happens to be signed in.
  assert.match(src, /role !== 'borrower'\) return;[\s\S]{0,200}my-account-link/);
  // And the public "Sign in" swap must target the sign-out link by id, or it
  // relabels "My account" instead — they share a class and this one is first.
  assert.ok(src.includes("getElementById('sign-out-link')"));
  assert.ok(!src.includes("querySelector('.office-out')"));
});
