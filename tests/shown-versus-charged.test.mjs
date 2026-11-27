import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* The figure on the form and the figure on the loan.
 *
 * The form used to work out its own prices, including one the table did not
 * sell: it quoted eight weeks at 50% while rates.json charged 55%. The server
 * re-prices every application from the table and overwrites int_rate,
 * total_int and total_repay, so the loan book itself is right — but it did not
 * touch sel_dur, which is the text the applicant read and signed:
 *
 *     sel_dur  = "8 Weeks (50% interest)"      what they were shown
 *     int_rate = 55                            what they are charged
 *
 * Two things here. The record stops disagreeing with itself from now on, and
 * the records that already disagree get found, rather than somebody being
 * asked to go through the loan book by hand looking for them.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

function run(body, pre = 'define("AVESTA_LIB", 1); require "api.php";') {
  const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r', pre + body],
    { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
  try { return JSON.parse(out); }
  catch { throw new Error('no JSON back:\n' + out.slice(0, 1500)); }
}

/* ── what gets written from now on ───────────────────────────────────────── */

test('the duration a record carries says the rate the record is charged',
  { skip: !hasPhp }, () => {
  const got = run(`echo json_encode([
    av_canonical_duration(8, 55), av_canonical_duration(4, 30),
    av_canonical_duration(1, 15), av_canonical_duration(2, 20)]);`);
  assert.deepEqual(got, ['8 Weeks (55% interest)', '4 Weeks (30% interest)',
                         '1 Week (15% interest)', '2 Weeks (20% interest)']);
});

test('and it still reads back as the right number of weeks', { skip: !hasPhp }, () => {
  // The whole point of the text. It has to survive the round trip.
  const got = run(`
    $out = [];
    foreach ([[1,15],[2,20],[3,25],[4,30],[8,55]] as [$w, $r]) {
      $out[] = av_weeks_from_duration(av_canonical_duration($w, $r));
    }
    echo json_encode($out);`);
  assert.deepEqual(got, [1, 2, 3, 4, 8]);
});

/* ── finding the ones that already disagree ──────────────────────────────── */

const shownRate = (record) => run(
  `echo json_encode(av_shown_rate(json_decode(${JSON.stringify(JSON.stringify(record))}, true)));`,
  'require "rates-lib.php";');

test('the rate an applicant was shown is read out of the text they signed',
  { skip: !hasPhp }, () => {
  assert.equal(shownRate({ sel_dur: '8 Weeks (50% interest)' }), 50);
  assert.equal(shownRate({ sel_dur: '4 Weeks (30% interest)' }), 30);
  // Where the original was kept, that is the one they actually read.
  assert.equal(shownRate({ sel_dur: '8 Weeks (55% interest)',
                           sel_dur_submitted: '8 Weeks (50% interest)' }), 50);
  // Nothing to compare: an older record with no rate in the text.
  assert.equal(shownRate({ sel_dur: '4 Weeks' }), null);
  assert.equal(shownRate({}), null);
});

test('THE ONE THAT MATTERS: a record charged more than it showed is found',
  { skip: !hasPhp }, () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-shown-'));
  try {
    const r = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "watchdog-lib.php";
       av_watchdog_path(${JSON.stringify(join(dir, 'watchdog.json'))});
       av_notify_path(${JSON.stringify(join(dir, 'notify.json'))});
       echo json_encode(av_watchdog_scan(json_decode(${JSON.stringify(JSON.stringify({
         now: '2026-11-27T09:00:00+00:00', since: '2026-11-26T09:00:00+00:00',
         audit: [], remittances: [],
         records: [
           // Shown 50%, charged 55%. The eight-week applicants.
           { _key: 'AV-1', status: 'Disbursed', disburse_date: '2026-11-20',
             sel_dur: '8 Weeks (50% interest)', int_rate: 55,
             loan_amt: 4000, total_repay: '6200.00', _payments: [] },
           // Agrees with itself.
           { _key: 'AV-2', status: 'Disbursed', disburse_date: '2026-11-20',
             sel_dur: '4 Weeks (30% interest)', int_rate: 30,
             loan_amt: 4000, total_repay: '5200.00', _payments: [] },
           // Older record with no rate in the text: nothing to compare.
           { _key: 'AV-3', status: 'Disbursed', disburse_date: '2026-11-20',
             sel_dur: '4 Weeks', int_rate: 30,
             loan_amt: 4000, total_repay: '5200.00', _payments: [] },
         ],
       }))}, true)));`], { encoding: 'utf8' });

    const scan = JSON.parse(r);
    const f = scan.findings.find(x => x.kind === 'rate_shown_differs');
    assert.ok(f, `expected the mismatch to be found, got ${scan.findings.map(x => x.kind)}`);
    assert.equal(f.severity, 'high', 'somebody signed one figure and is charged another');
    assert.equal(f.count, 1);
    assert.match(f.detail, /AV-1/);
    assert.doesNotMatch(f.detail, /AV-2|AV-3/);
    // It has to say which way round, or it cannot be acted on.
    assert.match(f.headline + ' ' + f.detail, /50/);
    assert.match(f.headline + ' ' + f.detail, /55/);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('a record shown MORE than it is charged is found too', { skip: !hasPhp }, () => {
  // The other direction is not a windfall for us, it is still a record that
  // disagrees with the paper, and somebody has to decide which one is right.
  const dir = mkdtempSync(join(tmpdir(), 'avesta-shown2-'));
  try {
    const r = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "watchdog-lib.php";
       av_watchdog_path(${JSON.stringify(join(dir, 'watchdog.json'))});
       av_notify_path(${JSON.stringify(join(dir, 'notify.json'))});
       echo json_encode(av_watchdog_scan(json_decode(${JSON.stringify(JSON.stringify({
         now: '2026-11-27T09:00:00+00:00', since: '2026-11-26T09:00:00+00:00',
         audit: [], remittances: [],
         records: [{ _key: 'AV-9', status: 'Disbursed', disburse_date: '2026-11-20',
                     sel_dur: '4 Weeks (40% interest)', int_rate: 30,
                     loan_amt: 4000, total_repay: '5200.00', _payments: [] }],
       }))}, true)));`], { encoding: 'utf8' });
    const f = JSON.parse(r).findings.find(x => x.kind === 'rate_shown_differs');
    assert.ok(f);
    assert.match(f.detail, /AV-9/);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('and the digest still names no applicant', { skip: !hasPhp }, () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-shown3-'));
  try {
    const r = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
      `require "watchdog-lib.php";
       av_watchdog_path(${JSON.stringify(join(dir, 'watchdog.json'))});
       av_notify_path(${JSON.stringify(join(dir, 'notify.json'))});
       $scan = av_watchdog_scan(json_decode(${JSON.stringify(JSON.stringify({
         now: '2026-11-27T09:00:00+00:00', since: '2026-11-26T09:00:00+00:00',
         audit: [], remittances: [],
         records: [{ _key: 'AV-1', full_name: 'Chanda Mulenga',
                     nrc_number: '123456/78/1', status: 'Disbursed',
                     disburse_date: '2026-11-20', sel_dur: '8 Weeks (50% interest)',
                     int_rate: 55, loan_amt: 4000, total_repay: '6200.00',
                     _payments: [] }],
       }))}, true));
       echo json_encode(av_watchdog_message($scan['findings'], $scan['window']));`],
      { encoding: 'utf8' });
    const text = JSON.parse(r).subject + '\n' + JSON.parse(r).text;
    for (const secret of ['Chanda Mulenga', '123456/78/1']) {
      assert.ok(!text.includes(secret), `the digest must not carry ${secret}\n${text}`);
    }
    assert.match(text, /AV-1/, 'but the reference has to be there, or it cannot be looked up');
  } finally { rmSync(dir, { recursive: true, force: true }); }
});
