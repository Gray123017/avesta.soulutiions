'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('Windows installer shortcuts target the actual packaged executable', () => {
  const root = path.resolve(__dirname, '..');
  const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
  const exe = (pkg.build.win?.executableName || pkg.build.productName || pkg.name) + '.exe';
  const installer = fs.readFileSync(path.join(root, 'assets', 'installer.nsi'), 'utf8');
  const targets = [...installer.matchAll(/^\s*CreateShortCut\s+"[^"]+"\s+"([^"]+)"/gm)].map(m => m[1]);

  assert.equal(targets.length, 2, 'Expected the Start Menu and desktop shortcuts');
  for (const target of targets) {
    assert.equal(target, '$INSTDIR\\' + exe,
      'The shortcut must point to the Electron executable, not the previous app name');
  }
});
