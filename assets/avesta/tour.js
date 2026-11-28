/* Avesta — a guided walkthrough of the loan application.
 *
 * This runs on top of a form that may already hold somebody's half-finished
 * application, saved to their device. That sets the rules:
 *
 *   It reads the form and points at it. It never writes to it — no field
 *   value, no checkbox, no submit, no clearing of saved progress.
 *
 *   Walking the tour moves the wizard through all five panels, so it
 *   remembers which step the applicant was on and puts them back there when
 *   it ends, however it ends.
 *
 *   It does not open by itself. An overlay thrown across a lending form is
 *   exactly the pop-up we declined to build on the home page; this one waits
 *   to be asked.
 *
 *   If it breaks, the form does not. Every entry point is wrapped, a stop
 *   whose target has been renamed is skipped rather than thrown, and a
 *   browser with localStorage switched off simply loses the "seen" flag.
 */
(() => {
  'use strict';

  const D = document;
  const SEEN = 'avesta.tour.seen';

  const steps = [
    {
      target: '.loan-start-card',
      title: 'Have these ready',
      body: 'Your NRC, a phone number that works, and your income or employer details. '
          + 'Your progress saves on this device, so you can stop and come back.',
    },
    {
      step: 1,
      target: '#applicant-category-section',
      title: 'Start with how you are applying',
      body: 'Employer check-off, formally employed, or self-employed against security. '
          + 'Your answer decides what the rest of the form asks you for.',
    },
    {
      step: 2,
      target: '#cc-total',
      title: 'See the full cost before you commit',
      body: 'Interest is flat, from 15% for one week to 30% for four. This panel shows '
          + 'what you borrow, what it costs and what you repay in total — before you go on.',
    },
    {
      step: 3,
      target: '#first_payment',
      title: 'Check the repayment dates',
      body: 'Choose how often you repay and the dates fill in. Look at them properly: '
          + 'these are the days the money has to be there.',
    },
    {
      step: 4,
      target: '#wiz-4',
      title: 'Upload your documents',
      body: 'Clear photographs are fine — both sides of your NRC, and anything your '
          + 'category asked for. A blurred page is the commonest reason for a delay.',
    },
    {
      step: 5,
      target: '#wiz-5',
      title: 'Review, then sign',
      body: 'Every figure is shown again here. Submitting sends it to Avesta for '
          + 'assessment — an application is not an approval, and nothing is owed until '
          + 'you sign an agreement.',
    },
  ];

  let still = false;
  try { still = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (_) { still = true; }

  let at = 0, ui = null, returnTo = 1, opener = null, onKey = null, onMove = null;

  const store = (key, value) => {
    try { if (value === undefined) return window.localStorage.getItem(key); window.localStorage.setItem(key, value); }
    catch (_) { return null; }
  };

  /* Which step the applicant is on, read from the form itself.
   *
   * Not from window.currentStep: the page declares it with `let` at the top
   * level of a classic script, and `let` creates a script-scoped binding
   * rather than a property of window. Reading it from here always gave
   * undefined, so the tour quietly returned everybody to step one no matter
   * where they had got to. The active panel is the form's own state and
   * cannot drift from what the applicant is looking at. */
  function here() {
    try {
      const panel = D.querySelector('.wiz-panel.active');
      const fromPanel = panel && Number(String(panel.id).replace('wiz-', ''));
      if (fromPanel) return fromPanel;
      const marker = D.querySelector('.wp-step.active[data-step]');
      return (marker && Number(marker.getAttribute('data-step'))) || 1;
    } catch (_) { return 1; }
  }

  function land(step) {
    if (!step) return;
    try { if (typeof window.goToStep === 'function') window.goToStep(step); } catch (_) {}
  }

  function place(target) {
    const ring = ui.ring, card = ui.card;
    const el = target ? D.querySelector(target) : null;
    if (!el) { ring.style.display = 'none'; card.setAttribute('data-av-loose', 'true'); return; }
    ring.style.display = '';
    card.removeAttribute('data-av-loose');
    /* Instant, never smooth. A smooth scroll finishes after this function
     * returns, so the rectangle measured below would be the one from before
     * the scroll — the spotlight lands off screen and the dimming covers the
     * whole page, including the thing it is meant to be pointing at. */
    try { el.scrollIntoView({block: 'center', behavior: 'auto'}); } catch (_) {}
    let box = {top: 0, left: 0, width: 0, height: 0};
    try { box = el.getBoundingClientRect(); } catch (_) {}
    const pad = 8;
    ring.style.top = (box.top - pad) + 'px';
    ring.style.left = (box.left - pad) + 'px';
    ring.style.width = (box.width + pad * 2) + 'px';
    ring.style.height = (box.height + pad * 2) + 'px';
    /* Clear of the ring, not merely clear of the element: the ring is drawn
     * `pad` larger on every side, and a card laid over the thing it is
     * pointing at is worse than no spotlight at all. */
    const gap = 18;
    const tall = card.offsetHeight || 230;
    const below = box.top + box.height + pad + gap;
    const roomBelow = (window.innerHeight || 800) - below;
    card.style.top = (roomBelow >= tall
      ? below
      : Math.max(12, box.top - pad - gap - tall)) + 'px';
    card.style.left = Math.max(12, Math.min(box.left, (window.innerWidth || 1000) - 380)) + 'px';
  }

  function draw() {
    const stop = steps[at];
    ui.title.textContent = stop.title;
    ui.body.textContent = stop.body;
    ui.count.textContent = (at + 1) + ' of ' + steps.length;
    ui.back.disabled = at === 0;
    ui.next.textContent = at === steps.length - 1 ? 'Finish' : 'Next';
    land(stop.step);
    place(stop.target);
  }

  function build() {
    const wrap = D.createElement('div');
    wrap.className = 'av-tour';
    wrap.setAttribute('data-av-tour', '');
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-modal', 'true');
    wrap.setAttribute('aria-labelledby', 'av-tour-title');
    if (still) wrap.setAttribute('data-av-still', 'true');
    wrap.innerHTML =
      '<div class="av-tour-ring" data-av-tour-ring></div>'
    + '<div class="av-tour-card">'
    + '  <p class="av-tour-count" data-av-tour-count></p>'
    + '  <h2 class="av-tour-title" id="av-tour-title"></h2>'
    + '  <p class="av-tour-body"></p>'
    + '  <div class="av-tour-actions">'
    + '    <button type="button" class="av-tour-skip" data-av-tour-skip>Close</button>'
    + '    <button type="button" class="av-tour-back" data-av-tour-back>Back</button>'
    + '    <button type="button" class="av-tour-next" data-av-tour-next>Next</button>'
    + '  </div>'
    + '</div>';
    D.body.append(wrap);

    ui = {
      wrap,
      ring: wrap.querySelector('[data-av-tour-ring]'),
      card: wrap.querySelector('.av-tour-card'),
      title: wrap.querySelector('.av-tour-title'),
      body: wrap.querySelector('.av-tour-body'),
      count: wrap.querySelector('[data-av-tour-count]'),
      back: wrap.querySelector('[data-av-tour-back]'),
      next: wrap.querySelector('[data-av-tour-next]'),
      skip: wrap.querySelector('[data-av-tour-skip]'),
    };
    ui.next.addEventListener('click', () => api.next());
    ui.back.addEventListener('click', () => api.back());
    ui.skip.addEventListener('click', () => api.close());

    onKey = event => {
      if (event.key === 'Escape') { event.stopPropagation(); api.close(); return; }
      if (event.key !== 'Tab') return;
      const able = [ui.skip, ui.back, ui.next].filter(b => !b.disabled);
      const first = able[0], last = able[able.length - 1];
      if (event.shiftKey && D.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && D.activeElement === last) { event.preventDefault(); first.focus(); }
    };
    D.addEventListener('keydown', onKey, true);

    // If the applicant scrolls or turns the phone, the spotlight follows.
    onMove = () => { try { if (ui) place(steps[at].target); } catch (_) {} };
    window.addEventListener('scroll', onMove, {passive: true});
    window.addEventListener('resize', onMove, {passive: true});
  }

  const api = {
    steps,
    open() {
      try {
        if (ui) return;
        opener = D.activeElement;
        returnTo = here();
        at = 0;
        build();
        draw();
        ui.next.focus();
        store(SEEN, '1');
      } catch (_) { api.close(); }
    },
    next() {
      try {
        if (!ui) return;
        if (at >= steps.length - 1) { api.close(); return; }
        at += 1; draw();
      } catch (_) { api.close(); }
    },
    back() {
      try { if (ui && at > 0) { at -= 1; draw(); } } catch (_) { api.close(); }
    },
    close() {
      try {
        if (onKey) { D.removeEventListener('keydown', onKey, true); onKey = null; }
        if (onMove) {
          window.removeEventListener('scroll', onMove);
          window.removeEventListener('resize', onMove);
          onMove = null;
        }
        if (ui && ui.wrap && ui.wrap.parentNode) ui.wrap.parentNode.removeChild(ui.wrap);
        ui = null;
        land(returnTo);
        if (opener && typeof opener.focus === 'function') opener.focus();
        opener = null;
      } catch (_) { ui = null; }
    },
  };

  function addTrigger() {
    try {
      const card = D.querySelector('.loan-start-card');
      if (!card || card.querySelector('[data-av-tour-start]')) return;
      const button = D.createElement('button');
      button.type = 'button';
      button.className = 'av-tour-start';
      button.setAttribute('data-av-tour-start', '');
      button.innerHTML = '<span class="av-tour-start-ring" aria-hidden="true">'
        + '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>'
        + '</span><span></span>';
      button.lastChild.textContent = store(SEEN) ? 'Show me how again' : 'First time? Show me how';
      button.addEventListener('click', () => api.open());
      card.append(button);
    } catch (_) { /* no trigger is better than a broken form */ }
  }

  window.AvestaTour = api;
  if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', addTrigger);
  else addTrigger();
})();
