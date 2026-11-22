'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { canonicalIdentity, accountKey, accountDirectory } = require('../src/core/account-identity.cjs');
const google = { provider:'google', issuer:'https://accounts.google.com', subject:'1034917562', email:'person@example.com' };
test('same verified identity maps to the same opaque account key', () => {
  assert.equal(accountKey(google), accountKey({...google, email:'new-address@example.com'}));
  assert.match(accountKey(google), /^account-[a-f0-9]{64}$/);
  assert.ok(!accountKey(google).includes(google.email));
});
test('distinct identities and providers never share a key', () => {
  assert.notEqual(accountKey(google), accountKey({...google, subject:'1034917563'}));
  assert.notEqual(accountKey(google), accountKey({...google, provider:'microsoft'}));
  assert.notEqual(accountKey(google), accountKey({...google, issuer:'https://other.example.com'}));
});
test('identity is not inferred from an email address', () => {
  assert.throws(()=>accountKey({provider:'google',email:'person@example.com'}), /issuer/i);
  assert.throws(()=>accountKey({...google, subject:''}), /subject/i);
});
test('reject invalid or untrusted identity input', () => {
  for(const input of [null, [], {}, {...google,provider:'unknown'}, {...google, subject:'\u0000'}]) {
    assert.throws(()=>canonicalIdentity(input), TypeError);
  }
});
test('account directory is deterministically nested under local user data', () => {
  const base=path.resolve('/tmp/subscription-desk-test');
  const directory=accountDirectory(base,google);
  assert.equal(path.dirname(directory), path.join(base,'accounts'));
  assert.equal(path.basename(directory),accountKey(google));
  assert.throws(()=>accountDirectory('relative/path',google), /absolute/);
});
