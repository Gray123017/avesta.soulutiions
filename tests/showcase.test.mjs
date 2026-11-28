import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';
import * as domain from '../assets/avesta/domain.js';

/* The presentation layer: moving headlines, a rotating advert, a tap-to-play
 * explainer and a counting total.
 *
 * All of it is decoration on a lending site, which sets the rules it has to
 * obey. Three of those are worth stating, because they are what these tests
 * are actually protecting:
 *
 *   Nothing may hide content. If the reveal animation were CSS that starts at
 *   opacity 0, then a stalled script, an old phone, or a thrown exception
 *   leaves a blank page where the loan terms should be. So the hiding is added
 *   BY the script, never by the stylesheet alone, and anyone without working
 *   JavaScript sees the whole page.
 *
 *   Nothing may cost data. Most borrowers are on mobile data they pay for by
 *   the megabyte. The explainer is drawn, not downloaded, and the whole layer
 *   makes no network request of any kind.
 *
 *   Nothing may lie about money. The total counts up, and the number it
 *   settles on has to be the number the engine computed — and it must never
 *   animate while somebody is typing an amount, because a figure sliding
 *   around under your fingers is how a borrower misreads what a loan costs.
 */

const appScript = readFileSync('assets/avesta/app.js', 'utf8')
  .replace(/^import .*$/m, 'const {money,quote,rates,schedule,today,escapeHTML:e}=window.__domain;');
const showcase = readFileSync('assets/avesta/showcase.js', 'utf8');
const showcaseCss = readFileSync('assets/avesta/showcase.css', 'utf8');
const indexHtml = readFileSync('index.php', 'utf8')
  .replace(/^<\?php[\s\S]*?\?>/, '')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');

const flush = (ms = 20) => new Promise(r => setTimeout(r, ms));

/* reduce: what the phone's accessibility setting says.
 * observer: false to act like a browser with no IntersectionObserver. */
async function boot({page = '', reduce = false, width = 1024, observer = true, run = true} = {}) {
  const w = new Window({
    url: 'https://avesta.test/index.php' + (page ? '?page=' + page : ''),
    settings: {enableJavaScriptEvaluation: true, disableCSSFileLoading: true,
               disableJavaScriptFileLoading: true, suppressInsecureJavaScriptEnvironmentWarning: true},
  });
  w.happyDOM.setWindowSize({width, height: 900});
  w.__domain = domain;

  const realMatchMedia = w.matchMedia.bind(w);
  w.matchMedia = query => {
    if (String(query).includes('prefers-reduced-motion')) {
      const wantsReduce = /no-preference/.test(query) ? !reduce : reduce;
      return {matches: wantsReduce, media: query, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {}};
    }
    return realMatchMedia(query);
  };

  const seen = [];
  if (observer) {
    w.IntersectionObserver = class {
      constructor(callback) { this.callback = callback; seen.push(this); }
      observe(el) { this.callback([{target: el, isIntersecting: true, intersectionRatio: 1}], this); }
      unobserve() {} disconnect() {} takeRecords() { return []; }
    };
  } else {
    delete w.IntersectionObserver;
  }

  w.document.write(indexHtml);
  const data = w.document.createElement('script');
  data.id = 'avesta-public-data';
  data.type = 'application/json';
  data.textContent = JSON.stringify({
    services: JSON.parse(readFileSync('assets/avesta/services.json', 'utf8')),
    help: JSON.parse(readFileSync('assets/avesta/help.json', 'utf8')),
  });
  w.document.body.append(data);

  const calls = [];
  w.fetch = async url => { calls.push(url); throw new Error('The showcase must not reach the network: ' + url); };

  w.eval(appScript);
  await flush();
  if (run) { w.eval(showcase); await flush(); }
  return {w, d: w.document, calls};
}

/* ───────────────────────── it must never hide the page ───────────────────────── */

test('THE ONE THAT MATTERS: with the script never running, every word is still on screen', async () => {
  // A phone that blocks the script, a syntax error, a CDN that drops the file.
  // Whatever the cause, the page underneath has to be the whole page.
  const {w, d} = await boot({run: false});

  const hero = d.querySelector('.hero');
  assert.ok(hero.textContent.includes('A little support'), 'the headline has to be in the markup, not written by the script');
  assert.ok(d.querySelector('main').textContent.length > 600);

  // Nothing in the markup may start invisible.
  for (const el of d.querySelectorAll('main [style]')) {
    assert.doesNotMatch(el.getAttribute('style'), /opacity\s*:\s*0|visibility\s*:\s*hidden|display\s*:\s*none/i,
      'markup must not pre-hide content: ' + el.outerHTML.slice(0, 120));
  }
  // And the stylesheet may only hide things once the script has opted in.
  // Two ways it could hide something: a rule that sets opacity:0 directly, or
  // an animation whose first frame does. Both have to be behind .av-motion.
  const css = showcaseCss.replace(/\/\*[\s\S]*?\*\//g, '');
  const hidingKeyframes = new Set();
  // The frames of this file sit one per line, so the closer is the first `}`
  // that starts a line.
  for (const [, name, frames] of css.matchAll(/@keyframes\s+([\w-]+)\s*\{([\s\S]*?)\n\}/g)) {
    if (/opacity\s*:\s*0(\D|$)/.test(frames)) hidingKeyframes.add(name);
  }
  const rules = css.replace(/@keyframes\s+[\w-]+\s*\{[\s\S]*?\n\}/g, '');
  for (const [, selector, body] of rules.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
    // Gated means the script has to opt in before anything disappears: either
    // the motion class or the ready flag, both of which only the script sets.
    // .av-scene is the exception, and an honest one — those are alternate
    // frames of a single drawing, the way frame two of a video is not missing
    // content. The words of the explainer are the transcript, asserted below.
    const gated = /\.av-motion\b|\[data-av-ready\]|\.av-scene\b/.test(selector);
    if (/opacity\s*:\s*0(\D|$)/.test(body)) {
      assert.ok(gated, 'this rule hides content without the script opting in: ' + selector.trim());
    }
    for (const name of hidingKeyframes) {
      if (new RegExp('animation(-name)?\\s*:[^;]*\\b' + name + '\\b').test(body)) {
        assert.ok(gated, 'this rule runs a hiding animation without the script opting in: ' + selector.trim());
      }
    }
  }
  assert.ok(hidingKeyframes.size > 0 || !/@keyframes/.test(css),
    'the scan above only means something if it actually parsed the keyframes');

  // The explainer is a picture, so the thing that must survive without a
  // script is its words.
  const transcript = d.querySelector('[data-av-transcript]');
  assert.ok(transcript, 'the explainer needs a written transcript');
  assert.equal(transcript.hidden, false, 'and it must be readable before any script runs');
  assert.equal(transcript.querySelectorAll('li').length, 4);
  assert.ok(transcript.textContent.includes('NRC'), 'the written steps must say what to bring');
  // Hiding it behind [hidden] is the script taking over, which is allowed.
  // Hiding it outright is not.
  assert.doesNotMatch(showcaseCss.replace(/\/\*[\s\S]*?\*\//g, ''),
    /\.av-transcript(?!\[hidden\])[^{]*\{[^}]*display: *none/,
    'the transcript may only be hidden by the script, once it has taken over');
  await w.happyDOM.close();
});

test('the reveal is skipped entirely when the phone asks for reduced motion', async () => {
  const {w, d} = await boot({reduce: true});
  assert.equal(d.documentElement.classList.contains('av-motion'), false,
    'reduced motion must not get the class that enables hiding');
  assert.equal(d.querySelectorAll('[data-av-reveal]:not([data-av-shown])').length, 0,
    'nothing may be left waiting for an animation that will never run');
  await w.happyDOM.close();
});

test('a browser with no IntersectionObserver shows everything at once', async () => {
  // Plenty of cheap Android handsets in this market are several versions behind.
  const {w, d} = await boot({observer: false});
  const pending = [...d.querySelectorAll('[data-av-reveal]')].filter(el => !el.hasAttribute('data-av-shown'));
  assert.equal(pending.length, 0, 'without an observer the fallback must reveal, not hide');
  await w.happyDOM.close();
});

/* ───────────────────────── the headline ───────────────────────── */

test('the animated headline still reads as one sentence to a screen reader', async () => {
  const {w, d} = await boot();
  const h1 = d.querySelector('.hero h1');
  assert.match(h1.textContent.replace(/\s+/g, ' ').trim(), /^A little support\.\s*A lot of possibility\.$/,
    'splitting the headline into animated pieces must not change what is read out');
  assert.ok(h1.querySelectorAll('.av-word').length >= 6, 'the headline should be split into animatable words');
  for (const word of h1.querySelectorAll('.av-word')) {
    assert.equal(word.getAttribute('aria-hidden'), null, 'the words are the text itself, so they must stay readable');
  }
  await w.happyDOM.close();
});

/* ───────────────────────── the advert, which must not be a pop-up ───────────────────────── */

test('the advert rotates in place and never covers the page', async () => {
  const {w, d} = await boot();
  const rotator = d.querySelector('[data-av-rotator]');
  assert.ok(rotator, 'the home page should carry a rotating advert');

  const slides = [...rotator.querySelectorAll('[data-av-slide]')];
  assert.ok(slides.length >= 2, 'a rotator needs something to rotate between');
  assert.equal(slides.filter(s => s.getAttribute('data-av-current') === 'true').length, 1,
    'exactly one advert is current at a time');

  // It is a section in the flow of the page, not something thrown over the top.
  const rules = showcaseCss.replace(/\s+/g, ' ');
  assert.doesNotMatch(rules, /\[data-av-rotator\][^{]*\{[^}]*position: *(fixed|sticky)/,
    'the advert must sit in the page, not float over it');

  const first = slides.findIndex(s => s.getAttribute('data-av-current') === 'true');
  rotator.querySelector('[data-av-next]').click();
  const second = slides.findIndex(s => s.getAttribute('data-av-current') === 'true');
  assert.notEqual(first, second, 'the next control must move it on');
  await w.happyDOM.close();
});

test('the advert holds still while somebody is reading or tabbing through it', async () => {
  const {w, d} = await boot();
  const rotator = d.querySelector('[data-av-rotator]');
  const at = () => [...rotator.querySelectorAll('[data-av-slide]')].findIndex(s => s.getAttribute('data-av-current') === 'true');

  rotator.dispatchEvent(new w.Event('pointerenter', {bubbles: true}));
  const held = at();
  w.eval('window.__avTick && window.__avTick()');
  assert.equal(at(), held, 'hovering must pause the rotation');

  rotator.dispatchEvent(new w.Event('pointerleave', {bubbles: true}));
  w.eval('window.__avTick && window.__avTick()');
  assert.notEqual(at(), held, 'and leaving must start it again');
  await w.happyDOM.close();
});

test('with reduced motion the advert stops rotating and offers the choice as plain links', async () => {
  const {w, d} = await boot({reduce: true});
  const rotator = d.querySelector('[data-av-rotator]');
  assert.equal(rotator.getAttribute('data-av-paused'), 'true');
  const links = [...rotator.querySelectorAll('a[href]')].map(a => new URL(a.href).pathname + new URL(a.href).search);
  assert.ok(links.some(h => /lending/.test(h)), 'the lending advert must still be reachable');
  assert.ok(links.some(h => /it/.test(h)), 'and the IT one too');
  await w.happyDOM.close();
});

/* ───────────────────────── the explainer ───────────────────────── */

test('the explainer never plays by itself and costs nothing until tapped', async () => {
  const {w, d, calls} = await boot();
  const player = d.querySelector('[data-av-explainer]');
  assert.ok(player, 'the home page should carry the explainer');
  assert.equal(player.getAttribute('data-av-playing'), null, 'it must be still until somebody asks for it');
  assert.equal(d.querySelectorAll('video[autoplay], audio[autoplay]').length, 0, 'nothing may autoplay');
  assert.equal(calls.length, 0, 'and nothing may be fetched for it');

  // Once the script is driving, the written steps fold away behind a toggle —
  // and the stylesheet has to let [hidden] win, or the fold does nothing.
  assert.equal(d.querySelector('[data-av-transcript]').hidden, true);
  assert.match(showcaseCss, /\.av-transcript\[hidden\][^{]*\{[^}]*display: *none/,
    'a display rule on the transcript outranks the browser rule for [hidden]');

  player.querySelector('[data-av-play]').click();
  assert.equal(player.getAttribute('data-av-playing'), 'true');
  assert.ok(player.querySelector('[data-av-caption]').textContent.trim().length > 0,
    'every scene needs a caption, because most people will watch it with the sound off');
  await w.happyDOM.close();
});

test('the explainer says the same published rates as the rest of the site', async () => {
  const {w, d} = await boot();
  const text = d.querySelector('[data-av-explainer]').textContent;
  const published = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8')).terms;
  const quoted = text.match(/\d+%/g) || [];
  for (const q of quoted) {
    assert.ok(Object.values(published).includes(Number(q.replace('%', ''))),
      q + ' is not a published rate, so the explainer is quoting a price the engine will not honour');
  }
  await w.happyDOM.close();
});

/* ───────────────────────── the counting total ───────────────────────── */

test('THE ONE THAT MATTERS: the total settles on the figure the engine worked out', async () => {
  const {w, d} = await boot({page: 'calculator'});
  const total = d.querySelector('[data-av-count]');
  assert.ok(total, 'the calculator total should be the counting figure');

  const truth = total.getAttribute('data-av-count');
  w.eval('window.__avSettle && window.__avSettle()');
  assert.equal(total.textContent.trim(), truth,
    'whatever it does on the way, it has to come to rest on the real total');
  assert.match(truth, /^K\s?[\d,]+\.\d{2}$/, 'and that total is money, formatted as money');
  await w.happyDOM.close();
});

test('the total does not animate while the amount is being typed', async () => {
  // A figure sliding about under somebody's fingers is how a borrower
  // misreads what a loan costs.
  const {w, d} = await boot({page: 'calculator'});
  const amount = d.querySelector('#calc-amount');
  amount.value = '4000';
  amount.dispatchEvent(new w.Event('input', {bubbles: true}));
  await flush();

  const total = d.querySelector('[data-av-count]');
  assert.equal(total.textContent.trim(), total.getAttribute('data-av-count'),
    'an edit must update the figure at once, with no animation in between');
  assert.ok(/4,?800/.test(total.textContent), 'K4,000 over two weeks is K4,800: ' + total.textContent);
  await w.happyDOM.close();
});

/* ───────────────────────── it has to survive the router ───────────────────────── */

test('moving between pages rebinds once, and does not leave a second rotator running', async () => {
  const {w, d} = await boot();
  w.eval("history.pushState({},'','/index.php?page=lending');window.dispatchEvent(new PopStateEvent('popstate'))");
  await flush(40);
  w.eval("history.pushState({},'','/index.php');window.dispatchEvent(new PopStateEvent('popstate'))");
  await flush(40);

  const rotator = d.querySelector('[data-av-rotator]');
  const at = () => [...rotator.querySelectorAll('[data-av-slide]')].findIndex(s => s.getAttribute('data-av-current') === 'true');
  const before = at();
  w.eval('window.__avTick && window.__avTick()');
  const after = at();
  w.eval('window.__avTick && window.__avTick()');
  assert.equal(at(), (after + 1) % rotator.querySelectorAll('[data-av-slide]').length,
    'two timers on one rotator would skip an advert every tick');
  assert.notEqual(before, after);
  await w.happyDOM.close();
});

test('the whole layer adds no network request on any public page', async () => {
  for (const page of ['', 'lending', 'calculator', 'it', 'about', 'contact']) {
    const {w, calls} = await boot({page});
    assert.deepEqual(calls, [], page + ' fetched something for decoration');
    await w.happyDOM.close();
  }
});

test('the layer is small enough to be free on a metered connection', async () => {
  const bytes = showcase.length + showcaseCss.length;
  assert.ok(bytes < 24000, 'the showcase is ' + bytes + ' bytes uncompressed; keep it under 24 kB');
});
