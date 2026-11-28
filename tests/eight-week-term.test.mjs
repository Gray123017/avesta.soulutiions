import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {spawnSync} from 'node:child_process';
import {rates, quote, schedule} from '../assets/avesta/domain.js';

/* Selling the eight-week term.
 *
 * rates.json has priced eight weeks at 55% since 27 November 2026, but only
 * so the overdue ladder had an honest figure to pass at day 56 — the whole
 * argument for the ladder is that defaulting should stop being cheaper than
 * borrowing for longer and paying. The term itself was never offered:
 * check-rates.mjs parked it in NOT_YET_IN_UI and domain.js listed one to four
 * weeks, so the public calculator refused it outright.
 *
 * Gray asked for it to be bookable on 28 November. These tests cover what
 * that has to mean for a lender:
 *
 *   the price a borrower is shown is the published one, in every place that
 *   quotes it — the previous version of this had a form saying 50% against a
 *   table charging 55%, so somebody signed one figure and was bound to
 *   another;
 *
 *   the instalments add up, at every frequency, including the two that only
 *   produce more than one payment at this length;
 *
 *   and the ladder still works. It is pegged to this exact rate, so changing
 *   who can buy an eight-week loan must not quietly change what defaulting
 *   on a shorter one costs.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;
const published = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8')).terms;
const domain = readFileSync('assets/avesta/domain.js', 'utf8');
const app = readFileSync('assets/avesta/app.js', 'utf8');

/* ───────────────── the price is the published one ───────────────── */

test('THE ONE THAT MATTERS: eight weeks is offered, at exactly the published rate', () => {
  assert.equal(published['8'], 55, 'rates.json no longer prices eight weeks at 55%');
  assert.equal(rates[8], 55,
    'domain.js must offer eight weeks at the published 55%, or the calculator and the '
    + 'server disagree about what the borrower is signing');

  const q = quote(1000, 8);
  assert.equal(q.rate, 55);
  assert.equal(q.interest, 55000, 'K1,000 for eight weeks is K550 of interest, in ngwee');
  assert.equal(q.total, 155000);
});

test('every published term is offered, and nothing is offered that is not published', () => {
  const offered = Object.keys(rates).map(Number).sort((a, b) => a - b);
  const inTable = Object.keys(published).filter(k => /^\d+$/.test(k)).map(Number).sort((a, b) => a - b);
  assert.deepEqual(offered, inTable,
    'domain.js offers ' + JSON.stringify(offered) + ' but the published table is '
    + JSON.stringify(inTable));
  for (const w of inTable) {
    assert.equal(rates[w], Number(published[String(w)]), `${w} weeks is priced differently in the two files`);
  }
});

test('the term is no longer parked as not-yet-offered', () => {
  const guard = readFileSync('scripts/check-rates.mjs', 'utf8');
  const set = guard.match(/const NOT_YET_IN_UI = new Set\(\[([^\]]*)\]\)/);
  assert.ok(set, 'NOT_YET_IN_UI has moved or been renamed');
  const parked = set[1].split(',').map(s => s.trim()).filter(Boolean).map(Number);
  assert.ok(!parked.includes(8),
    'eight weeks is still listed as not-yet-offered, so the guard will not check it');
});

test('the refusal message no longer promises a range that is wrong', () => {
  assert.doesNotMatch(domain, /from 1 to 4 weeks/,
    'the error still tells a borrower the terms stop at four weeks');
  assert.throws(() => quote(1000, 6), /term/i, 'six weeks is not published and must still be refused');
  assert.throws(() => quote(1000, 0), /valid/i);
  assert.throws(() => quote(-5, 8), /valid/i);
});

/* ───────────────── the instalments add up ───────────────── */

test('THE ONE THAT MATTERS: instalments add up to the total at every frequency', () => {
  for (const weeks of Object.keys(rates).map(Number)) {
    for (const freq of ['Weekly', 'Bi-weekly', 'Monthly', 'Lump sum']) {
      const q = quote(1000, weeks, freq);
      const sum = q.instalments.reduce((a, b) => a + b, 0);
      assert.equal(sum, q.total,
        `${weeks} weeks ${freq}: instalments come to ${sum} but the total is ${q.total}`);
      assert.equal(q.instalments.length, q.count);
      assert.ok(q.count >= 1);
    }
  }
});

test('a monthly eight-week loan is actually paid monthly', () => {
  /* Before this term was sellable, Monthly meant one payment whatever the
   * length, because nothing longer than four weeks could be bought and a
   * month covered the lot. At eight weeks that produces a single lump sum
   * with "Monthly" written on it. */
  const q = quote(1000, 8, 'Monthly');
  assert.equal(q.count, 2, 'eight weeks paid monthly is two payments, not one');

  const rows = schedule(1000, 8, 'Monthly', '2026-12-01');
  assert.equal(rows.length, 2);
  assert.notEqual(rows[0].date, rows[1].date, 'both payments fall on the same day');
  assert.equal(rows[rows.length - 1].date, '2027-01-26', 'the last payment is due at week eight');

  // Shorter terms keep exactly the behaviour they had.
  for (const w of [1, 2, 3, 4]) {
    assert.equal(quote(1000, w, 'Monthly').count, 1, `${w} weeks monthly should still be one payment`);
  }
});

test('the weekly and bi-weekly schedules run the full eight weeks', () => {
  const weekly = schedule(1000, 8, 'Weekly', '2026-12-01');
  assert.equal(weekly.length, 8);
  assert.equal(weekly[0].date, '2026-12-08', 'the first weekly payment is a week in');
  assert.equal(weekly[7].date, '2027-01-26', 'the last lands at week eight');
  for (let i = 1; i < weekly.length; i++) {
    assert.ok(weekly[i].date > weekly[i - 1].date, 'the dates must keep moving forward');
  }

  const fortnightly = schedule(1000, 8, 'Bi-weekly', '2026-12-01');
  assert.equal(fortnightly.length, 4);
  assert.equal(fortnightly[3].date, '2027-01-26');
});

/* ───────────────── the ladder is pegged to this rate ───────────────── */

test('THE ONE THAT MATTERS: the overdue ladder still passes the honest price at day 56',
  {skip: !hasPhp}, () => {
  /* The ladder exists because a flat penalty made defaulting cheaper than
   * buying the longer product. It is pegged to this term's 55%, so selling
   * the term must not move the peg.
   *
   * Asks the real engine rather than repeating its arithmetic here. The first
   * draft of this test reimplemented the ladder, subtracted the grace days in
   * the wrong place and disagreed with av_overdue_state() by a whole band. */
  const band = daysLate => {
    const due = '2026-01-01';
    const grace = Number(JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8')).overdue.gracePeriodDays);
    const asOf = new Date(Date.parse(due + 'T12:00:00Z') + (grace + daysLate) * 86400000)
      .toISOString().slice(0, 10);
    const out = spawnSync(php, ['-r',
      `require 'rates-lib.php'; $s = av_overdue_state('${due}', '${asOf}'); `
      + `echo $s['overdue'] ? $s['bandPercent'] : 'none';`], {encoding: 'utf8'});
    assert.equal(out.status, 0, out.stderr);
    return out.stdout.trim() === 'none' ? 0 : Number(out.stdout);
  };

  const honest = Number(published['8']);
  assert.ok(band(56) >= honest,
    `by day 56 the penalty is ${band(56)}%, which must reach the honest eight-week `
    + `price of ${honest}% or defaulting pays again`);
  assert.ok(band(1) < honest,
    `somebody one day past the grace period is charged ${band(1)}%, which must be below `
    + 'the eight-week price');
  assert.ok(band(27) < honest, 'the first band must still sit below it');
});

/* ───────────────── the page says the same thing ───────────────── */

test('nothing still tells a visitor the terms stop at four weeks', () => {
  const claims = [];
  const files = {
    'assets/avesta/app.js': app,
    'assets/avesta/site-knowledge.json': readFileSync('assets/avesta/site-knowledge.json', 'utf8'),
    'assets/avesta/guide.js': readFileSync('assets/avesta/guide.js', 'utf8'),
    'share-tags.php': readFileSync('share-tags.php', 'utf8'),
  };
  for (const [name, text] of Object.entries(files)) {
    for (const m of text.matchAll(/(1\s*(?:to|–|-)\s*4\s*weeks?|one to four weeks)/gi)) {
      claims.push(`${name}: "${m[1]}"`);
    }
  }
  assert.deepEqual(claims, [],
    'these still describe a one-to-four-week range the site no longer offers:\n  '
    + claims.join('\n  '));
});

test('the calculator builds its tiles from the table rather than a fixed four', () => {
  assert.match(app, /Object\.keys\(rates\)/,
    'the rate cards must come from the published table, so a new term appears by itself');
  // A hard-coded list of four would silently drop the new term.
  assert.doesNotMatch(app, /\[1,\s*2,\s*3,\s*4\]/, 'a hard-coded four-term list is still in app.js');
});
