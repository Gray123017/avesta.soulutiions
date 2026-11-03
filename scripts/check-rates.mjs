// The published rates appear in several places: the calculator's table in
// domain.js, the term dropdown in the borrower app, and prose on the site that
// quotes the numbers in words. Before this check existed they were four
// independent copies, and nothing stopped one being updated alone.
//
// assets/avesta/rates.json is the source. This fails the build if any copy
// disagrees with it.
//
// Run: node scripts/check-rates.mjs

import { readFileSync } from 'node:fs';

const cfg = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8'));
const terms = Object.fromEntries(
  Object.entries(cfg.terms)
    .filter(([k]) => /^\d+$/.test(k))
    .map(([k, v]) => [Number(k), Number(v)])
);

// Terms that are priced but deliberately not yet offered in the interface.
// Adding one here is a conscious act; removing a term from the UI without
// listing it makes the build fail, which is the point.
const NOT_YET_IN_UI = new Set([8]);

let failures = 0;
const fail = (what, detail) => {
  failures++;
  console.log(`  FAIL  ${what}\n        ${detail}`);
};
const pass = (what) => console.log(`  PASS  ${what}`);

console.log('\n-- the calculator table matches the published rates --');
{
  const src = readFileSync('assets/avesta/domain.js', 'utf8');
  const m = src.match(/export\s+const\s+rates\s*=\s*\{([^}]*)\}/);
  if (!m) {
    fail('domain.js exports a rates table', 'no `export const rates={...}` found');
  } else {
    const found = {};
    for (const pair of m[1].split(',')) {
      const bits = pair.split(':').map((s) => s.trim());
      if (bits.length === 2 && /^\d+$/.test(bits[0])) found[Number(bits[0])] = Number(bits[1]);
    }
    let ok = true;
    for (const [weeks, percent] of Object.entries(found)) {
      if (terms[weeks] === undefined) {
        fail(`domain.js offers ${weeks} weeks`, 'that term is not in rates.json at all');
        ok = false;
      } else if (terms[weeks] !== percent) {
        fail(`domain.js prices ${weeks} weeks at ${percent}%`, `rates.json says ${terms[weeks]}%`);
        ok = false;
      }
    }
    for (const weeks of Object.keys(terms)) {
      if (found[weeks] === undefined && !NOT_YET_IN_UI.has(Number(weeks))) {
        fail(`rates.json prices ${weeks} weeks`, 'domain.js does not offer it, and it is not listed in NOT_YET_IN_UI');
        ok = false;
      }
    }
    if (ok) pass('domain.js agrees with rates.json');
  }
}

console.log('\n-- the application form offers the same terms at the same prices --');
{
  const src = readFileSync('app/index.php', 'utf8');
  const options = [...src.matchAll(/<option value="(\d+)">\s*(\d+)\s*Weeks?\s*[—-]\s*(\d+)%\s*interest/gi)];
  if (!options.length) {
    fail('the term dropdown was found', 'no `<option value="N">N Weeks — P% interest` in app/index.php');
  } else {
    let ok = true;
    for (const [, value, label, percent] of options) {
      if (value !== label) {
        fail(`dropdown option value=${value}`, `its label says ${label} weeks`);
        ok = false;
      }
      if (terms[value] === undefined) {
        fail(`dropdown offers ${value} weeks`, 'not a published term');
        ok = false;
      } else if (terms[value] !== Number(percent)) {
        fail(`dropdown prices ${value} weeks at ${percent}%`, `rates.json says ${terms[value]}%`);
        ok = false;
      }
    }
    if (ok) pass(`${options.length} dropdown options agree with rates.json`);
  }
}

console.log('\n-- prose that quotes a rate quotes the right one --');
{
  // Catches sentences like "15% for 1 week" wherever they are written, so the
  // FAQ and the marketing copy cannot drift from the table either.
  const files = ['assets/avesta/app.js', 'app/index.php', 'app/admin.php'];
  let checked = 0, ok = true;
  for (const file of files) {
    let src;
    try { src = readFileSync(file, 'utf8'); } catch { continue; }
    for (const [whole, percent, weeks] of src.matchAll(/(\d+)%\s+for\s+(\d+)\s+weeks?/gi)) {
      checked++;
      if (terms[weeks] === undefined) {
        fail(`${file}: "${whole}"`, `${weeks} weeks is not a published term`);
        ok = false;
      } else if (terms[weeks] !== Number(percent)) {
        fail(`${file}: "${whole}"`, `rates.json prices ${weeks} weeks at ${terms[weeks]}%`);
        ok = false;
      }
    }
  }
  if (ok) pass(`${checked} rate mentions in prose agree with rates.json`);
}

console.log('\n-- the server prices from the table, not its own copy --');
{
  // api.php used to carry [1=>15,2=>20,3=>25,4=>30] with 30 + (weeks-4)*5
  // beyond it, which priced eight weeks at 50% while the table said 45%. It
  // is the file that actually binds a borrower, so a copy here is the most
  // expensive one to get wrong.
  const src = readFileSync('api.php', 'utf8');
  let ok = true;

  if (!/require_once\s+__DIR__\s*\.\s*'\/rates-lib\.php'/.test(src)) {
    fail('api.php loads rates-lib.php', 'no require_once of rates-lib.php');
    ok = false;
  }
  if (!/av_quote\s*\(/.test(src)) {
    fail('api.php prices through av_quote()', 'no call to av_quote() found');
    ok = false;
  }
  const inlineTable = src.match(/\[\s*1\s*=>\s*15\s*,\s*2\s*=>\s*20\s*,\s*3\s*=>\s*25\s*,\s*4\s*=>\s*30\s*\]/);
  if (inlineTable) {
    fail('api.php has no rate table of its own', 'found an inline [1=>15,...] table');
    ok = false;
  }
  if (/30\s*\+\s*\(\s*\(\s*\$weeks\s*-\s*4\s*\)\s*\*\s*5\s*\)/.test(src)) {
    fail('api.php has no extrapolation of its own', 'found 30 + (($weeks - 4) * 5)');
    ok = false;
  }
  if (ok) pass('api.php prices from rates.json via rates-lib.php');
}

console.log('\n-- going overdue never costs a borrower less --');
{
  // The band replaces the term rate, and startPercent (35) sits below the
  // eight-week rate (45). Without a floor in av_amount_owing() an eight-week
  // loan became K400 cheaper the day it fell overdue. This asserts the floor
  // is still there, whatever the table says.
  const lib = readFileSync('rates-lib.php', 'utf8');
  if (/max\(\s*\(float\)\s*\$state\['bandPercent'\],\s*\(float\)\s*\$quote\['rate'\]\s*\)/.test(lib)) {
    pass('av_amount_owing() floors the applied rate at the term rate');
  } else {
    fail(
      'av_amount_owing() floors the applied rate at the term rate',
      'the floor is gone — an overdue loan priced below its own term rate pays the borrower to default'
    );
  }
}

console.log('\n-- the overdue ladder is sane --');
{
  const o = cfg.overdue;
  const longest = Math.max(...Object.keys(terms).map(Number));
  const longestRate = terms[longest];

  if (o.startPercent <= Math.max(...Object.values(terms).filter((_, i) => i < Object.values(terms).length - 1))) {
    // Starting below the ordinary term rates would make going overdue cheaper
    // than borrowing, which cannot be right under any pricing.
    if (o.startPercent < terms[4]) {
      fail('the overdue band starts above the four-week rate', `${o.startPercent}% is below ${terms[4]}%`);
    }
  }
  if (o.capPercent !== undefined && o.capPercent < o.startPercent) {
    fail('the cap is above the starting band', `cap ${o.capPercent}% < start ${o.startPercent}%`);
  }
  const flat = Number(o.stepPercent) === 0;
  if (flat) {
    console.log(
      `  NOTE  the penalty is flat at ${o.startPercent}% — stepPercent is 0, so it never ` +
      `climbs.\n        Someone two days late and someone two years late owe the same.`
    );
  }
  if (o.startPercent < longestRate) {
    const day = flat
      ? null
      : Math.ceil((longestRate - o.startPercent) / o.stepPercent) * o.stepEveryDays;
    console.log(
      `  NOTE  the penalty (${o.startPercent}%) is below the ${longest}-week rate ` +
      `(${longestRate}%), so a\n        ${longest}-week loan is held at its term rate by the ` +
      `floor and carries no late\n        charge ` +
      (flat ? 'at all, however long it runs.' : `before day ${day}.`) +
      ` Recorded in rates.json under\n        overdue._eightWeekGap; the floor itself is checked above.`
    );
  }
  if (!failures) pass('the ladder is internally consistent');
}

console.log('\n' + '='.repeat(54));
console.log(failures ? `RATES: ${failures} disagreement(s)` : 'RATES: every copy agrees');
console.log('='.repeat(54));
process.exit(failures ? 1 : 0);
