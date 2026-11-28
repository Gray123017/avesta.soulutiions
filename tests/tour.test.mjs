import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';

/* The guided tour of the loan application.
 *
 * This one runs on top of the live application form, which is the riskiest
 * place on the site to put decoration. The form holds somebody's half-filled
 * loan application, saved to their device. So the tour is bound by rules the
 * showcase layer never needed:
 *
 *   It may not touch the application. No field value changes, nothing is
 *   submitted, no saved progress is cleared. It reads the form and points at
 *   it; it never writes.
 *
 *   It must put the applicant back. Walking the tour moves the wizard through
 *   all five panels, so when it ends the applicant has to be returned to the
 *   step they were on, not abandoned on step five.
 *
 *   It may not open by itself. An overlay that throws itself across a lending
 *   form is the pop-up we deliberately did not build on the home page.
 *
 *   It may not break the form if it breaks itself. A missing target, a dead
 *   localStorage, a thrown exception: the application still has to work.
 */

const source = readFileSync('app/index.php', 'utf8');
const tourJs = readFileSync('assets/avesta/tour.js', 'utf8');
const tourCss = readFileSync('assets/avesta/tour.css', 'utf8');

function section(start, end) {
  const a = source.indexOf(start), b = source.indexOf(end, a + start.length);
  assert.ok(a >= 0 && b > a, 'could not find ' + start);
  return source.slice(a, b);
}

/* The real form: the checklist card, the progress bar and all five panels,
 * with the real goToStep driving them. */
/* Which panel the form is actually showing — the same thing an applicant
 * sees, and the only honest way to check where the tour left them. */
const at = d => Number(String((d.querySelector('.wiz-panel.active') || {}).id || '').replace('wiz-', '')) || 0;

function boot({reduce = false, storage = true} = {}) {
  const w = new Window({settings: {enableJavaScriptEvaluation: true, disableCSSFileLoading: true,
                                   suppressInsecureJavaScriptEnvironmentWarning: true}});
  w.happyDOM.setWindowSize({width: 1100, height: 900});
  w.document.body.innerHTML =
    section('<div class="loan-start-card"', '<!-- RESUME BANNER -->') +
    section('<div class="wizard-progress" id="wizard-progress">', '<!-- ═══ STEP 4: DOCUMENTS') +
    '</div><div class="wiz-panel" id="wiz-4"></div><div class="wiz-panel" id="wiz-5"></div>'
    // The footer nav and the card wrapper live outside the slice above, but
    // the real goToStep reaches for them, so the harness has to supply them.
    + '<div class="form-card"></div>'
    + '<button id="wiz-back"></button><button id="wiz-next"></button>'
    + '<button id="submit-btn"></button><button id="print-btn"></button>';

  w.matchMedia = query => ({matches: /prefers-reduced-motion/.test(String(query))
      ? (/no-preference/.test(query) ? !reduce : reduce) : false,
    media: query, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {}});

  if (!storage) {
    Object.defineProperty(w, 'localStorage', {get() { throw new Error('blocked'); }});
  }
  w.scrollTo = () => {};
  w.Element.prototype.scrollIntoView = () => {};

  // The real navigation, and nothing else from the 7,700-line page.
  w.eval(`
    const TOTAL_STEPS = 5; let currentStep = 1;
    function updateFieldCounter(){} function saveProgress(){} function hideStepBanner(){}
    // Step five re-scales the signature pads; no canvas here, so leave it empty.
    const sigCanvases = {}, sigSigned = {};
    ${section('function goToStep(n) {', 'function wizNav(')}
    window.goToStep = goToStep;
    // Deliberately NOT exposed on window: the real page declares it with a
    // let at the top level of a classic script, so window.currentStep is
    // undefined there. The tour has to read the form, not a global.
  `);
  w.eval(tourJs);
  return {w, d: w.document};
}

const rates = JSON.parse(readFileSync('assets/avesta/rates.json', 'utf8')).terms;

/* ───────────────────────── it may not touch the application ───────────────────────── */

test('THE ONE THAT MATTERS: the tour never writes to the application', async () => {
  // Reading the form is the whole job. Writing to it is somebody's loan.
  const forbidden = [
    [/\.value\s*=[^=]/, 'assigns a field value'],
    [/\.submit\s*\(/, 'submits the form'],
    [/\.click\s*\(\s*\)/, 'clicks something on the applicant’s behalf'],
    [/\.checked\s*=[^=]/, 'ticks a box for them'],
    [/removeItem|\.clear\s*\(/, 'clears saved progress'],
    [/fetch\s*\(|XMLHttpRequest/, 'talks to the server'],
  ];
  for (const [pattern, what] of forbidden) {
    assert.doesNotMatch(tourJs, pattern, 'the tour ' + what + ', which it must never do');
  }
  // And prove it on the real markup rather than only in the source.
  const {w, d} = boot();
  const amount = d.createElement('input');
  amount.id = 'loan_amt'; amount.value = '4000';
  d.getElementById('wiz-1').append(amount);
  w.AvestaTour.open();
  for (let i = 0; i < 8; i++) w.AvestaTour.next();
  assert.equal(amount.value, '4000', 'a field changed while the tour was running');
  await w.happyDOM.close();
});

test('THE ONE THAT MATTERS: it puts the applicant back on the step they were on', async () => {
  const {w, d} = boot();
  w.goToStep(3);
  assert.equal(at(d), 3);

  w.AvestaTour.open();
  for (let i = 0; i < 4; i++) w.AvestaTour.next();
  assert.notEqual(at(d), 3, 'the tour should actually move through the form');

  w.AvestaTour.close();
  assert.equal(at(d), 3, 'ending the tour must return them to where they were');
  assert.equal(d.getElementById('wiz-3').classList.contains('active'), true);
  await w.happyDOM.close();
});

test('escape closes it, and leaves them where they started', async () => {
  const {w, d} = boot();
  w.goToStep(2);
  w.AvestaTour.open();
  w.AvestaTour.next();
  d.dispatchEvent(new w.KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
  assert.equal(d.querySelector('[data-av-tour]'), null, 'escape must close the tour');
  assert.equal(at(d), 2, 'escape must also put them back where they were');
  await w.happyDOM.close();
});

/* ───────────────────────── it may not open by itself ───────────────────────── */

test('it is shut on arrival, and opens only when asked', async () => {
  const {w, d} = boot();
  assert.equal(d.querySelector('[data-av-tour]'), null, 'the tour must not throw itself at an applicant');
  const trigger = d.querySelector('[data-av-tour-start]');
  assert.ok(trigger, 'the checklist card should carry the button that starts it');
  trigger.click();
  assert.ok(d.querySelector('[data-av-tour]'), 'and the button has to actually start it');
  await w.happyDOM.close();
});

/* ───────────────────────── it must point at things that exist ───────────────────────── */

test('THE ONE THAT MATTERS: every element the tour points at is really in the form', async () => {
  /* This is the test that earns its keep. The tour names parts of a
   * 7,700-line page by selector; rename one and the tour silently points at
   * nothing. */
  const {w, d} = boot();
  const missing = [];
  for (const step of w.AvestaTour.steps) {
    assert.ok(step.title && step.body, 'every stop needs a title and an explanation');
    if (!step.target) continue;                       // a stop about the form as a whole
    if (!d.querySelector(step.target)) missing.push(step.target + '  (' + step.title + ')');
  }
  assert.deepEqual(missing, [],
    'the tour points at parts of the form that no longer exist:\n  ' + missing.join('\n  '));
  await w.happyDOM.close();
});

test('the stops cover the five steps of the wizard, in order', async () => {
  const {w} = boot();
  const steps = w.AvestaTour.steps.map(s => s.step).filter(Boolean);
  assert.deepEqual([...new Set(steps)], [1, 2, 3, 4, 5],
    'the walkthrough should visit each wizard step once, in order');
  await w.happyDOM.close();
});

test('it quotes no price the engine will not honour', async () => {
  const {w} = boot();
  const published = Object.values(published_rates());
  for (const step of w.AvestaTour.steps) {
    for (const quoted of (step.title + ' ' + step.body).match(/\d+(\.\d+)?%/g) || []) {
      assert.ok(published.includes(Number(quoted.replace('%', ''))),
        quoted + ' is not a published rate, but the tour tells an applicant it is');
    }
  }
  await w.happyDOM.close();
  function published_rates() { return rates; }
});

/* ───────────────────────── it may not break the form ───────────────────────── */

test('a stop whose target has been renamed is skipped, not thrown', async () => {
  const {w, d} = boot();
  d.getElementById('wizard-progress').remove();        // pretend somebody renamed it
  w.AvestaTour.open();
  for (let i = 0; i < 8; i++) w.AvestaTour.next();     // must not throw
  w.AvestaTour.close();
  assert.equal(d.querySelector('[data-av-tour]'), null);
  await w.happyDOM.close();
});

test('a browser with localStorage switched off still gets the tour', async () => {
  const {w, d} = boot({storage: false});
  const trigger = d.querySelector('[data-av-tour-start]');
  trigger.click();
  assert.ok(d.querySelector('[data-av-tour]'), 'a blocked localStorage must not cost somebody the tour');
  w.AvestaTour.close();
  await w.happyDOM.close();
});

/* ───────────────────────── the usual courtesies ───────────────────────── */

test('it is a dialog, it takes focus, and it gives it back', async () => {
  const {w, d} = boot();
  const trigger = d.querySelector('[data-av-tour-start]');
  trigger.focus();
  trigger.click();

  const card = d.querySelector('[data-av-tour]');
  assert.equal(card.getAttribute('role'), 'dialog');
  assert.equal(card.getAttribute('aria-modal'), 'true');
  assert.ok(card.getAttribute('aria-labelledby'), 'the dialog has to say what it is');
  assert.ok(card.contains(d.activeElement), 'focus must move into the tour');

  w.AvestaTour.close();
  assert.equal(d.activeElement, trigger, 'and come back to the button that opened it');
  await w.happyDOM.close();
});

test('back goes back, and the counter tells the truth', async () => {
  const {w, d} = boot();
  w.AvestaTour.open();
  const at = () => d.querySelector('[data-av-tour-count]').textContent;
  const total = w.AvestaTour.steps.length;
  assert.equal(at(), '1 of ' + total);
  w.AvestaTour.next();
  assert.equal(at(), '2 of ' + total);
  w.AvestaTour.back();
  assert.equal(at(), '1 of ' + total);
  w.AvestaTour.back();
  assert.equal(at(), '1 of ' + total, 'back from the first stop should stay put, not go negative');
  await w.happyDOM.close();
});

test('with reduced motion the spotlight stops moving and nothing fades', async () => {
  const {w, d} = boot({reduce: true});
  w.AvestaTour.open();
  assert.equal(d.querySelector('[data-av-tour]').getAttribute('data-av-still'), 'true');
  const css = tourCss.replace(/\/\*[\s\S]*?\*\//g, '');
  const guarded = css.slice(css.indexOf('prefers-reduced-motion'));
  assert.match(guarded, /transition: *none/, 'reduced motion has to switch the transitions off');
  await w.happyDOM.close();
});

test('the tour is small enough not to be felt on a metered connection', () => {
  const bytes = tourJs.length + tourCss.length;
  assert.ok(bytes < 20000, 'the tour is ' + bytes + ' bytes uncompressed; keep it under 20 kB');
});
