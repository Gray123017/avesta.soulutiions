import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';
import * as domain from '../assets/avesta/domain.js';

/* The IT services section: four tabs instead of twelve open cards.
 *
 * Twelve services all expanded at once made the page enormous, which is the
 * complaint. Tabs only help if each panel is genuinely short, so a test holds
 * the group sizes down rather than trusting whoever edits the data next.
 *
 * The grouping used to be an index map in the code — [0,1,3] was "Repairs" —
 * so reordering services.json silently reshuffled which service sat under
 * which heading, with nothing to notice. It lives in the data now, beside the
 * service it describes, which is where the rates and categories already live.
 */

const appScript = readFileSync('assets/avesta/app.js', 'utf8')
  .replace(/^import .*$/m, 'const {money,quote,rates,schedule,today,escapeHTML:e}=window.__domain;');
const indexHtml = readFileSync('index.php', 'utf8')
  .replace(/^<\?php[\s\S]*?\?>/, '')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
const services = JSON.parse(readFileSync('assets/avesta/services.json', 'utf8'));

const flush = (ms = 20) => new Promise(r => setTimeout(r, ms));

async function boot() {
  const w = new Window({
    url: 'https://avesta.test/index.php?page=it',
    settings: {enableJavaScriptEvaluation: true, disableCSSFileLoading: true,
               disableJavaScriptFileLoading: true, suppressInsecureJavaScriptEnvironmentWarning: true},
  });
  w.happyDOM.setWindowSize({width: 1100, height: 900});
  w.__domain = domain;
  w.document.write(indexHtml);
  const data = w.document.createElement('script');
  data.id = 'avesta-public-data';
  data.type = 'application/json';
  data.textContent = JSON.stringify({
    services,
    help: JSON.parse(readFileSync('assets/avesta/help.json', 'utf8')),
  });
  w.document.body.append(data);
  w.Element.prototype.scrollIntoView = () => {};
  w.eval(appScript);
  await flush();
  return {w, d: w.document};
}

/* ───────────────────────── the data carries the grouping ───────────────────────── */

test('THE ONE THAT MATTERS: every service names its own group, in the data', () => {
  const groups = new Map();
  for (const s of services) {
    assert.ok(s.group && typeof s.group === 'string',
      `"${s.name}" has no group, so it would fall off the tabs entirely`);
    groups.set(s.group, (groups.get(s.group) || 0) + 1);
  }
  assert.ok(groups.size >= 3 && groups.size <= 5,
    `${groups.size} tabs: fewer than three is not worth tabbing, more than five does not fit a phone`);
  for (const [group, n] of groups) {
    assert.ok(n <= 4, `"${group}" holds ${n} services; a panel over four is the long page again`);
    assert.ok(n >= 2, `"${group}" holds ${n}; a tab for one service is just a heading`);
  }
  assert.equal([...groups.values()].reduce((a, b) => a + b, 0), services.length);
});

test('the code no longer decides the grouping by list position', () => {
  /* The old map was serviceCategory=i=>[0,1,3].includes(i)?'Repairs':…
   * Reordering services.json moved services between headings with nothing to
   * notice. If that pattern comes back, this fails. */
  const src = readFileSync('assets/avesta/app.js', 'utf8');
  assert.doesNotMatch(src, /\[\s*\d+\s*,\s*\d+[^\]]*\]\s*\.includes\(\s*i\s*\)/,
    'the grouping is being decided by index again; it belongs in services.json');
  assert.doesNotMatch(src, /serviceCategory/,
    'serviceCategory was the index map and should be gone');
});

/* ───────────────────────── it has to be a real tablist ───────────────────────── */

test('the tabs are a tablist a screen reader and a keyboard can use', async () => {
  const {w, d} = await boot();
  const list = d.querySelector('[role="tablist"]');
  assert.ok(list, 'the service categories should be a tablist, not a row of pressed buttons');

  const tabs = [...list.querySelectorAll('[role="tab"]')];
  const groups = [...new Set(services.map(s => s.group))];
  assert.equal(tabs.length, groups.length, 'one tab per group');

  const selected = tabs.filter(t => t.getAttribute('aria-selected') === 'true');
  assert.equal(selected.length, 1, 'exactly one tab is selected at a time');

  for (const tab of tabs) {
    const panelId = tab.getAttribute('aria-controls');
    assert.ok(panelId, `"${tab.textContent.trim()}" controls nothing`);
    const panel = d.getElementById(panelId);
    assert.ok(panel, `"${tab.textContent.trim()}" points at #${panelId}, which does not exist`);
    assert.equal(panel.getAttribute('role'), 'tabpanel');
    // Only the selected tab's control is in the tab order.
    const chosen = tab.getAttribute('aria-selected') === 'true';
    assert.equal(tab.getAttribute('tabindex'), chosen ? '0' : '-1',
      'arrow keys move between tabs, so only the selected one takes a tab stop');
  }
  await w.happyDOM.close();
});

test('arrow keys move along the tabs and wrap round', async () => {
  const {w, d} = await boot();
  const tabs = [...d.querySelectorAll('[role="tab"]')];
  const at = () => tabs.findIndex(t => t.getAttribute('aria-selected') === 'true');
  const press = key => tabs[at()].dispatchEvent(new w.KeyboardEvent('keydown', {key, bubbles: true}));

  assert.equal(at(), 0);
  press('ArrowRight');
  assert.equal(at(), 1, 'right arrow should move to the next tab');
  press('ArrowLeft');
  assert.equal(at(), 0);
  press('ArrowLeft');
  assert.equal(at(), tabs.length - 1, 'left from the first should wrap to the last');
  await w.happyDOM.close();
});

test('THE ONE THAT MATTERS: only one panel of services shows at a time', async () => {
  const {w, d} = await boot();
  const shown = () => [...d.querySelectorAll('[role="tabpanel"]')].filter(p => !p.hidden);
  assert.equal(shown().length, 1, 'that is the whole point: one short panel, not twelve cards');

  const cards = shown()[0].querySelectorAll('.service-card');
  assert.ok(cards.length >= 2 && cards.length <= 4,
    `the open panel shows ${cards.length} services; it should be a short list`);

  d.querySelectorAll('[role="tab"]')[2].click();
  const now = shown();
  assert.equal(now.length, 1);
  assert.equal(now[0].id, d.querySelectorAll('[role="tab"]')[2].getAttribute('aria-controls'));
  await w.happyDOM.close();
});

test('THE ONE THAT MATTERS: the stylesheet lets [hidden] actually hide', () => {
  /* The browser's own rule for [hidden] is display:none at the lowest
   * precedence, so any class setting a display outranks it. .card-grid sets
   * display:grid, so without this the script hides a panel and the panel
   * stays on screen — which is exactly what happened, with all four service
   * panels showing at once. The same bug hit the explainer transcript the
   * same day. */
  const css = readFileSync('assets/avesta/styles.css', 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
  assert.match(css, /\[hidden\]\s*\{[^}]*display:\s*none\s*!important/,
    'styles.css needs a global [hidden]{display:none!important}, or hiding anything is a coin flip');
});

test('every service is reachable through exactly one tab', async () => {
  const {w, d} = await boot();
  const tabs = [...d.querySelectorAll('[role="tab"]')];
  const seen = [];
  for (const tab of tabs) {
    tab.click();
    const panel = d.getElementById(tab.getAttribute('aria-controls'));
    for (const h of panel.querySelectorAll('.service-card h3')) seen.push(h.textContent.replace(/[−+]/g, '').trim());
  }
  for (const s of services) {
    const hits = seen.filter(t => t.includes(s.name)).length;
    assert.equal(hits, 1, `"${s.name}" appears ${hits} times across the tabs; it should appear once`);
  }
  await w.happyDOM.close();
});

/* ───────────────────────── search still reaches everything ───────────────────────── */

test('searching looks across every group, not just the open tab', async () => {
  const {w, d} = await boot();
  // "backup" is in Security & training; start on the first tab and still find it.
  const box = d.querySelector('#service-search');
  box.value = 'backup';
  box.dispatchEvent(new w.Event('input', {bubbles: true}));
  await flush();

  const text = d.querySelector('#service-results, #service-grid, [role="tabpanel"]:not([hidden])').textContent;
  assert.match(text, /Backup|backup/,
    'a search from the first tab has to find a service filed under another one');
  assert.doesNotMatch(text, /Printer & Scanner/,
    'and show only what matched');
  await w.happyDOM.close();
});

test('clearing the search puts the tabs back', async () => {
  const {w, d} = await boot();
  const box = d.querySelector('#service-search');
  box.value = 'backup';
  box.dispatchEvent(new w.Event('input', {bubbles: true}));
  await flush();
  box.value = '';
  box.dispatchEvent(new w.Event('input', {bubbles: true}));
  await flush();

  const tabs = [...d.querySelectorAll('[role="tab"]')];
  assert.equal(tabs.filter(t => t.getAttribute('aria-selected') === 'true').length, 1);
  assert.equal([...d.querySelectorAll('[role="tabpanel"]')].filter(p => !p.hidden).length, 1);
  await w.happyDOM.close();
});

test('a search that finds nothing says so rather than showing an empty panel', async () => {
  const {w, d} = await boot();
  const box = d.querySelector('#service-search');
  box.value = 'zzzzzz';
  box.dispatchEvent(new w.Event('input', {bubbles: true}));
  await flush();
  assert.match(d.querySelector('#services').textContent, /No services|nothing matched/i);
  await w.happyDOM.close();
});

/* ───────────────────────── the enquiry link must keep working ───────────────────────── */

test('"Enquire about this service" still fills the form with the right service', async () => {
  const {w, d} = await boot();
  const panel = [...d.querySelectorAll('[role="tabpanel"]')].find(p => !p.hidden);
  const card = panel.querySelector('.service-card');
  const name = card.querySelector('h3').textContent.replace(/[−+]/g, '').trim();
  card.querySelector('[data-enquire]').click();
  await flush();
  const picked = d.querySelector('#it-service').value;
  assert.ok(name.includes(picked), `the form was set to "${picked}" from the card "${name}"`);
  await w.happyDOM.close();
});
