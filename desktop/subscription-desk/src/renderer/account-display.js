'use strict';
// Presentation only. Never use a display name or initials to authorize access.
(function (root) {
  function initialsFromName(name) {
    if (typeof name !== 'string') return '';
    const parts = name.trim().split(/\s+/u).map(part =>
      part.replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '')
    ).filter(Boolean);
    if (!parts.length) return '';
    const first = word => Array.from(word)[0]?.toLocaleUpperCase() || '';
    return parts.length === 1 ? first(parts[0]) : first(parts[0]) + first(parts[parts.length - 1]);
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = { initialsFromName };
  if (root) root.AccountDisplay = Object.freeze({ initialsFromName });
})(typeof window !== 'undefined' ? window : null);
