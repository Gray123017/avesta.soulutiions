// Static release guard for the Avesta Hostinger source tree.
// Installer binaries and user data intentionally live on Hostinger; this does NOT
// verify deployed file existence or replace live browser/end-to-end checks.
import { readFileSync, existsSync } from 'node:fs';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';

const required = [
  'index.php', 'downloads.php',
  'assets/avesta/app.js', 'assets/avesta/styles.css',
  'assets/avesta/services.json', 'assets/avesta/help.json',
  'assets/avesta/assistant.js', 'assets/avesta/responsive.css',
  'assets/avesta/software-tabs.js'
];
for (const path of required) {
  assert(existsSync(path), `Required source file missing: ${path}`);
}
for (const path of ['assets/avesta/services.json', 'assets/avesta/help.json']) {
  JSON.parse(readFileSync(path, 'utf8'));
}
const home = readFileSync('index.php', 'utf8');
for (const asset of ['app.js', 'styles.css', 'services.json', 'help.json']) {
  assert(home.includes(asset), `Homepage no longer references ${asset}`);
}
/* This used to assert that four specific download URLs appeared in
 * downloads.php, and signed off with a note that the binaries "require
 * separate live verification". Nobody ever did that verification, and three
 * of the four files had never been committed — so the guard was holding the
 * page's dead links in place rather than catching them. Every one of those
 * three returned 404 to a visitor.
 *
 * The invariant that actually matters is the other way round: a link offered
 * for download must be a file that exists. */
const downloads = readFileSync('downloads.php', 'utf8');
const offered = [...downloads.matchAll(/'primary_href'\s*=>\s*'([^']+)'/g)].map(m => m[1])
  .filter(href => /\.(exe|zip|rar|msi|deb|apk|pdf|txt)$/i.test(href));
assert(offered.length > 0, 'downloads.php offers no files at all');
for (const href of offered) {
  assert(existsSync('.' + href), `downloads.php offers ${href}, which is not in this repository`);
}
console.log(`Avesta static release guard passed. ${offered.length} download(s), every file present.`);

const preview = readFileSync('preview/depth.html', 'utf8');
for (const ref of ['optional-depth.css', 'optional-depth.js', 'data-avesta-depth="enabled"']) {
  assert(preview.includes(ref), `Standalone preview reference missing: ${ref}`);
}
for (const forbidden of ['app.js', 'services.json', 'help.json', 'assistant-api.php']) {
  assert(!preview.includes('src="../assets/avesta/' + forbidden + '"'), `Preview should not initialize production: ${forbidden}`);
}
for (const path of ['assets/avesta/optional-depth.css', 'assets/avesta/optional-depth.js']) {
  assert(existsSync(path), `Optional depth asset missing: ${path}`);
}

// ── Nothing personal may ever be committed ───────────────────────────────────
// The repository is PUBLIC, and GitHub is outside Zambia, which the Data
// Protection Act 2021 does not allow for personal data. .gitignore is what
// keeps applicant records and NRC scans out, and a .gitignore only protects a
// file that was never added — once a file is tracked, the ignore rule is
// silently skipped. So the check is what git actually tracks, not what the
// ignore file says it should.
const tracked = execFileSync('git', ['ls-files', '-z'], { encoding: 'utf8' })
  .split('\0').filter(Boolean);

const forbidden = [
  // The stores. Named with their extensions so a guarded .php copy is caught
  // as well as a legacy .json one.
  /^(records_data|users|enquiries|it_questions|login_attempts|password_resets|email_recovery|audit_log|enquiry_rate|ithelp_rate|assistant_rate)\.(php|json|jsonl)$/,
  /^avesta-secrets\.php$/,
  /^\.env/,
  // A downloaded backup carries every NRC scan and password hash on the
  // server. Committing one to a public repository would publish all of them.
  /\.(tar|tgz|gz|bak|sql)$/,
  /avesta-backup-/,
];

for (const path of tracked) {
  // documents/.htaccess is the rule that keeps the folder private, and it has
  // to be in Git or a fresh deployment serves every scan in it.
  if (path === 'documents/.htaccess') continue;

  if (path.startsWith('documents/')) {
    throw new Error(
      `Applicant documents must never be committed: ${path}\n` +
      `The repository is public. Remove it from history, not just from the index.`
    );
  }
  for (const pattern of forbidden) {
    assert(!pattern.test(path),
      `This file must never be committed to a public repository: ${path}`);
  }
}
console.log(`Checked ${tracked.length} tracked files: no applicant data, secrets or backups.`);

// ── Nothing outside the website is reachable from the website ────────────────
// The repository root IS the Hostinger document root, so every directory added
// to this repo for any reason is deployed and served. A desktop application's
// source sitting at the web root of a money-lending site is the shape of that
// problem; the rule exists so a new top-level directory has to be considered
// rather than quietly published.
const htaccess = readFileSync('.htaccess', 'utf8');
const refused = (htaccess.match(/RedirectMatch 404 \^\/\(([^)]+)\)/) || [, ''])[1]
  .split('|').map(s => s.replace(/\\/g, ''));

// The deploy's own checkout. It is never a tracked path, so the loop below
// cannot discover it from the repository — it has to be asserted by name, or
// the one directory holding the entire history of the site is the one
// directory this guard says nothing about.
assert(refused.includes('.git'),
  '.htaccess must refuse /.git/ — it is the deploy checkout at the web root, ' +
  'it holds the whole history of the site, and it is not a tracked path, so ' +
  'nothing else here can notice it going missing from the rule.');

const webDirs = new Set(
  tracked.filter(p => p.includes('/')).map(p => p.split('/')[0]));
// Directories the website itself asks for. Everything else deploying to the
// document root has to be named in the refusal rule above, so adding a
// directory to this repository for some other purpose fails the build until
// somebody decides which of the two it is.
const servedOnPurpose = new Set([
  'app',        // the pages themselves
  'assets',     // stylesheets, the published rate and category tables
  'documents',  // applicant uploads, protected by their own .htaccess
  'downloads',  // the software catalogue the site offers
  'icons',      // PWA icons: without these the app cannot be installed
  'images',
  'preview',
]);

for (const dir of webDirs) {
  if (servedOnPurpose.has(dir)) continue;
  assert(refused.includes(dir),
    `/${dir}/ deploys to the live web root but .htaccess does not refuse it.\n` +
    `Add it to the RedirectMatch rule, or add it to servedOnPurpose here if the ` +
    `website genuinely serves it.`);
}
console.log(`Web root: ${refused.join(', ')} refused; ` +
  `${[...webDirs].filter(d => servedOnPurpose.has(d)).join(', ')} served on purpose.`);
