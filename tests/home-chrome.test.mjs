import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

/* The home page furniture taken from how the Zambian banks lay a site out:
 * the contact bar above the nav, the division cards that list what is
 * actually inside them, a dated updates strip, and the branch locator turned
 * into a find-us band for a business with one office.
 *
 * Adopted on 28 November 2026 after Gray sent izb.co.zm. Three of their
 * patterns were deliberately not taken, and the reasons are worth keeping
 * because somebody will suggest them again: the auto-advancing hero carousel
 * (slides after the first get a fraction of the clicks, and an auto-advancing
 * one needs a pause control to satisfy WCAG 2.2.2), the visitor counter, and
 * stock photography of people fanning banknotes, which is a poor look for any
 * lender and a worse one for an applicant awaiting a money-lender's licence.
 *
 * What these tests protect is mostly honesty and reach: the numbers in the
 * bar have to be the real ones, every product link has to go somewhere, and
 * the updates strip must never sit on the page looking abandoned.
 */

const page = readFileSync('index.php', 'utf8');
const app = readFileSync('assets/avesta/app.js', 'utf8');
const css = readFileSync('assets/avesta/styles.css', 'utf8');
const updatesFile = JSON.parse(readFileSync('assets/avesta/updates.json', 'utf8'));

/** freshUpdates() lifted out of app.js and run for real, rather than re-typed here. */
function loadFreshUpdates() {
  const start = app.indexOf('function freshUpdates(file){');
  assert.ok(start !== -1, 'freshUpdates has been renamed or removed');
  const end = app.indexOf('\n}', start) + 2;
  // eslint-disable-next-line no-new-func
  return new Function(app.slice(start, end) + '; return freshUpdates;')();
}

/* ───────────────── the contact bar ───────────────── */

test('THE ONE THAT MATTERS: the numbers in the bar are the real ones', () => {
  const bar = page.slice(page.indexOf('<div class="utility-bar">'), page.indexOf('<div class="header-inner">'));
  assert.ok(bar.length > 100, 'the utility bar is missing from index.php');

  // Both numbers appear elsewhere on the site; the bar must not invent a third.
  for (const num of ['+260971013108', '+260769974200']) {
    assert.ok(app.includes(num) || page.includes(num), num + ' is not used anywhere else on the site');
  }
  const tels = [...bar.matchAll(/href="tel:([^"]+)"/g)].map(m => m[1]);
  assert.deepEqual(tels, ['+260971013108', '+260769974200']);
  assert.match(bar, /href="https:\/\/wa\.me\/260971013108"/);
  assert.match(bar, /href="mailto:info@avesta\.solutions"/,
    'info@ is the only mailbox on the domain; any other address would bounce');
  assert.doesNotMatch(bar, /no-reply@/i);
});

test('the bar links are reachable with a thumb and keep out of the way on a phone', () => {
  assert.match(css, /\.utility-links a\{[^}]*min-height:24px/,
    'WCAG 2.5.8 wants a 24px target; these are small links in a thin bar');
  assert.match(css, /@media \(max-width:620px\)/,
    'the second number and the email should drop on a narrow screen');
  assert.match(css, /\.utility-links a:focus-visible\{[^}]*outline/,
    'the bar is dark, so it needs its own visible focus ring');
});

/* ───────────────── the division cards ───────────────── */

test('THE ONE THAT MATTERS: every product link on the home page goes somewhere real', () => {
  const known = new Set(['/lending', '/it', '/apply', '/calculator', '/how-it-works',
                         '/currency', '/about', '/contact', '/downloads.php', '/portal']);
  const block = app.slice(app.indexOf('<article class="choice-card">'), app.indexOf('<div class="trust-row">'));
  const hrefs = [...block.matchAll(/href="([^"]+)"/g)].map(m => m[1]);
  assert.ok(hrefs.length >= 10, 'expected both cards to list their products; found ' + hrefs.length);
  for (const href of hrefs) {
    const path = href.split('#')[0];
    assert.ok(known.has(path), `the home page links to ${href}, which is not a route this site has`);
  }
});

test('the cards are no longer one enormous link', () => {
  /* They used to be a single <a> wrapping the heading and all the text, which
   * a screen reader announces as one link with a paragraph inside it — and
   * which cannot legally contain the product links at all. */
  assert.doesNotMatch(app, /<a class="choice-card"/,
    'the card is an anchor again, so its product links are nested anchors');
  assert.match(app, /<article class="choice-card">/);
  assert.match(app, /<h2><a href="\/lending">/, 'the heading should carry the link instead');
});

/* ───────────────── the updates strip ───────────────── */

test('THE ONE THAT MATTERS: the strip hides itself rather than going stale', () => {
  const freshUpdates = loadFreshUpdates();
  const day = 86400000;
  const iso = ms => new Date(ms).toISOString().slice(0, 10);

  const old = freshUpdates({_staleAfterDays: 120, updates: [
    {date: iso(Date.now() - 200 * day), title: 'Ancient'},
  ]});
  assert.deepEqual(old, [],
    'a notice older than the window must disappear; a lender showing months-old '
    + '"latest news" looks abandoned, which is worse than showing none');

  const recent = freshUpdates({_staleAfterDays: 120, updates: [
    {date: iso(Date.now() - 5 * day), title: 'Recent'},
  ]});
  assert.equal(recent.length, 1);
});

test('the strip renders nothing at all when there is nothing to say', () => {
  const freshUpdates = loadFreshUpdates();
  for (const empty of [null, undefined, {}, {updates: []}, {updates: 'nope'}]) {
    assert.deepEqual(freshUpdates(empty), [], 'should be empty for ' + JSON.stringify(empty));
  }
  assert.match(app, /function updatesStrip\(\)\{\s*if\(!updates\.length\)return '';/,
    'an empty list must produce no markup, not an empty heading');
});

test('it shows the newest three, newest first', () => {
  const freshUpdates = loadFreshUpdates();
  const day = 86400000;
  const iso = ms => new Date(ms).toISOString().slice(0, 10);
  const got = freshUpdates({_staleAfterDays: 365, updates: [
    {date: iso(Date.now() - 30 * day), title: 'C'},
    {date: iso(Date.now() - 1 * day),  title: 'A'},
    {date: iso(Date.now() - 60 * day), title: 'D'},
    {date: iso(Date.now() - 10 * day), title: 'B'},
  ]});
  assert.deepEqual(got.map(u => u.title), ['A', 'B', 'C'], 'wrong order, or more than three');
});

test('a malformed entry is skipped instead of breaking the page', () => {
  const freshUpdates = loadFreshUpdates();
  const got = freshUpdates({_staleAfterDays: 365, updates: [
    null, {}, {title: 'no date'}, {date: 'not-a-date', title: 'bad date'},
    {date: new Date().toISOString().slice(0, 10), title: 'Good'},
  ]});
  assert.deepEqual(got.map(u => u.title), ['Good']);
});

test('the shipped updates file is valid and says only true things', () => {
  assert.ok(Array.isArray(updatesFile.updates));
  assert.ok(Number(updatesFile._staleAfterDays) > 0, 'the staleness window must be set');
  for (const u of updatesFile.updates) {
    assert.ok(u.title && u.date, 'every notice needs a title and a date');
    assert.ok(!Number.isNaN(Date.parse(u.date)), `"${u.date}" is not a date`);
    assert.ok(Date.parse(u.date) <= Date.now() + 86400000, `"${u.date}" is in the future`);
    if (u.href) assert.match(u.href, /^\/[a-z0-9/.?=-]*$/i, 'notices should link within this site');
  }
  // The one shipped notice describes the eight-week term; it has to be true.
  const rates = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8')).terms;
  if (updatesFile.updates.some(u => /eight-week|8 week/i.test(u.title + ' ' + (u.body || '')))) {
    assert.equal(Number(rates['8']), 55,
      'a notice announces eight-week loans at 55%, but the published table says otherwise');
  }
});

test('the updates reach the page through the block the page already inlines', () => {
  /* The fetch path in start() is a legacy fallback: when the server renders
   * #avesta-public-data, start() reads it and returns before reaching the
   * fetch. Anything wired only into the fallback silently never appears,
   * which is exactly what happened the first time this was built. */
  assert.match(page, /'updates' => json_decode\(@file_get_contents\(__DIR__ \. '\/assets\/avesta\/updates\.json'\)/,
    'updates.json must be inlined into $public_data');
  assert.match(app, /services=data\.services;help=data\.help;updates=freshUpdates\(data\.updates\);/,
    'the fast path in start() must read the updates it was given');
});

/* ───────────────── find us ───────────────── */

test('the address in the band matches the one on the contact page', () => {
  const band = app.slice(app.indexOf('const findUs='), app.indexOf('const findUs=') + 900);
  assert.match(band, /House No\. 3, Thom Avenue/);
  assert.match(band, /Kansenshi, Ndola/);
  // The contact page states the same address; they must not drift apart.
  assert.ok(app.includes('House No. 3, Thom Avenue<br>Kansenshi, Ndola, Zambia')
         || app.includes('House No. 3, Thom Avenue'),
    'the contact page address has changed shape; check the band still agrees');
  assert.match(band, /08:00&ndash;17:00|08:00–17:00/);
});

test('the home page closes once, not twice', () => {
  /* The band carries the same three ways to make contact that the closing
   * call-to-action did, and two green slabs asking for the same thing read
   * as a mistake. */
  const home = app.slice(app.indexOf('function home(){'), app.indexOf('const adverts='));
  assert.match(home, /\$\{findUs\(\)\}/);
  assert.doesNotMatch(home, /\$\{cta\(/, 'the home page has a call-to-action block again as well as the band');
  const actions = app.slice(app.indexOf('<div class="find-actions">'), app.indexOf('</div></div></div></section>`;'));
  assert.ok((actions.match(/<a /g) || []).length >= 3, 'the band should carry all three contact routes');
});

/* ───────────────── what was deliberately not taken ───────────────── */

test('no carousel, no visitor counter', () => {
  const home = app.slice(app.indexOf('function home(){'), app.indexOf('const adverts='));
  assert.doesNotMatch(home, /carousel|slider|autoplay|auto-advance/i,
    'an auto-advancing hero was considered and declined: slides after the first '
    + 'get a fraction of the clicks and it needs a pause control for WCAG 2.2.2');
  assert.doesNotMatch(app, /visitor\s*(count|number)|You are the/i,
    'a visitor counter is not a trust signal');
});
