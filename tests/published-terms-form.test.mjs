import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Window } from 'happy-dom';

/* What the form quotes, against what the server will charge.
 *
 * The form carried its own price list: four tiles with the rates typed into
 * the markup, and a formula — 30% plus 5% for every extra week — inventing a
 * price for anything past a month. The server prices from rates.json and
 * refuses a term that is not in it, so:
 *
 *   An applicant could choose six weeks, be quoted 40%, upload their NRC and
 *   payslip, sign, submit, and only then be told "that loan term is not
 *   offered". Nothing told them before they did all of it.
 *
 *   At eight weeks the formula said 50% and the table says 55%. They signed
 *   one figure and were bound to another, K200 more on K4,000.
 *
 * These tests read the real markup and the real functions out of app/index.php
 * and drive them against the published table, so the form cannot quote a price
 * the server will not honour.
 */

const source = readFileSync('app/index.php', 'utf8');
const cfg = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8'));
const terms = Object.fromEntries(
  Object.entries(cfg.terms).filter(([k]) => !k.startsWith('_')).map(([k, v]) => [Number(k), v]));

function section(start, end) {
  const a = source.indexOf(start), b = source.indexOf(end, a + start.length);
  assert.ok(a >= 0 && b > a, 'could not find section: ' + start);
  return source.slice(a, b);
}

function boot(ratesOverride) {
  const w = new Window({ settings: {
    enableJavaScriptEvaluation: true, suppressInsecureJavaScriptEnvironmentWarning: true } });

  // The fields these functions read that live in earlier sections of the form.
  // Supplied rather than stubbed over, so the extracted code runs for real —
  // the slice carries the actual updateTotals(), not a stand-in.
  w.document.body.innerHTML =
    '<input id="loan_amt"><input id="disburse_date">'
    + '<span id="loan_words"></span>'
    + '<span id="cc-principal"></span><span id="cc-interest"></span><span id="cc-total"></span>'
    + section('<div class="f-section">\n          <div class="f-head">3. Repayment Terms',
              '<div class="f-group full">');
  w.AV_RATES = ratesOverride === undefined
    ? { version: cfg.version, terms: cfg.terms, overdue: cfg.overdue }
    : ratesOverride;

  w.eval('function saveProgress(){} function updateFieldCounter(){}'
    + 'function updateRepaymentSchedule(){} function updateInstallment(){}'
    + section('// ── PUBLISHED TERMS ──', 'function updateInstallment('));
  return w;
}

const val = (w, id) => (w.document.getElementById(id) || {}).value;

test('the form offers exactly the terms the table prices', async () => {
  const w = boot();
  assert.deepEqual(Array.from(w.avPublishedWeeks()), Object.keys(terms).map(Number).sort((a, b) => a - b));
  for (const [weeks, rate] of Object.entries(terms)) {
    assert.equal(w.avPublishedRate(Number(weeks)), rate, `${weeks} weeks`);
  }
  // Nothing the server cannot price.
  for (const weeks of [5, 6, 7, 9, 12, 52]) {
    if (weeks in terms) continue;
    assert.equal(w.avPublishedRate(weeks), null,
      `${weeks} weeks is not in the table, so the form must not price it`);
  }
  await w.happyDOM.close();
});

test('THE ONE THAT MATTERS: an unpriceable term is refused before any of the work',
  async () => {
  const w = boot();
  const input = w.document.getElementById('custom_weeks');
  assert.ok(input, 'the custom-term input should still be on the page');

  input.value = '6';
  w.updateLongTermDur();

  assert.equal(val(w, 'sel_dur'), '',
    'a term the server will refuse must not become the chosen duration');
  assert.equal(val(w, 'int_rate'), '');
  assert.match(val(w, 'custom_rate_display'), /not offered/i,
    `six weeks should say so plainly, got "${val(w, 'custom_rate_display')}"`);
  // And it should say what IS offered, rather than leaving them guessing.
  assert.match(val(w, 'custom_rate_display'), /8/);
  await w.happyDOM.close();
});

test('a longer term that IS priced is quoted at the published rate', async () => {
  const w = boot();
  const longest = Math.max(...Object.keys(terms).map(Number));
  assert.ok(longest > 4, 'this test needs a term longer than a month in the table');

  w.document.getElementById('custom_weeks').value = String(longest);
  w.updateLongTermDur();

  assert.equal(val(w, 'int_rate'), String(terms[longest]),
    `${longest} weeks is ${terms[longest]}% in the table`);
  assert.equal(val(w, 'sel_dur'), `${longest} Weeks (${terms[longest]}% interest)`);
  // The old formula gave 30 + 5 × 4 = 50 for eight weeks. That is the bug.
  if (longest === 8) {
    assert.notEqual(val(w, 'int_rate'), '50',
      'eight weeks was quoted at 50% while the table charged 55%');
  }
  await w.happyDOM.close();
});

test('the tiles show the published rate, and a tile with no rate is taken away',
  async () => {
  const w = boot();
  w.avRatesRender();

  const tiles = Array.from(w.document.querySelectorAll('.dur-tile[onclick^="selectDur"]'));
  assert.ok(tiles.length >= 4);
  for (const tile of tiles) {
    const weeks = Number(tile.getAttribute('onclick').match(/selectDur\(this,\s*(\d+)/)[1]);
    if (!(weeks in terms)) {
      assert.equal(tile.style.display, 'none',
        `${weeks} weeks has no published rate, so its tile must not be offered`);
      continue;
    }
    assert.equal(tile.querySelector('.dt-rate').textContent, terms[weeks] + '%');
  }

  // And the longer-term tile names the terms that exist rather than "+5%/wk".
  const longNote = w.document.getElementById('dur-long-note').textContent;
  for (const weeks of Object.keys(terms).map(Number).filter(n => n > 4)) {
    assert.match(longNote, new RegExp(`${weeks} weeks at ${terms[weeks]}%`), longNote);
  }
  assert.doesNotMatch(longNote, /5%\/wk|every extra week/,
    'the page must not promise a per-week formula the table does not implement');
  await w.happyDOM.close();
});

test('the late charge shown is the one the engine applies', async () => {
  const w = boot();
  const o = cfg.overdue;

  // Nothing entered: the terms in one sentence.
  w.avRatesRender();
  w.updateLateFee();
  const sentence = val(w, 'late_rate_display');
  assert.match(sentence, new RegExp(String(o.startPercent)), sentence);
  if (o.gracePeriodDays > 0) {
    assert.match(sentence, new RegExp(String(o.gracePeriodDays)), sentence);
  }
  assert.doesNotMatch(sentence, /7\.5/, 'the 7.5% a week figure was never what the engine charged');

  // A number of weeks: the band the engine would be on, floored at the term
  // rate, because the penalty replaces the rate and never reduces the debt.
  w.document.getElementById('loan_amt').value = '4000';
  w.document.getElementById('int_rate').value = '30';
  w.document.getElementById('weeks_overdue').value = '1';
  w.updateLateFee();
  assert.match(val(w, 'late_rate_display'), new RegExp(`${o.startPercent}%`),
    val(w, 'late_rate_display'));

  // Far enough out that the ceiling is reached, if there is one.
  if (o.capPercent != null && o.stepPercent > 0) {
    w.document.getElementById('weeks_overdue').value = '104';
    w.updateLateFee();
    assert.match(val(w, 'late_rate_display'), new RegExp(`${o.capPercent}%`),
      'the form must show the ceiling, not keep climbing past it');
  }
  await w.happyDOM.close();
});

test('with no table the form quotes nothing rather than guessing', async () => {
  // The server is the thing that decides the price. If this page cannot read
  // the table it should say nothing, not fall back to a formula.
  const w = boot(null);
  assert.deepEqual(Array.from(w.avPublishedWeeks()), []);
  assert.equal(w.avPublishedRate(4), null);

  w.document.getElementById('custom_weeks').value = '8';
  w.updateLongTermDur();
  assert.equal(val(w, 'int_rate'), '', 'no table, no quote');

  w.updateLateFee();
  assert.equal(val(w, 'late_rate_display'), '');
  await w.happyDOM.close();
});

test('the form sends the server a term, and the server prices it anyway', () => {
  // Belt and braces: whatever the page shows, api.php re-prices from the table
  // and overwrites the client's figures. This asserts that has not been
  // loosened into trusting the browser.
  const api = readFileSync('api.php', 'utf8');
  assert.match(api, /\$quote\s*=\s*av_quote\(/);
  assert.match(api, /That loan term is not offered/);
  assert.match(api, /\$canonicalRate\s*=\s*\$quote\['rate'\]/);
});
