import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, cpSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync, execFileSync } from 'node:child_process';
import { createServer } from 'node:net';

/* Who may take a backup, over real HTTP.
 *
 * One request hands over every NRC scan and every password hash on the server.
 * That makes this the most dangerous endpoint on the site, so the gate is
 * tested from the outside, with real sessions, for every role — not by reading
 * the code and agreeing with it.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;
const hasTar = spawnSync('tar', ['--version']).status === 0;

test('only a super admin can take a backup, and only one at a time',
  { skip: !hasPhp || !hasTar, timeout: 30000 }, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-backup-http-'));
  let server;
  try {
    for (const f of ['api.php', 'auth.php', 'portal.php', 'apply.php',
                     'rates-lib.php', 'categories-lib.php', 'backup-lib.php']) {
      copyFileSync(f, join(dir, f));
    }
    mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
    for (const f of ['rates.json', 'categories.json']) {
      copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
    }
    cpSync('app', join(dir, 'app'), { recursive: true });
    mkdirSync(join(dir, 'sessions'));

    // Test-only sessions in a temporary copy. Never touches live accounts.
    // PHP only accepts [a-zA-Z0-9,-] in a session id, so the cookie value
    // cannot carry the underscore in super_admin — hence the separate key.
    writeFileSync(join(dir, 'fixture.php'), `<?php
      require __DIR__.'/auth.php';
      foreach (['superadmin'=>'super_admin','admin'=>'admin',
                'staff'=>'staff','borrower'=>'borrower'] as $key => $role) {
        session_id('test-'.$key); av_session_start();
        $_SESSION = ['uid'=>$key,'username'=>$role,'name'=>'Test',
          'role'=>$role,'seen'=>time(),'started'=>time()];
        session_write_close();
      }
    `);
    const config = ['-d', 'session.save_path=' + join(dir, 'sessions')];
    const fixture = spawnSync(php, [...config, join(dir, 'fixture.php')], { encoding: 'utf8' });
    assert.equal(fixture.status, 0, fixture.stderr);

    // A loan book with a scan in it, so there is something real to lose.
    const guarded = (d) => '<?php http_response_code(404); exit; ?>\n' + JSON.stringify(d);
    writeFileSync(join(dir, 'records_data.php'), guarded([{ _key: 'app_one', name: 'Chanda Mulenga' }]));
    mkdirSync(join(dir, 'documents', 'app_one'), { recursive: true });
    writeFileSync(join(dir, 'documents', 'app_one', 'doc_nrc_front.jpg'),
      Buffer.from([0xff, 0xd8, 0x00, 0x7f, 0xff, 0xd9]));
    writeFileSync(join(dir, 'documents', '.htaccess'), 'Require all denied\n');
    writeFileSync(join(dir, 'avesta-secrets.php'), "<?php return ['key'=>'sk-live-must-not-ship'];");

    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    const base = 'http://127.0.0.1:' + port;
    server = spawn(php, [...config, '-S', '127.0.0.1:' + port, '-t', dir], { stdio: 'ignore' });

    const request = (path, role) => fetch(base + path, {
      headers: role ? { Cookie: 'AVESTASESS=test-' + role } : {},
    });
    for (let i = 0; i < 40; i++) {
      try { await request('/api.php?action=ping'); break; }
      catch { await new Promise(r => setTimeout(r, 50)); }
    }

    // Nobody below super admin, including an ordinary admin.
    assert.equal((await request('/api.php?action=backup')).status, 401);
    for (const role of ['borrower', 'staff', 'admin']) {
      assert.equal((await request('/api.php?action=backup', role)).status, 403,
        `${role} must not be able to download the whole loan book`);
    }

    // A super admin gets a tar.
    const res = await request('/api.php?action=backup', 'superadmin');
    assert.equal(res.status, 200, await res.clone().text());
    assert.equal(res.headers.get('content-type'), 'application/x-tar');
    assert.match(res.headers.get('content-disposition'), /^attachment; filename="avesta-backup-/);
    assert.equal(res.headers.get('cache-control'), 'private, no-store');

    const tarPath = join(dir, 'downloaded.tar');
    writeFileSync(tarPath, Buffer.from(await res.arrayBuffer()));
    const listed = execFileSync('tar', ['-tf', tarPath], { encoding: 'utf8' }).trim().split('\n');
    assert.ok(listed.includes('MANIFEST.json'));
    assert.ok(listed.includes('records_data.php'));
    assert.ok(listed.includes('documents/app_one/doc_nrc_front.jpg'));
    assert.ok(listed.includes('documents/.htaccess'));
    assert.ok(!listed.includes('avesta-secrets.php'), 'the download must not carry API keys');

    // It is written down. These are identity documents leaving the server.
    const audit = readFileSync(join(dir, 'audit_log.jsonl'), 'utf8');
    const entry = audit.trim().split('\n').map(JSON.parse)
      .find(e => e.action === 'backup.downloaded');
    assert.ok(entry, 'a backup must appear in the audit log');
    assert.equal(entry.user, 'super_admin');
    assert.equal(entry.detail.records, 1);

    // And straight away again is refused: rebuilding every document is the most
    // expensive thing this site can be asked to do.
    const again = await request('/api.php?action=backup', 'superadmin');
    assert.equal(again.status, 429);
    const body = await again.json();
    assert.equal(body.ok, false);
    assert.ok(body.retry_after > 0 && body.retry_after <= 300);

    // The status call stays available, so the portal can report without downloading.
    const status = await (await request('/api.php?action=backupStatus', 'superadmin')).json();
    assert.equal(status.ok, true);
    assert.equal(status.counts.records, 1);
    assert.equal(status.counts.documents, 1);
    assert.ok(status.retry_after > 0);
    assert.equal((await request('/api.php?action=backupStatus', 'admin')).status, 403);

    // The button has to exist in the portal, or none of the above is reachable.
    // It is removed from the page for anyone who is not a super admin, so the
    // markup ships to every admin and the script is what takes it out again.
    const portal = await (await request('/portal.php', 'superadmin')).text();
    assert.ok(!/Fatal error|Parse error|Warning:/.test(portal), portal.slice(0, 400));
    assert.ok(portal.includes('id="backup-card"'));
    assert.ok(portal.includes('action=backupStatus'));
    assert.match(portal, /role !== 'super_admin'[\s\S]{0,200}removeChild\(card\)/,
      'the card must be taken out of the document for anyone below super admin');
  } finally {
    if (server) { const closed = new Promise(r => server.once('exit', r)); server.kill(); await closed; }
    rmSync(dir, { recursive: true, force: true });
  }
});
