'use strict';
// Identity keys are derived only from verified provider claims, never a user-entered email.
// This module does NOT authenticate users, encrypt profiles, or grant access to data.
const crypto = require('node:crypto');
const path = require('node:path');
const PROVIDERS = new Set(['google', 'microsoft']);
function canonicalIdentity(claims) {
  if (!claims || typeof claims !== 'object' || Array.isArray(claims)) throw new TypeError('A verified identity is required.');
  const provider = claims.provider;
  const issuer = claims.issuer;
  const subject = claims.subject;
  if (!PROVIDERS.has(provider)) throw new TypeError('Unsupported identity provider.');
  if (typeof issuer !== 'string' || !issuer.trim() || issuer.length > 512 || /[\x00-\x1f]/.test(issuer)) throw new TypeError('Invalid issuer.');
  if (typeof subject !== 'string' || !subject.trim() || subject.length > 512 || /[\x00-\x1f]/.test(subject)) throw new TypeError('Invalid subject.');
  return { provider, issuer: issuer.trim(), subject: subject.trim() };
}
function accountKey(verifiedClaims) {
  const { provider, issuer, subject } = canonicalIdentity(verifiedClaims);
  const digest = crypto.createHash('sha256').update(JSON.stringify([provider, issuer, subject]), 'utf8').digest('hex');
  return 'account-' + digest;
}
function accountDirectory(userDataDirectory, verifiedClaims) {
  if (typeof userDataDirectory !== 'string' || !path.isAbsolute(userDataDirectory)) throw new TypeError('An absolute user-data directory is required.');
  return path.join(userDataDirectory, 'accounts', accountKey(verifiedClaims));
}
module.exports = { canonicalIdentity, accountKey, accountDirectory };
