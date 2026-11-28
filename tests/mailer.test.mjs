import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {spawn, spawnSync} from 'node:child_process';
import {createServer} from 'node:net';

/* How the site sends email, and why it stopped being mail().
 *
 * On 28 November 2026 the password-reset codes in recovery-lib.php and the
 * new-application alerts in notify-lib.php both went out through PHP mail().
 * Checked against DNS that day, avesta.solutions publishes:
 *
 *   MX     5 mx1.hostinger.com, 10 mx2.hostinger.com
 *   SPF    v=spf1 include:_spf.mail.hostinger.com include:_spf.reach.hostinger.com ~all
 *   DMARC  v=DMARC1; p=none
 *   DKIM   none, at any selector tried
 *
 * So SPF exists — but mail() sets "From:" as a *header* while SPF is checked
 * against the *envelope* sender, which on shared hosting is the hosting
 * account, not the domain. The two never aligned, there was no DKIM to fall
 * back on, and notify-lib.php was additionally sending as
 * no-reply@avesta.solutions, which is not a real mailbox. Codes that Gmail
 * junks are codes that never arrive, and a borrower who cannot receive one
 * cannot sign in at all.
 *
 * The fix is to hand the message to Hostinger's own mail server, signed in as
 * the real mailbox, so the envelope sender and the From header are the same
 * domain and SPF covers it. These tests hold that property — the envelope
 * matching the header is the whole point and is checked against what a server
 * actually received, not against what the code meant to send.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;

const PASS = 'sMtP-s3cret-never-logged';
const USER = 'info@avesta.solutions';

/* A standing-in SMTP server. It speaks just enough to complete a send and
 * records every line it was given, so a test can assert on the conversation
 * rather than on our own idea of it. */
function fakeSmtp({refuseAuth = false, refuseAt = ''} = {}) {
  const seen = {lines: [], mailFrom: null, rcptTo: [], data: [], authed: false};
  const server = createServer(sock => {
    let buf = '';
    let inData = false;
    sock.write('220 smtp.test ESMTP ready\r\n');
    sock.on('data', chunk => {
      buf += chunk.toString('binary');
      let i;
      while ((i = buf.indexOf('\r\n')) !== -1) {
        const line = buf.slice(0, i);
        buf = buf.slice(i + 2);

        if (inData) {
          if (line === '.') { inData = false; sock.write('250 2.0.0 queued\r\n'); continue; }
          seen.data.push(line);
          continue;
        }

        seen.lines.push(line);
        const verb = line.split(/[ :]/)[0].toUpperCase();
        if (refuseAt && verb === refuseAt) { sock.write('550 5.0.0 refused by test\r\n'); continue; }

        if (verb === 'EHLO' || verb === 'HELO') {
          sock.write('250-smtp.test\r\n250-AUTH LOGIN PLAIN\r\n250 8BITMIME\r\n');
        } else if (verb === 'AUTH') {
          if (refuseAuth) { sock.write('535 5.7.8 bad credentials\r\n'); continue; }
          // AUTH LOGIN: username then password, each base64, each prompted.
          if (/LOGIN/i.test(line)) { sock.write('334 VXNlcm5hbWU6\r\n'); seen.authStep = 'user'; }
          else { seen.authed = true; sock.write('235 2.7.0 accepted\r\n'); }
        } else if (seen.authStep === 'user') {
          seen.authUser = Buffer.from(line, 'base64').toString();
          seen.authStep = 'pass'; sock.write('334 UGFzc3dvcmQ6\r\n');
        } else if (seen.authStep === 'pass') {
          seen.authPass = Buffer.from(line, 'base64').toString();
          seen.authStep = null; seen.authed = true; sock.write('235 2.7.0 accepted\r\n');
        } else if (verb === 'MAIL') {
          seen.mailFrom = (line.match(/<([^>]*)>/) || [])[1] ?? '';
          sock.write('250 2.1.0 ok\r\n');
        } else if (verb === 'RCPT') {
          seen.rcptTo.push((line.match(/<([^>]*)>/) || [])[1] ?? '');
          sock.write('250 2.1.5 ok\r\n');
        } else if (verb === 'DATA') {
          inData = true; sock.write('354 go ahead\r\n');
        } else if (verb === 'QUIT') {
          sock.write('221 2.0.0 bye\r\n'); sock.end();
        } else {
          sock.write('250 2.0.0 ok\r\n');
        }
      }
    });
    sock.on('error', () => {});
  });
  return {server, seen};
}

async function withSmtp(opts, fn) {
  const {server, seen} = fakeSmtp(opts);
  await new Promise(r => server.listen(0, '127.0.0.1', r));
  const port = server.address().port;
  try { return await fn(port, seen); }
  finally { await new Promise(r => server.close(r)); }
}

/** Runs a snippet with mailer-lib.php loaded and the transport pointed at the fake.
 *
 * Deliberately async. spawnSync blocks this process's event loop, so the fake
 * server above would accept the connection at the OS level and then never get
 * to answer it — PHP reported "connected" and sat there until its read timed
 * out. Every send test failed, and the two that expected a failure passed for
 * the wrong reason. */
function run(snippet, port, extraEnv = {}) {
  const env = {
    ...process.env,
    AVESTA_SMTP_HOST: '127.0.0.1',
    AVESTA_SMTP_PORT: String(port),
    AVESTA_SMTP_SCHEME: 'tcp',          // loopback only; see the TLS test below
    AVESTA_SMTP_USER: USER,
    AVESTA_SMTP_PASS: PASS,
    AVESTA_SMTP_FROM: USER,
    ...extraEnv,
  };
  return new Promise((resolve) => {
    const p = spawn(php, ['-r', "require 'mailer-lib.php'; " + snippet], {env});
    let out = '';
    p.stdout.on('data', d => { out += d; });
    p.stderr.on('data', d => { out += d; });
    p.on('close', () => resolve(out));
  });
}

/* ───────────────────── the two that decide whether this works ───────────────────── */

test('THE ONE THAT MATTERS: the envelope sender matches the From header, so SPF aligns',
  {skip: !hasPhp, timeout: 20000}, async () => {
  await withSmtp({}, async (port, seen) => {
    const out = await run("var_export(av_mail_send('borrower@gmail.com', 'Your code', 'Code: 12345678'));", port);
    assert.match(out, /'ok'\s*=>\s*true/, 'the send did not report success: ' + out);

    assert.equal(seen.mailFrom, USER,
      'the SMTP envelope sender is "' + seen.mailFrom + '". SPF is checked against this, '
      + 'not the From: header, which is exactly why mail() never aligned.');

    const from = seen.data.find(l => /^From:/i.test(l)) || '';
    const headerDomain = (from.match(/@([A-Za-z0-9.-]+)/) || [])[1];
    const envelopeDomain = seen.mailFrom.split('@')[1];
    assert.equal(headerDomain, envelopeDomain,
      'From: header domain (' + headerDomain + ') must match the envelope domain ('
      + envelopeDomain + ') or DMARC alignment fails');

    assert.doesNotMatch(from, /no-reply@/i,
      'no-reply@avesta.solutions is not a real mailbox; sending as it hurt deliverability '
      + 'and sent bounces nowhere');
    assert.deepEqual(seen.rcptTo, ['borrower@gmail.com']);
  });
});

test('THE ONE THAT MATTERS: the password is never returned, printed or audited',
  {skip: !hasPhp, timeout: 20000}, async () => {
  await withSmtp({}, async (port, seen) => {
    const out = await run("$r = av_mail_send('borrower@gmail.com', 'Your code', 'Code: 12345678');"
                  + "var_export($r); echo PHP_EOL, av_mail_describe();", port);
    assert.doesNotMatch(out, new RegExp(PASS.replace(/[-]/g, '\\-')),
      'the password came back out of the mailer: ' + out);
    assert.equal(seen.authPass, PASS, 'the server should still have received it over the wire');
  });

  // And a failure must not report it either — error strings are shown to admins.
  await withSmtp({refuseAuth: true}, async (port) => {
    const out = await run("var_export(av_mail_send('a@b.com', 's', 't'));", port);
    assert.match(out, /'ok'\s*=>\s*false/, 'a refused login must not report success');
    assert.doesNotMatch(out, new RegExp(PASS.replace(/[-]/g, '\\-')),
      'the password leaked into an error message');
  });
});

/* ───────────────────── it must not fail silently ───────────────────── */

test('with no password configured it says so, rather than reporting success',
  {skip: !hasPhp, timeout: 20000}, async () => {
  await withSmtp({}, async (port) => {
    const out = await run("var_export(av_mail_available()); echo PHP_EOL;"
                  + "var_export(av_mail_send('a@b.com', 's', 't'));", port, {AVESTA_SMTP_PASS: ''});
    assert.match(out, /^false/, 'av_mail_available() must be false with no password');
    assert.match(out, /'ok'\s*=>\s*false/,
      'sending with no password must fail loudly; silently returning true is how the '
      + 'recovery codes went missing in the first place');
    assert.match(out, /password|not configured|sign in/i, 'the error should say what is wrong');
  });
});

test('a server that refuses the recipient is reported as a failure',
  {skip: !hasPhp, timeout: 20000}, async () => {
  await withSmtp({refuseAt: 'RCPT'}, async (port) => {
    const out = await run("var_export(av_mail_send('nope@example.com', 's', 't'));", port);
    assert.match(out, /'ok'\s*=>\s*false/,
      'recovery-lib rolls its challenge back on a false return, so this has to be false');
  });
});

/* ───────────────────── transport security ───────────────────── */

test('anything but loopback has to be encrypted', {skip: !hasPhp, timeout: 20000}, async () => {
  const out = await run("var_export(av_mail_send('a@b.com', 's', 't'));", 25, {
    AVESTA_SMTP_HOST: 'smtp.hostinger.com', AVESTA_SMTP_SCHEME: 'tcp', AVESTA_SMTP_PORT: '25',
  });
  assert.match(out, /'ok'\s*=>\s*false/,
    'a plaintext connection to a remote SMTP server would put the mailbox password on the wire');
  assert.match(out, /encrypt|TLS|ssl/i, 'and it should say why');
});

test('the shipped default is TLS to Hostinger on 465 as the real mailbox', {skip: !hasPhp}, () => {
  const r = spawnSync(php, ['-r', "require 'mailer-lib.php'; $c = av_mail_config();"
    + "unset($c['pass']); echo json_encode($c);"], {encoding: 'utf8', env: {PATH: process.env.PATH}});
  const c = JSON.parse(r.stdout);
  assert.equal(c.host, 'smtp.hostinger.com');
  assert.equal(Number(c.port), 465);
  assert.match(c.scheme, /^(ssl|tls)$/, 'port 465 is implicit TLS');
  assert.equal(c.user, 'info@avesta.solutions', 'the only mailbox that exists on the domain');
  assert.equal(c.from, 'info@avesta.solutions');
  assert.ok(!('pass' in c) || c.pass === undefined);
});

/* ───────────────────── message construction ───────────────────── */

test('a line of a single dot cannot cut the message short',
  {skip: !hasPhp, timeout: 20000}, async () => {
  await withSmtp({}, async (port, seen) => {
    await run("av_mail_send('a@b.com', 'subj', \"first\\n.\\nlast\");", port);
    const body = seen.data.join('\n');
    assert.match(body, /last/,
      'the text after a bare "." was dropped, so the message must be dot-stuffed');
    assert.match(seen.data.join('\n'), /^\.\./m, 'the bare dot should have been doubled');
  });
});

test('headers cannot be injected through the recipient or the subject',
  {skip: !hasPhp, timeout: 20000}, async () => {
  await withSmtp({}, async (port, seen) => {
    const out = await run("var_export(av_mail_send(\"a@b.com\\r\\nBcc: sneak@evil.com\", "
                  + "\"subj\\r\\nBcc: other@evil.com\", 'body'));", port);
    const injected = seen.rcptTo.some(r => /evil\.com/.test(r))
                  || seen.data.some(l => /^Bcc:/i.test(l));
    assert.ok(!injected, 'a CRLF in the recipient or subject added a header: '
      + JSON.stringify({rcpt: seen.rcptTo, data: seen.data.filter(l => /evil/.test(l))}));
    assert.match(out, /'ok'\s*=>\s*(false|true)/);
  });
});

test('the message says it is UTF-8, because Zambian names and ZMW amounts need it',
  {skip: !hasPhp, timeout: 20000}, async () => {
  await withSmtp({}, async (port, seen) => {
    await run("av_mail_send('a@b.com', 'subj', 'K1,500 for Mwansa');", port);
    assert.match(seen.data.join('\n'), /charset=UTF-8/i);
    assert.match(seen.data.join('\n'), /^Date:/mi, 'a message with no Date header scores as spam');
    assert.match(seen.data.join('\n'), /^Message-ID:/mi, 'and so does one with no Message-ID');
  });
});

/* ───────────────────── the callers actually use it ───────────────────── */

test('the two things that send email both go through this one sender', () => {
  const recovery = readFileSync('recovery-lib.php', 'utf8');
  assert.match(recovery, /av_mail_send/,
    'recovery-lib.php must send the sign-in codes through the SMTP sender');
  assert.doesNotMatch(recovery.replace(/\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, ''), /@?\bmail\s*\(/,
    'recovery-lib.php is still calling mail() somewhere');

  const notify = readFileSync('notify-lib.php', 'utf8');
  const bare = notify.replace(/\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, '');
  assert.match(notify, /av_mail_send/, 'notify-lib.php must use the SMTP sender too');
  assert.doesNotMatch(bare, /@?\bmail\s*\(/, 'notify-lib.php is still calling mail()');
  assert.doesNotMatch(bare, /no-reply@/i,
    'notify-lib.php was sending as a mailbox that does not exist');
});

test('the admin panel does not claim email works just because PHP has a mail() function', () => {
  const api = readFileSync('api.php', 'utf8');
  assert.doesNotMatch(api, /'recovery_mail_available'\s*=>\s*function_exists\('mail'\)/,
    "function_exists('mail') is true on every PHP install ever shipped, so this told Gray "
    + 'email was fine while nothing was being delivered');
  assert.match(api, /'recovery_mail_available'\s*=>\s*av_mail_available\(\)/,
    'it should report whether a mailbox password is actually configured');
});

/* ───────────────────── the secret is stored like the other one ───────────────────── */

test('the mailbox password lives beside the TextMeBot key, out of git and backups', () => {
  const allowlist = readFileSync('backup-lib.php', 'utf8');
  const block = allowlist.slice(allowlist.indexOf('function av_backup_allowlist'));
  assert.doesNotMatch(block.slice(0, block.indexOf('}')), /avesta-secrets/,
    'the secrets file must never enter a backup');
  assert.match(readFileSync('.gitignore', 'utf8'), /^\/avesta-secrets\.php$/m);

  const mailer = readFileSync('mailer-lib.php', 'utf8');
  assert.match(mailer, /smtp_password/,
    'the password should be read from the secrets file under a named key');
  assert.doesNotMatch(mailer.replace(/\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, ''),
    /['"][A-Za-z0-9!@#$%^&*()_+=-]{8,}['"]\s*;?\s*\/\/\s*pass|\$pass\s*=\s*['"][^'"]{6,}['"]/,
    'no password may be hard-coded in the source, which is public');
});
