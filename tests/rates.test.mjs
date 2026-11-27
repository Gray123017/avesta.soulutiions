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
  assert.equal(eight.rate, 55);
  assert.equal(K(eight.total), 6200);
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

test('a loan is not overdue until the grace days have run out', () => {
  // Three days' grace, which legal.php has promised since 3 October 2026 and
  // the engine did not give until 27 November. Somebody whose salary lands two
  // days late should not owe a penalty for it.
  const grace = cfg.overdue.gracePeriodDays;
  assert.ok(grace >= 1, 'the published terms promise grace, so there has to be some');

  assert.equal(php('av_overdue_state("2026-11-28","2026-11-28")').overdue, false);
  for (let d = 1; d <= grace; d++) {
    const day = `2026-11-${String(28 + d).padStart(2, '0')}`;
    assert.equal(php(`av_overdue_state("2026-11-28","${day}")`).overdue, false,
      `${day} is inside the ${grace}-day grace`);
  }

  const late = php(`av_overdue_state("2026-11-28","2026-12-0${grace >= 3 ? 2 : 1}")`);
  assert.equal(late.overdue, true, 'the day after the grace runs out');
  assert.equal(late.bandPercent, cfg.overdue.startPercent);
});

test('the penalty climbs, and stops at the ceiling', () => {
  /* Decided 27 November 2026, replacing the flat 35% of 3 November. A flat
   * penalty sat below the 55% charged for eight weeks, so defaulting on a
   * four-week loan was permanently cheaper than borrowing honestly for eight,
   * and an overdue eight-week loan carried no charge at all, ever.
   *
   * It climbs instead: 35% to day 27, then 10 points every 28 days, with a
   * ceiling at twice the principal. */
  const o = cfg.overdue;
  assert.ok(o.stepPercent > 0, 'a flat penalty is what left defaulting cheaper');

  // Day 1 past grace, then each step boundary.
  const bands = [[1, 35], [o.stepEveryDays, 35 + o.stepPercent],
                 [o.stepEveryDays * 2, 35 + o.stepPercent * 2]];
  for (const [pastGrace, expected] of bands) {
    const day = new Date(Date.UTC(2026, 10, 28));
    day.setUTCDate(day.getUTCDate() + o.gracePeriodDays + pastGrace);
    const asOf = day.toISOString().slice(0, 10);
    const state = php(`av_overdue_state("2026-11-28","${asOf}")`);
    assert.equal(state.bandPercent, expected,
      `${asOf} (${pastGrace} days past grace) should be ${expected}%`);
  }

  // And a next step is reported, so a borrower can be told what is coming.
  const soon = php('av_overdue_state("2026-11-28","2026-12-05")');
  assert.ok(soon.nextStepOn, 'a climbing penalty has a next step, and a date for it');
  assert.equal(soon.nextBand, 35 + o.stepPercent);
});

test('the band replaces the term rate instead of adding to it', () => {
  // K4,000 at 30% is K5,200. Once overdue it is 35% of the PRINCIPAL —
  // K5,400 — not the total plus a further 35%.
  const owing = php('av_amount_owing(400000, 4, "2026-11-28", "2026-12-02")');
  assert.equal(owing.termRate, 30);
  assert.equal(owing.rateApplied, 35);
  assert.equal(K(owing.totalDue), 5400);
  assert.notEqual(K(owing.totalDue), 5200 * 1.35);
});

test('the in duplum ceiling is reached, and holds', () => {
  // No longer dormant. Interest stops at the principal, so the most anybody
  // can owe is twice what they borrowed — the rule applied across the region.
  const ancient = php('av_overdue_state("2016-11-28","2026-11-28")');
  assert.equal(ancient.bandPercent, cfg.overdue.capPercent);
  assert.equal(ancient.capped, true, 'a climbing penalty must stop somewhere');

  assert.equal(K(php('av_amount_owing(400000, 4, "2016-11-28", "2026-11-28")').totalDue), 8000,
    'twice the K4,000 borrowed and not a ngwee more');

  // It holds there rather than creeping past it.
  assert.equal(K(php('av_amount_owing(400000, 4, "2016-11-28", "2036-11-28")').totalDue), 8000);
});

test('THE GAP IS CLOSED: defaulting no longer undercuts the longer product', () => {
  /* This test used to assert the opposite, and said so: "if this ever fails
   * the gap has been closed — update this test and rates.json". It has been.
   *
   * On K4,000 an honest eight-week loan costs K6,200. A four-week loan that
   * defaulted cost K5,400 for ever, so a borrower wanting eight weeks of money
   * paid K800 less by taking four weeks and not paying. */
  const honest = php('av_quote(400000, 8)');
  assert.equal(K(honest.total), 6200);

  // By the time an eight-week loan would have matured, defaulting costs at
  // least as much as having borrowed honestly.
  const matured = php('av_amount_owing(400000, 4, "2026-11-28", "2027-01-26")');
  assert.ok(matured.totalDue >= honest.total,
    `defaulting for two months costs ${K(matured.totalDue)}, `
    + `against ${K(honest.total)} for borrowing honestly — it must not be cheaper`);

  // And it keeps rising, rather than sitting still for ever.
  const later = php('av_amount_owing(400000, 4, "2027-05-28", "2027-11-28")');
  assert.ok(later.totalDue > matured.totalDue);
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
  // The band REPLACES the term rate and starts at 35%, which is below the 55%
  // charged for eight weeks. Without a floor an eight-week loan got cheaper
  // the day it fell overdue, and the borrower was paid to default.
  const onTime = php('av_quote(400000, 8)');
  assert.equal(K(onTime.total), 6200);

  // Early on the floor holds it at the term rate: 35% and 45% are both below 55%.
  for (const asOf of ['2026-12-31', '2027-01-20']) {
    const late = php(`av_amount_owing(400000, 8, "2026-12-26", "${asOf}")`);
    assert.ok(late.totalDue >= onTime.total,
      `${asOf}: overdue total ${K(late.totalDue)} is below the on-time ${K(onTime.total)}`);
    assert.equal(late.rateApplied, 55, `${asOf}: held at the term rate`);
    assert.equal(late.heldAtTermRate, true);
  }

  // Then the ladder climbs past 55% and the charge finally bites — which it
  // never did under the flat penalty, however long the loan ran.
  const biting = php('av_amount_owing(400000, 8, "2026-12-26", "2027-06-01")');
  assert.ok(biting.rateApplied > 55,
    `an eight-week loan five months overdue should carry a charge, got ${biting.rateApplied}%`);
  assert.equal(biting.heldAtTermRate, false);
  assert.ok(biting.totalDue > onTime.total);

  // And no term, at any age, ever owes less than it was signed for.
  for (const weeks of [1, 2, 3, 4, 8]) {
    const signed = php(`av_quote(400000, ${weeks})`);
    for (const asOf of ['2026-12-31', '2027-03-01', '2029-01-01']) {
      const r = php(`av_amount_owing(400000, ${weeks}, "2026-12-26", "${asOf}")`);
      assert.ok(r.totalDue >= signed.total,
        `${weeks} weeks on ${asOf}: ${K(r.totalDue)} is below the signed ${K(signed.total)}`);
    }
  }
});

test('the floor does not disturb the four-week penalty', () => {
  // 35% is above the four-week rate of 30%, so nothing is floored at the first
  // band and the penalty applies exactly as configured.
  for (const asOf of ['2026-12-02', '2026-12-20', '2026-12-25']) {
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
  assert.equal(cfg.terms['8'], 55, 'the published eight-week rate');
});
