import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';

/* loanSummary() in api.php is what the staff panel and the borrower's own
 * account both read. Past the due date the published penalty replaces the
 * rate written at signing, so this is where "what is owed" is decided.
 *
 * api.php guards its router behind AVESTA_LIB, so it can be required and the
 * function called directly — no server, no session, no records file. */
function summary(record) {
  const code = `
    define('AVESTA_LIB', true);
    require "api.php";
    echo json_encode(loanSummary(${phpArray(record)}));
  `;
  const out = execFileSync('php', ['-d', 'error_reporting=E_ALL', '-r', code], { encoding: 'utf8' });
  try { return JSON.parse(out); }
  catch { throw new Error('loanSummary did not return JSON:\n' + out); }
}

/** JSON is valid PHP array syntax for our purposes via json_decode. */
function phpArray(obj) {
  return `json_decode(${JSON.stringify(JSON.stringify(obj))}, true)`;
}

const daysAgo = (n) => {
  const d = new Date();
  d.setUTCDate(d.getUTCDate() - n);
  return d.toISOString().slice(0, 10);
};

// K4,000 over four weeks at 30% — K5,200 at signing.
const fourWeek = (extra = {}) => ({
  loan_amt: '4000', total_repay: '5200', int_rate: '30', sel_dur: '4 Weeks', ...extra,
});
const SIGNED_UNDER_ENGINE = { terms_version: '2026-11-03' };

test('a loan inside its term is untouched', () => {
  const s = summary(fourWeek({ disburse_date: daysAgo(0), ...SIGNED_UNDER_ENGINE }));
  assert.equal(s.state, 'on_track');
  assert.equal(s.penalty_applied, false);
  assert.equal(s.total_due, 5200);
  assert.equal(s.rate, 30);
});

test('past the due date the penalty replaces the signed rate', () => {
  // Disbursed 40 days ago on a 4-week term: 12 days overdue.
  const s = summary(fourWeek({ disburse_date: daysAgo(40), ...SIGNED_UNDER_ENGINE }));
  assert.equal(s.state, 'overdue');
  assert.equal(s.days_overdue, 12);
  assert.equal(s.penalty_applied, true);
  assert.equal(s.rate, 35, 'the penalty rate, not the signed one');
  assert.equal(s.signed_rate, 30, 'what was signed is still reported');
  assert.equal(s.total_due, 5400);
  assert.equal(s.signed_total, 5200);
  assert.equal(s.penalty_amount, 200);
});

test('the penalty is flat, so a long default owes the same as a short one', () => {
  const short = summary(fourWeek({ disburse_date: daysAgo(40), ...SIGNED_UNDER_ENGINE }));
  const long  = summary(fourWeek({ disburse_date: daysAgo(400), ...SIGNED_UNDER_ENGINE }));
  assert.equal(long.penalty_applied, true);
  assert.equal(long.total_due, short.total_due, 'flat penalty: 5,400 either way');
  assert.ok(long.days_overdue > 350);
});

test('a loan signed before this engine keeps the terms it was signed under', () => {
  // No terms_version. The old rule was 7.5% a week, additive, held in the
  // record's own late_payment_rate. Charging today's penalty would be
  // charging terms nobody agreed to.
  const s = summary(fourWeek({ disburse_date: daysAgo(40) }));
  assert.equal(s.state, 'overdue');
  assert.equal(s.terms_engine, 'legacy');
  assert.equal(s.penalty_applied, false, 'never silently repriced');
  assert.equal(s.legacy_overdue, true, 'flagged so a human can deal with it');
  assert.equal(s.total_due, 5200, 'still the figure it was signed at');
});

test('a loan already paid off does not acquire a penalty', () => {
  const s = summary(fourWeek({
    disburse_date: daysAgo(40), ...SIGNED_UNDER_ENGINE,
    _payments: [{ amount: 5200 }],
  }));
  assert.equal(s.state, 'settled');
  assert.equal(s.penalty_applied, false);
  assert.equal(s.balance, 0);
});

test('a part-paid overdue loan owes the penalised total less what was paid', () => {
  const s = summary(fourWeek({
    disburse_date: daysAgo(40), ...SIGNED_UNDER_ENGINE,
    _payments: [{ amount: 2000 }],
  }));
  assert.equal(s.penalty_applied, true);
  assert.equal(s.total_due, 5400);
  assert.equal(s.paid, 2000);
  assert.equal(s.balance, 3400);
});

test('without a disbursement date a loan cannot be aged at all', () => {
  const s = summary(fourWeek(SIGNED_UNDER_ENGINE));
  assert.equal(s.state, 'no_due_date');
  assert.equal(s.due_date, null);
  assert.equal(s.penalty_applied, false);
});

test('an eight-week loan carries no penalty, because 35% is below its own rate', () => {
  // The known consequence of a flat penalty against a 55% term rate: the
  // floor in av_amount_owing() holds it at the term rate, so nothing is
  // added. Recorded in rates.json under overdue._eightWeekGap.
  const s = summary({
    loan_amt: '4000', total_repay: '6200', int_rate: '55', sel_dur: '8 Weeks',
    disburse_date: daysAgo(70), ...SIGNED_UNDER_ENGINE,
  });
  assert.equal(s.state, 'overdue');
  assert.equal(s.penalty_applied, false, 'the floor holds it at the term rate');
  assert.equal(s.total_due, 6200);
  assert.equal(s.rate, 55);
});
