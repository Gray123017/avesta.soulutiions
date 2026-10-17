# Subscription Desk in Avesta

Version: 1.0.1.

The application source is stored in `desktop/subscription-desk`. It is a desktop project, separate from the PHP website. Do not include this directory when preparing an allowlisted Hostinger website deployment.

## Build and download

The root workflow `.github/workflows/subscription-desk.yml` runs core tests and builds on native Ubuntu and Windows runners when a pull request changes this project. It also runs after relevant changes reach `main` and supports manual dispatch once available on the default branch.

In GitHub, open **Actions → Subscription Desk desktop packages → a successful run**. Under Artifacts, download the Windows x64 or Linux amd64 ZIP and extract the installer. Artifacts are retained for 30 days and require GitHub access. These are unsigned build artifacts; successful packaging does not establish that installation or live Google account access has been tested.

The larger compiled installers are generated as workflow artifacts instead of being committed into Git. No GitHub Release, website catalogue entry, Hostinger deployment, or automatic update channel is created by this workflow.

For local builds:

```bash
cd desktop/subscription-desk
npm ci
npm test
npm run dist:linux
# On Windows or a supported cross-build environment:
npm run dist:windows
```

Linux output: `dist/linux-package/Subscription-Desk-1.0.1-linux-amd64.deb`.

Windows output: `dist/Subscription-Desk-1.0.1-win-x64.exe`.

The nested `.github/workflows/build.yml` is the original standalone-project workflow. GitHub uses only the workflow at the Avesta repository root in this monorepo.

## Account setup

Follow [SETUP.md](SETUP.md) for the one-time Google Desktop OAuth configuration. No OAuth client, tokens, mailbox exports, personal subscription register, or payment details are included in the source. Provider payments and cancellations are completed on provider websites after the user's review.

See [VALIDATION.md](VALIDATION.md) for completed checks and testing boundaries. Current GitHub run results are shown on the pull request and Actions pages.
