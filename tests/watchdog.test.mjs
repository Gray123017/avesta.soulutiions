import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* The watchdog: the thing that notices.
 *
 * Everything it looks at is already written down — the audit log records who
 * viewed which identity document and when, the loan book knows which loans are
 * past due, the remittance store knows which money arrived and could not be
 * matched. None of it was ever read by anybody unless somebody went looking.
 *
 * It is a set of rules over that data. Calling it an AI agent would oversell
 * it: there is no model here and no judgement, only thresholds that somebody
 * has to agree with. The thresholds are the whole design, so they are what
 * these tests are about.
 *
 * Two rules it is built around, both learned from notify-lib.php:
 *
 *   A digest nobody reads is worse than no digest, because it looks like
 *   monitoring. So nothing is sent when nothing has happened, and a standing
 *   problem is not re-sent every night until it becomes wallpaper.
 *
 *   A digest carries no applicant's name and no NRC number. It gets forwarded.
 *   Staff usernames are the point of an alert and stay; the applicant's
 *   identity is one sign-in away, behind an account that is logged.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

/** Run a snippet against watchdog-lib.php with its store in a temp directory. */
function lib(body) {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-watchdog-'));
  try {
    const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "watchdog-lib.php";
       av_watchdog_path(${JSON.stringify(join(dir, 'watchdog.json'))});
       av_notify_path(${JSON.stringify(join(dir, 'notify.json'))});
       ${body}`], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
    try { return JSON.parse(out); }
    catch { throw new Error('watchdog-lib.php did not return JSON:\n' + out.slice(0, 2000)); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

/* ── helpers for building the input ──────────────────────────────────────── */

const NOW = '2026-11-27T09:00:00+00:00';        // 11:00 in Ndola
const SINCE = '2026-11-26T09:00:00+00:00';      // a day's window

/** An audit row, the shape av_audit() writes. */
const row = (action, over = {}) => ({
  ts: '2026-11-27T08:00:00+00:00', user: 'staffone', user_id: 'u_1',
  role: 'staff', action, target: '', detail: {}, ip: '41.77.0.1', ...over,
});

/** A PHP expression that rebuilds a JavaScript value as a PHP array. */
const asPhp = (value) =>
  `json_decode(${JSON.stringify(JSON.stringify(value))}, true)`;

const scan = (input, extra = '') => lib(`
  ${extra}
  echo json_encode(av_watchdog_scan(${asPhp({ now: NOW, since: SINCE, ...input })}));
`);

const kinds = (r) => r.findings.map(f => f.kind);
const find = (r, kind) => r.findings.find(f => f.kind === kind);

/* ── nothing happening ──────────────────────────────────────────────────── */

test('a quiet day produces nothing at all', { skip: !hasPhp }, () => {
  const r = scan({
    audit: [row('login.success'), row('records.list'), row('logout')],
    records: [], remittances: [],
  });
  assert.deepEqual(r.findings, [],
    'ordinary work must not raise anything, or the digest becomes wallpaper');
});

test('and nothing is sent on a quiet day, even with recipients configured',
  { skip: !hasPhp }, () => {
  const r = lib(`
    av_notify_save(['email' => ['on' => true, 'to' => ['gray@example.test']]]);
    $out = av_watchdog_run(function () { return true; }, ${JSON.stringify(NOW)},
      ['audit' => [], 'records' => [], 'remittances' => []]);
    echo json_encode(['sent' => $out['sent'], 'findings' => count($out['findings'])]);`);
  assert.equal(r.findings, 0);
  assert.deepEqual(r.sent, [],
    'a nightly "all is well" message trains everybody to ignore the next one');
});

/* ── signing in ─────────────────────────────────────────────────────────── */

test('a burst of failed logins against one account is raised', { skip: !hasPhp }, () => {
  const audit = [];
  for (let i = 0; i < 6; i++) {
    audit.push(row('login.failed', { user: 'adminone', ip: '203.0.113.' + i }));
  }
  const r = scan({ audit, records: [], remittances: [] });
  const f = find(r, 'failed_logins');
  assert.ok(f, `expected failed_logins, got ${kinds(r)}`);
  assert.equal(f.severity, 'high');
  assert.equal(f.count, 6);
  assert.match(f.headline, /adminone/);
});

test('a burst from one address is raised even when it is spraying usernames',
  { skip: !hasPhp }, () => {
  // Six different accounts, one failure each — no single account looks under
  // attack, which is exactly how somebody with a list of usernames behaves.
  const audit = ['a', 'b', 'c', 'd', 'e', 'f'].map(u =>
    row('login.failed', { user: u, ip: '198.51.100.7' }));
  const r = scan({ audit, records: [], remittances: [] });
  const f = find(r, 'failed_logins');
  assert.ok(f, `expected failed_logins, got ${kinds(r)}`);
  assert.match(f.headline, /198\.51\.100\.7/);
});

test('a handful of failures is somebody mistyping their password', { skip: !hasPhp }, () => {
  const audit = [row('login.failed', { user: 'staffone' }),
                 row('login.failed', { user: 'staffone' }),
                 row('login.failed', { user: 'staffone' })];
  assert.deepEqual(kinds(scan({ audit, records: [], remittances: [] })), []);
});

test('a lockout and an attempt on a disabled account are both raised',
  { skip: !hasPhp }, () => {
  const r = scan({
    audit: [row('login.locked_out', { user: 'adminone' }),
            row('login.disabled_account', { user: 'oldstaff' })],
    records: [], remittances: [],
  });
  assert.ok(find(r, 'lockout'), kinds(r).join(','));
  assert.ok(find(r, 'disabled_account_attempt'), kinds(r).join(','));
  assert.equal(find(r, 'lockout').severity, 'high');
});

test('an account reaching above its role is raised', { skip: !hasPhp }, () => {
  // Somebody is signed in and asking for something their role refuses. Either
  // a staff member is exploring, or a session is not in the hands it belongs
  // to. Both are worth a look on the day it happens, not a month later.
  const r = scan({
    audit: [row('access.denied', { user: 'staffone', target: '/portal.php' }),
            row('api.forbidden', { user: 'staffone', target: 'users' })],
    records: [], remittances: [],
  });
  const f = find(r, 'privilege_refusals');
  assert.ok(f, kinds(r).join(','));
  assert.equal(f.severity, 'high');
  assert.equal(f.count, 2);
});

/* ── identity documents ─────────────────────────────────────────────────── */

test('THE ONE THAT MATTERS: identity documents read in the middle of the night',
  { skip: !hasPhp }, () => {
  /* The server keeps time in UTC and Ndola is two hours ahead of it, so this
   * rule is wrong in both directions if the offset is skipped: 23:30 UTC looks
   * like the end of the evening and is half past one in the morning, and 05:00
   * UTC looks nocturnal and is a reasonable 07:00 start. */
  const r = scan({
    audit: [row('document.viewed', { ts: '2026-11-26T23:30:00+00:00', user: 'staffone' })],
    records: [], remittances: [],
  });
  const f = find(r, 'documents_out_of_hours');
  assert.ok(f, `01:30 in Ndola must be out of hours — got ${kinds(r)}`);
  assert.equal(f.severity, 'high');
  assert.match(f.headline, /staffone/);

  // 07:00 in Ndola is somebody starting early, not somebody worth waking for.
  const early = scan({
    audit: [row('document.viewed', { ts: '2026-11-27T05:00:00+00:00', user: 'staffone' })],
    records: [], remittances: [],
  });
  assert.ok(!find(early, 'documents_out_of_hours'),
    '07:00 in Ndola is a working morning, not a night-time read');

  // And the boundary is read in Ndola time too: 18:00 UTC is 20:00 local.
  const evening = scan({
    audit: [row('document.viewed', { ts: '2026-11-26T18:30:00+00:00', user: 'staffone' })],
    records: [], remittances: [],
  });
  assert.ok(find(evening, 'documents_out_of_hours'),
    '20:30 in Ndola is after hours');
});

test('one person paging through many applicants\' documents is raised',
  { skip: !hasPhp }, () => {
  const audit = [];
  for (let i = 0; i < 15; i++) {
    audit.push(row('document.viewed', { user: 'staffone', target: 'AV-2026-' + i }));
  }
  const r = scan({ audit, records: [], remittances: [] });
  const f = find(r, 'documents_bulk');
  assert.ok(f, kinds(r).join(','));
  assert.equal(f.count, 15);
  assert.match(f.headline, /staffone/);

  // Looking at a few applications is the job.
  const few = [];
  for (let i = 0; i < 4; i++) few.push(row('document.viewed', { target: 'AV-' + i }));
  assert.ok(!find(scan({ audit: few, records: [], remittances: [] }), 'documents_bulk'));
});

test('a borrower reaching for somebody else\'s document is raised at once',
  { skip: !hasPhp }, () => {
  const r = scan({
    audit: [row('document.denied', { user: 'chanda', role: 'borrower' })],
    records: [], remittances: [],
  });
  const f = find(r, 'document_denied');
  assert.ok(f, kinds(r).join(','));
  assert.equal(f.severity, 'high');
});

test('the whole loan book leaving the server is always worth a line',
  { skip: !hasPhp }, () => {
  const r = scan({
    audit: [row('backup.downloaded', { user: 'gray', role: 'super_admin' })],
    records: [], remittances: [],
  });
  assert.ok(find(r, 'backup_taken'), kinds(r).join(','));
});

test('deletions are raised, because nothing else will mention them',
  { skip: !hasPhp }, () => {
  const r = scan({
    audit: [row('record.deleted', { target: 'AV-2026-0041' }),
            row('payment.deleted', { target: 'AV-2026-0041' })],
    records: [], remittances: [],
  });
  assert.ok(find(r, 'records_deleted'), kinds(r).join(','));
  assert.ok(find(r, 'payments_deleted'), kinds(r).join(','));
});

/* ── the loan book ──────────────────────────────────────────────────────── */

const loan = (over = {}) => ({
  _key: 'AV-2026-0041', full_name: 'Chanda Mulenga', nrc_number: '123456/78/1',
  status: 'Disbursed', disburse_date: '2026-09-01', loan_amt: 2000.00,
  total_repay: '2600.00', sel_dur: '4 Weeks', int_rate: 30, _payments: [], ...over,
});

test('loans past their due date are counted, with what is still owed',
  { skip: !hasPhp }, () => {
  // 'Repaid' and 'Rejected' are the real closed statuses — the dropdown offers
  // New Application, Under Review, Approved, Disbursed, Repaid and Rejected,
  // and av_status_closed() is written against that set.
  const r = scan({
    audit: [],
    records: [loan({ _key: 'AV-1' }), loan({ _key: 'AV-2' }),
              // Marked repaid, so not overdue however old it is.
              loan({ _key: 'AV-3', status: 'Repaid' }),
              // Paid off in full but nobody has changed the status yet. The
              // status is not the thing that settles a loan; the money is.
              loan({ _key: 'AV-5', _payments: [{ id: 'p1', amount: 2600.00,
                                                 date: '2026-09-28' }] }),
              // Disbursed this week: not due yet.
              loan({ _key: 'AV-4', disburse_date: '2026-11-25' })],
    remittances: [],
  });
  const f = find(r, 'overdue_loans');
  assert.ok(f, kinds(r).join(','));
  assert.equal(f.count, 2);
  assert.match(f.headline, /2 loans overdue/);
  assert.match(f.detail, /AV-1/);
  assert.match(f.detail, /AV-2/);
  assert.doesNotMatch(f.detail, /AV-3|AV-4|AV-5/);

  // And the amount is what is actually outstanding, not the sum of the totals.
  assert.match(f.headline, /K 5,200\.00/);
});

test('a loan marked paid out with no date is raised as its own problem',
  { skip: !hasPhp }, () => {
  /* The payroll schedule skips a loan with no disburse_date, and so does the
   * overdue rule above — there is no day to count from. So a loan in this
   * state is money that has gone out and can never be chased, and nothing
   * else in the system will ever mention it. */
  const r = scan({
    audit: [],
    records: [loan({ _key: 'AV-9', status: 'Disbursed', disburse_date: '' })],
    remittances: [],
  });
  const f = find(r, 'undated_disbursements');
  assert.ok(f, kinds(r).join(','));
  assert.equal(f.severity, 'high');
  assert.match(f.detail, /AV-9/);
  assert.ok(!find(r, 'overdue_loans'),
    'it cannot be overdue and undated at once — the second is the real finding');
});

test('money an employer sent that could not be matched is raised',
  { skip: !hasPhp }, () => {
  const r = scan({
    audit: [], records: [],
    remittances: [{ id: 'r1', employer_id: 'e1', period: '2026-11',
                    received_ngwee: 150000, held_ngwee: 45000, status: 'held' },
                  { id: 'r2', employer_id: 'e1', period: '2026-10',
                    received_ngwee: 100000, held_ngwee: 0, status: 'posted' }],
  });
  const f = find(r, 'held_remittances');
  assert.ok(f, kinds(r).join(','));
  assert.equal(f.count, 1);
  assert.match(f.headline, /450\.00/, 'say how much is sitting unallocated');
});

/* ── what goes out ──────────────────────────────────────────────────────── */

test('THE OTHER ONE THAT MATTERS: the digest carries no applicant',
  { skip: !hasPhp }, () => {
  const r = lib(`
    $scan = av_watchdog_scan(${asPhp({
      now: NOW, since: SINCE,
      audit: [{ ts: '2026-11-26T23:30:00+00:00', user: 'staffone', user_id: 'u_1',
                role: 'staff', action: 'document.viewed', target: 'AV-2026-0041',
                detail: { field: 'doc_nrc_front' }, ip: '41.77.0.1' }],
      records: [{ _key: 'AV-2026-0041', full_name: 'Chanda Mulenga',
                  nrc_number: '123456/78/1', phone: '0966123456',
                  address: 'Plot 44 Kabundi Road, Ndola',
                  gps_latitude: '-12.958870', collateral_items: 'Toyota Hilux',
                  next_of_kin: 'Mary Mulenga', status: 'Disbursed',
                  disburse_date: '2026-09-01', loan_amt: 2000, total_repay: '2600.00',
                  sel_dur: '4 Weeks', _payments: [] }],
      remittances: [],
    })});
    echo json_encode(av_watchdog_message($scan['findings'], $scan['window']));`);

  const text = r.subject + '\n' + r.text;

  // Enough to act on: what happened, who, and where to go.
  assert.match(text, /staffone/, text);
  assert.match(text, /portal|sign in|avesta\.solutions/i, text);

  // A digest gets forwarded. An NRC number in one is gone for good.
  for (const secret of ['Chanda Mulenga', '123456/78/1', '0966123456',
                        'Kabundi Road', '-12.958870', 'Toyota Hilux',
                        'Mary Mulenga', 'action=doc']) {
    assert.ok(!text.includes(secret), `the digest must not carry ${secret}\n${text}`);
  }
});

test('the most serious thing is the first thing read', { skip: !hasPhp }, () => {
  const r = lib(`
    $scan = av_watchdog_scan(${asPhp({
      now: NOW, since: SINCE,
      audit: [{ ts: '2026-11-27T08:00:00+00:00', user: 'gray', user_id: 'u_0',
                role: 'super_admin', action: 'backup.downloaded', target: '',
                detail: {}, ip: '41.77.0.1' },
               { ts: '2026-11-26T23:30:00+00:00', user: 'staffone', user_id: 'u_1',
                 role: 'staff', action: 'document.viewed', target: 'AV-1',
                 detail: {}, ip: '41.77.0.1' }],
      records: [], remittances: [],
    })});
    echo json_encode(['order' => array_column($scan['findings'], 'severity'),
                      'text' => av_watchdog_message($scan['findings'], $scan['window'])['text']]);`);
  assert.equal(r.order[0], 'high', JSON.stringify(r.order));
  // The night-time document read is above the backup in the message itself.
  assert.ok(r.text.indexOf('out of hours') < r.text.indexOf('Backup')
         || r.text.indexOf('out of hours') < r.text.indexOf('backup'),
    r.text);
});

/* ── not saying the same thing every night ──────────────────────────────── */

test('a standing problem is reported once, not every night', { skip: !hasPhp }, () => {
  /* An overdue loan is still overdue tomorrow. Saying so every night is how a
   * digest stops being read. Incidents out of the audit log need no cooldown:
   * the window moves past them on its own. */
  const input = asPhp({
    audit: [],
    records: [{ _key: 'AV-1', status: 'Disbursed', disburse_date: '2026-09-01',
                loan_amt: 2000, total_repay: '2600.00', sel_dur: '4 Weeks',
                _payments: [] }],
    remittances: [],
  });
  const r = lib(`
    av_notify_save(['email' => ['on' => true, 'to' => ['gray@example.test']]]);
    $in = ${input};
    $first  = av_watchdog_run(function () { return true; }, '2026-11-27T09:00:00+00:00', $in);
    $second = av_watchdog_run(function () { return true; }, '2026-11-28T09:00:00+00:00', $in);
    $later  = av_watchdog_run(function () { return true; }, '2026-12-10T09:00:00+00:00', $in);
    echo json_encode([
      'first'  => ['findings' => count($first['findings']),  'sent' => count($first['sent'])],
      'second' => ['findings' => count($second['findings']), 'sent' => count($second['sent'])],
      'later'  => ['findings' => count($later['findings']),  'sent' => count($later['sent'])],
    ]);`);

  assert.equal(r.first.findings, 1);
  assert.equal(r.first.sent, 1);

  assert.equal(r.second.sent, 0, 'the same overdue loan must not be sent again tomorrow');

  // But it must come back eventually, or a problem left alone goes quiet.
  assert.equal(r.later.sent, 1,
    'after the cooldown a problem still outstanding has to be raised again');
});

/* ── it must never break anything ───────────────────────────────────────── */

test('a sender that explodes never reaches the caller', { skip: !hasPhp }, () => {
  const r = lib(`
    av_notify_save(['email' => ['on' => true, 'to' => ['gray@example.test']]]);
    $out = av_watchdog_run(function () { throw new Error('catastrophe'); },
      ${JSON.stringify(NOW)},
      ${asPhp({ audit: [{ ts: '2026-11-26T23:30:00+00:00', user: 'x',
                          action: 'document.viewed', role: 'staff', target: '',
                          detail: {}, ip: '1.1.1.1' }],
                records: [], remittances: [] })});
    echo json_encode(['ok' => true, 'failed' => count($out['failed'])]);`);
  assert.equal(r.ok, true);
  assert.equal(r.failed, 1);
});

test('rubbish in the audit log is stepped over, not thrown on', { skip: !hasPhp }, () => {
  const r = scan({
    audit: [null, 'not a row', { action: 'document.viewed' },        // no ts
            { ts: 'not a date', action: 'document.viewed', user: 'x' },
            row('login.locked_out')],
    records: [null, 'nonsense', {}],
    remittances: [null, 'nonsense'],
  });
  assert.ok(find(r, 'lockout'), 'the good row still has to be found');
});

/* ── the thresholds are not published ───────────────────────────────────── */

test('the thresholds are not readable from the web', { skip: !hasPhp }, () => {
  /* rates.json and categories.json are published on purpose: an applicant is
   * entitled to the price before they borrow. These are the opposite. Telling
   * a stranger that the sixth failed login is the one that raises an alarm
   * tells them they have five for free. */
  const published = execFileSync('ls', ['assets/avesta'], { encoding: 'utf8' });
  assert.ok(!/watchdog/.test(published),
    'watchdog thresholds must not sit in the published config directory');

  const t = lib('echo json_encode(av_watchdog_thresholds());');
  assert.ok(t.failed_logins >= 4, JSON.stringify(t));
  assert.ok(t.documents_bulk >= 5, JSON.stringify(t));
  assert.ok(t.hours.zone.includes('Lusaka') || t.hours.zone.includes('Harare'),
    `business hours must be read in Zambian time, got ${t.hours.zone}`);
});

test('the store is backed up, web-denied and kept out of Git', { skip: !hasPhp }, () => {
  const allow = execFileSync(php, ['-r',
    'require "backup-lib.php"; echo json_encode(array_keys(av_backup_allowlist()));'],
    { encoding: 'utf8' });
  assert.ok(JSON.parse(allow).includes('watchdog.php'),
    'a new store has to be in the backup or it is lost on a restore');
  assert.match(readFileSync('.htaccess', 'utf8'),
    /\^\([^)]*\bwatchdog\b[^)]*\)\\\.\(json\|jsonl\)\$/);
  assert.match(readFileSync('.gitignore', 'utf8'), /^\/watchdog\.(php|json)$/m);
});
