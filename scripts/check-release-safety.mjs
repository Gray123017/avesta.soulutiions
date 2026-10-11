// Static release guard for the Avesta Hostinger source tree.
// Installer binaries and user data intentionally live on Hostinger; this does NOT
// verify deployed file existence or replace live browser/end-to-end checks.
import { readFileSync, existsSync } from 'node:fs';
import { strict as assert } from 'node:assert';

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
const downloads = readFileSync('downloads.php', 'utf8');
for (const link of [
  '/downloads/asset-tracker/GIT-Asset-Tracker-1.5.1-Windows.zip',
  '/downloads/g-it/G-IT-Setup-x64.exe',
  '/downloads/moneytrack/Avesta-MoneyTrack-1.2.0-Windows-x64-Setup.exe',
  '/sentinel/downloads/Avanto-Sentinel-Windows-x64.rar'
]) {
  assert(downloads.includes(link), `Software catalogue link missing: ${link}`);
}
console.log('Avesta static release guard passed. Hostinger binaries require separate live verification.');

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
