import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* Telling somebody an application has arrived.
 *
 * What was there before was not a notification: the portal popped a mailto:
 * draft in the admin's own mail client, which only happens if an admin already
 * has the portal open and then sends the email to themselves. An application
 * arriving at two in the morning reached nobody.
 *
 * This sends from the server, when the application is stored, whether or not
 * anybody has a tab open.
 *
 * Two rules it is built around: a notification must never be able to stop an
 * application being accepted, and it must never carry more about the applicant
 * than the person reading it needs — a WhatsApp message gets forwarded, and an
 * NRC number in one is gone for good.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

function lib(body) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-notify-'));
  try {
    const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "notify-lib.php";
       av_notify_path(${JSON.stringify(join(dir, 'notify.json'))});
       ${body}`], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
    try { return JSON.parse(out); }
    catch { throw new Error('notify-lib.php did not return JSON:\n' + out.slice(0, 1500)); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

/* An application with every confidential thing a real one carries. */
const RECORD = `
  $rec = ['_key'=>'AV-2026-0041','full_name'=>'Chanda Mulenga','loan_amt'=>2000.00,
          'sel_dur'=>'4 Weeks','total_repay'=>'2600.00','applicant_category'=>'employed',
          'submitted_at'=>'2026-11-05 02:14',
          'nrc_number'=>'123456/78/1','phone'=>'0966123456','email'=>'chanda@example.test',
          'address'=>'Plot 44 Kabundi Road, Ndola','gps_latitude'=>'-12.958870',
          'doc_nrc_front_url'=>'/api.php?action=doc&key=AV-2026-0041',
          'collateral_items'=>'Toyota Hilux','next_of_kin'=>'Mary Mulenga'];
`;

test('nothing is sent until somebody has been named to send it to',
  { skip: !hasPhp }, () => {
  const r = lib(RECORD + `
    echo json_encode(av_notify_new_application($rec, function () { return true; }));`);
  // Not an error: a site with nobody configured should accept applications
  // quietly rather than log a failure every time.
  assert.deepEqual(r.sent, []);
  assert.deepEqual(r.failed, []);
  assert.ok(r.skipped.length > 0);
});

test('an email goes to each address, once', { skip: !hasPhp }, () => {
  const r = lib(RECORD + `
    av_notify_save(['email' => ['on' => true, 'to' => ['gray@example.test','desk@example.test']]]);
    $seen = [];
    $out = av_notify_new_application($rec, function ($channel, $to, $msg) use (&$seen) {
      $seen[] = [$channel, $to, $msg['subject'] ?? '', $msg['text']];
      return true;
    });
    echo json_encode(['sent' => $out['sent'], 'seen' => $seen]);`);

  assert.equal(r.seen.length, 2);
  assert.deepEqual(r.seen.map(s => s[1]).sort(), ['desk@example.test', 'gray@example.test']);
  assert.ok(r.seen.every(s => s[0] === 'email'));
  assert.match(r.seen[0][2], /new application/i);
  assert.equal(r.sent.length, 2);
});

test('THE TEST THAT MATTERS: the message carries nothing it does not need',
  { skip: !hasPhp }, () => {
  const r = lib(RECORD + `echo json_encode(av_notify_message($rec));`);
  const text = r.subject + '\n' + r.text;

  // Enough to act on.
  assert.match(text, /Chanda Mulenga/);
  assert.match(text, /AV-2026-0041/);
  assert.match(text, /2,000\.00/);
  assert.match(text, /4 Weeks/);

  // A WhatsApp message gets forwarded. An NRC number in one is gone for good.
  for (const secret of ['123456/78/1', '0966123456', 'chanda@example.test',
                        'Kabundi Road', '-12.958870', 'action=doc',
                        'Toyota Hilux', 'Mary Mulenga']) {
    assert.ok(!text.includes(secret), `a notification must not carry ${secret}\n${text}`);
  }

  // And it says where to go to see the rest.
  assert.match(text, /portal|sign in|avesta\.solutions/i);
});

test('one channel failing does not stop the other', { skip: !hasPhp }, () => {
  const r = lib(RECORD + `
    av_notify_save([
      'email'    => ['on' => true, 'to' => ['gray@example.test']],
      'whatsapp' => ['on' => true, 'to' => ['260970000000']]]);
    $out = av_notify_new_application($rec, function ($channel) {
      if ($channel === 'email') throw new RuntimeException('mail server down');
      return true;
    });
    echo json_encode(['sent' => $out['sent'], 'failed' => $out['failed']]);`);

  assert.equal(r.sent.length, 1);
  assert.equal(r.sent[0].channel, 'whatsapp');
  assert.equal(r.failed.length, 1);
  assert.equal(r.failed[0].channel, 'email');
  assert.match(r.failed[0].error, /mail server down/);
});

test('a sender that explodes never reaches the caller', { skip: !hasPhp }, () => {
  // The rule that matters most: an application is accepted whether or not
  // anybody could be told about it. Losing the borrower's application because
  // a mail server was down would be the worst possible trade.
  const r = lib(RECORD + `
    av_notify_save(['email' => ['on' => true, 'to' => ['gray@example.test']]]);
    $out = av_notify_new_application($rec, function () { throw new Error('catastrophe'); });
    echo json_encode(['ok' => true, 'failed' => count($out['failed'])]);`);
  assert.equal(r.ok, true);
  assert.equal(r.failed, 1);
});

test('a channel switched off is not used, even with recipients saved',
  { skip: !hasPhp }, () => {
  const r = lib(RECORD + `
    av_notify_save([
      'email'    => ['on' => false, 'to' => ['gray@example.test']],
      'whatsapp' => ['on' => true,  'to' => ['260970000000']]]);
    $seen = [];
    av_notify_new_application($rec, function ($c, $to) use (&$seen) { $seen[] = $c; return true; });
    echo json_encode($seen);`);
  assert.deepEqual(r, ['whatsapp']);
});

test('a bad address is refused rather than sent to', { skip: !hasPhp }, () => {
  const r = lib(RECORD + `
    av_notify_save(['email' => ['on' => true, 'to' => ['not-an-address','gray@example.test']]]);
    $seen = [];
    $out = av_notify_new_application($rec, function ($c, $to) use (&$seen) { $seen[] = $to; return true; });
    echo json_encode(['seen' => $seen, 'skipped' => $out['skipped']]);`);
  assert.deepEqual(r.seen, ['gray@example.test']);
  assert.ok(r.skipped.some(s => /not-an-address/.test(JSON.stringify(s))));
});

test('settings survive being written and read back, and are guarded',
  { skip: !hasPhp }, () => {
  const r = lib(`
    av_notify_save(['email' => ['on' => true, 'to' => ['gray@example.test']]]);
    av_notify_reset_cache();
    $back = av_notify_load();
    echo json_encode(['to' => $back['email']['to'], 'on' => $back['email']['on'],
      'guarded' => str_starts_with(file_get_contents(av_notify_store()), '<?php')]);`);
  assert.deepEqual(r.to, ['gray@example.test']);
  assert.equal(r.on, true);
  assert.equal(r.guarded, true);
});

test('the WhatsApp key is never kept in the settings store', { skip: !hasPhp }, () => {
  /* It is read from avesta-secrets.php instead. The backup deliberately
   * excludes secrets and includes settings, so a key saved here would end up
   * in an unencrypted archive that gets emailed around. */
  const r = lib(`
    av_notify_save(['whatsapp' => ['on' => true, 'to' => ['260970000000'],
                                   'api_key' => 'sk-should-not-persist']]);
    av_notify_reset_cache();
    echo json_encode(['raw' => file_get_contents(av_notify_store())]);`);
  assert.ok(!r.raw.includes('sk-should-not-persist'),
    'an API key must not be written to the settings store');
});

test('the settings store is backed up, web-denied and kept out of Git',
  { skip: !hasPhp }, () => {
  const allow = execFileSync(php, ['-r',
    'require "backup-lib.php"; echo json_encode(array_keys(av_backup_allowlist()));'],
    { encoding: 'utf8' });
  assert.ok(JSON.parse(allow).includes('notify.php'));
  assert.match(readFileSync('.htaccess', 'utf8'),
    /\^\([^)]*\bnotify\b[^)]*\)\\\.\(json\|jsonl\)\$/);
  assert.match(readFileSync('.gitignore', 'utf8'), /^\/notify\.(php|json)$/m);
});

test('the portal panel reads and writes the server settings, not localStorage', () => {
  const src = readFileSync('app/admin.php', 'utf8');
  assert.ok(src.includes('action=notifySettings'));
  assert.ok(src.includes('action=notifySave'));
  assert.ok(src.includes('action=notifyTest'));
  // The old panel opened a mailto: draft, which is not a notification and only
  // worked while somebody had the portal open.
  assert.ok(!src.includes("localStorage.getItem('avesta_notify_email')"),
    'the email panel should no longer read its settings from this browser');
  assert.match(src, /if \(tab === 'email'\) loadServerNotify\(\);/);
});
