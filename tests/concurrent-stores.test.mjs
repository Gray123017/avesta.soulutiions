import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, copyFileSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/* The same race, in the stores that are not the loan book.
 *
 * #61 fixed the loan book: the read was outside the lock, so two requests both
 * read the whole file, each changed its own part, and each wrote back a copy
 * with none of the other's change. The second write won and the first change
 * was gone, silently.
 *
 * The employer register and the remittance store are written exactly the same
 * way, and av_employer_put() makes it worse rather than better: it re-reads
 * the register and replaces one employer WHOLESALE, so two people enrolling
 * two different employees at the same company lose one of them.
 *
 * What that costs: an employee is enrolled, told their repayments will be
 * deducted at payroll, and is not on the register. Nobody deducts anything,
 * the loan quietly goes unpaid, and the first anybody knows is a default that
 * was not the borrower's doing.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

const LIBS = ['auth.php', 'rates-lib.php', 'employers-lib.php', 'remittance-lib.php'];

function workspace(extra = '') {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-stores-'));
  for (const f of LIBS) copyFileSync(f, join(dir, f));
  mkdirSync(join(dir, 'assets', 'avesta'), { recursive: true });
  copyFileSync(join('assets', 'avesta', 'rates.json'), join(dir, 'assets', 'avesta', 'rates.json'));
  writeFileSync(join(dir, 'setup.php'), `<?php
    require __DIR__ . '/employers-lib.php';
    av_employers_path(__DIR__ . '/employers.json');
    ${extra}
  `);
  return dir;
}

function run(dir, body) {
  return execFileSync(php, ['-d', 'error_reporting=E_ALL', '-r',
    `require "${join(dir, 'setup.php')}"; ${body}`], { encoding: 'utf8' });
}

function race(dir, bodies) {
  return Promise.all(bodies.map((body) => new Promise((resolve) => {
    const p = spawn(php, ['-r', `require "${join(dir, 'setup.php')}"; ${body}`],
      { stdio: 'ignore' });
    p.on('exit', resolve);
  })));
}

test('THE ONE THAT MATTERS: two people enrolling staff at once keep both',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = workspace();
  try {
    const made = JSON.parse(run(dir, `
      $e = av_employer_create(['name' => 'Avanto Group Limited',
                               'contact_name' => 'Gray', 'contact_phone' => '0970000000']);
      av_employer_set_status($e['employer']['id'], 'active');
      $b = av_employer_add_branch($e['employer']['id'], 'Ndola Head Office', 'Ndola');
      echo json_encode(['employer' => $e['employer']['id'], 'branch' => $b['branch']['id']]);`));

    // Two staff, two different employees, same company, same moment. The
    // slower one reads first and writes last.
    const enrol = (user, payroll, delay) =>
      `av_employers_lock();
       $d = av_employers_load();
       usleep(${delay} * 1000);
       echo json_encode(av_employer_enrol(${JSON.stringify(made.employer)}, [
         'user_id' => ${JSON.stringify(user)}, 'name' => ${JSON.stringify(user)},
         'payroll_no' => ${JSON.stringify(payroll)},
         'branch_id' => ${JSON.stringify(made.branch)}]));`;

    await race(dir, [enrol('chanda', 'AV-0001', 300), enrol('mwila', 'AV-0002', 50)]);

    const staff = JSON.parse(run(dir, `
      av_employers_reset_cache();
      $d = av_employers_load();
      $names = [];
      foreach ($d['employers'][0]['employees'] ?? [] as $x) $names[] = $x['payroll_no'];
      sort($names);
      echo json_encode($names);`));

    assert.deepEqual(staff, ['AV-0001', 'AV-0002'],
      'an employee enrolled and then dropped is a salary nobody deducts from, '
      + `and a default that was not theirs. Register holds: ${JSON.stringify(staff)}`);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('a company registered while another is being registered survives',
  { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = workspace();
  try {
    const create = (name, delay) =>
      `av_employers_lock();
       $d = av_employers_load();
       usleep(${delay} * 1000);
       av_employer_create(['name' => ${JSON.stringify(name)},
                           'contact_name' => 'X', 'contact_phone' => '0970000000']);`;
    await race(dir, [create('Alpha Ltd', 260), create('Beta Ltd', 120),
                     create('Gamma Ltd', 30)]);

    const names = JSON.parse(run(dir, `
      av_employers_reset_cache();
      $out = array_map(fn($e) => $e['name'], av_employers_load()['employers']);
      sort($out); echo json_encode($out);`));
    assert.deepEqual(names, ['Alpha Ltd', 'Beta Ltd', 'Gamma Ltd'], JSON.stringify(names));
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('two remittances posted at once are both kept', { skip: !hasPhp, timeout: 30000 }, async () => {
  const dir = workspace();
  try {
    writeFileSync(join(dir, 'setup.php'), `<?php
      require __DIR__ . '/employers-lib.php';
      require __DIR__ . '/remittance-lib.php';
      av_employers_path(__DIR__ . '/employers.json');
      av_remittances_path(__DIR__ . '/remittances.json');
    `);
    const post = (period, amount, delay) =>
      `av_remittances_lock();
       $d = av_remittances_load();
       usleep(${delay} * 1000);
       av_remittance_record('emp-1', ${JSON.stringify(period)}, ${amount}, null, []);`;

    await race(dir, [post('2026-09', 150000, 280), post('2026-10', 250000, 110),
                     post('2026-11', 350000, 20)]);

    const periods = JSON.parse(run(dir, `
      av_remittances_reset_cache();
      $out = [];
      foreach (av_remittances_load() as $k => $v) {
        if (!is_array($v)) continue;
        foreach ($v as $row) if (is_array($row) && isset($row['period'])) $out[] = $row['period'];
      }
      sort($out); echo json_encode(array_values(array_unique($out)));`));

    assert.deepEqual(periods, ['2026-09', '2026-10', '2026-11'],
      `money an employer sent must not be dropped. Store holds: ${JSON.stringify(periods)}`);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('every store that is written has a lock to take', { skip: !hasPhp }, () => {
  const got = JSON.parse(execFileSync(php, ['-r',
    `require "employers-lib.php"; require "remittance-lib.php";
     require "notify-lib.php"; require "watchdog-lib.php";
     echo json_encode(array_map('function_exists', [
       'av_employers_lock', 'av_remittances_lock',
       'av_notify_lock', 'av_watchdog_lock', 'av_store_lock']));`],
    { encoding: 'utf8' }));
  assert.deepEqual(got, [true, true, true, true, true]);
});

test('taking a store lock twice in one request does not hang',
  { skip: !hasPhp, timeout: 20000 }, () => {
  const dir = workspace();
  try {
    const out = run(dir, `
      av_employers_lock(); av_employers_lock(); av_employers_lock();
      $r = av_employer_create(['name' => 'Twice Ltd', 'contact_name' => 'X',
                               'contact_phone' => '0970000000']);
      echo json_encode(['ok' => $r['ok']]);`);
    assert.equal(JSON.parse(out).ok, true);
  } finally { rmSync(dir, { recursive: true, force: true }); }
});

test('the lock reads fresh, rather than trusting what was cached before it',
  { skip: !hasPhp, timeout: 20000 }, () => {
  /* A request that read a store early, then locked and wrote, would write back
   * what it cached before the lock — which is the same lost update wearing a
   * different hat. Taking the lock throws the cached copy away. */
  const dir = workspace();
  try {
    run(dir, `av_employer_create(['name' => 'First Ltd', 'contact_name' => 'X',
                                  'contact_phone' => '0970000000']);`);
    writeFileSync(join(dir, 'child.php'), `<?php
      require ${JSON.stringify(join(dir, 'setup.php'))};
      av_employer_create(['name' => 'Second Ltd', 'contact_name' => 'X',
                          'contact_phone' => '0970000000']);
    `);
    const out = run(dir, `
      $before = count(av_employers_load()['employers']);        // caches it
      exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(${JSON.stringify(join(dir, 'child.php'))}));
      av_employers_lock();
      $after = count(av_employers_load()['employers']);
      echo json_encode(['before' => $before, 'after' => $after]);`);
    const got = JSON.parse(out);
    assert.equal(got.before, 1);
    assert.equal(got.after, 2,
      'after taking the lock the store must be re-read, not served from the cache');
  } finally { rmSync(dir, { recursive: true, force: true }); }
});
