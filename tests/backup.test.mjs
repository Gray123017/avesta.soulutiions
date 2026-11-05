import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, readdirSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, relative } from 'node:path';
import { createHash } from 'node:crypto';

/* The backup.
 *
 * Applicant records and NRC scans exist in exactly one place — this server —
 * and they are kept out of Git on purpose, because the repository is public
 * and GitHub is outside Zambia. So the only copy that is not Hostinger's is
 * the one the owner downloads, and a backup nobody can restore is worse than
 * no backup, because it is believed.
 *
 * The test that matters is the last one in this file: build the archive, hand
 * it to the system tar, and compare every byte that comes back out.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;
const hasTar = spawnSync('tar', ['--version']).status === 0;

/** A sandbox that looks like a live Hostinger root: data files, documents, secrets. */
function sandbox() {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-backup-'));

  const guarded = (data) => '<?php http_response_code(404); exit; ?>\n' + JSON.stringify(data, null, 2);

  // Irreplaceable: the loan book, the accounts, the identity documents.
  writeFileSync(join(dir, 'records_data.php'), guarded([
    { _key: 'app_one', name: 'Chanda Mulenga', loan_amount: 2000, applicant_category: 'employed' },
    { _key: 'app_two', name: 'Mwila Banda', loan_amount: 5000, applicant_category: 'self_employed' },
  ]));
  writeFileSync(join(dir, 'users.php'), guarded([
    { id: 'u1', username: 'gray', role: 'super_admin', pass_hash: '$2y$10$notarealhash' },
    { id: 'u2', username: 'chanda', role: 'borrower', pass_hash: '$2y$10$alsonotreal' },
  ]));
  writeFileSync(join(dir, 'enquiries.php'), guarded([{ id: 'e1', message: 'Do you lend in Kitwe?' }]));
  writeFileSync(join(dir, 'audit_log.jsonl'),
    '{"ts":"2026-11-01T08:00:00+02:00","action":"document.viewed","target":"app_one"}\n' +
    '{"ts":"2026-11-02T09:30:00+02:00","action":"payment.recorded","target":"app_two"}\n');

  mkdirSync(join(dir, 'documents', 'app_one'), { recursive: true });
  mkdirSync(join(dir, 'documents', 'app_two'), { recursive: true });
  // Real bytes, including a NUL and a high byte, so a text-mode bug shows up.
  writeFileSync(join(dir, 'documents', 'app_one', 'doc_nrc_front.jpg'),
    Buffer.from([0xff, 0xd8, 0x00, 0xfe, 0x01, 0xff, 0xd9]));
  writeFileSync(join(dir, 'documents', 'app_one', 'doc_payslip.pdf'),
    Buffer.from('%PDF-1.4\nchecked\n%%EOF'));
  writeFileSync(join(dir, 'documents', 'app_two', 'doc_bank_statement.pdf'),
    Buffer.from('%PDF-1.4\n' + 'x'.repeat(2000) + '\n%%EOF'));   // crosses a 512-byte block
  writeFileSync(join(dir, 'documents', '.htaccess'),
    "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n");

  mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
  for (const f of ['rates.json', 'categories.json']) {
    writeFileSync(join(dir, 'assets', 'avesta', f), readFileSync(join('assets', 'avesta', f)));
  }

  // Must NOT come out in the archive.
  writeFileSync(join(dir, 'avesta-secrets.php'), "<?php return ['openai_key' => 'sk-live-do-not-ship'];");
  writeFileSync(join(dir, 'login_attempts.php'), guarded({ gray: { fails: 3, until: 999 } }));
  writeFileSync(join(dir, 'rates_cache.php'), guarded({ ZMW: 27.4 }));
  writeFileSync(join(dir, 'api.php'), '<?php // source code, already in Git');
  writeFileSync(join(dir, '.env'), 'SECRET=nope');
  writeFileSync(join(dir, 'records_data.lock'), '');
  writeFileSync(join(dir, 'records_data.php.tmp'), 'half a write');

  return dir;
}

/** Run a snippet against backup-lib.php and return its JSON. */
function lib(code) {
  const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
    'require "backup-lib.php"; ' + code], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  try { return JSON.parse(out); }
  catch { throw new Error('backup-lib.php did not return JSON:\n' + out.slice(0, 2000)); }
}

const sources = (root) => lib(`echo json_encode(array_keys(av_backup_sources(${JSON.stringify(root)})));`);

test('the backup carries everything that cannot be rebuilt', { skip: !hasPhp }, () => {
  const dir = sandbox();
  try {
    const names = sources(dir);
    for (const want of [
      'records_data.php',          // the loan book
      'users.php',                 // accounts and password hashes
      'enquiries.php',
      'audit_log.jsonl',           // who looked at which identity document
      'documents/app_one/doc_nrc_front.jpg',
      'documents/app_one/doc_payslip.pdf',
      'documents/app_two/doc_bank_statement.pdf',
    ]) assert.ok(names.includes(want), `backup is missing ${want}\ngot: ${names.join(', ')}`);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('documents/.htaccess travels with the documents it protects', { skip: !hasPhp }, () => {
  const dir = sandbox();
  try {
    // Restoring NRC scans without this file publishes every one of them.
    assert.ok(sources(dir).includes('documents/.htaccess'));
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('the backup never carries secrets, throttle counters or source code', { skip: !hasPhp }, () => {
  const dir = sandbox();
  try {
    const names = sources(dir);
    for (const never of [
      'avesta-secrets.php',        // API keys: re-entered by hand, never shipped in a download
      '.env',
      'api.php',                   // source code lives in Git
      'login_attempts.php',        // restoring these locks people out for no reason
      'rates_cache.php',
      'records_data.lock',
      'records_data.php.tmp',      // a half-finished write is not a record
    ]) assert.ok(!names.includes(never), `backup must not carry ${never}`);

    // And nothing outside the allowlist sneaks in by another name.
    for (const n of names) {
      assert.ok(!/secret|\.env|\.lock$|\.tmp$/i.test(n), `unexpected entry: ${n}`);
    }
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('every store worth backing up is also one the web server refuses to serve',
  { skip: !hasPhp }, () => {
  /* Two lists have to agree: what the backup keeps, and what .htaccess denies.
   * A new store added to one and forgotten in the other is the quiet failure —
   * either applicant data that never gets backed up, or applicant data served
   * to anyone who guesses the filename. This is the check that catches it. */
  const htaccess = readFileSync('.htaccess', 'utf8');
  const denied = new Set();
  for (const m of htaccess.matchAll(/\^\(([^)]+)\)\\\.\(json\|jsonl\)\$/g)) {
    for (const name of m[1].split('|')) denied.add(name);
  }
  assert.ok(denied.size > 5, 'could not read the .htaccess deny list: ' + [...denied]);

  const allow = lib('echo json_encode(array_keys(av_backup_allowlist()));');
  for (const rel of allow) {
    if (rel.includes('/') || !rel.endsWith('.php')) continue;   // assets are public on purpose
    const store = rel.slice(0, -4);
    assert.ok(denied.has(store),
      `${store} holds applicant data but .htaccess would serve ${store}.json to anyone`);
  }
});

test('an empty site still produces a valid backup, so it can be tested before go-live',
  { skip: !hasPhp || !hasTar }, () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-backup-empty-'));
  try {
    const out = join(dir, 'empty.tar');
    lib(`$r = av_backup_stream(${JSON.stringify(dir)}, fopen(${JSON.stringify(out)}, 'wb'));
         echo json_encode(['bytes' => $r['bytes'], 'entries' => count($r['entries'])]);`);
    assert.ok(statSync(out).size > 0);

    const listed = execFileSync('tar', ['-tf', out], { encoding: 'utf8' }).trim().split('\n');
    assert.deepEqual(listed, ['MANIFEST.json'],
      'an empty site should still carry a manifest saying so');
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('the manifest states what is in the archive and the checksum of each file',
  { skip: !hasPhp }, () => {
  const dir = sandbox();
  try {
    const m = lib(`echo json_encode(av_backup_manifest(${JSON.stringify(dir)}));`);

    assert.equal(m.counts.records, 2);
    assert.equal(m.counts.accounts, 2);
    assert.equal(m.counts.documents, 3);      // .htaccess is protection, not a document
    assert.ok(m.generated, 'the manifest must say when it was taken');

    const byPath = Object.fromEntries(m.files.map(f => [f.path, f]));
    const real = readFileSync(join(dir, 'documents', 'app_one', 'doc_nrc_front.jpg'));
    assert.equal(byPath['documents/app_one/doc_nrc_front.jpg'].sha256,
      createHash('sha256').update(real).digest('hex'));
    assert.equal(byPath['documents/app_one/doc_nrc_front.jpg'].bytes, real.length);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('the promised Content-Length is exactly what gets written', { skip: !hasPhp }, () => {
  const dir = sandbox();
  try {
    const out = join(dir, 'sized.tar');
    const r = lib(`$m = av_backup_manifest(${JSON.stringify(dir)}, 1762300000);
      $predicted = av_backup_size($m);
      $w = av_backup_stream(${JSON.stringify(dir)}, fopen(${JSON.stringify(out)}, 'wb'), 1762300000, $m);
      echo json_encode(['predicted' => $predicted, 'written' => $w['bytes']]);`);

    // A byte out either way and the download hangs or truncates, on a mobile
    // connection, without anything appearing to have gone wrong.
    assert.equal(r.predicted, r.written);
    assert.equal(statSync(out).size, r.predicted);
    assert.equal(r.predicted % 10240, 0, 'a tar should end on a record boundary');
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('a file that vanishes mid-backup still fills its promised space', { skip: !hasPhp || !hasTar }, () => {
  const dir = sandbox();
  try {
    const out = join(dir, 'vanished.tar');
    // Manifest first, then delete a document, then stream against that manifest.
    const r = lib(`$m = av_backup_manifest(${JSON.stringify(dir)}, 1762300000);
      $predicted = av_backup_size($m);
      unlink(${JSON.stringify(join(dir, 'documents', 'app_one', 'doc_payslip.pdf'))});
      $w = av_backup_stream(${JSON.stringify(dir)}, fopen(${JSON.stringify(out)}, 'wb'), 1762300000, $m);
      echo json_encode(['predicted' => $predicted, 'written' => $w['bytes'],
        'unread' => array_values(array_map(fn($e) => $e['path'],
          array_filter($w['entries'], fn($e) => isset($e['read']) && !$e['read'])))]);`);

    assert.equal(r.written, r.predicted, 'the archive must still be the length it promised');
    assert.deepEqual(r.unread, ['documents/app_one/doc_payslip.pdf'],
      'and it must say which file it could not read');

    const res = spawnSync('tar', ['-tf', out], { encoding: 'utf8' });
    assert.equal(res.status, 0, res.stderr);
    assert.equal(res.stderr.trim(), '', 'a truncated archive is the failure this guards against');
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('a long document path survives the 100-character tar name limit',
  { skip: !hasPhp || !hasTar }, () => {
  const dir = sandbox();
  try {
    const key = 'app_' + 'k'.repeat(90);
    mkdirSync(join(dir, 'documents', key), { recursive: true });
    writeFileSync(join(dir, 'documents', key, 'doc_nrc_back.jpg'), Buffer.from('deep'));

    const out = join(dir, 'long.tar');
    lib(`$r = av_backup_stream(${JSON.stringify(dir)}, fopen(${JSON.stringify(out)}, 'wb'));
         echo json_encode(['bytes' => $r['bytes']]);`);

    const extract = mkdtempSync(join(tmpdir(), 'avesta-backup-long-'));
    try {
      const res = spawnSync('tar', ['-xf', out, '-C', extract], { encoding: 'utf8' });
      assert.equal(res.status, 0, res.stderr);
      assert.equal(res.stderr.trim(), '', 'the system tar should read it without complaint');
      assert.equal(readFileSync(join(extract, 'documents', key, 'doc_nrc_back.jpg'), 'utf8'), 'deep');
    } finally { rmSync(extract, { recursive: true, force: true }); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('THE TEST THAT MATTERS: the system tar restores every byte',
  { skip: !hasPhp || !hasTar }, () => {
  const dir = sandbox();
  const extract = mkdtempSync(join(tmpdir(), 'avesta-restore-'));
  try {
    const out = join(dir, 'backup.tar');
    const made = lib(`$r = av_backup_stream(${JSON.stringify(dir)}, fopen(${JSON.stringify(out)}, 'wb'));
         echo json_encode(['bytes' => $r['bytes'], 'entries' => array_column($r['entries'], 'path')]);`);

    assert.equal(statSync(out).size, made.bytes,
      'the byte count reported to the browser must match the archive actually written');

    // GNU tar is the judge. Any warning at all is a malformed archive.
    const res = spawnSync('tar', ['-xf', out, '-C', extract], { encoding: 'utf8' });
    assert.equal(res.status, 0, res.stderr);
    assert.equal(res.stderr.trim(), '', 'tar reported a problem with the archive');

    const manifest = JSON.parse(readFileSync(join(extract, 'MANIFEST.json'), 'utf8'));
    assert.ok(manifest.files.length > 0);
    assert.ok(/RESTORE/i.test(JSON.stringify(manifest)), 'the manifest must say how to restore');

    // Every file the manifest claims is there, is there, byte for byte.
    for (const f of manifest.files) {
      const restored = readFileSync(join(extract, f.path));
      const original = readFileSync(join(dir, f.path));
      assert.deepEqual(restored, original, `${f.path} did not survive the round trip`);
      assert.equal(createHash('sha256').update(restored).digest('hex'), f.sha256,
        `${f.path} does not match its recorded checksum`);
    }

    // Nothing extra came out, and nothing claimed was left behind.
    const walk = (d) => readdirSync(d, { withFileTypes: true }).flatMap(e =>
      e.isDirectory() ? walk(join(d, e.name)) : [relative(extract, join(d, e.name))]);
    assert.deepEqual(walk(extract).sort(),
      ['MANIFEST.json', ...manifest.files.map(f => f.path)].sort());
  } finally {
    rmSync(dir, { recursive: true, force: true });
    rmSync(extract, { recursive: true, force: true });
  }
});
