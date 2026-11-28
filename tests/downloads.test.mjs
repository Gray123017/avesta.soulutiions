import {test, before, after} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, existsSync} from 'node:fs';
import {spawn, spawnSync, execFileSync} from 'node:child_process';
import {createServer} from 'node:net';

/* The software hub, and the bug that produced these tests.
 *
 * On 28 November 2026 the live page offered six products with Download
 * buttons. Four of them — the asset tracker ZIP, both Avanto Sentinel links
 * and the MoneyTrack installer — pointed at files that had never been
 * committed, so every one of them returned 404 to anybody who clicked. The
 * page had looked finished for weeks.
 *
 * A dead download on a lender's own site costs more than a missing one: it
 * is the first thing a visitor tries, and it tells them the business does not
 * check its own work. So the rule here is absolute — a Download button
 * exists only where a file exists, and these tests check it against the
 * filesystem and over HTTP rather than against anyone's intention.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;
const source = readFileSync('downloads.php', 'utf8');

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
    try { await fetch(base + '/downloads.php'); break; } catch { await new Promise(r => setTimeout(r, 50)); }
  }
});

after(async () => {
  if (server) { const done = new Promise(r => server.once('exit', r)); server.kill(); await done; }
});

const products = () => JSON.parse(execFileSync(php, ['-r',
  'ob_start(); include "downloads.php"; ob_end_clean(); echo json_encode($products);'],
  {encoding: 'utf8'}));

/* ───────────────────────── no dead downloads, ever ───────────────────────── */

test('THE ONE THAT MATTERS: every download link on the page really downloads',
  {skip: !hasPhp}, async () => {
  const html = await (await fetch(base + '/downloads.php')).text();
  const links = [...html.matchAll(/<a[^>]*\bhref="([^"]+)"[^>]*\bdownload\b/g)].map(m => m[1]);
  assert.ok(links.length >= 1, 'the page should offer at least one real download');

  const dead = [];
  for (const href of links) {
    const res = await fetch(base + href, {redirect: 'manual'});
    const size = Number(res.headers.get('content-length') || 0);
    if (res.status !== 200) dead.push(`${href} → ${res.status}`);
    else if (size < 1024) dead.push(`${href} → 200 but only ${size} bytes`);
  }
  assert.deepEqual(dead, [],
    'a Download button that 404s is worse than no button:\n  ' + dead.join('\n  '));
});

test('THE ONE THAT MATTERS: a product with no file never shows a Download button',
  {skip: !hasPhp}, () => {
  for (const p of products()) {
    const href = p.primary_href || '';
    const onDisk = href !== '' && existsSync('.' + href);
    if (p.status === 'available') {
      assert.ok(onDisk, `"${p.name}" is marked available but ${href || '(no file)'} is not in the repo`);
    } else {
      assert.ok(!onDisk || !p.primary_download,
        `"${p.name}" is ${p.status} yet still carries a download link`);
    }
  }
});

test('the renderer refuses a download even if the status is left wrong',
  {skip: !hasPhp}, () => {
  /* Belt to the braces. Somebody removes a build but forgets the status;
   * without this check the page would put a 404 in front of a visitor
   * again, which is the bug this file exists for. */
  const out = execFileSync(php, ['-r',
    'ob_start(); include "downloads.php"; ob_end_clean();'
    + 'echo json_encode(av_product_actions(["name"=>"Ghost","status"=>"available",'
    + '"primary_href"=>"/downloads/nothing/here.exe","primary_label"=>"Download",ّ'
      .replace('ّ', '')
    + '"primary_download"=>true]));'], {encoding: 'utf8'});
  const actions = JSON.parse(out);
  assert.ok(!actions.some(a => a.download),
    'a missing file must not produce a download link whatever the status says');
  assert.match(actions[0].href, /^mailto:|^https:\/\/wa\.me/,
    'it should offer a way to ask instead');
});

/* ───────────────────────── it has to be honest ───────────────────────── */

test('every product declares a status the page knows how to render', {skip: !hasPhp}, () => {
  const allowed = new Set(['available', 'on_request', 'in_development']);
  for (const p of products()) {
    assert.ok(allowed.has(p.status), `"${p.name}" has status "${p.status}"`);
    assert.ok(p.name && p.description && Array.isArray(p.features) && p.features.length >= 2,
      `"${p.name}" is missing a description or features`);
  }
});

test('a product that is not downloadable says so in words', {skip: !hasPhp}, async () => {
  const html = await (await fetch(base + '/downloads.php')).text();
  for (const p of products().filter(p => p.status !== 'available')) {
    const panel = html.slice(html.indexOf(`id="${p.id}"`));
    const end = panel.indexOf('</article>');
    const body = panel.slice(0, end);
    assert.match(body, /Not finished yet|not published here yet/,
      `"${p.name}" is not downloadable but the card does not say so`);
    assert.match(body, /Ask for a copy|Tell me when it is ready/,
      `"${p.name}" offers no way to ask`);
  }
});

test('no product claims a licence, an approval or a price it has not got', {skip: !hasPhp}, () => {
  for (const p of products()) {
    const text = [p.name, p.description, ...(p.features || []), ...(p.meta || [])].join(' ');
    assert.doesNotMatch(text, /licen[cs]ed|certified|approved by|accredited|guaranteed/i,
      `"${p.name}" makes a claim it cannot back: ${text}`);
  }
});

test('the client-built product does not name the client', {skip: !hasPhp}, () => {
  /* BizPOS was built for a named business. Putting a client's name on a
   * public page is theirs to agree to, not ours to assume. */
  assert.doesNotMatch(source, /Samuka/i,
    "a client's name is on the public software page; that needs their say-so");
});

/* ───────────────────────── the motion stays polite ───────────────────────── */

test('the hub animations are entrances, and stop for reduced motion', () => {
  const css = readFileSync('assets/avesta/software-hub.css', 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
  const animated = [...css.matchAll(/animation:[^;]*infinite/g)].map(m => m[0]);
  for (const a of animated) {
    const at = css.indexOf(a);
    const guard = css.lastIndexOf('prefers-reduced-motion: no-preference', at);
    assert.ok(guard !== -1, 'a looping animation outside a reduced-motion guard: ' + a);
  }
  assert.match(css, /@media \(prefers-reduced-motion: no-preference\)/,
    'the new motion must be opt-in on the preference, not opt-out');
});
