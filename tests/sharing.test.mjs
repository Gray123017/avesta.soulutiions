import {test, before, after} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, existsSync} from 'node:fs';
import {spawn, spawnSync} from 'node:child_process';
import {createServer} from 'node:net';

/* What a link to Avesta looks like when somebody shares it.
 *
 * In Ndola a business spreads on WhatsApp, and WhatsApp does not run
 * JavaScript: it fetches the page once and reads the Open Graph tags. Before
 * this, avesta.solutions had none at all, so every shared link rendered as a
 * bare blue URL with no title, no description and no picture — which, for a
 * lender, reads as something you should not tap.
 *
 * The same fetchers are the reason these tags have to be in the HTML the
 * server sends. A tag written in by app.js after load is a tag no crawler,
 * preview bot or link scanner will ever see.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;
const rates = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8')).terms;

let server, base;

before(async () => {
  if (!hasPhp) return;
  const socket = createServer();
  await new Promise(r => socket.listen(0, '127.0.0.1', r));
  const port = socket.address().port;
  await new Promise(r => socket.close(r));
  base = 'http://127.0.0.1:' + port;
  server = spawn(php, ['-S', '127.0.0.1:' + port, '-t', '.'], {stdio: 'ignore'});
  for (let i = 0; i < 60; i++) {
    try { await fetch(base + '/index.php'); break; } catch { await new Promise(r => setTimeout(r, 50)); }
  }
});

after(async () => {
  if (server) { const done = new Promise(r => server.once('exit', r)); server.kill(); await done; }
});

/* The pages a person can actually land on and pass along. */
const PAGES = ['/index.php', '/index.php?page=lending', '/index.php?page=it',
               '/index.php?page=calculator', '/index.php?page=about',
               '/index.php?page=contact', '/loans.php', '/downloads.php', '/legal.php?p=privacy'];

const metaOf = html => {
  const out = {};
  for (const [, attr, name, content] of html.matchAll(
    /<meta\s+(property|name)="([^"]+)"\s+content="([^"]*)"/g)) out[name] = content;
  const canon = html.match(/<link rel="canonical" href="([^"]+)"/);
  if (canon) out.canonical = canon[1];
  const title = html.match(/<title>([\s\S]*?)<\/title>/);
  if (title) out.title = title[1].trim();
  return out;
};

const get = async path => {
  const res = await fetch(base + path, {redirect: 'manual'});
  return {status: res.status, location: res.headers.get('location'), html: await res.text()};
};

/* ───────────────────────── the tags themselves ───────────────────────── */

test('THE ONE THAT MATTERS: every shareable page carries a full preview card',
  {skip: !hasPhp}, async () => {
  const missing = [];
  for (const path of PAGES) {
    const {html} = await get(path);
    const m = metaOf(html);
    for (const tag of ['og:title', 'og:description', 'og:image', 'og:url', 'og:type',
                       'og:site_name', 'twitter:card', 'description']) {
      if (!m[tag]) missing.push(path + ' → ' + tag);
    }
    if (m['twitter:card'] && m['twitter:card'] !== 'summary_large_image') {
      missing.push(path + ' → twitter:card is ' + m['twitter:card']);
    }
  }
  assert.deepEqual(missing, [],
    'a page without these shares as a bare link:\n  ' + missing.join('\n  '));
});

test('the preview image is a real file, absolute, and the size the platforms want',
  {skip: !hasPhp}, async () => {
  const seen = new Set();
  for (const path of PAGES) {
    const m = metaOf((await get(path)).html);
    const url = m['og:image'];
    assert.match(url, /^https:\/\/avesta\.solutions\//,
      path + ': og:image must be an absolute https URL — a relative one is dropped by most fetchers');
    seen.add(url.replace('https://avesta.solutions/', '').split('?')[0]);
    assert.equal(m['twitter:image'], url, path + ': twitter:image should match og:image');
  }
  for (const file of seen) {
    assert.ok(existsSync(file), 'og:image points at ' + file + ', which is not in the repo');
    // PNG puts width and height at bytes 16..24 of the IHDR chunk.
    const head = readFileSync(file).subarray(0, 24);
    assert.equal(head.subarray(1, 4).toString(), 'PNG', file + ' is not a PNG');
    const [w, h] = [head.readUInt32BE(16), head.readUInt32BE(20)];
    assert.deepEqual([w, h], [1200, 630], file + ' is ' + w + '×' + h + '; the cards want 1200×630');
  }
});

test('the canonical and og:url agree, and point at the apex over https',
  {skip: !hasPhp}, async () => {
  for (const path of PAGES) {
    const m = metaOf((await get(path)).html);
    assert.equal(m['og:url'], m.canonical, path + ': og:url and canonical disagree');
    assert.match(m.canonical, /^https:\/\/avesta\.solutions(\/|$)/,
      path + ': canonical must be the apex over https, not www and not a bare path');
    assert.doesNotMatch(m.canonical, /127\.0\.0\.1|localhost/, path + ': canonical leaked the test host');
  }
});

test('each page says something of its own', {skip: !hasPhp}, async () => {
  const titles = new Map(), descs = new Map();
  for (const path of PAGES) {
    const m = metaOf((await get(path)).html);
    assert.ok(m['og:description'].length >= 50 && m['og:description'].length <= 200,
      path + ': the description is ' + m['og:description'].length
      + ' characters; aim between 50 and 200 so it is neither empty nor cut off');
    if (titles.has(m['og:title'])) {
      // The client-side routes share one shell, so a few may legitimately match
      // the home page — but lending and IT must not.
      const clash = titles.get(m['og:title']);
      if (/lending|page=it|loans/.test(path) || /lending|page=it|loans/.test(clash)) {
        assert.fail(path + ' shares its og:title with ' + clash);
      }
    }
    titles.set(m['og:title'], path);
    descs.set(m['og:description'], path);
  }
  assert.ok(titles.size >= 5, 'only ' + titles.size + ' distinct titles across ' + PAGES.length + ' pages');
});

/* ───────────────────────── it must not overclaim ───────────────────────── */

test('THE ONE THAT MATTERS: no preview claims a licence Avesta does not hold',
  {skip: !hasPhp}, async () => {
  /* A share card is an advertisement, and advertising a licence you have not
   * been issued is the thing the Money-Lenders Act is pointed at. */
  for (const path of PAGES) {
    const m = metaOf((await get(path)).html);
    const text = [m['og:title'], m['og:description'], m.title, m.description].join(' ');
    assert.doesNotMatch(text, /licen[cs]ed|regulated|authorised by|registered with|approved by/i,
      path + ' makes a regulatory claim in its share card: ' + text);
    for (const quoted of text.match(/\d+(\.\d+)?%/g) || []) {
      assert.ok(Object.values(rates).includes(Number(quoted.replace('%', ''))),
        path + ' advertises ' + quoted + ', which is not a published rate');
    }
  }
});

test('the structured data on the home page parses and invents no reviews',
  {skip: !hasPhp}, async () => {
  const {html} = await get('/index.php');
  const blocks = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)];
  assert.ok(blocks.length >= 1,
    'the home page had no structured data at all — app.js was injecting it after load, where no crawler sees it');
  for (const [, body] of blocks) {
    let data;
    assert.doesNotThrow(() => { data = JSON.parse(body); }, 'the JSON-LD does not parse');
    const flat = JSON.stringify(data);
    for (const invented of ['aggregateRating', 'reviewCount', 'ratingValue', 'review']) {
      assert.doesNotMatch(flat, new RegExp('"' + invented + '"'),
        'the structured data claims ' + invented + ', which would be fabricated');
    }
    assert.match(flat, /avesta\.solutions/);
  }
});

/* ───────────────────────── the sitemap has to be honest ───────────────────────── */

test('every URL in the sitemap answers 200 rather than redirecting', {skip: !hasPhp}, async () => {
  const sitemap = readFileSync('sitemap.xml', 'utf8');
  const locs = [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
  assert.ok(locs.length >= 4, 'the sitemap looks empty');
  const bad = [];
  for (const loc of locs) {
    const path = loc.replace('https://avesta.solutions', '') || '/';
    const {status, location} = await get(path);
    if (status >= 300 && status < 400) bad.push(`${loc} → ${status} to ${location}`);
    else if (status !== 200) bad.push(`${loc} → ${status}`);
  }
  assert.deepEqual(bad, [],
    'a sitemap entry that redirects sends a crawler somewhere it was not told about:\n  ' + bad.join('\n  '));
});

test('the tags are in the HTML the server sends, not written in later', () => {
  // The whole point: WhatsApp and the other preview fetchers never run the
  // scripts, so anything app.js adds is invisible to them.
  const app = readFileSync('assets/avesta/app.js', 'utf8');
  assert.doesNotMatch(app, /og:(title|description|image|url)/,
    'app.js is setting Open Graph tags; no preview fetcher will ever run it');
  assert.doesNotMatch(app, /application\/ld\+json/,
    'app.js is injecting structured data, which crawlers will not see — render it server-side');
});
