/* Avesta presentation layer: reveal on scroll, a rotating advert, a drawn
 * explainer and a total that counts up.
 *
 * Three rules this file keeps, because it runs on a lending site:
 *
 *   It never hides anything. The class that lets the stylesheet hide content
 *   is added here, after the motion check, so a page whose script never runs
 *   is a page you can still read.
 *
 *   It never touches the network. Everything is drawn from markup already on
 *   the page. Borrowers pay for their data by the megabyte.
 *
 *   It never animates a figure somebody is editing. The total counts up once,
 *   on arrival, and after that it changes instantly — a number sliding about
 *   under your fingers is how a borrower misreads what a loan costs.
 */
(() => {
  'use strict';

  const D = document;
  const main = D.getElementById('main');
  if (!main) return;

  let reduce = false;
  try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (_) { reduce = true; }

  /* The one switch. Everything the stylesheet hides is nested under it. */
  if (!reduce) D.documentElement.classList.add('av-motion');

  /* ── reveal ───────────────────────────────────────────────────────────── */

  const REVEAL = '.card, .choice-card, .rate-card, .steps-grid > div, .trust-row > div,'
               + ' .quote-card, .section-head, [data-av-rotator], [data-av-explainer]';
  let watcher = null;

  function show(el) { el.setAttribute('data-av-shown', 'true'); }

  function setupReveal() {
    if (reduce) return;                       // nothing is tagged, so nothing can hide
    const items = [...main.querySelectorAll(REVEAL)].filter(el => !el.hasAttribute('data-av-reveal'));
    if (!items.length) return;
    items.forEach((el, i) => {
      el.setAttribute('data-av-reveal', '');
      el.style.setProperty('--av-i', String(i % 4));   // a short stagger, not a queue
    });
    if (typeof IntersectionObserver !== 'function') { items.forEach(show); return; }
    if (!watcher) {
      watcher = new IntersectionObserver((entries, self) => {
        for (const entry of entries) if (entry.isIntersecting) { show(entry.target); self.unobserve(entry.target); }
      }, {rootMargin: '0px 0px -8% 0px', threshold: 0.08});
    }
    items.forEach(el => watcher.observe(el));
  }

  /* ── the headline, a word at a time ───────────────────────────────────── */

  function textNodes(node, out) {
    for (const child of [...node.childNodes]) {
      if (child.nodeType === 3) { if (child.nodeValue.trim()) out.push(child); }
      else if (child.nodeType === 1) textNodes(child, out);
    }
    return out;
  }

  function splitHeadline() {
    if (reduce) return;
    const h1 = main.querySelector('.hero h1');
    if (!h1 || h1.hasAttribute('data-av-split')) return;
    h1.setAttribute('data-av-split', '');
    let i = 0;
    for (const node of textNodes(h1, [])) {
      const frag = D.createDocumentFragment();
      // The separators are kept, so textContent comes out byte for byte the
      // same and a screen reader reads the sentence it always read.
      for (const part of node.nodeValue.split(/(\s+)/)) {
        if (part === '') continue;
        if (/^\s+$/.test(part)) { frag.append(D.createTextNode(part)); continue; }
        const span = D.createElement('span');
        span.className = 'av-word';
        span.style.setProperty('--av-i', String(i++));
        span.textContent = part;
        frag.append(span);
      }
      node.replaceWith(frag);
    }
    const words = [...h1.querySelectorAll('.av-word')];
    const reveal = () => { show(h1); words.forEach(show); };
    if (typeof IntersectionObserver !== 'function') { reveal(); return; }
    new IntersectionObserver((entries, self) => {
      if (entries.some(e => e.isIntersecting)) { reveal(); self.disconnect(); }
    }, {threshold: 0.2}).observe(h1);
  }

  /* ── the rotating advert ──────────────────────────────────────────────── */

  let rot = null;

  function paint() {
    if (!rot) return;
    rot.slides.forEach((s, i) => s.setAttribute('data-av-current', String(i === rot.index)));
    rot.dots.forEach((d, i) => {
      d.setAttribute('aria-current', String(i === rot.index));
      d.setAttribute('aria-label', 'Show advert ' + (i + 1) + ' of ' + rot.slides.length);
    });
  }

  function go(step) { if (rot) { rot.index = (rot.index + step + rot.slides.length) % rot.slides.length; paint(); } }

  function tick() { if (rot && !rot.paused) go(1); }
  window.__avTick = tick;                     // a seam for the tests; harmless in a browser

  function teardownRotator() {
    if (rot && rot.timer) clearInterval(rot.timer);
    rot = null;
  }

  function setupRotator() {
    const el = main.querySelector('[data-av-rotator]');
    if (rot && rot.el === el) return;
    teardownRotator();
    if (!el) return;

    const slides = [...el.querySelectorAll('[data-av-slide]')];
    if (slides.length < 2) return;
    rot = {el, slides, dots: [], index: 0, paused: reduce, timer: null};

    if (reduce) {
      // Paused and unstacked: all the adverts simply sit there as links.
      el.setAttribute('data-av-paused', 'true');
      slides.forEach(s => s.setAttribute('data-av-current', 'true'));
      const controls = el.querySelector('.av-rotator-controls');
      if (controls) controls.hidden = true;
      return;
    }

    el.setAttribute('data-av-ready', 'true');
    rot.dots = [...el.querySelectorAll('[data-av-dot]')];
    rot.dots.forEach((d, i) => d.addEventListener('click', () => { rot.index = i; paint(); }));
    const next = el.querySelector('[data-av-next]'), prev = el.querySelector('[data-av-prev]');
    if (next) next.addEventListener('click', () => go(1));
    if (prev) prev.addEventListener('click', () => go(-1));

    const hold = v => () => { if (rot) rot.paused = v; };
    el.addEventListener('pointerenter', hold(true));
    el.addEventListener('pointerleave', hold(false));
    el.addEventListener('focusin', hold(true));
    el.addEventListener('focusout', hold(false));
    D.addEventListener('visibilitychange', () => { if (rot) rot.paused = D.hidden; });

    paint();
    rot.timer = setInterval(tick, 6500);
  }

  /* ── the drawn explainer ──────────────────────────────────────────────── */

  let film = null;

  function teardownExplainer() {
    if (film && film.timer) clearInterval(film.timer);
    film = null;
  }

  function setupExplainer() {
    const el = main.querySelector('[data-av-explainer]');
    if (film && film.el === el) return;
    teardownExplainer();
    if (!el) return;

    const caption = el.querySelector('[data-av-caption]');
    const transcript = el.querySelector('[data-av-transcript]');
    const lines = transcript ? [...transcript.querySelectorAll('li')].map(li => li.textContent.trim()) : [];
    const bars = [...el.querySelectorAll('[data-av-bar]')];
    if (!caption || !lines.length) return;

    film = {el, scene: 0, timer: null};
    if (transcript) transcript.hidden = true;          // the script is driving now
    el.setAttribute('data-av-ready', 'true');

    const draw = () => {
      el.setAttribute('data-av-scene', String(film.scene));
      caption.textContent = lines[film.scene - 1] || '';
      bars.forEach((b, i) => b.setAttribute('data-av-done', String(i < film.scene)));
    };
    const stop = () => { if (film.timer) clearInterval(film.timer); film.timer = null; };
    const advance = () => {
      if (film.scene >= lines.length) { stop(); el.setAttribute('data-av-finished', 'true'); return; }
      film.scene += 1; draw();
    };
    const play = () => {
      el.setAttribute('data-av-playing', 'true');
      el.removeAttribute('data-av-finished');
      if (film.scene >= lines.length) film.scene = 0;
      advance();
      stop();
      if (!reduce) film.timer = setInterval(advance, 4200);
    };

    const button = el.querySelector('[data-av-play]');
    if (button) button.addEventListener('click', play);
    const step = el.querySelector('[data-av-step]');
    if (step) step.addEventListener('click', () => { stop(); advance(); });
    const read = el.querySelector('[data-av-read]');
    if (read && transcript) read.addEventListener('click', () => {
      transcript.hidden = !transcript.hidden;
      read.setAttribute('aria-expanded', String(!transcript.hidden));
    });
  }

  /* ── the counting total ───────────────────────────────────────────────── */

  let counted = false, countFrame = null, settle = null;

  function setupCount() {
    const el = main.querySelector('[data-av-count]');
    if (!el) return;
    const target = el.getAttribute('data-av-count');

    // Any change after the first is somebody editing the amount: no animation.
    if (reduce || counted) { el.textContent = target; return; }
    counted = true;

    const digits = target.replace(/[^\d.]/g, '');
    const value = Number(digits);
    if (!Number.isFinite(value) || value <= 0) { el.textContent = target; return; }

    const started = Date.now(), span = 900;
    settle = () => {
      if (countFrame) clearInterval(countFrame);
      countFrame = null; settle = null;
      el.textContent = target;                 // always comes to rest on the real figure
    };
    window.__avSettle = () => { if (settle) settle(); };

    countFrame = setInterval(() => {
      const t = Math.min(1, (Date.now() - started) / span);
      if (t >= 1) { settle(); return; }
      const eased = 1 - Math.pow(1 - t, 3);
      el.textContent = target.replace(digits, (value * eased).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    }, 40);
  }

  if (!window.__avSettle) window.__avSettle = () => { if (settle) settle(); };

  /* ── keeping up with the router ───────────────────────────────────────── */

  function scan() {
    try { splitHeadline(); setupRotator(); setupExplainer(); setupCount(); setupReveal(); }
    catch (_) { /* decoration must never take the page down with it */ }
  }

  D.addEventListener('avesta:route', () => { counted = false; });

  let pending = null;
  try {
    new MutationObserver(() => {
      clearTimeout(pending);
      pending = setTimeout(scan, 0);
    }).observe(main, {childList: true, subtree: false});
  } catch (_) { /* no observer: the first scan still runs */ }

  scan();
})();
