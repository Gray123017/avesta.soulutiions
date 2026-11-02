# Avesta project context

Shared state for any tool or person working on this repo (Claude, ChatGPT, humans). Update it at the end of every session. This file is blocked from web access by `.htaccess`; keep it free of secrets, credentials and customer data regardless.

## What this is
Avesta website for Avesta Enterprises (micro-lending): public pages, loans, IT services, login, staff/admin portal, PHP backend, document uploads, WhatsApp/email notifications. Domain: https://www.avesta.solutions/. Hosted on Hostinger as plain PHP.

## Deployment (read before pushing anything)
- Hostinger Git deployment: `main` → `public_html` (per README). Whether auto-deploy is currently enabled is unverified; check in hPanel → Advanced → Git.
- Assume every push to `main` can go live. Work on branches, open PRs, and merge to `main` only after the release gate in `STAGING_RELEASE_RUNBOOK.md` passes.
- Never deploy a full-tree replacement over production-only state: `public_html/downloads/`, `public_html/sentinel/`, user accounts, password resets, borrower documents, loan records, confidential config.
- Secrets live only on the server (for example `avesta-secrets.php`, excluded in `.gitignore` and denied in `.htaccess`). Never commit them.
- Data and runtime files (`users.json`, `records_data.json`, `enquiries.json`, rate caches, logs, `/documents/*`) are excluded from Git and must be preserved on upgrades.

## Checks (same as CI, `.github/workflows/check.yml`)
```
npm ci --ignore-scripts
npm run build
npm test
node scripts/check-release-safety.mjs
find . -path ./node_modules -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```
CI also starts `php -S` and runs `scripts/check-php.mjs` for public routes and the account gate. A separate workflow builds the Subscription Desk desktop packages (`desktop/subscription-desk`); it does not deploy the website.

## Layout
- Root PHP pages: `index.php`, `loans.php`, `apply.php`, `login.php`, `portal.php`, `it.php`, `it-help.php`, `downloads.php`, `recovery.php`, `setup.php`, `api.php`, `assistant-api.php`
- `app/` admin and staff application, `assets/`, `documents/` (uploads, never public), `desktop/` (desktop software source), `scripts/`, `tests/`
- Further docs: `README.md`, `DEPLOY_TO_HOSTINGER.txt`, `IT_CHATBOT_SETUP.md`, `PASSWORD_RECOVERY.md`, `STAGE6_LENDING_NOTES.md`, `STAGING_RELEASE_RUNBOOK.md`

## Decisions
- Query-string routes on `index.php` (no rewrite rules needed).
- Authenticated session checks replaced the old browser PIN gates.
- State-changing calls use POST.
- Do not replace `assets/avesta/app.js` or overwrite the JSON data files when adding 3D or visual features; keep them optional with an off switch and `prefers-reduced-motion` respected.

## Open issues
- Internal docs were previously servable from the web root; `.htaccess` rule added in the PR that introduced this file. Verify on the live site after merge that `/README.md` returns 403.
- Live-host testing (HTTPS, permissions, notification delivery, backups, upload limits, session behaviour, recovery) is still listed as pending in `DEPLOY_TO_HOSTINGER.txt`.

## Session log
- 2026-10-17: Added `.htaccess` rule blocking `.md` files and `DEPLOY_TO_HOSTINGER.txt`; added this file.
