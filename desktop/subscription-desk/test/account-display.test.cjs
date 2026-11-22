'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { initialsFromName } = require('../src/renderer/account-display.js');

test('uses first and last name initials', () => {
  assert.equal(initialsFromName('Gray Banda'), 'GB');
  assert.equal(initialsFromName('  Anita   Maria  Nkosi  '), 'AN');
});
test('supports a single name without inventing another initial', () => {
  assert.equal(initialsFromName('Chipo'), 'C');
});
test('supports accented names', () => {
  assert.equal(initialsFromName('Éric Ndlovu'), 'ÉN');
});
test('does not derive identity or initials from missing display names', () => {
  assert.equal(initialsFromName(''), '');
  assert.equal(initialsFromName('   '), '');
  assert.equal(initialsFromName(null), '');
  assert.equal(initialsFromName({ email: 'person@example.com' }), '');
});
