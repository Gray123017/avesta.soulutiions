import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

/* The three ways in, and what each must bring. The form shows an applicant
 * their own list; this is the check that actually holds, because a browser
 * field can be edited before it is sent. */

const cfg = JSON.parse(readFileSync('assets/avesta/categories.json', 'utf8'));

function missing(payload) {
  const code = `
    require "categories-lib.php";
    echo json_encode(av_category_missing(json_decode(${JSON.stringify(JSON.stringify(payload))}, true)));
  `;
  const out = execFileSync('php', ['-d', 'error_reporting=E_ALL', '-r', code], { encoding: 'utf8' });
  try { return JSON.parse(out); }
  catch { throw new Error('categories-lib.php did not return JSON:\n' + out); }
}

const NRC = { doc_nrc_front_base64: 'x', doc_nrc_back_base64: 'x' };

test('the three published ways in are the three we designed', () => {
  assert.deepEqual(Object.keys(cfg.categories).sort(),
    ['company_employee', 'employed', 'self_employed']);
});

test('an employer-channel application needs NRC and a payslip, nothing more', () => {
  assert.deepEqual(missing({ ...NRC, applicant_category: 'company_employee', doc_payslip_base64: 'x' }), []);

  // No bank statement is asked for: the employer confirms the salary and
  // deducts at payroll, so there is nothing for a statement to evidence.
  const c = cfg.categories.company_employee;
  assert.ok(!c.documents.includes('doc_bank_statement'));
  assert.equal(c.collateralRequired, false);
});

test('an employed applicant also needs a bank statement', () => {
  assert.deepEqual(
    missing({ ...NRC, applicant_category: 'employed', doc_payslip_base64: 'x', doc_bank_statement_base64: 'x' }),
    []
  );
  assert.deepEqual(
    missing({ ...NRC, applicant_category: 'employed', doc_payslip_base64: 'x' }),
    ['Bank statement, 3 months']
  );
});

test('a self-employed applicant needs security, not a payslip', () => {
  const base = { ...NRC, applicant_category: 'self_employed', doc_bank_statement_base64: 'x' };

  assert.deepEqual(missing(base),
    ['At least one security item, with a description or a photograph']);

  // A photograph counts.
  assert.deepEqual(missing({ ...base, doc_collateral1_base64: 'x' }), []);
  // So does a description without a photograph yet.
  assert.deepEqual(missing({ ...base, collateral1_desc: 'Toyota Hilux 2014' }), []);
  // An empty row is not security.
  assert.deepEqual(missing({ ...base, collateral1_desc: '   ' }),
    ['At least one security item, with a description or a photograph']);

  assert.ok(!cfg.categories.self_employed.documents.includes('doc_payslip'));
});

test('every missing item is named, not just the first', () => {
  const m = missing({ applicant_category: 'employed' });
  assert.deepEqual(m, ['NRC, front', 'NRC, back', 'Your latest payslip', 'Bank statement, 3 months']);
});

test('a category we do not offer is refused', () => {
  const m = missing({ ...NRC, applicant_category: 'vip' });
  assert.equal(m.length, 1);
  assert.match(m[0], /not one we offer/);
});

test('a document already stored on the record counts as supplied', () => {
  // Someone returning to a half-finished application has already uploaded.
  assert.deepEqual(missing({
    applicant_category: 'company_employee',
    doc_nrc_front: 'documents/key/doc_nrc_front.jpg',
    doc_nrc_back: 'documents/key/doc_nrc_back.jpg',
    doc_payslip: 'documents/key/doc_payslip.pdf',
  }), []);
});

test('an application with no category is still accepted, for now', () => {
  // The live form does not ask yet. Refusing these would reject every
  // application made before the chooser ships, including half-finished ones.
  assert.equal(cfg.requireCategory, false, 'still in the rollout window');
  assert.deepEqual(missing({}), []);
  assert.deepEqual(missing({ loan_amt: '4000' }), []);
});

test('turning requireCategory on closes that door without touching code', () => {
  // Proves the rollout switch works before anyone relies on it.
  const strict = JSON.parse(JSON.stringify(cfg));
  strict.requireCategory = true;
  const code = `
    require "categories-lib.php";
    $cfg = json_decode(${JSON.stringify(JSON.stringify(strict))}, true);
    echo json_encode(av_category_missing([], $cfg));
  `;
  const out = JSON.parse(execFileSync('php', ['-r', code], { encoding: 'utf8' }));
  assert.equal(out.length, 1);
  assert.match(out[0], /Choose how you are applying/);
});

test('every category names only documents the server knows how to store', () => {
  // A required document the upload pipeline has no field for could never be
  // supplied, so the application would be unsubmittable.
  const api = readFileSync('api.php', 'utf8');
  const fields = api.match(/\$DOC_FIELDS\s*=\s*\[([\s\S]*?)\];/)[1]
    .match(/'([a-z0-9_]+)'/g).map((s) => s.replace(/'/g, ''));

  for (const [name, c] of Object.entries(cfg.categories)) {
    for (const doc of c.documents) {
      assert.ok(fields.includes(doc), `${name} requires ${doc}, which $DOC_FIELDS has no slot for`);
    }
  }
});
