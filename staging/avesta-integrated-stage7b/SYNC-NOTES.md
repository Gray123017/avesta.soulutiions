# Avesta Stage 7B Hostinger reconciliation

Source: Hostinger `staging(1).zip` snapshot supplied 21 October 2026.

This source-control copy preserves the Stage 7B combined Avesta site while correcting the hosted folder split that placed required library files in `lib.7177` instead of `lib/`. The reviewed v2 request guard from `lib.2341/security.php` is retained as `lib/security.php`.

Intentionally not committed: the live `config.php` instance key/private path, disposable user password hashes, parent Hostinger `.htpasswd`, private runtime/session state, duplicate `lib.2341`/`lib.7177` folders, and rollback/upload ZIPs.

This branch is a staging reconciliation candidate, not an automatic production deployment. `main` remains unchanged until hosted acceptance succeeds.
