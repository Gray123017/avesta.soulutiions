// The three ways in agree everywhere they appear.
//
// categories.json is the single published source: the application form reads it
// to show an applicant their own list, and categories-lib.php reads it to
// enforce that list when the application arrives. This fails the build if any
// of those copies drifts — a requirement with no box to upload into, a channel
// nobody can choose, or a sentence that says one thing before submitting and
// another after.
//
// The same shape as check-rates.mjs, for the same reason: the price of credit
// and the cost of entry are the two things a borrower is entitled to know
// before they start.
import { readFileSync } from 'node:fs';

const cfg   = JSON.parse(readFileSync('assets/avesta/categories.json', 'utf8'));
const form  = readFileSync('app/index.php', 'utf8');
const api   = readFileSync('api.php', 'utf8');
const lib   = readFileSync('categories-lib.php', 'utf8');

/**
 * The page with its commentary removed, for checks about what an applicant
 * actually sees. Explaining in a comment why a label is not hard-coded should
 * not read as hard-coding it.
 *
 * Block and HTML comments go entirely; a // comment only counts at the start of
 * a line, so a URL in the middle of a line cannot swallow the code after it and
 * turn a real disagreement into a pass.
 */
const formCode = form
  .replace(/\/\*[\s\S]*?\*\//g, ' ')
  .replace(/<!--[\s\S]*?-->/g, ' ')
  .replace(/^[ \t]*\/\/.*$/gm, ' ');

let failed = 0;
const pass = (m) => console.log('  PASS  ' + m);
const note = (m) => console.log('  NOTE  ' + m);
function check(label, ok, detail) {
  if (ok) { pass(label); return; }
  console.log('  FAIL  ' + label);
  if (detail) console.log('        ' + String(detail).split('\n').join('\n        '));
  failed++;
}
const head = (m) => console.log('\n-- ' + m + ' --');

const keys = Object.keys(cfg.categories || {});
const docs = [...new Set(keys.flatMap(k => cfg.categories[k].documents || []))];

head('every published channel asks for something, and says what it is for');
for (const key of keys) {
  const c = cfg.categories[key];
  check(`${key} names its documents, its label and whether security is required`,
    Array.isArray(c.documents) && c.documents.length > 0
      && typeof c.label === 'string' && c.label !== ''
      && typeof c.short === 'string' && c.short !== ''
      && typeof c.collateralRequired === 'boolean',
    JSON.stringify(c));
}

head('every document a channel asks for has somewhere to upload it');
for (const field of docs) {
  // The form finds a document's box through its file input, so the input id is
  // the contract. A requirement with no input is a channel nobody can complete.
  check(`${field} has a file input in the application form`,
    new RegExp('id="' + field + '"').test(form),
    'add an upload box with <input type="file" id="' + field + '"> to app/index.php');
}

head('the server knows how to store every one of them');
const fields = (api.match(/\$DOC_FIELDS\s*=\s*\[([\s\S]*?)\];/) || [, ''])[1];
for (const field of docs) {
  check(`${field} is in $DOC_FIELDS`, fields.includes("'" + field + "'"),
    'api.php would accept the application and silently drop this document');
}

head('every document has a published label, and the labels are used, not retyped');
for (const field of docs) {
  check(`${field} has a label in documentLabels`,
    typeof (cfg.documentLabels || {})[field] === 'string'
      && cfg.documentLabels[field] !== '',
    'the checklist would show the raw field name to an applicant');
}
for (const field of docs) {
  const label = cfg.documentLabels[field];
  // A published label hard-coded into the page is a copy that will rot.
  check(`"${label}" is not written into the page`,
    !formCode.includes(label),
    'read it from window.AV_CATEGORIES.documentLabels instead of typing it in');
}

head('the form and the server refuse missing security in the same words');
check('collateralLabel is published',
  typeof cfg.collateralLabel === 'string' && cfg.collateralLabel !== '');
check('categories-lib.php reads it rather than carrying its own copy',
  /\$cfg\['collateralLabel'\]/.test(lib),
  'the refusal an applicant sees would stop matching the checklist');
check('the form reads it too',
  /cfg\.collateralLabel|AV_CATEGORIES[\s\S]{0,40}collateralLabel/.test(form),
  'the checklist would stop matching the refusal');

head('the chooser reads the file rather than listing the channels itself');
const chooser = (form.match(/\/\/ ── APPLICANT CATEGORY ─+\n([\s\S]*?)\/\/ ── END APPLICANT CATEGORY/) || [, ''])[1];
check('the chooser block is present and marked', chooser.length > 500,
  'tests/chooser.test.mjs and this check both read it by those markers');
for (const key of keys) {
  // Mentioning a key for an icon is fine. Listing its documents is not: that
  // is the copy that drifts.
  const asDocList = new RegExp("categories\\s*\\.\\s*" + key + "\\s*\\.\\s*documents|'" + key + "'\\s*:\\s*\\[");
  check(`${key}'s documents are not restated in the form`, !asDocList.test(chooser),
    'the form would ask for one set and the server enforce another');
}

head('the rollout switch is still where it can be flipped without code');
check('requireCategory is published', typeof cfg.requireCategory === 'boolean');
if (cfg.requireCategory === false) {
  note('requireCategory is false: an application naming NO channel is still');
  note('accepted. That is deliberate while pages cached before the chooser');
  note('shipped are still in use. Set it to true once the chooser has been');
  note('live long enough that nobody is submitting from one.');
}

console.log('\n======================================================');
if (failed) {
  console.log(`CATEGORIES: ${failed} disagreement(s) — see FAIL above`);
  console.log('======================================================');
  process.exit(1);
}
console.log('CATEGORIES: the form, the server and the published file agree');
console.log('======================================================');
