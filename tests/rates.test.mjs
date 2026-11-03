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

test('the penalty is flat: it never climbs, however long the delay', () => {
  // Decided 3 November 2026. stepPercent is 0, so going overdue sets 35% and
  // it stays there. Someone a day late and someone ten years late owe the
  // same, which is the whole point of the decision.
  for (const asOf of ['2026-11-29', '2026-12-26', '2027-01-23', '2027-05-28', '2036-11-28']) {
    const state = php(`av_overdue_state("2026-11-28","${asOf}")`);
    assert.equal(state.overdue, true, asOf);
    assert.equal(state.bandPercent, 35, `${asOf}: the penalty should not move`);
    assert.equal(state.nextStepOn, null, `${asOf}: a flat penalty has no next step`);
    assert.equal(state.nextBand, null, `${asOf}: nor a next band`);
  }

  // The ladder machinery is still there, dormant. Turning stepPercent back up
  // is a config change, not a code change.
  assert.equal(cfg.overdue.stepPercent, 0, 'flat by configuration');
  assert.equal(cfg.overdue.startPercent, 35);
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

test('the in duplum cap is in place, dormant while the penalty is flat', () => {
  // A flat 35% never approaches the cap, so a ten-year-old debt is still
  // 35% and not capped. The cap stays configured so the protection is
  // already there if the ladder is ever switched back on.
  const ancient = php('av_overdue_state("2016-11-28","2026-11-28")');
  assert.equal(ancient.bandPercent, 35);
  assert.equal(ancient.capped, false, 'a flat penalty never reaches the cap');
  assert.equal(K(php('av_amount_owing(400000, 4, "2016-11-28", "2026-11-28")').totalDue), 5400);

  assert.equal(cfg.overdue.capPercent, 100, 'the cap is still configured');
});

test('KNOWN GAP: defaulting on four weeks undercuts the two-month product', () => {
  // Documented rather than hidden. A borrower wanting eight weeks of money
  // pays less by taking the four-week loan and not paying — and with a flat
  // penalty that stays true however long they leave it.
  const honest = php('av_quote(400000, 8)');
  assert.equal(K(honest.total), 5800);

  for (const asOf of ['2026-12-26', '2027-01-23', '2027-05-28']) {
    const defaulted = php(`av_amount_owing(400000, 4, "2026-11-28", "${asOf}")`);
    assert.equal(K(defaulted.totalDue), 5400, asOf);
    assert.ok(
      defaulted.totalDue < honest.total,
      'if this ever fails the gap has been closed — update this test and rates.json'
    );
  }
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

  // The penalty is a flat 35%, below the 45% term rate, so an overdue
  // eight-week loan is floored at its term rate for as long as it runs.
  for (const [asOf, rawBand] of [['2026-12-27', 35], ['2027-01-23', 35], ['2027-06-01', 35]]) {
    const late = php(`av_amount_owing(400000, 8, "2026-12-26", "${asOf}")`);
    assert.ok(
      late.totalDue >= onTime.total,
      `${asOf}: overdue total ${K(late.totalDue)} is below the on-time total ${K(onTime.total)}`
    );
    assert.equal(late.rateApplied, 45, `${asOf}: should be held at the term rate`);
    assert.equal(late.heldAtTermRate, true);
    assert.equal(late.bandPercent, rawBand, 'the raw band is still reported for the audit trail');
  }

  // The floor never stops biting here: the penalty never reaches 45%, so an
  // eight-week loan carries no late charge at all. Accepted on 3 November;
  // recorded in rates.json under overdue._eightWeekGap.
  const past = php('av_amount_owing(400000, 8, "2026-12-26", "2036-12-26")');
  assert.equal(past.rateApplied, 45);
  assert.equal(past.bandPercent, 35);
  assert.equal(past.heldAtTermRate, true);
});

test('the floor does not disturb the four-week penalty', () => {
  // 35% is above the four-week rate of 30%, so nothing is floored here and
  // the penalty applies exactly as configured, at every age.
  for (const asOf of ['2026-11-29', '2026-12-25', '2026-12-26', '2027-01-23']) {
    const r = php(`av_amount_owing(400000, 4, "2026-11-28", "${asOf}")`);
    assert.equal(r.rateApplied, 35, `${asOf} rate`);
    assert.equal(K(r.totalDue), 5400, `${asOf} total`);
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
