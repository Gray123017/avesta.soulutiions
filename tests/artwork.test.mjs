import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, existsSync, readdirSync, statSync} from 'node:fs';

/* The artwork band, the self-hosted type, and what they cost a visitor.
 *
 * Added 28 November 2026. The site had no imagery and no webfonts: it asked
 * for Inter, which is installed on almost nobody's phone, so body text was
 * really Arial, and headings fell back to Georgia.
 *
 * Both additions are bytes somebody in Ndola pays for on mobile data, so the
 * tests below are mostly about weight and about not wasting it:
 *
 *   every image the markup offers must exist, at every width it claims —
 *   a 404 in a srcset is silent, the browser just picks another;
 *
 *   every <img> must state width and height, or the text under it jumps
 *   while the picture arrives, which is worst on exactly the slow
 *   connections this audience has;
 *
 *   the fonts must be the Fontsource latin files and must stay under budget;
 *
 *   nothing may move for a visitor who asked for stillness.
 */

const css   = readFileSync('assets/avesta/artwork.css', 'utf8');
const bare  = css.replace(/\/\*[\s\S]*?\*\//g, '');
const js    = readFileSync('assets/avesta/artwork.js', 'utf8');
const app   = readFileSync('assets/avesta/app.js', 'utf8');
const page  = readFileSync('index.php', 'utf8');
const styles = readFileSync('assets/avesta/styles.css', 'utf8');

const WIDTHS = [560, 840, 1120];

/* ───────────────── nothing offered that is not there ───────────────── */

test('THE ONE THAT MATTERS: every image width the markup offers really exists', () => {
  /* artFigure() builds each srcset from a list passed to set(); read every
   * list out of the source rather than trusting the copy in this file, so
   * adding a rendition cannot quietly escape the existence check below. */
  const lists = [...app.matchAll(/set\('(?:webp|jpg)',\[([\d,\s]+)\]\)/g)]
    .map(m => m[1].split(',').map(n => Number(n.trim())));
  assert.ok(lists.length >= 2, 'expected several srcset width lists, found ' + lists.length);
  const inSource = [...new Set(lists.flat())].sort((a, b) => a - b);
  assert.deepEqual(inSource, WIDTHS,
    'artFigure offers widths ' + JSON.stringify(inSource) + ' but this test checks ' + JSON.stringify(WIDTHS));

  /* A phone must not be offered the largest rendition. A three-times screen
   * asking honestly for 92vw would take it, which is 41 kB rather than 25 kB
   * on the connections least able to afford it. */
  const smallScreen = [...app.matchAll(/media="\(max-width:700px\)" srcset="\$\{set\('(?:webp|jpg)',\[([\d,\s]+)\]\)/g)]
    .map(m => m[1].split(',').map(n => Number(n.trim())));
  assert.ok(smallScreen.length >= 2, 'expected a phone-capped <source> for each format');
  for (const list of smallScreen) {
    assert.ok(!list.includes(Math.max(...WIDTHS)),
      'the phone sources offer ' + JSON.stringify(list) + ', which includes the largest rendition');
  }

  const names = [...app.matchAll(/artFigure\('([a-z0-9-]+)'/g)].map(m => m[1]);
  assert.ok(names.length >= 2, 'expected both artworks to be used; found ' + names.length);

  const missing = [];
  for (const name of new Set(names)) {
    for (const w of WIDTHS) {
      for (const ext of ['webp', 'jpg']) {
        const f = `assets/avesta/img/${name}-${w}.${ext}`;
        if (!existsSync(f)) missing.push(f);
      }
    }
  }
  assert.deepEqual(missing, [],
    'the markup offers files that are not in the repository:\n  ' + missing.join('\n  '));
});

test('THE ONE THAT MATTERS: nothing shifts while a picture loads', () => {
  const fig = app.slice(app.indexOf('const artFigure='), app.indexOf('const lowestRate='));
  assert.match(fig, /width="\$\{w\}"\s+height="\$\{h\}"/,
    'every <img> must carry width and height, or the text below it jumps when '
    + 'the picture arrives — worst on the slow connections this audience has');
  // And the callers must pass the real pixel dimensions, not placeholders.
  for (const [, name, w, h] of app.matchAll(/artFigure\('([a-z0-9-]+)','[^']*',(\d+),(\d+)/g)) {
    const file = `assets/avesta/img/${name}-1120.webp`;
    assert.ok(existsSync(file), file + ' is missing');
    assert.equal(Number(w), 1120, `${name} declares width ${w}; the largest rendition is 1120`);
    const ratio = Number(w) / Number(h);
    assert.ok(ratio > 1.1 && ratio < 1.5,
      `${name} declares ${w}x${h}, a ratio of ${ratio.toFixed(2)} — that is not one of the two artworks`);
  }
});

test('the pictures stay within a sensible weight for mobile data', () => {
  const files = readdirSync('assets/avesta/img');
  const heaviest = {webp: 0, jpg: 0};
  for (const f of files) {
    const kb = statSync('assets/avesta/img/' + f).size / 1024;
    if (f.endsWith('.webp')) heaviest.webp = Math.max(heaviest.webp, kb);
    if (f.endsWith('.jpg'))  heaviest.jpg  = Math.max(heaviest.jpg, kb);
  }
  assert.ok(heaviest.webp < 60, `the largest WebP is ${heaviest.webp.toFixed(1)} kB; keep it under 60`);
  assert.ok(heaviest.jpg  < 130, `the largest JPEG is ${heaviest.jpg.toFixed(1)} kB; keep it under 130`);

  // The smallest rendition is what a phone actually fetches; it is the one
  // that matters most and the one easiest to let drift.
  for (const name of ['sculpture', 'circuitry']) {
    const kb = statSync(`assets/avesta/img/${name}-560.webp`).size / 1024;
    assert.ok(kb < 20, `${name}-560.webp is ${kb.toFixed(1)} kB; a phone should pay under 20`);
  }
});

test('WebP is offered first, with a JPEG that still works without it', () => {
  const fig = app.slice(app.indexOf('const artFigure='), app.indexOf('const lowestRate='));
  assert.match(fig, /<source type="image\/webp"/, 'WebP should be the preferred source');
  assert.match(fig, /<img src="\/assets\/avesta\/img\/\$\{name\}-840\.jpg"/,
    'the <img> fallback must be a JPEG, for anything that cannot read WebP');
  assert.match(fig, /sizes="/, 'without sizes the browser assumes full width and over-fetches');
});

test('every picture has alt text, and the decoration is hidden from screen readers', () => {
  const calls = [...app.matchAll(/artFigure\('[a-z0-9-]+','([^']*)'/g)].map(m => m[1]);
  assert.ok(calls.length >= 2);
  for (const alt of calls) {
    assert.ok(alt.length > 20, 'alt text "' + alt + '" is too thin to describe the picture');
  }
  assert.match(app, /<span class="mark" aria-hidden="true">A<\/span>/,
    'the oversized letter is decoration and must not be read out');
});

/* ───────────────── the figure on the picture is the real one ───────────────── */

test('the rate on the artwork comes from rates.json, not from the markup', () => {
  assert.match(app, /const lowestRate=\(\)=>Math\.min\(\.\.\.Object\.values\(rates\)\)/,
    'the badge must read the published rates');
  assert.match(app, /artBadge\('Flat interest from',lowestRate\(\)\+'%'\)/,
    'a hard-coded percentage on the picture would drift from the calculator');

  /* The published price lives under "terms" in rates.json; domain.js carries
   * the subset the self-serve calculator offers. The badge must agree with
   * the published floor, whichever of the two it reads.
   *
   * Note while here: rates.json publishes an eight-week term at 55% that
   * domain.js does not offer, so the calculator refuses it. check-rates.mjs
   * passes, so that looks deliberate — left alone rather than quietly
   * changed, but it is why this reads the floor and not the ceiling. */
  const published = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8')).terms;
  const values = Object.entries(published)
    .filter(([k]) => /^\d+$/.test(k)).map(([, v]) => Number(v));
  assert.ok(values.length >= 2, 'expected several published rates');

  const offered = readFileSync('assets/avesta/domain.js', 'utf8').match(/export const rates=\{([^}]*)\}/)[1];
  const offeredValues = [...offered.matchAll(/:(\d+)/g)].map(m => Number(m[1]));
  assert.equal(Math.min(...offeredValues), Math.min(...values),
    'the lowest rate the badge shows must be the lowest published rate');
  // The stat strip quotes the same lowest rate; it must come from the same place.
  assert.match(app, /<strong>\$\{lowestRate\(\)\}%<\/strong>/,
    'the stat strip should quote the published lowest rate too');
  assert.match(app, /1–\$\{Math\.max\(\.\.\.Object\.keys\(rates\)\.map\(Number\)\)\}/,
    'the term range should come from the published rates as well');
});

/* ───────────────── motion stays polite ───────────────── */

test('nothing moves for a visitor who asked for stillness', () => {
  // Every looping animation must sit inside a no-preference guard.
  for (const m of bare.matchAll(/animation:[^;]*infinite/g)) {
    const at = bare.indexOf(m[0]);
    const guard = bare.lastIndexOf('prefers-reduced-motion: no-preference', at);
    const closes = bare.indexOf('}', guard);
    assert.ok(guard !== -1 && guard < at,
      'a looping animation outside a reduced-motion guard: ' + m[0]);
  }
  assert.match(bare, /@media \(hover: hover\) and \(prefers-reduced-motion: no-preference\)/,
    'hover motion must be opt-in on the preference');

  assert.match(js, /prefers-reduced-motion: reduce/, 'the tilt must check the preference');
  assert.match(js, /\(hover: hover\) and \(pointer: fine\)/,
    'the tilt must be gated on a fine pointer, or a thumb leaves the picture stuck at an angle');
  assert.match(js, /pointerType !== 'mouse'/, 'and it should ignore touch and pen events');
});

test('the tilt finds figures that appear after the first render', () => {
  /* The marketing pages are rendered by app.js on navigation, so binding once
   * at load would leave every page after the first inert. */
  assert.match(js, /MutationObserver/,
    'artwork.js must watch for figures added by the router');
  assert.match(js, /dataset\.avTilt/, 'and must not bind the same figure twice');
});

/* ───────────────── the type is really served ───────────────── */

test('THE ONE THAT MATTERS: the fonts exist and the stylesheet points at them', () => {
  const faces = [...styles.matchAll(/src:url\((fonts\/[^)]+)\)/g)].map(m => m[1]);
  assert.equal(faces.length, 2, 'expected two @font-face sources, found ' + faces.length);
  for (const f of faces) {
    assert.ok(existsSync('assets/avesta/' + f), 'styles.css asks for ' + f + ', which is not in the repo');
  }
  assert.doesNotMatch(styles, /fonts\.googleapis\.com|fonts\.gstatic\.com/,
    'the fonts are self-hosted on purpose; a Google request would undo that');
});

test('text is readable before the fonts arrive, and the first two are preloaded', () => {
  const faceBlock = styles.slice(0, styles.indexOf(':root'));
  const faces = faceBlock.match(/@font-face\{[^}]*\}/g) || [];
  assert.equal(faces.length, 2);
  for (const f of faces) {
    assert.match(f, /font-display:swap/,
      'without swap the text is invisible until the font lands, which on a slow '
      + 'connection is worse than the fallback face');
  }
  assert.match(page, /rel="preload" as="font" type="font\/woff2" crossorigin href="\/assets\/avesta\/fonts\/manrope\.woff2"/,
    'the body face should be preloaded');
  assert.match(page, /fraunces-normal\.woff2/, 'and the heading face');
  // crossorigin is required even same-origin, or the preload is thrown away
  // and the font is fetched a second time.
  for (const m of page.matchAll(/<link rel="preload" as="font"[^>]*>/g)) {
    assert.match(m[0], /crossorigin/, 'a font preload without crossorigin is fetched twice: ' + m[0]);
  }
});

test('the typography budget holds', () => {
  let total = 0;
  for (const f of readdirSync('assets/avesta/fonts')) {
    if (f.endsWith('.woff2')) total += statSync('assets/avesta/fonts/' + f).size;
  }
  const kb = total / 1024;
  assert.ok(kb < 70, `the fonts come to ${kb.toFixed(1)} kB; keep the set under 70`);
  assert.ok(kb > 40, `only ${kb.toFixed(1)} kB of fonts — a file is probably missing`);
});

test('the italic stays dropped unless somebody decides otherwise', () => {
  /* It was 44.6 kB of a 105 kB set for one line of each heading. Dropped on
   * 28 November; the emphasis is carried by weight, colour and the gold rule
   * instead. Re-adding it is a decision about mobile data, not a tidy-up. */
  assert.ok(!existsSync('assets/avesta/fonts/fraunces-italic.woff2'),
    'the italic file is back; that is 44.6 kB on every first visit');
  assert.doesNotMatch(styles, /font-style:italic[^}]*fraunces-italic/,
    'an italic @font-face has reappeared');
  assert.match(styles, /\.hero h1 em,\.page-head h1 em\{font-style:normal/,
    'the emphasised heading line must pin font-style:normal, or the browser '
    + 'skews the roman into a fake italic, which looks mechanical on a serif');
});

test('the fonts are licensed and the licences ship with them', () => {
  for (const f of ['LICENSE-Fraunces.txt', 'LICENSE-Manrope.txt']) {
    const p = 'assets/avesta/fonts/' + f;
    assert.ok(existsSync(p), f + ' is missing; both faces are OFL and the licence must travel with them');
    assert.match(readFileSync(p, 'utf8'), /SIL OPEN FONT LICENSE/i);
  }
});

test('nothing is still asking for a font the site never loads', () => {
  assert.doesNotMatch(styles, /font-family:Inter\b/,
    'Inter was declared but never served, so this was really Arial');
  // Georgia stays as the fallback behind Fraunces, but never on its own.
  for (const m of styles.matchAll(/font-family:([^;}]+)/g)) {
    const stack = m[1];
    if (/Georgia/.test(stack)) {
      assert.match(stack, /^Fraunces,/, 'a Georgia stack with no Fraunces in front: ' + stack);
    }
  }
  const appStacks = [...app.matchAll(/font-family:([^;"'}]+)/g)].map(m => m[1]);
  for (const stack of appStacks) {
    if (/Georgia/.test(stack)) {
      assert.match(stack, /^Fraunces,/, 'app.js still sets a bare Georgia stack: ' + stack);
    }
  }
});

/* ───────────────── wired into the page ───────────────── */

test('the stylesheet and script are loaded, versioned by their contents', () => {
  for (const f of ['artwork.css', 'artwork.js']) {
    assert.match(page, new RegExp("av_asset\\('/assets/avesta/" + f.replace('.', '\\.') + "'\\)"),
      f + ' must be loaded through av_asset, so the CDN cannot serve a stale copy');
  }
  assert.match(page, /artwork\.js'\) \?>" defer/, 'artwork.js should not block rendering');
});

test('the band degrades to a picture beside words', () => {
  /* Presentation only: if the stylesheet fails, the markup must still be a
   * figure and some text, not an empty box. */
  assert.match(app, /<figure class="art-figure/);
  assert.doesNotMatch(bare, /\.art-band[^{]*\{[^}]*display:\s*none/,
    'nothing in the band may start hidden; there is no script here to reveal it');
  assert.doesNotMatch(bare, /content:\s*"[^"]{2,}"/,
    'no real text may be injected from CSS — it would be invisible to search and to readers');
});
