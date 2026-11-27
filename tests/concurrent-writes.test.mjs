import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, copyFileSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* Two people recording payments at the same time.
 *
 * Every write to the loan book was shaped like this:
 *
 *     $records = loadRecords();       // read the whole book
 *     ...change one loan...
 *     saveRecords($records);          // write the whole book back
 *
 * saveRecords() takes an exclusive lock and writes atomically, so the file is
 * never torn. But the READ is outside the lock, so two requests both read the
 * same book, each changes its own loan, and each writes back a whole book that
 * does not contain the other's change. The second write wins and the first
 * payment is gone.
 *
 * It is silent. The audit log records payment.recorded for both, the staff
 * member who took the money is told "Payment of K500 recorded", and the
 * borrower still owes it. The only trace is the audit log disagreeing with the
 * loan book.
 *
 * Reproduced below before it was fixed: a K500 payment on one loan vanished
 * while a K1,000 payment on another was recorded a moment later.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

const LIBS = ['api.php', 'auth.php', 'rates-lib.php', 'categories-lib.php',
              'backup-lib.php', 'employers-lib.php', 'remittance-lib.php',
              'disbursement-lib.php', 'notify-lib.php'];

/** A loan book with two loans in it, in a directory of its own. */
function book() {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-race-'));
  for (const f of LIBS) copyFileSync(f, join(dir, f));
  mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
  for (const f of ['rates.json', 'categories.json']) {
    copyFileSync(join('assets', 'avesta', f), join(dir, 'assets', 'avesta', f));
  }
  mkdirSync(join(dir, 'app'));

  const loan = (key, amt, total) => ({
    _key: key, status: 'Disbursed', loan_amt: amt, total_repay: total,
    int_rate: 30, sel_dur: '4 Weeks (30% interest)',
    disburse_date: '2026-11-01', _payments: [],
  });
  writeFileSync(join(dir, 'records_data.php'),
    '<?php http_response_code(404); exit; ?>\n' +
    JSON.stringify([loan('AV-1', 4000, '5200.00'), loan('AV-2', 3000, '3900.00')]));

  // A worker doing exactly what addPayment does, with a pause where a real
  // request spends its time between reading and writing.
  writeFileSync(join(dir, 'worker.php'), `<?php
    define('AVESTA_LIB', 1);
    require __DIR__ . '/api.php';
    [, $key, $amount, $delayMs] = $argv;

    av_lock_records();                 // the fix: the lock covers the read too
    $records = loadRecords();
    usleep(((int) $delayMs) * 1000);
    foreach ($records as &$r) {
        if (($r['_key'] ?? '') !== $key) continue;
        if (!isset($r['_payments']) || !is_array($r['_payments'])) $r['_payments'] = [];
        $r['_payments'][] = ['id' => 'p_' . bin2hex(random_bytes(6)),
                             'amount' => (float) $amount, 'date' => '2026-11-27',
                             'method' => 'Cash', 'taken_by' => 'staff'];
        break;
    }
    unset($r);
    saveRecords($records);
  `);
  return dir;
}

const payments = (dir) => JSON.parse(execFileSync(php, ['-r',
  `require "${join(dir, 'auth.php')}";
   $d = av_guarded_decode(file_get_contents("${join(dir, 'records_data.php')}"));
   $out = [];
   foreach ((array) $d as $r) {
     $out[$r['_key']] = array_sum(array_column($r['_payments'] ?? [], 'amount'));
   }
   echo json_encode($out);`], { encoding: 'utf8' }));

function race(dir, jobs) {
  return Promise.all(jobs.map(([key, amount, delay]) => new Promise((resolve) => {
    const p = spawn(php, [join(dir, 'worker.php'), key, String(amount), String(delay)],
      { stdio: 'ignore' });
    p.on('exit', resolve);
  })));
}

test('THE ONE THAT MATTERS: a payment taken at the same moment as another is not lost',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = book();
  try {
    // The slower request reads first and writes last — the order that loses
    // the other's change when the read is outside the lock.
    await race(dir, [['AV-1', 1000, 300], ['AV-2', 500, 50]]);

    const got = payments(dir);
    assert.equal(got['AV-1'], 1000, 'the K1,000 payment');
    assert.equal(got['AV-2'], 500,
      `the K500 payment was taken from a borrower and is not on their loan. `
      + `Book: ${JSON.stringify(got)}`);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('and neither is lost with several at once', { skip: !hasPhp, timeout: 60000 }, async () => {
  const dir = book();
  try {
    await race(dir, [
      ['AV-1', 100, 220], ['AV-2', 200, 40], ['AV-1', 300, 120],
      ['AV-2', 400, 180], ['AV-1', 500, 10], ['AV-2', 600, 90],
    ]);
    const got = payments(dir);
    assert.equal(got['AV-1'], 900, `100 + 300 + 500. Book: ${JSON.stringify(got)}`);
    assert.equal(got['AV-2'], 1200, `200 + 400 + 600. Book: ${JSON.stringify(got)}`);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('taking the lock twice in one request does not hang', { skip: !hasPhp, timeout: 20000 }, () => {
  // saveRecords() takes the lock as well, so a path that locks and then saves
  // asks for it twice. On a second file handle that waits for ever.
  const dir = book();
  try {
    const out = execFileSync(php, ['-r',
      `define('AVESTA_LIB', 1); require "${join(dir, 'api.php')}";
       av_lock_records(); av_lock_records();
       $r = loadRecords();
       $r[0]['_payments'][] = ['id' => 'p_x', 'amount' => 1.0, 'date' => '2026-11-27'];
       echo json_encode(['saved' => saveRecords($r)]);`],
      { encoding: 'utf8', timeout: 10000 });
    assert.equal(JSON.parse(out).saved, true);
    assert.equal(payments(dir)['AV-1'], 1);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('every path that writes the loan book takes the lock first', () => {
  /* The shape of the bug. A write path that reads the book without the lock
   * will lose somebody else's change, and the loss is silent, so this is
   * checked by reading the source rather than waiting to notice. */
  const src = execFileSync('cat', ['api.php'], { encoding: 'utf8' });
  const lines = src.split('\n');

  const writers = [];
  lines.forEach((line, i) => {
    if (/\bsaveRecords\s*\(/.test(line) && !/^function saveRecords/.test(line.trim())) {
      writers.push(i);
    }
  });
  assert.ok(writers.length >= 8, `expected the write paths, found ${writers.length}`);

  for (const at of writers) {
    // Look back for the loadRecords() this save belongs to, and check a lock
    // was taken between the top of the action and that read.
    let read = -1;
    for (let i = at; i >= 0 && i > at - 220; i--) {
      if (/\$records\s*=\s*loadRecords\(\)/.test(lines[i])
          || /loadRecords\(\)/.test(lines[i])) { read = i; break; }
    }
    if (read === -1) continue;                    // saves something it built itself
    let locked = false;
    for (let i = read; i >= 0 && i > read - 60; i--) {
      if (/av_lock_records\s*\(/.test(lines[i])) { locked = true; break; }
      if (/^if \(\$action ===/.test(lines[i])) break;   // start of the action
    }
    assert.ok(locked,
      `api.php:${read + 1} reads the loan book and api.php:${at + 1} writes it, `
      + `with no av_lock_records() between the start of the action and the read. `
      + `Another request can write between the two and have its change thrown away.`);
  }
});
