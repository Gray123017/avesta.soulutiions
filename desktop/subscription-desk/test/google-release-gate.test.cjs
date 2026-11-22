'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const {assertGoogleReleaseReady}=require('../scripts/assert-google-release-ready.cjs');

test('missing Google publisher app registration blocks packaging even when claimed tested',()=>{
  assert.throws(()=>assertGoogleReleaseReady({googleClientId:''},'true'),/RELEASE BLOCKED: Google OAuth/);
});
test('missing real-world test attestation blocks packaging even if OAuth ID exists',()=>{
  assert.throws(()=>assertGoogleReleaseReady({googleClientId:'123.apps.googleusercontent.com'},''),/RELEASE BLOCKED: Real Gmail sign-in/);
  assert.throws(()=>assertGoogleReleaseReady({googleClientId:'123.apps.googleusercontent.com'},'false'),/RELEASE BLOCKED: Real Gmail sign-in/);
});
test('only registered client and explicit test attestation pass the packaging gate',()=>{
  assert.equal(assertGoogleReleaseReady({googleClientId:'123.apps.googleusercontent.com'},'true'),true);
});
