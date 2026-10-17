# Subscription Desk 1.0.1

A private desktop subscription manager for **Windows x64** and **Linux amd64 (.deb)**. It finds subscription-related Gmail messages, keeps dated evidence, helps you decide what to keep, and opens provider account pages to review payments, renewals and cancellations.

## Working inside the Avesta repository

This desktop project lives in `desktop/subscription-desk`. Run the commands below from that directory, not the website root. The repository workflow `.github/workflows/subscription-desk.yml` builds Windows x64 and Linux amd64 installers and uploads them as GitHub Actions artifacts. Open a completed workflow run to download `subscription-desk-windows-x64` or `subscription-desk-linux-amd64`, then extract the artifact ZIP. These artifacts are not a signed public release.

The desktop source is not part of the Hostinger website deployment. See [repository and build notes](docs/REPOSITORY.md) for the branch and download workflow.

## Start here

1. Install the package for your computer. Windows: run the `.exe` installer. Debian/Ubuntu: run `sudo apt install ./Subscription-Desk-1.0.1-linux-amd64.deb` from the download directory.
2. Open **Subscription Desk** from the application menu. Select **Explore sample data** to see how it works, or **Import register** to load an existing subscription register JSON.
3. For your email, follow **Settings & connection** to configure Google once. Then use **Sign in with Google** and **Scan Gmail**.
4. Open each record, confirm its account/project and dates, mark usage and choose **Keep**, **Review** or **Cancel**. Save the review.
5. Use **Review payment**, **Review renewal**, or **Review cancellation** to open the provider account. Review and approve the actual transaction or cancellation on that provider's site. Scan again for confirmation.

This is an unsigned private build. Windows may display an unknown-publisher warning. Verify the supplied SHA-256 checksum and the source before deciding whether to install it. A public release should be signed by its publisher.

## What works in this release

- Local desktop UI, persistent records, evidence history, manual editing, usage and keep/cancel decisions.
- Gmail and Google Workspace email through Google OAuth with PKCE and a loopback callback in the system browser. No email password is collected.
- Past-13-month or all-history Gmail search, pagination, read-only message access, cancellation of scans, duplicate message checks and partial-scan reporting. A scan is capped at 20,000 matching messages to bound resource use.
- Conservative local English-language rules for failures, paused access, interruption warnings, payment receipts, cancellations, refunds, free plans and expired trials. No email content is sent to an AI provider.
- A provider catalogue for Render, Claude, ChatGPT, OpenAI API, Ask AI Web/Paddle support, Hostinger, Replit, Lovable, Intercom, Google One, Workspace, Play, monday.com, Zapier, Netflix, Spotify and Adobe. Unrecognized providers are review candidates with no invented billing URL.
- Google Calendar reminders five days before reviewed, explicit or user-confirmed future dates, at 09:00 in the chosen timezone. Deterministic IDs prevent duplicates created by this app. `.ics` export is available without Calendar permission.
- Excel, PDF and JSON exports. JSON backup and the previous subscription/deadline register format can be imported. Existing record IDs are preserved on import, not overwritten.

## Interface improvements in 1.0.1

A cleaner toolbar, consistent icons, clearer text contrast and adaptive cards keep the workspace readable in windows down to 820 × 640. Collapse or expand the sidebar using the top-left navigation button; your choice is remembered on this device. The review panel supports keyboard focus containment, Escape to close, and focus return to the selected record. Short transitions respect the operating system’s reduced-motion preference.

## One-time Google setup

The installer does **not** include a shared Google OAuth client or your ChatGPT connection. Your connected email in ChatGPT cannot be transferred to a separate desktop app. This private build therefore needs a Desktop OAuth client before its Gmail button can work.

1. Open [Google Cloud Console](https://console.cloud.google.com/). Create or choose a project.
2. Under APIs & Services, enable **Gmail API** and, for Google reminders, **Google Calendar API**.
3. Configure the **Google Auth Platform / OAuth consent screen**. For personal testing, choose External audience in Testing and add your own Google email as a test user. Workspace policies may require administrator approval.
4. Configure these scopes: `https://www.googleapis.com/auth/gmail.readonly`; optionally `https://www.googleapis.com/auth/calendar.events`.
5. Create an OAuth client with application type **Desktop app**. Download the client JSON. Do not choose Web application and do not create an API key instead.
6. In the app, open **Settings & connection → Import client JSON**. Enable the Calendar preference if needed, save, then sign in. Google hosts the login and permission screens in your default browser.
7. Confirm the intended account on Google's screen. Return to the app and scan. Changing Calendar permissions requires a fresh sign-in; desktop clients do not support incremental authorization.

For an External app left in Testing, Google commonly expires refresh tokens after seven days when these scopes are requested. Reconnect when asked. Public distribution with a shared sign-in configuration needs Google's verification requirements for Gmail's restricted scope to be addressed, a privacy policy, and release signing. These are publisher setup tasks, not implemented credentials.

Official references, checked 17 October 2026:

- [Google OAuth for desktop applications](https://developers.google.com/identity/protocols/oauth2/native-app)
- [Gmail scope requirements](https://developers.google.com/workspace/gmail/api/auth/scopes)
- [Google token expiration rules](https://developers.google.com/identity/protocols/oauth2#expiration)
- [Gmail message list and search](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/list)
- [Google Calendar event creation](https://developers.google.com/workspace/calendar/api/v3/reference/events/insert)

## Important behavior

**Coverage:** Email is evidence, not live access to provider billing. The app cannot guarantee detection of every subscription, including services without emails, translated notices, receipts outside the chosen search period, or unsubscribed mailboxes. Check bank/card statements and providers to complete the inventory. It does not inspect your bank, payment cards or other apps.

**Identity:** Separate mailboxes and identified subscription IDs/products are kept separate. When an email omits identity, messages for that provider are grouped as a review candidate marked “Account not identified.” Multiple real subscriptions could be represented by that candidate. Correct or add records manually before acting. Imported records do not silently merge with newly detected Gmail candidates; compare them to avoid duplicate entries.

**Dates:** Only full ISO dates or written dates including the year, near an explicit deadline phrase, are extracted. No renewal date is inferred from a monthly receipt, billing frequency, or an ambiguous numeric date. A cancellation invalidates an earlier renewal unless access-end evidence is retained. Past dates require a new confirmed deadline. If the five-day reminder date has already passed, review now; the app will not invent a different reminder date.

**Money and cancellation:** No payment gateway, card vault, automatic charge, universal cancel API or auto-renew toggle is provided. The buttons open fixed provider account pages in your browser after an explicit confirmation. An opened page is logged as “completion not verified.” Actual payment and cancellation depend on provider login, account role, plan terms, and payment channel. Mobile-store plans may require Apple or Google Play. Failed payments, refunds and newsletter unsubscribe links do not establish subscription cancellation. Render service deletion can affect hosted data and should be reviewed per service.

**Reminders:** Google events are private, have no attendees, and are transparent/free time. They are review events on the reminder date itself, five days before the deadline, with email and popup notifications at 09:00. This app only removes events it created. Existing reminders imported from another register are flagged; creating another reminder for the same imported deadline is blocked to avoid duplication. `.ics` files use an all-day review event with a 09:00 display alarm; calendar clients may interpret or ignore alarms differently. The app does not run background scans when closed. The optional scan-on-open setting is the only automatic scan.

**Usage:** Savings decisions are suggestions based on usage you enter. Email does not establish that a service is unused. There is no combined spend total across unknown currencies or mixed one-time and recurring amounts.

## Privacy and local files

- No telemetry, advertising, third-party model, developer server or cloud database.
- Gmail read-only access; no send, delete, unsubscribe or mailbox-modification capability.
- OAuth access and refresh tokens stay in the main process and use Electron's OS-backed safe storage. Windows uses DPAPI; Linux uses an available desktop secret store. When secure storage is unavailable, tokens remain in memory only. There is no plaintext token fallback.
- Subscription records, excerpts and the public Desktop OAuth client configuration are local plain-text files. Full email bodies and attachments are not retained. Use disk encryption and protect exported files.
- Records live under `%APPDATA%` on Windows or `~/.config` on Linux, in the Electron application data directory (typically `Subscription Desk` or `subscription-desk`). The exact directory follows the application name and platform configuration.
- Disconnect revokes Google access and removes saved tokens while retaining records. Delete local records or remove the user-data directory after quitting to erase them. Uninstalling should not be assumed to erase user data.
- The renderer is sandboxed, cannot run Node.js, cannot navigate to remote sites, and receives only a narrow IPC interface. Email content is displayed as text. Provider actions are restricted to the built-in catalogue; original-email actions only allow `https://mail.google.com` links.

Security references: [Electron security](https://www.electronjs.org/docs/latest/tutorial/security), [Electron safeStorage](https://www.electronjs.org/docs/latest/api/safe-storage).

## Build from source

Use Node.js 24 and npm. Dependencies are pinned in `package-lock.json`.

```bash
npm ci
npm test
npm start
npm run dist:linux
npm run dist:windows
```

`dist:linux` builds an amd64 `.deb` under `dist/linux-package/`; `dist:windows` builds the x64 app inside an NSIS installer. NSIS's installer bootstrap can be 32-bit while the installed Electron application is 64-bit. Windows resource editing and code signing are disabled for this private cross-build. The installed application may retain Electron's default executable metadata/icon; the application window, shortcuts and installer use the app branding where supported.

The reserved `example.invalid` homepage and maintainer email in build metadata indicate a private project with no published support site. Replace them before public distribution. Do not add real OAuth secrets, tokens, mailbox exports or personal subscription data to source control. Desktop OAuth clients are public clients, but user tokens are credentials.

The included GitHub Actions workflow builds on native Ubuntu and Windows runners and uploads artifacts. The standalone workflow is retained here for reuse; the active Avesta workflow is at the repository root. See the pull request checks for the current run results. Linux development UI tests use Playwright's Electron driver and an X display. Install Playwright for development, then run `xvfb-run -a npm run test:ui`.

## Validation boundaries

See `docs/VALIDATION.md` for the checks actually run on this build. Live Google consent, a real mailbox scan, a real Calendar write, Windows installation, provider payments and cancellations need to be verified on your computer with your accounts. No live financial action has been taken while building the app.

## Troubleshooting

- **Access blocked / unverified app:** Verify the Desktop client type, enabled APIs, consent scopes and your test-user email. For public use, complete Google's applicable verification rather than assuming testing credentials are suitable for distribution.
- **Gmail works but Calendar fails:** Enable Calendar API and the Calendar setting, save, then reconnect and grant the Calendar scope.
- **Need to sign in every restart on Linux:** Unlock/install the desktop's Secret Service or KWallet. The app deliberately uses memory-only tokens if the secure store is unavailable.
- **No records detected:** Try all email history, review the search coverage and add missing subscriptions manually. Detection rules are English-language and cannot infer purchases with no billing email.
- **Linux Chromium sandbox error:** Install the `.deb` through the package manager and run it as your normal desktop user. Do not disable the sandbox as a routine workaround. Ubuntu AppArmor/user-namespace policies may require a distribution-specific signed/policy-integrated release.
- **Windows antivirus/SmartScreen warning:** This build is unsigned. Do not turn off protection. Verify the checksum/source and follow your organization's installation policy, or have a trusted publisher sign a production build.

MIT licensed. Third-party licenses are included in the packaged Electron distribution and dependencies.
