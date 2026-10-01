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
