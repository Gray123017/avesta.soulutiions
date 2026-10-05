# Avesta website — Hostinger PHP deployment

Extracted website code from avesta-harvester-final-hostinger.zip.
Company email: info@avesta.solutions.
The previous avesta-ready-to-run.zip remains available in Git history.

## Deployment
1. Back up the existing Hostinger website and all account/application data.
2. In Hostinger: Websites → select website → Dashboard → Advanced → Git.
3. Connect GitHub and select Gray123017/avesta.soulutiions, branch main.
4. Confirm the destination (normally public_html). Deploy to a test site first.
5. Configure notification credentials on the server; never commit them.
6. On a fresh installation, create the first administrator via setup.php immediately.
7. Verify home, loans, IT services, login, admin access, uploads, and notifications.
8. Enable auto-deployment after testing and checking preservation of server-created data.

Account stores, application records, logs, caches, passwords and uploaded documents
are intentionally excluded from Git. Preserve existing server data on upgrades.
Read DEPLOY_TO_HOSTINGER.txt and IT_CHATBOT_SETUP.md for additional configuration.

GitHub deployment is not a direct connection from this chat to Hostinger.

Legacy browser PIN gates have been replaced with authenticated session checks.
Administrator logout ends the server session. IT question history is excluded.

## Public design update (1 October 2026)

The public homepage now serves the adapted Avesta design. Use `index.php?page=lending`, `how-it-works`, `calculator`, `currency`, `it`, `about` or `contact` for public sections. Query routes require no web-server rewrite changes. `apply.php` uses the existing login gate before opening the existing loan form. Authentication, records, documents, loan processing, staff portal and legal pages retain their PHP implementation.

G.I.T provides local saved guidance with official source links. It does not submit chat messages or access customer records. Live AI/web search is not enabled. Currency conversion reads the existing `api.php?action=rates` response and labels stale data; manual conversion remains available if the feed fails.

Deploy the repository root through the existing Hostinger `main` → `public_html` connection. Node is used only for checks: `npm ci`, `npm run build`, `npm test`. PHP syntax and anonymous route checks run in GitHub Actions. Do not deploy the separate Cloudflare review source as a PHP replacement. Existing production data files and uploads are excluded from Git and must be preserved. The Sites monitoring job does not monitor this Hostinger deployment.


## Deployment persistence guard

Software download binaries and product routes used by `downloads.php` must be present in the deployed tree before promotion. Current required routes include Asset Tracker, Sentinel and Device Health. A successful Git/Hostinger deployment is not acceptance by itself: verify each product tab on desktop/mobile and verify every download/open action after deployment. Do not delete server-created account/application data or secrets during deployment.
