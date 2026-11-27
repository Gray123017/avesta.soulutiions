import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

/* How long a loan is for, read back out of the record.
 *
 * The form stores the duration as text, and the text it stores carries the
 * rate in brackets:
 *
 *     sel_dur = "4 Weeks (30% interest)"
 *
 * It was read back by stripping everything that was not a digit, which turns
 * that into "430" — four hundred and thirty weeks. A four-week loan was priced
 * with a due date in 2034, so it was never overdue, never carried a delay
 * penalty, never appeared on an overdue list, and showed the borrower
 * "on track" a year after it should have been repaid. The same reading set the
 * due dates an employer sees against their staff's salary deductions.
 *
 * Every test in the suite used the bare "4 Weeks", which parses correctly, so
 * nothing caught it. These use what the form actually writes.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

function run(body, lib = 'rates-lib.php') {
  const out = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
    `require "${lib}"; ${body}`], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
  try { return JSON.parse(out); }
  catch { throw new Error('no JSON back:\n' + out.slice(0, 1500)); }
}

test('THE ONE THAT MATTERS: the duration the form writes reads back correctly',
  { skip: !hasPhp }, () => {
  const cases = [
    ['4 Weeks (30% interest)', 4],      // what every tile writes
    ['1 Week (15% interest)', 1],
    ['2 Weeks (20% interest)', 2],
    ['3 Weeks (25% interest)', 3],
    ['8 Weeks (55% interest)', 8],      // what the longer term writes
    ['4 Weeks', 4],                     // the bare form every test used
    ['1 Week', 1],
    ['  8 Weeks  ', 8],
    ['8 weeks (55% interest)', 8],      // case does not matter
  ];
  const got = run(`echo json_encode(array_map('av_weeks_from_duration',
    json_decode(${JSON.stringify(JSON.stringify(cases.map(c => c[0])))}, true)));`);

  cases.forEach(([text, weeks], i) => {
    assert.equal(got[i], weeks,
      `"${text}" is a ${weeks}-week loan, read back as ${got[i]}`);
  });
});

test('nonsense reads back as nothing, rather than as a number of weeks',
  { skip: !hasPhp }, () => {
  const junk = ['', '   ', 'Weeks', 'soon', '—', 'Lump Sum', null];
  const got = run(`echo json_encode(array_map('av_weeks_from_duration',
    json_decode(${JSON.stringify(JSON.stringify(junk))}, true)));`);
  for (const [i, v] of got.entries()) {
    assert.equal(v, 0, `${JSON.stringify(junk[i])} should read back as 0, got ${v}`);
  }
});

test('a real record goes overdue, and is charged', { skip: !hasPhp }, () => {
  /* The end to end of it: a four-week loan disbursed in January, stored the
   * way the form stores it, looked at in November. */
  const record = {
    _key: 'AV-T', status: 'Disbursed', loan_amt: 4000.00, total_repay: '5200.00',
    int_rate: 30, sel_dur: '4 Weeks (30% interest)', disburse_date: '2026-01-05',
    _payments: [], terms_version: '2026-11-27',
  };
  const s = execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
    `define('AVESTA_LIB', 1); require "api.php";
     echo json_encode(loanSummary(json_decode(${JSON.stringify(JSON.stringify(record))}, true),
                                  '2026-11-27'));`], { encoding: 'utf8' });
  const sum = JSON.parse(s);

  assert.equal(sum.weeks, 4, `read back as ${sum.weeks} weeks`);
  assert.equal(sum.due_date, '2026-02-02', 'four weeks after 5 January');
  assert.equal(sum.state, 'overdue',
    'a loan ten months past its date must not read "on track"');
  assert.ok(sum.days_overdue > 290, `${sum.days_overdue} days overdue`);
  assert.equal(sum.penalty_applied, true, 'and it must carry the delay charge');
});

test('the employer sees the same due date the engine does', { skip: !hasPhp }, () => {
  // The employer's view of their staff's loans derived weeks the same broken
  // way, so a company checking what to deduct saw a date eight years out.
  const got = run(`
    $r = ['_key' => 'AV-T', 'sel_dur' => '4 Weeks (30% interest)',
          'disburse_date' => '2026-01-05'];
    echo json_encode(['weeks' => av_weeks_from_duration($r['sel_dur'])]);`,
    'rates-lib.php');
  assert.equal(got.weeks, 4);

  // And this file has to be able to reach the parser on its own: it carried no
  // requires at all, so whether the function existed depended on who had
  // loaded it first — a difference that only shows at the moment somebody is
  // told what to deduct from a salary.
  const lib = readFileSync('employers-lib.php', 'utf8');
  assert.match(lib, /require_once __DIR__ \. '\/rates-lib\.php'/);
  assert.match(lib, /av_weeks_from_duration\(/);
});

test('nowhere reads a duration by stripping digits out of it', { skip: !hasPhp }, () => {
  // The shape of the bug, kept out for good: "4 Weeks (30% interest)" has
  // three runs of digits in it and only the first one is the term.
  const hits = spawnSync('grep', ['-rn',
    "preg_replace('/\\[^0-9\\]/', '', (string) ($r\\['sel_dur'\\]",
    '--include=*.php', '.'], { encoding: 'utf8' });
  assert.equal((hits.stdout || '').trim(), '', hits.stdout);
});
