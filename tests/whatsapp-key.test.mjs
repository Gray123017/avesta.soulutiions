import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, mkdirSync, copyFileSync, writeFileSync, readFileSync, existsSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn, spawnSync} from 'node:child_process';
import {createServer} from 'node:net';

/* Where the TextMeBot API key lives.
 *
 * It used to live in one browser's localStorage. Three things followed from
 * that, and all three were real:
 *
 *   The key existed on exactly one device. Clear the browser, or open the
 *   portal on a phone, and WhatsApp alerts stopped with nothing to say so.
 *
 *   The server could never see it. notify-lib.php reads the key from the
 *   secrets file, so every server-side alert — a new application, the
 *   watchdog — stayed silent no matter what the panel showed.
 *
 *   The panel contradicted itself, because its "key present" light came from
 *   the server while its Save wrote to the browser.
 *
 * The key now lives in avesta-secrets.php, which is the place the design
 * always intended: notify.php's own entry in the backup allowlist says so in
 * writing. That file is in .gitignore and is not in the allowlist, so it can
 * reach neither the public repository nor a downloaded backup — and the
 * tests below hold both of those shut.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

const LIBS = ['auth.php', 'asset-version.php', 'notify-lib.php', 'mailer-lib.php', 'rates-lib.php',
              'backup-lib.php', 'categories-lib.php', 'employers-lib.php',
              'remittance-lib.php', 'disbursement-lib.php', 'api.php', 'watchdog-lib.php'];

async function portal(role, fn) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-wakey-'));
  let server;
  try {
    for (const f of LIBS) if (existsSync(f)) copyFileSync(f, join(dir, f));
    mkdirSync(join(dir, 'assets', 'avesta'), {recursive: true});
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    mkdirSync(join(dir, 'sessions'));
    writeFileSync(join(dir, 'records_data.php'), '<?php http_response_code(404); exit; ?>\n[]');

    writeFileSync(join(dir, 'fixture.php'), `<?php
      require __DIR__.'/auth.php';
      session_id('wakey'); av_session_start();
      $_SESSION = ['uid'=>'u1','username'=>'tester','name'=>'Tester',
        'role'=>${JSON.stringify(role)},'seen'=>time(),'started'=>time()];
      session_write_close();`);
    const config = ['-d', 'session.save_path=' + join(dir, 'sessions')];
    assert.equal(spawnSync(php, [...config, join(dir, 'fixture.php')], {encoding: 'utf8'}).status, 0);

    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], {stdio: 'ignore'});
    for (let i = 0; i < 60; i++) {
      try { await fetch(`http://127.0.0.1:${port}/api.php?action=ping`); break; }
      catch { await new Promise(r => setTimeout(r, 50)); }
    }

    const call = async (action, body) => {
      const res = await fetch(`http://127.0.0.1:${port}/api.php?action=${action}`, {
        method: body ? 'POST' : 'GET',
        headers: {Cookie: 'AVESTASESS=wakey', 'Content-Type': 'application/json'},
        body: body ? JSON.stringify(body) : undefined,
      });
      const text = await res.text();
      let json = null; try { json = JSON.parse(text); } catch { /* keep text */ }
      return {status: res.status, json, text};
    };
    const serverSeesKey = () => spawnSync(php, ['-r',
      `require ${JSON.stringify(join(dir, 'notify-lib.php'))}; echo av_notify_whatsapp_key();`],
      {encoding: 'utf8'}).stdout;

    await fn({call, dir, serverSeesKey});
  } finally {
    if (server) { const done = new Promise(r => server.once('exit', r)); server.kill(); await done; }
    rmSync(dir, {recursive: true, force: true});
  }
}

const KEY = 'tmb_live_9f2Ac7Qx41';

/* ───────────────────────── the key must not leak ───────────────────────── */

test('THE ONE THAT MATTERS: the key is never sent back to a browser',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call}) => {
    const saved = await call('notifyKeySave', {key: KEY});
    assert.equal(saved.status, 200, saved.text);
    assert.doesNotMatch(saved.text, new RegExp(KEY), 'the save echoed the key straight back');

    const settings = await call('notifySettings');
    assert.doesNotMatch(settings.text, new RegExp(KEY),
      'the settings endpoint hands the key to anybody with an admin session and a console');
    assert.equal(settings.json.whatsapp_key_present, true,
      'it should say a key is set without saying what it is');
  });
});

test('THE ONE THAT MATTERS: the key reaches the server, which is the whole point',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call, serverSeesKey}) => {
    assert.equal(serverSeesKey(), '', 'nothing configured to begin with');
    await call('notifyKeySave', {key: KEY});
    assert.equal(serverSeesKey(), KEY,
      'notify-lib.php must be able to read what the portal saved, or alerts stay silent');
  });
});

test('the key is written where backups and git cannot reach it',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call, dir}) => {
    await call('notifyKeySave', {key: KEY});
    const file = join(dir, 'avesta-secrets.php');
    assert.ok(existsSync(file), 'the key should land in avesta-secrets.php');
    const body = readFileSync(file, 'utf8');
    assert.match(body, /^<\?php/, 'it has to be a PHP file, so fetching it returns nothing');
    assert.match(body, new RegExp(KEY));

    // Not in the allowlist means it cannot be in a backup. Check the list itself.
    const allowlist = readFileSync('backup-lib.php', 'utf8');
    const block = allowlist.slice(allowlist.indexOf('function av_backup_allowlist'));
    assert.doesNotMatch(block.slice(0, block.indexOf('}')), /avesta-secrets/,
      'the secrets file must never be added to the backup allowlist');
    assert.match(readFileSync('.gitignore', 'utf8'), /^\/avesta-secrets\.php$/m,
      'and it must stay ignored by git, because this repository is public');

    /* The repository root IS public_html, so every file in it is web-served
     * unless Apache is told otherwise. The file emits nothing when executed,
     * but that only holds while PHP is executing it. */
    const htaccess = readFileSync('.htaccess', 'utf8');
    const denyBlock = htaccess.slice(htaccess.indexOf('<Files "avesta-secrets.php">'));
    assert.ok(denyBlock.startsWith('<Files "avesta-secrets.php">'),
      'avesta-secrets.php is not denied in .htaccess, so it is served from the web root');
    assert.match(denyBlock.slice(0, denyBlock.indexOf('</Files>')), /Require all denied|Deny from all/,
      'the .htaccess entry for the secrets file does not actually deny anything');
  });
});

test('saving a key leaves any other secret in the file alone',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call, dir}) => {
    writeFileSync(join(dir, 'avesta-secrets.php'),
      "<?php return ['smtp_password' => 'keep-me', 'textmebot_key' => 'old'];\n");
    await call('notifyKeySave', {key: KEY});
    const body = readFileSync(join(dir, 'avesta-secrets.php'), 'utf8');
    assert.match(body, /keep-me/, 'another secret in the same file was destroyed');
    assert.match(body, new RegExp(KEY));
    assert.doesNotMatch(body, /'old'/, 'the replaced key should be gone');
  });
});

/* ───────────────────────── who may set it ───────────────────────── */

test('an ordinary admin cannot set the key; only the super admin can',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('admin', async ({call, serverSeesKey}) => {
    const res = await call('notifyKeySave', {key: KEY});
    assert.equal(res.status, 403, 'a secret belongs to the account that owns the business');
    assert.equal(serverSeesKey(), '', 'and nothing may have been written');
  });
});

test('rubbish is refused rather than written into a PHP file',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call, dir}) => {
    for (const bad of ["'; system('id'); //", '<?php echo 1;', 'a'.repeat(300), '', 'has spaces']) {
      const res = await call('notifyKeySave', {key: bad});
      assert.notEqual(res.status, 200, 'accepted a key it should have refused: ' + bad.slice(0, 30));
    }
    const file = join(dir, 'avesta-secrets.php');
    if (existsSync(file)) {
      const body = readFileSync(file, 'utf8');
      assert.doesNotMatch(body, /system\(|echo 1/, 'input was written into executable code');
    }
  });
});

test('clearing the key turns WhatsApp off rather than leaving a stale one',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call, serverSeesKey}) => {
    await call('notifyKeySave', {key: KEY});
    assert.equal(serverSeesKey(), KEY);
    const res = await call('notifyKeySave', {key: null, clear: true});
    assert.equal(res.status, 200, res.text);
    assert.equal(serverSeesKey(), '', 'clearing has to actually clear it');
  });
});

/* ───────────────────────── the browser side stops hoarding it ───────────────────────── */

test('the portal no longer keeps the key in localStorage', () => {
  /* Storing the alert NUMBER in the browser is fine — it is not a secret.
   * What must never go back is the key, so this checks what is written
   * rather than the name it is written under. */
  const admin = readFileSync('app/admin.php', 'utf8');
  for (const [, payload] of admin.matchAll(/localStorage\.setItem\([^,]+,\s*([^;]+?)\);/g)) {
    assert.doesNotMatch(payload, /\bkey\b/,
      'something key-shaped is going into localStorage again: ' + payload.slice(0, 80));
  }
  assert.doesNotMatch(admin, /api\.textmebot\.com/,
    'the browser must not call TextMeBot directly; that is why it needed the key');
  assert.match(admin, /action=notifyWhatsApp/,
    'sends should go through the server endpoint');
});

test('the TextMeBot link does not build a URL out of a browser-held secret', () => {
  const admin = readFileSync('app/admin.php', 'utf8');
  const fn = admin.slice(admin.indexOf('function openWaFallback'),
                         admin.indexOf('function openWaFallback') + 700);
  assert.doesNotMatch(fn, /localStorage/,
    'the link used to read the key out of localStorage, which is where it no longer lives');
});

/* ─────────────────── the mailbox password, same rules ───────────────────
 *
 * Added 28 November 2026 with mailer-lib.php. It lives in the same file, is
 * written by the same single writer (av_secret_set) and carries the same
 * guarantee: the portal may set it and may learn whether one is set, and may
 * never read it back. Kept in this file because the harness and the promises
 * are identical — a second copy of both is how the WhatsApp key ended up with
 * two homes that could not see each other.
 */

const MAILPASS = 'hostinger-mailbox-pw-9931';

test('THE ONE THAT MATTERS: the mailbox password is never sent back to a browser',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call}) => {
    const saved = await call('notifyMailSave', {password: MAILPASS});
    assert.equal(saved.status, 200, saved.text);
    assert.doesNotMatch(saved.text, new RegExp(MAILPASS), 'the save echoed the password back');
    assert.match(saved.text, /"mail_available":true/, 'it should confirm email can now be sent');

    const settings = await call('notifySettings');
    assert.doesNotMatch(settings.text, new RegExp(MAILPASS),
      'notifySettings hands the mailbox password to anybody with a session and a console');
    assert.equal(settings.json.mail_available, true);
  });
});

test('the mailbox password and the TextMeBot key do not overwrite each other',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call, dir, serverSeesKey}) => {
    await call('notifyKeySave', {key: KEY});
    await call('notifyMailSave', {password: MAILPASS});

    const body = readFileSync(join(dir, 'avesta-secrets.php'), 'utf8');
    assert.match(body, new RegExp(KEY), 'saving the mailbox password destroyed the WhatsApp key');
    assert.match(body, new RegExp(MAILPASS));
    assert.equal(serverSeesKey(), KEY, 'and notify-lib must still read the key');
  });
});

test('an ordinary admin cannot set the mailbox password',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('admin', async ({call, dir}) => {
    const res = await call('notifyMailSave', {password: MAILPASS});
    assert.equal(res.status, 403, 'the mailbox password belongs to the owner of the business');
    const file = join(dir, 'avesta-secrets.php');
    if (existsSync(file)) {
      assert.doesNotMatch(readFileSync(file, 'utf8'), new RegExp(MAILPASS),
        'it was written anyway');
    }
  });
});

test('clearing the mailbox password stops email rather than half-sending it',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call}) => {
    await call('notifyMailSave', {password: MAILPASS});
    const cleared = await call('notifyMailSave', {clear: true});
    assert.equal(cleared.status, 200, cleared.text);
    assert.match(cleared.text, /"mail_available":false/);

    // And an empty field must not silently clear it, same as the key.
    await call('notifyMailSave', {password: MAILPASS});
    const blank = await call('notifyMailSave', {password: '   '});
    assert.notEqual(blank.status, 200, 'a blank input wiped the configured password');
    const still = await call('notifySettings');
    assert.equal(still.json.mail_available, true, 'and it is still set');
  });
});

test('the test-send refuses anything that is not an email address',
  {skip: !hasPhp, timeout: 30000}, async () => {
  await portal('super_admin', async ({call}) => {
    for (const bad of ['', 'not-an-address', 'a@b', 'x@y.com, z@w.com']) {
      const res = await call('notifyMailTest', {to: bad});
      assert.equal(res.status, 400, 'accepted "' + bad + '" as a destination');
    }
  });
});
