import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

/* Exercises rates-lib.php, which is what actually binds a borrower. The
 * browser copy in domain.js is checked against the same table by
 * scripts/check-rates.mjs. */

const cfg = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8'));

/** Call one function in rates-lib.php and get its return value back as JSON. */
function php(expr) {
  const code = `require "rates-lib.php"; echo json_encode(${expr});`;
  const out = execFileSync('php', ['-d', 'error_reporting=E_ALL', '-r', code], {
    encoding: 'utf8',
  });
  try {
    return JSON.parse(out);
  } catch {
    throw new Error('rates-lib.php did not return JSON:\n' + out);
  }
}

const K = (ngwee) => ngwee / 100;

test('published terms price as the site says they do', () => {
  // K1,000 over 2 weeks gives K200 interest — the worked example in the FAQ.
  const two = php('av_quote(100000, 2)');
  assert.equal(two.rate, 20);
  assert.equal(K(two.interest), 200);
  assert.equal(K(two.total), 1200);

  // The figure on the calculator and all through the design.
  const four = php('av_quote(400000, 4)');
  assert.equal(four.rate, 30);
  assert.equal(K(four.total), 5200);

  // The two-month product.
  const eight = php('av_quote(400000, 8)');
  assert.equal(eight.rate, 45);
  assert.equal(K(eight.total), 5800);
});

test('a term that is not published is refused rather than guessed', () => {
  assert.throws(() => php('av_quote(400000, 6)'), /No published rate|Uncaught/);
});

test('instalments always add up to the total, to the ngwee', () => {
  // 5,200.00 over 3 does not divide evenly; the remainder must not vanish.
  const parts = php('av_instalments(520000, 3)');
  assert.equal(parts.length, 3);
  assert.equal(parts.reduce((a, b) => a + b, 0), 520000);
  assert.deepEqual(parts, [173334, 173333, 173333]);
});

test('a loan is not overdue on its due date, and is the next day', () => {
  assert.equal(php('av_overdue_state("2026-11-28","2026-11-28")').overdue, false);
  const late = php('av_overdue_state("2026-11-28","2026-11-29")');
  assert.equal(late.overdue, true);
  assert.equal(late.daysOverdue, 1);
  assert.equal(late.bandPercent, cfg.overdue.startPercent);
});

test('the band steps once a month, and says when it steps again', () => {
  // The band steps ON the boundary, so the ladder reads as specified:
  // 35% at week four, 40% at week eight, 45% at week twelve.
  //
  // Day 1 to day 27 is the first band.
  assert.equal(php('av_overdue_state("2026-11-28","2026-12-25")').bandPercent, 35);
  // Day 28 — week eight — begins the second.
  const second = php('av_overdue_state("2026-11-28","2026-12-26")');
  assert.equal(second.daysOverdue, 28);
  assert.equal(second.bandPercent, 40);
  assert.equal(second.nextStepOn, '2027-01-23');
  assert.equal(second.nextBand, 45);
  // Day 56 — week twelve — the third.
  assert.equal(php('av_overdue_state("2026-11-28","2027-01-23")').bandPercent, 45);
});

test('the band replaces the term rate instead of adding to it', () => {
  // K4,000 at 30% is K5,200. One day overdue it is 35% of the PRINCIPAL —
  // K5,400 — not the total plus a further 35%.
  const owing = php('av_amount_owing(400000, 4, "2026-11-28", "2026-11-29")');
  assert.equal(owing.termRate, 30);
  assert.equal(owing.rateApplied, 35);
  assert.equal(K(owing.totalDue), 5400);
  assert.notEqual(K(owing.totalDue), 5200 * 1.35);
});

test('interest stops at the principal, so a debt cannot run away', () => {
  // Ten years late. Without the cap the band would be far past 100%.
  const ancient = php('av_overdue_state("2016-11-28","2026-11-28")');
  assert.equal(ancient.capped, true);
  assert.equal(ancient.bandPercent, cfg.overdue.capPercent);
  assert.equal(ancient.nextStepOn, null, 'a capped loan has no next step');

  const owing = php('av_amount_owing(400000, 4, "2016-11-28", "2026-11-28")');
  assert.equal(K(owing.totalDue), 8000, 'never more than twice the principal');
});

test('KNOWN GAP: defaulting on four weeks undercuts the two-month product', () => {
  // Documented rather than hidden. A borrower wanting eight weeks of money
  // pays less by taking the four-week loan and not paying.
  //
  //   four weeks, settled at week 8 (day 28, where the band steps to 40%)
  const defaulted = php('av_amount_owing(400000, 4, "2026-11-28", "2026-12-26")');
  //   the honest two-month product
  const honest = php('av_quote(400000, 8)');

  assert.equal(K(defaulted.totalDue), 5600);
  assert.equal(K(honest.total), 5800);
  assert.ok(
    defaulted.totalDue < honest.total,
    'if this ever fails the gap has been closed — update this test and rates.json'
  );

  // K200 at week eight, where it used to be K400, and parity arrives at
  // week twelve when the band reaches the two-month rate.
  assert.equal(K(php('av_amount_owing(400000, 4, "2026-11-28", "2026-12-25")').totalDue), 5400); // day 27
  assert.equal(K(php('av_amount_owing(400000, 4, "2026-11-28", "2027-01-23")').totalDue), 5800); // day 56
});

test('loans signed under the old rules keep them', () => {
  // No terms_version means it predates the ladder. Its agreement said 7.5% a
  // week, additive, and that is what binds — the ladder must not touch it.
  const legacy = php('av_rates_terms_for_record(["late_payment_rate" => "7.5% per week overdue"])');
  assert.equal(legacy.engine, 'legacy');
  assert.equal(legacy.legacyLatePercentPerWeek, 7.5);

  const current = php(`av_rates_terms_for_record(["terms_version" => "${cfg.version}"])`);
  assert.equal(current.engine, 'ladder');
});

test('a broken rate table stops the request instead of pricing at zero', () => {
  assert.throws(
    () => php('av_rates_load("/nonexistent/rates.json")'),
    /Rate table missing|Uncaught/
  );
});

test('going overdue never costs a borrower less than paying on time', () => {
  // The band REPLACES the term rate and starts at 35%, which is below the 45%
  // charged for eight weeks. Without a floor, an eight-week loan got K400
  // cheaper the day it fell overdue — the borrower was paid to default.
  const onTime = php('av_quote(400000, 8)');
  assert.equal(K(onTime.total), 5800);

  // Day 1 and day 27 sit in the 35% band; day 28 steps to 40%. Both are
  // below the 45% term rate, so both are floored.
  for (const [asOf, rawBand] of [['2026-12-27', 35], ['2027-01-21', 35], ['2027-01-23', 40]]) {
    const late = php(`av_amount_owing(400000, 8, "2026-12-26", "${asOf}")`);
    assert.ok(
      late.totalDue >= onTime.total,
      `${asOf}: overdue total ${K(late.totalDue)} is below the on-time total ${K(onTime.total)}`
    );
    assert.equal(late.rateApplied, 45, `${asOf}: should be held at the term rate`);
    assert.equal(late.heldAtTermRate, true);
    assert.equal(late.bandPercent, rawBand, 'the raw band is still reported for the audit trail');
  }

  // At day 56 the ladder reaches the term rate and the floor stops biting.
  const past = php('av_amount_owing(400000, 8, "2026-12-26", "2027-02-20")');
  assert.equal(past.rateApplied, 45);
  assert.equal(past.bandPercent, 45);
  assert.equal(past.heldAtTermRate, false);
});

test('the floor does not disturb the four-week ladder', () => {
  // 35% is above the four-week rate of 30%, so nothing is floored here and
  // the bands apply exactly as configured.
  const cases = [
    ['2026-11-29', 35, 5400],   // day 1 — first band
    ['2026-12-25', 35, 5400],   // day 27 — last day of the first band
    ['2026-12-26', 40, 5600],   // day 28 — week eight, steps to 40%
    ['2027-01-23', 45, 5800],   // day 56 — week twelve, steps to 45%
  ];
  for (const [asOf, rate, total] of cases) {
    const r = php(`av_amount_owing(400000, 4, "2026-11-28", "${asOf}")`);
    assert.equal(r.rateApplied, rate, `${asOf} rate`);
    assert.equal(K(r.totalDue), total, `${asOf} total`);
    assert.equal(r.heldAtTermRate, false, `${asOf} should not be floored`);
  }
});

test('the server prices an application from the published table', () => {
  // api.php carried its own table and extrapolated beyond four weeks as
  // 30 + (weeks-4)*5, which priced eight weeks at 50% against the table's 45%.
  const src = readFileSync('api.php', 'utf8');
  assert.match(src, /require_once __DIR__ \. '\/rates-lib\.php'/);
  assert.match(src, /av_quote\(/);
  assert.doesNotMatch(src, /\[\s*1\s*=>\s*15\s*,\s*2\s*=>\s*20/, 'inline rate table is back');
  assert.doesNotMatch(src, /30\s*\+\s*\(\(\$weeks - 4\) \* 5\)/, 'inline extrapolation is back');
  assert.equal(cfg.terms['8'], 45, 'the published eight-week rate');
});
