import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

/* A loan is charged the terms it was signed under, and nobody else's.
 *
 * The late charge changed on 27 November 2026: from a flat 35% however long
 * the delay, to a ladder that starts at 35% and steps up every 28 days, with a
 * few days' grace at the start and a ceiling at twice the principal.
 *
 * The reason for the change: a flat 35% sat below the 55% charged for the
 * eight-week term, so on K4,000 a four-week loan that defaulted cost K5,400
 * for ever, against K6,200 for borrowing eight weeks honestly. Defaulting was
 * K800 cheaper at any point past week four, and an overdue eight-week loan
 * carried no late charge at all however long it ran.
 *
 * The reason for this file: av_amount_owing() reads whatever rates.json says
 * today, and av_rates_terms_for_record() returned 'ladder' for every record
 * that had any terms_version at all. So switching the ladder on would have
 * silently repriced every loan already signed — people who agreed to a flat
 * 35% would have started owing more each month, without being asked and
 * without anybody being told. That is the thing these tests exist to prevent.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

const cfg = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8'));
const CURRENT = cfg.version;
const FLAT_ERA = '2026-11-03';          // the flat-35% terms

function run(body) {
  const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
    `define('AVESTA_LIB', 1); require "api.php"; ${body}`],
    { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
  try { return JSON.parse(out); }
  catch { throw new Error('no JSON back:\n' + out.slice(0, 2000)); }
}

/** A real stored record: K4,000 over 4 weeks at 30%, disbursed and unpaid. */
const record = (over = {}) => JSON.stringify({
  _key: 'AV-T', full_name: 'Test Borrower', status: 'Disbursed',
  loan_amt: 4000.00, total_repay: '5200.00', int_rate: 30, sel_dur: '4 Weeks',
  disburse_date: '2026-01-05', _payments: [], ...over,
});

/** What loanSummary() charges a record on a given day. */
const charged = (over, today) => run(
  `$s = loanSummary(json_decode(${JSON.stringify(record(over))}, true), ${JSON.stringify(today)});
   echo json_encode(['total' => $s['total_due'] ?? $s['total'] ?? null,
                     'rate' => $s['penalty_rate'] ?? null,
                     'applied' => $s['penalty_applied'] ?? null,
                     'state' => $s['state'] ?? null,
                     'days' => $s['days_overdue'] ?? null,
                     'all' => $s]);`);

/* ── the guard ───────────────────────────────────────────────────────────── */

test('THE ONE THAT MATTERS: a loan signed under the flat terms keeps them for ever',
  { skip: !hasPhp }, () => {
  /* Signed 5 January under a flat 35%. Nearly eleven months late by the dates
   * below. Under the ladder it would owe the ceiling; under the terms it was
   * actually signed under it owes 35%, and that is what it must be charged. */
  const base = { terms_version: FLAT_ERA, disburse_date: '2026-01-05' };

  for (const today of ['2026-02-10', '2026-04-01', '2026-08-01', '2026-11-27', '2027-06-01']) {
    const r = charged(base, today);
    assert.equal(r.total, 5400,
      `on ${today} a flat-terms loan owes K5,400, not K${r.total} — `
      + `charging it the new ladder is repricing an agreement somebody signed`);
    assert.equal(r.rate, 35, `on ${today} the rate must stay 35, got ${r.rate}`);
  }
});

test('a loan signed before the engine existed is still left to a human',
  { skip: !hasPhp }, () => {
  // No terms_version: the old additive rule, in the record's own field. The
  // engine has never applied the ladder to these and still must not.
  const r = charged({ terms_version: undefined, late_payment_rate: 7.5 }, '2026-11-27');
  assert.equal(r.applied, false, JSON.stringify(r.all).slice(0, 300));
  assert.equal(r.total, 5200, 'it keeps its signed figures');
});

test('an unrecognised terms version is charged the gentlest terms we know',
  { skip: !hasPhp }, () => {
  /* A version this code has never heard of means we cannot show what the
   * borrower agreed to. The answer to that is not to charge them the harshest
   * thing on the shelf. */
  const r = charged({ terms_version: '2099-01-01' }, '2026-11-27');
  assert.equal(r.total, 5400,
    `an unknown version must fall back to the flat 35%, got K${r.total}`);
});

/* ── the new terms ───────────────────────────────────────────────────────── */

test('a loan signed under the new terms climbs, and stops at twice the principal',
  { skip: !hasPhp }, () => {
  const grace = cfg.overdue.gracePeriodDays;
  const step  = cfg.overdue.stepEveryDays;

  // Due 28 days after disbursement on 2026-06-01 → due 2026-06-29.
  const base = { terms_version: CURRENT, disburse_date: '2026-06-01' };
  const after = (days) => {
    const d = new Date(Date.UTC(2026, 5, 29));
    d.setUTCDate(d.getUTCDate() + grace + days);
    return d.toISOString().slice(0, 10);
  };

  assert.equal(charged(base, after(1)).rate, 35, 'the first band is 35%');
  assert.equal(charged(base, after(1)).total, 5400);

  assert.equal(charged(base, after(step)).rate, 45, 'the second band at 28 days');
  assert.equal(charged(base, after(step)).total, 5800);

  assert.equal(charged(base, after(step * 2)).rate, 55, 'the third at 56 days');
  assert.equal(charged(base, after(step * 2)).total, 6200);

  // And a ceiling: interest may never exceed the principal, so the most
  // anybody can owe is twice what they borrowed. The in duplum rule.
  const years = charged(base, '2029-01-01');
  assert.equal(years.rate, 100, `the ceiling is 100%, got ${years.rate}`);
  assert.equal(years.total, 8000, 'twice the K4,000 borrowed and not a ngwee more');
});

test('a day or two late costs nothing extra', { skip: !hasPhp }, () => {
  const grace = cfg.overdue.gracePeriodDays;
  assert.ok(grace >= 1, 'there should be some grace, or paying a day late is punished');

  const base = { terms_version: CURRENT, disburse_date: '2026-06-01' };  // due 2026-06-29
  for (let d = 1; d <= grace; d++) {
    const day = new Date(Date.UTC(2026, 5, 29));
    day.setUTCDate(day.getUTCDate() + d);
    const r = charged(base, day.toISOString().slice(0, 10));
    assert.equal(r.total, 5200,
      `${d} day(s) late must still owe the signed K5,200, got K${r.total}`);
    assert.equal(r.applied, false);
  }
});

test('an overdue eight-week loan finally carries a late charge', { skip: !hasPhp }, () => {
  /* It never did. The flat 35% was below its own 55% term rate, so the floor
   * held it at 55% and the penalty was nothing at all, for ever. */
  const eight = { terms_version: CURRENT, sel_dur: '8 Weeks', int_rate: 55,
                  total_repay: '6200.00', disburse_date: '2026-06-01' };

  // Early on it is still held at its term rate — going overdue must never
  // reduce what is owed, and 35% is less than 55%.
  const early = charged(eight, '2026-08-01');
  assert.equal(early.total, 6200, 'the floor still holds here');

  // But the ladder climbs past 55% and then it bites.
  const later = charged(eight, '2026-12-01');
  assert.ok(later.total > 6200,
    `an eight-week loan six months overdue must owe more than K6,200, got K${later.total}`);
  assert.equal(later.applied, true);
});

test('defaulting is no longer cheaper than borrowing honestly', { skip: !hasPhp }, () => {
  /* The whole point. An honest eight-week loan on K4,000 costs K6,200. A
   * four-week loan that defaults used to cost K5,400 for ever. */
  const base = { terms_version: CURRENT, disburse_date: '2026-06-01' };  // due 2026-06-29

  // Two months past due is the same elapsed time as the eight-week product.
  const twoMonths = charged(base, '2026-08-28');
  assert.ok(twoMonths.total >= 6200,
    `by the time an eight-week loan would have matured, defaulting must not be `
    + `cheaper than the K6,200 it would have cost — got K${twoMonths.total}`);

  // And it keeps getting dearer, rather than sitting still for ever.
  assert.ok(charged(base, '2026-12-28').total > twoMonths.total);
});

test('going overdue can still never reduce what is owed', { skip: !hasPhp }, () => {
  // The floor. Without it an eight-week loan became cheaper the day it fell
  // overdue, and the borrower was paid to default.
  const r = run(`
    $out = [];
    foreach ([1,2,3,4,8] as $w) {
      $cfg = av_rates_load();
      $signed = 400000 + (int) round(400000 * av_quote(400000, $w, $cfg)['rate'] / 100);
      foreach ([1, 20, 60, 400] as $late) {
        $o = av_amount_owing(400000, $w, date('Y-m-d', strtotime("2026-11-27 -{$late} days")),
                             '2026-11-27', 0, $cfg);
        $out[] = ['weeks' => $w, 'late' => $late,
                  'signed' => $signed, 'owing' => $o['totalDue']];
      }
    }
    echo json_encode($out);`);
  for (const row of r) {
    assert.ok(row.owing >= row.signed,
      `a ${row.weeks}-week loan ${row.late} days late owes ${row.owing}, `
      + `less than the ${row.signed} it was signed for`);
  }
});

/* ── the history has to be real ──────────────────────────────────────────── */

test('the old terms are written down, or the guard above guards nothing', () => {
  assert.ok(cfg.history && typeof cfg.history === 'object',
    'rates.json needs a history of past terms for signed loans to be priced by');

  const flat = cfg.history[FLAT_ERA];
  assert.ok(flat, `the flat-35% terms of ${FLAT_ERA} must be kept`);
  assert.equal(flat.startPercent, 35);
  assert.equal(flat.stepPercent, 0, 'those terms were flat, however long the delay');

  assert.ok(!(CURRENT in cfg.history),
    'the current terms live in overdue, not in the history');
  assert.notEqual(CURRENT, FLAT_ERA, 'a new terms version is what makes this a new deal');
});

test('the new terms are a ladder with a ceiling and some grace', () => {
  const o = cfg.overdue;
  assert.equal(o.startPercent, 35, 'it still starts where it did');
  assert.ok(o.stepPercent > 0, 'a flat penalty is what left defaulting cheaper');
  assert.ok(o.stepEveryDays >= 28);
  assert.equal(o.capPercent, 100, 'interest stops at the principal — in duplum');
  assert.ok(o.gracePeriodDays >= 1, 'paying a day late should not be punished');
});
