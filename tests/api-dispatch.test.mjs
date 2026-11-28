import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, cpSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

/* What the API says when it does not recognise the request.
 *
 * The reason this file exists: `?action=submit` is only matched on POST, and
 * anything that was not matched fell through to a friendly
 *
 *     200 {"ok": true, "message": "Avesta API ready..."}
 *
 * A browser turns a POST into a GET when it follows a 301 or 302 — which is
 * exactly what a www → apex redirect is. So an applicant on www.avesta.solutions
 * would have submitted, had the POST re-issued as a GET, received `ok: true`,
 * and been told their application was in. Nothing would have been stored: not
 * the application, not the NRC scans, not the payslip.
 *
 * An API that answers `ok: true` to a request it did not carry out can lose
 * somebody's loan application without anybody finding out. A request this
 * server did not handle must say so.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

test('an unhandled request is refused, not answered with ok',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-api-dispatch-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'asset-version.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'notify-lib.php']) {
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
      session_id('test-borrower'); av_session_start();
      $_SESSION = ['uid'=>'borrower','username'=>'borrower','name'=>'Test',
        'role'=>'borrower','seen'=>time(),'started'=>time()];
      session_write_close();
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

    // ── THE ONE THAT MATTERS ──────────────────────────────────────────────
    // A POST turned into a GET by a redirect. The application was not stored,
    // so the answer must not be a success.
    const submitted = await get('/api.php?action=submit', 'borrower');
    assert.equal(submitted.status, 405,
      'a GET to the submission endpoint must be refused, not answered 200');
    assert.equal(submitted.json.ok, false,
      `an unstored application must never come back ok:true — got ${submitted.text}`);
    assert.match(submitted.json.error, /POST/i);

    // Signed out, the same request must still not claim success.
    const anon = await get('/api.php?action=submit');
    assert.notEqual(anon.status, 200, anon.text);
    assert.equal(anon.json.ok, false, anon.text);

    // ── and the general case ──────────────────────────────────────────────
    // A renamed or mistyped action is a bug in the caller. Returning ok:true
    // hides it; every client checking `res.ok` reads it as success.
    for (const action of ['disbursements', 'notSuchThing', 'getRecord']) {
      const r = await get('/api.php?action=' + action, 'borrower');
      assert.equal(r.status, 404, `?action=${action} should be 404, got ${r.status}`);
      assert.equal(r.json.ok, false, r.text);
    }

    // The bare path stays a friendly, public "yes, this is here" — the tests
    // and any uptime check use it, and it discloses nothing.
    const bare = await get('/api.php');
    assert.equal(bare.status, 200, bare.text);
    assert.equal(bare.json.ok, true);
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});

test('no answer from the API may be stored by anything in between',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  /* api.php set Cache-Control on exactly two endpoints — doc and backup. Every
   * other answer, the loan book and the account list among them, went out with
   * no cache directives at all.
   *
   * Two separate consequences, and the same one-line fix closes both:
   *
   * 1. A response with no directives is heuristically cacheable, so a browser
   *    may write it to disk. On a shared machine at a branch, the loan book is
   *    then still on that disk after the staff member signs out, and still
   *    there for the back button.
   *
   * 2. Hostinger's CDN sits in front of this site and was observed returning
   *    `cache-control: public, max-age=604800` on a response whose origin never
   *    set it — so its default for an answer with no directives is to cache it
   *    publicly for a week. Whether it does that to a cookie-bearing request is
   *    Hostinger's business and not something this repository can depend on.
   *
   * Declaring it is free and settles the question wherever the answer travels.
   */
  const dir = mkdtempSync(join(tmpdir(), 'avesta-api-nostore-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'asset-version.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'notify-lib.php']) {
      copyFileSync(f, join(dir, f));
    }
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    cpSync('app', join(dir, 'app'), { recursive: true });
    mkdirSync(join(dir, 'sessions'));

    // A real applicant in the store, so the cacheable answer is a real one.
    writeFileSync(join(dir, 'records_data.php'),
      '<?php http_response_code(404); exit; ?>\n' +
      JSON.stringify([{ _key: 'AV-2026-0041', full_name: 'Chanda Mulenga',
                        nrc_number: '123456/78/1', loan_amt: 2000 }]));

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
    const base = 'http://127.0.0.1:' + port;
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const head = async (path, role) => {
      const res = await fetch(base + path, {
        headers: role ? { Cookie: 'AVESTASESS=test-' + role } : {},
      });
      return { status: res.status, cc: res.headers.get('cache-control'),
               body: await res.text() };
    };
    for (let i = 0; i < 40; i++) {
      try { await head('/api.php?action=ping'); break; }
      catch { await new Promise(r => setTimeout(r, 50)); }
    }

    // The answer that matters most: a signed-in read of the loan book.
    const book = await head('/api.php?action=getRecords', 'admin');
    assert.equal(book.status, 200, book.body.slice(0, 300));
    assert.ok(book.body.includes('Chanda Mulenga'),
      'this test is worthless if the answer carries no applicant');
    assert.match(book.cc || '', /no-store/,
      `the loan book went out cacheable — Cache-Control: ${book.cc}`);
    assert.match(book.cc || '', /private/);

    // And every other answer, whatever it is and whoever asked.
    const cases = [
      ['/api.php?action=users', 'admin'],
      ['/api.php?action=employers', 'staff'],
      ['/api.php?action=remittances', 'staff'],
      ['/api.php?action=auditLog', 'admin'],
      ['/api.php?action=me', 'borrower'],
      ['/api.php?action=myEmployer', 'borrower'],
      ['/api.php?action=ping', null],            // public, but no reason to store it
      ['/api.php?action=rates', null],
      ['/api.php?action=users', null],           // the 401 as well
      ['/api.php?action=noSuchAction', null],    // and the 404
      ['/api.php', null],
    ];
    for (const [path, role] of cases) {
      const r = await head(path, role);
      assert.match(r.cc || '', /no-store/,
        `${path} as ${role || 'a stranger'} → Cache-Control: ${r.cc} (status ${r.status})`);
    }
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});

test('ping tells a stranger that the site is up and nothing else',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  /* ping answered anybody with the PHP version, the live loan-book count and
   * the server's upload limits. The version number is the first thing worth
   * knowing if you are looking for a way in, and how many applications a
   * lender holds is the lender's business. Staff still get all of it; they are
   * who the diagnostics on the settings page are written for. */
  const dir = mkdtempSync(join(tmpdir(), 'avesta-api-ping-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'asset-version.php', 'portal.php', 'apply.php', 'rates-lib.php',
                     'categories-lib.php', 'backup-lib.php', 'employers-lib.php',
                     'notify-lib.php']) {
      copyFileSync(f, join(dir, f));
    }
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    cpSync('app', join(dir, 'app'), { recursive: true });
    mkdirSync(join(dir, 'sessions'));

    // A loan book with something in it, so a leaked count would be a real one.
    writeFileSync(join(dir, 'records_data.php'),
      '<?php http_response_code(404); exit; ?>\n' +
      JSON.stringify([{ _key: 'a', full_name: 'Chanda Mulenga' },
                      { _key: 'b', full_name: 'Mary Phiri' }]));

    writeFileSync(join(dir, 'fixture.php'), `<?php
      require __DIR__.'/auth.php';
      foreach (['staff'=>'staff','borrower'=>'borrower'] as $key => $role) {
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

    const ping = async (role) => {
      const res = await fetch(base + '/api.php?action=ping', {
        headers: role ? { Cookie: 'AVESTASESS=test-' + role } : {},
      });
      return { status: res.status, text: await res.text() };
    };
    for (let i = 0; i < 40; i++) {
      try { await ping(); break; } catch { await new Promise(r => setTimeout(r, 50)); }
    }

    // Still a readiness probe: the tests above wait on this, so it must answer
    // a stranger, and answer 200.
    const anon = await ping();
    assert.equal(anon.status, 200, anon.text);
    const body = JSON.parse(anon.text);
    assert.equal(body.ok, true);
    assert.ok(body.ts, 'a probe is more use with a timestamp on it');

    for (const field of ['php', 'records', 'docs_writable', 'recovery_mail_available',
                         'post_max_size', 'upload_max_filesize', 'memory_limit',
                         'user_ini_active']) {
      assert.ok(!(field in body),
        `ping must not tell a stranger "${field}" — got ${anon.text}`);
    }
    // Belt and braces: not the version string, and not the count, under any name.
    assert.doesNotMatch(anon.text, /\d+\.\d+\.\d+/, anon.text);

    // A borrower is signed in but is not staff. The count is every application
    // on the server, which is none of one borrower's business.
    const borrower = JSON.parse((await ping('borrower')).text);
    assert.equal(borrower.ok, true);
    assert.ok(!('records' in borrower), 'a borrower must not see the loan-book size');
    assert.ok(!('php' in borrower));

    // Staff keep the diagnostics. The settings page tells them to open this URL.
    const staff = JSON.parse((await ping('staff')).text);
    assert.ok(staff.php, 'staff must still see the PHP version');
    assert.equal(staff.records, 2);
    assert.equal(typeof staff.docs_writable, 'boolean');
    assert.ok('post_max_size' in staff);
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});
