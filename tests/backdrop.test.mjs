import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, readdirSync} from 'node:fs';

/* The living backdrops behind the page headers.
 *
 * Gray asked for a live background. The version that costs a visitor nothing
 * is gradients moved by one transform: no image to fetch, no canvas, no
 * script, and no animation loop on the main thread. These tests hold it to
 * that, because the obvious ways to make a background "live" — a video, a
 * canvas of particles, a JavaScript requestAnimationFrame loop — all spend
 * either somebody's data or somebody's battery, and this audience is on
 * phones with both in short supply.
 *
 * The seam test is the fussy one and it earns its place: each pattern is a
 * 46px tile and each drift moves exactly 46px, which is what makes the loop
 * close invisibly. Change one without the other and the background twitches
 * once every thirty seconds, which is the kind of thing nobody can name but
 * everybody notices.
 */

const css = readFileSync('assets/avesta/backdrop.css', 'utf8');
const bare = css.replace(/\/\*[\s\S]*?\*\//g, '');
const app = readFileSync('assets/avesta/app.js', 'utf8');

test('THE ONE THAT MATTERS: it costs nothing to fetch and nothing to run', () => {
  assert.doesNotMatch(bare, /url\s*\(/,
    'a backdrop that fetches an image is a backdrop that costs mobile data');
  assert.ok(!readdirSync('assets/avesta').includes('backdrop.js'),
    'this is meant to be CSS only — no script, so nothing to execute and nothing to break');
  assert.doesNotMatch(app, /requestAnimationFrame[^;]{0,80}backdrop|backdropLoop/,
    'no animation loop: the compositor moves one transform on its own thread');
  assert.ok(css.length < 4000, 'the backdrop is ' + css.length + ' bytes; keep it under 4 kB');
});

test('THE ONE THAT MATTERS: every drift closes its own loop', () => {
  // A tile of N px must be moved by exactly N px, or the loop visibly jumps.
  const tiles = new Set();
  for (const [, sizes] of bare.matchAll(/background-size:\s*([^;]+);/g)) {
    for (const [, n] of sizes.matchAll(/(\d+)px\s+\1px/g)) tiles.add(Number(n));
  }
  assert.ok(tiles.size > 0, 'no tile sizes found, so this test is not checking anything');

  const moves = new Set();
  for (const [, frames] of bare.matchAll(/@keyframes\s+av-drift-[\w-]+\s*\{([\s\S]*?)\n\}/g)) {
    for (const [, n] of frames.matchAll(/-(\d+)px/g)) moves.add(Number(n));
  }
  assert.ok(moves.size > 0, 'no drift distances found');

  for (const move of moves) {
    assert.ok([...tiles].some(t => move % t === 0),
      `a drift of ${move}px does not land on a whole number of tiles (${[...tiles].join(', ')}px), `
      + 'so the pattern will jump when the animation restarts');
  }
});

test('the words stay above the pattern', () => {
  assert.match(bare, /\[data-backdrop\]\s*>\s*\*\s*\{[^}]*z-index:\s*1/,
    'the header content must be lifted above the backdrop layer');
  assert.match(bare, /\[data-backdrop\]::before\s*\{[^}]*z-index:\s*0/);
  assert.match(bare, /\[data-backdrop\]::before\s*\{[^}]*pointer-events:\s*none/,
    'the pattern must not swallow taps meant for the page');
});

test('asked to hold still, it holds still', () => {
  const guarded = bare.slice(bare.indexOf('prefers-reduced-motion'));
  assert.ok(guarded, 'there is no reduced-motion guard at all');
  assert.match(guarded, /animation:\s*none/,
    'reduced motion has to stop the drift');
  // The texture should remain: switching the pattern off entirely is not what
  // reduced motion asks for, and it would change the page's look for no reason.
  assert.doesNotMatch(guarded, /display:\s*none|content:\s*none/,
    'reduced motion means stop moving, not disappear');
});

test('the colours are washes, not blocks', () => {
  /* The headers carry the sentences that say what a loan costs. A backdrop
   * that darkens them undoes the contrast pass of 28 November. */
  const alphas = [...bare.matchAll(/rgba\([^)]*?,\s*\.(\d+)\s*\)/g)].map(m => Number('0.' + m[1]));
  assert.ok(alphas.length >= 3, 'expected several rgba washes');
  for (const a of alphas) {
    assert.ok(a <= 0.45, 'an alpha of ' + a + ' is too strong to sit behind text');
  }
});

test('each of the three headers is wired to a backdrop, and no other', () => {
  const downloads = readFileSync('downloads.php', 'utf8');
  const used = [...new Set([...(app + downloads).matchAll(/data-backdrop="([a-z]+)"/g)].map(m => m[1]))].sort();
  assert.deepEqual(used, ['home', 'it', 'lending'],
    'the front door, lending, IT and the software hub get backdrops; nothing else does');
  for (const theme of used) {
    assert.match(bare, new RegExp('\\[data-backdrop="' + theme + '"\\]::before'),
      theme + ' is set in the markup but has no pattern in the stylesheet');
  }
  // And nothing in the stylesheet points at a theme the markup never sets.
  for (const [, theme] of bare.matchAll(/\[data-backdrop="([a-z]+)"\]/g)) {
    assert.ok(used.includes(theme), 'the stylesheet styles "' + theme + '", which no page uses');
  }
});

test('the stylesheet is actually loaded, versioned by its contents', () => {
  const page = readFileSync('index.php', 'utf8');
  assert.match(page, /av_asset\('\/assets\/avesta\/backdrop\.css'\)/,
    'backdrop.css has to be linked, and through av_asset so the CDN cannot serve a stale copy');
});
