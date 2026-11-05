import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Window } from 'happy-dom';

/* The chooser: the half of the three channels the applicant actually sees.
 *
 * Everything it shows comes from assets/avesta/categories.json, which is the
 * same file categories-lib.php enforces when the application arrives. These
 * tests read the real markup and the real functions out of app/index.php, so
 * they fail if the page stops agreeing with the published file.
 */

const source = readFileSync('app/index.php', 'utf8');
const cfg = JSON.parse(readFileSync('assets/avesta/categories.json', 'utf8'));

function section(start, end) {
  const a = source.indexOf(start), b = source.indexOf(end, a + start.length);
  assert.ok(a >= 0 && b > a, 'could not find section: ' + start);
  return source.slice(a, b);
}

function boot() {
  const w = new Window({ settings: {
    enableJavaScriptEvaluation: true, suppressInsecureJavaScriptEnvironmentWarning: true } });

  w.document.body.innerHTML =
    section('<div class="wiz-panel active" id="wiz-1">', '<!-- ═══ STEP 2') +
    section('<!-- ═══ STEP 4', '<!-- ═══ STEP 5');

  w.AV_CATEGORIES = cfg;
  w.eval('function saveProgress(){} function updateFieldCounter(){}'
    + section('function toggleSecuritySection(', 'let collateralCount = 1;'));
  w.avCategoryRender();
  return w;
}

/** Attach a file to a document input, the way picking one in the browser does. */
function attach(w, field) {
  const el = w.document.getElementById(field);
  assert.ok(el, 'no input for ' + field);
  el.files = [new w.File(['x'], field + '.pdf', { type: 'application/pdf' })];
}

const boxOf = (w, field) => w.document.getElementById(field).closest('.upload-item');
// Arrays come back from happy-dom's realm, so copy before a strict deepEqual.
const missing = (w) => Array.from(w.avCategoryMissing());
const needs = (w) => Array.from(
  w.document.querySelectorAll('#category-needs-list li')).map(li => li.textContent);

test('the three cards are built from the published file, not written into the page',
  async () => {
  const w = boot();
  const cards = Array.from(w.document.querySelectorAll('#category-grid .method-card'));
  assert.equal(cards.length, Object.keys(cfg.categories).length);

  const values = cards.map(c => c.querySelector('input[name="applicant_category"]').value);
  assert.deepEqual(values, Object.keys(cfg.categories));

  // The wording is the file's wording. Nothing about the channels is typed twice.
  for (const card of cards) {
    const key = card.querySelector('input').value;
    // The applicant's own words lead; the filing tag sits under them.
    assert.equal(card.querySelector('.mc-name').textContent, cfg.categories[key].label);
    assert.equal(card.querySelector('.mc-desc').textContent, cfg.categories[key].short);
  }
  await w.happyDOM.close();
});

test('the employer channel asks for a payslip and no bank statement', async () => {
  const w = boot();
  w.avCategoryChoose('company_employee');

  assert.deepEqual(needs(w), ['NRC, front', 'NRC, back', 'Your latest payslip']);
  assert.equal(w.avCategorySelected, 'company_employee');

  // The payslip comes up out of the optional list; the bank statement stays there.
  const required = w.document.getElementById('category-required-grid');
  assert.equal(boxOf(w, 'doc_payslip').parentNode, required);
  assert.notEqual(boxOf(w, 'doc_bank_statement').parentNode, required);
  assert.ok(boxOf(w, 'doc_payslip').classList.contains('required-doc'));

  // The box is relabelled to match the checklist above it. Two names for the
  // same document in one form reads as two different documents.
  const label = boxOf(w, 'doc_payslip').querySelector('.upload-label').textContent;
  assert.match(label, /REQUIRED/);
  assert.ok(label.startsWith(cfg.documentLabels.doc_payslip),
    `promoted box says "${label}", checklist says "${cfg.documentLabels.doc_payslip}"`);

  assert.equal(w.document.getElementById('category-required-docs').style.display, 'block');
  await w.happyDOM.close();
});

test('the self-employed channel asks for security instead of a payslip', async () => {
  const w = boot();
  w.avCategoryChoose('self_employed');

  assert.deepEqual(needs(w),
    ['NRC, front', 'NRC, back', 'Bank statement, 3 months', cfg.collateralLabel]);

  const required = w.document.getElementById('category-required-grid');
  assert.equal(boxOf(w, 'doc_bank_statement').parentNode, required);
  assert.notEqual(boxOf(w, 'doc_payslip').parentNode, required);
  await w.happyDOM.close();
});

test('changing the answer leaves nothing behind from the first one', async () => {
  const w = boot();
  const required = w.document.getElementById('category-required-grid');

  w.avCategoryChoose('employed');
  assert.equal(required.children.length, 2);          // payslip + bank statement

  w.avCategoryChoose('company_employee');
  assert.equal(required.children.length, 1);
  assert.equal(required.children[0], boxOf(w, 'doc_payslip'));

  // The bank statement went back where it came from, stopped being required,
  // and got its own wording back rather than keeping a REQUIRED badge in the
  // optional list.
  const demoted = boxOf(w, 'doc_bank_statement');
  assert.notEqual(demoted.parentNode, required);
  assert.ok(!demoted.classList.contains('error'));
  assert.ok(!demoted.classList.contains('required-doc'));
  assert.doesNotMatch(demoted.querySelector('.upload-label').textContent, /REQUIRED/);

  w.avCategoryChoose('self_employed');
  assert.equal(required.children.length, 1);
  assert.equal(required.children[0], boxOf(w, 'doc_bank_statement'));
  await w.happyDOM.close();
});

test('the checklist says exactly what the server will ask for', async () => {
  const w = boot();
  // Both sides read the same file, so these have to agree for all three.
  for (const key of Object.keys(cfg.categories)) {
    w.avCategoryChoose(key);
    const expected = cfg.categories[key].documents.map(f => cfg.documentLabels[f]);
    if (cfg.categories[key].collateralRequired) expected.push(cfg.collateralLabel);
    assert.deepEqual(needs(w), expected, key);
  }
  await w.happyDOM.close();
});

test('nothing uploaded names everything missing at once, then clears', async () => {
  const w = boot();
  w.avCategoryChoose('employed');

  // The NRC is left to the block that has its own message for it.
  assert.deepEqual(missing(w),
    ['Your latest payslip', 'Bank statement, 3 months']);

  attach(w, 'doc_payslip');
  assert.deepEqual(missing(w), ['Bank statement, 3 months']);

  attach(w, 'doc_bank_statement');
  assert.deepEqual(missing(w), []);
  await w.happyDOM.close();
});

test('security counts as supplied by a description or by a photograph', async () => {
  const w = boot();
  w.avCategoryChoose('self_employed');
  attach(w, 'doc_bank_statement');

  assert.deepEqual(missing(w), [cfg.collateralLabel]);

  // A photograph.
  attach(w, 'doc_collateral1');
  assert.deepEqual(missing(w), []);

  // Or a described item with no photograph yet — the same rule the server
  // applies, in the same published wording.
  const fresh = boot();
  fresh.avCategoryChoose('self_employed');
  attach(fresh, 'doc_bank_statement');
  const row = fresh.document.querySelector('#collateral-list .collateral-item input');
  assert.ok(row, 'the collateral list should have a first row to describe an item in');
  row.value = 'Toyota Hilux 2014';
  assert.deepEqual(missing(fresh), []);

  // An empty row is not security.
  row.value = '   ';
  assert.deepEqual(missing(fresh), [cfg.collateralLabel]);

  await w.happyDOM.close();
  await fresh.happyDOM.close();
});

test('choosing a channel suggests its security arrangement without locking it',
  async () => {
  const w = boot();
  w.avCategoryChoose('self_employed');
  const checked = () => {
    const el = w.document.querySelector('input[name="security_type"]:checked');
    return el ? el.value : '';
  };
  assert.equal(checked(), cfg.categories.self_employed.suggestedSecurityType);

  // Already answered, so a later change of channel does not overwrite it.
  w.avCategoryChoose('company_employee');
  assert.equal(checked(), 'collateral');
  await w.happyDOM.close();
});

test('with no published categories the form behaves exactly as it did before',
  async () => {
  const w = new Window({ settings: {
    enableJavaScriptEvaluation: true, suppressInsecureJavaScriptEnvironmentWarning: true } });
  w.document.body.innerHTML =
    section('<div class="wiz-panel active" id="wiz-1">', '<!-- ═══ STEP 2') +
    section('<!-- ═══ STEP 4', '<!-- ═══ STEP 5');
  w.AV_CATEGORIES = null;                 // the file was unreadable on the server
  w.eval('function saveProgress(){} function updateFieldCounter(){}'
    + section('function toggleSecuritySection(', 'let collateralCount = 1;'));

  w.avCategoryRender();
  assert.equal(w.document.getElementById('applicant-category-section').style.display, 'none');
  assert.equal(w.document.getElementById('category-grid').children.length, 0);
  assert.deepEqual(missing(w), []);        // nothing extra is demanded

  // And the documents are all still where they always were.
  assert.ok(w.document.getElementById('doc_payslip'));
  assert.ok(w.document.getElementById('doc_bank_statement'));
  await w.happyDOM.close();
});

test('the application sends the chosen channel with it', async () => {
  // The payload is built in submitApplication; this checks the field is there
  // and reads the chooser rather than being left behind on a rename.
  const payload = section('const data = {', 'security_type:');
  assert.match(payload, /applicant_category:\s*window\.avCategorySelected/);
});
