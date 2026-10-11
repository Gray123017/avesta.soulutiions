# Avesta staging / release safety (2026-10-11)

This staging branch is isolated from production. Do not connect it to Hostinger auto-deploy.

## Protected production-only state
- `public_html/downloads/` (Asset Tracker, Device Health, MoneyTrack installers)
- `public_html/sentinel/` (Sentinel application and installer)
- User accounts, password resets, borrower documents, loan records, confidential configuration and databases

Never publish secrets or live user data to GitHub. Never deploy a full-tree replacement over these directories.

## Release gate
1. Run `npm ci --ignore-scripts && npm run build && npm test`.
2. Run `node scripts/check-release-safety.mjs` and PHP lint.
3. Confirm all workflow jobs pass.
4. Verify real download URLs and application flows on a **non-production staging host** with safe test accounts; the GitHub guard cannot check live binaries.
5. Take and label a fresh Hostinger files + database backup.
6. Generate an explicit file allowlist for the deploy and review a manifest/diff against production before any overwrite.
7. Deploy only reviewed allowlisted files; avoid `downloads/`, `sentinel/`, and runtime state.
8. Test refresh, auth, recovery, admin/staff, loans, IT, chatbot, all downloads, and mobile.
9. Keep a tested rollback copy of all changed files.

## 3D changes
Use isolated optional CSS/JS loaded only on approved static sections. Do not replace `assets/avesta/app.js` or overwrite the JSON data files. Respect touch controls, `prefers-reduced-motion`, and keep an off switch. Require a staging test and user sign-off before production activation.

*This runbook and the release guard are static safeguards, not evidence of a verified live deployment.*
