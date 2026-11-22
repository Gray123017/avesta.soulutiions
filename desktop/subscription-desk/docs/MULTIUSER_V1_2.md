# Subscription Tracker v1.2: multi-user design and release gates

**Status:** foundation only. The existing v1.1.0 runtime is still single-workspace and Gmail-only. Do not describe this branch as production-ready multi-user software.

## Naming and profile display

- **Application brand:** Subscription Tracker. This is constant across all installations and all users.
- **Signed-in account avatar:** derive one or two initials from the provider-verified account owner's display name (e.g. "Gray Banda" -> "GB"; "Chipo" -> "C"). Never hardcode a particular account owner's name or initials.
- Use the display name only for presentation. The workspace identity and authorization still rely on immutable verified identity claims, **never** display name or initials.
- If the provider does not return a display name, show a neutral avatar placeholder instead of inventing a name. Existing v1.1 Gmail integration currently returns email but not a profile display name; full personalized initials require its v1.2 sign-in upgrade.
- Profiles are not yet selectable or securely isolated in the running desktop UI; the helper does not make the current release multi-user.

## End-user onboarding and publisher setup (Fedora feedback, November 22)

The old Google Cloud / JSON import wizard is removed from the normal desktop UI.
Users must not be asked to register an OAuth application or supply developer keys.
The app shows **Continue with Google** only when its publisher's Google client
configuration was included during packaging; otherwise it clearly shows the
Google option as unavailable. Microsoft's button is explicitly disabled until
the Graph adapter, authentication, and account isolation are implemented.

**One-time publisher action, not end-user action:** register a Google Desktop
OAuth client with the Gmail read-only scope and a Microsoft desktop/public
client with Microsoft Graph delegated Mail.Read. For GitHub Actions, set
repository variable `SUBSCRIPTION_TRACKER_GOOGLE_CLIENT_ID`, optionally
repository secret `SUBSCRIPTION_TRACKER_GOOGLE_DESKTOP_CLIENT_SECRET` (Google
desktop applications cannot keep embedded values confidential), and
`SUBSCRIPTION_TRACKER_MICROSOFT_CLIENT_ID` as a repository variable.
The packaging job writes these into the app's public-client configuration
without logging the values. Do not distribute an installer containing an
unapproved production client, and do not assume a green CI build proves
OAuth login works. Google restricted Gmail scopes may require verification
before unlisted users can authorize the app.

**Current limitation:** This development branch still uses a single
workspace. Do not connect different people's mailboxes on a shared OS
login until identity-verified vault isolation and migration are implemented.

## Product decisions

- Support sign-in with **Google/Gmail** and **Microsoft Outlook / Microsoft 365**, using provider-hosted login and OAuth 2.0 authorization code with PKCE.
- Use delegated read-only mailbox permissions for discovery: Gmail `gmail.readonly`; Microsoft Graph `Mail.Read` (and `User.Read` for identity). Calendar write permissions, if requested, must be a separate optional consent.
- Each verified identity gets its own logical workspace, subscription records, settings, OAuth tokens, exports, and scanning history. One user must never see another user's records after account switching.
- A signed-in person may link additional mailboxes only after explicitly authenticating and confirming ownership. Keep provider identity, mailbox identity, and human workspace identity distinct.
- Use immutable, provider-verified identity keys (OIDC issuer + subject or verified tenant + object ID), **not email addresses**, for account isolation. The initial `account-identity.cjs` helper only derives opaque keys; it does not authenticate, authorize or encrypt anything.
- **Shared computer protection:** A directory per identity is not sufficient security if app users share the same operating-system account. Before enabling app-level switching, encrypt records and tokens per identity using OS-backed keys plus a separately authenticated unlock flow, or restrict use to individually protected OS user accounts. Lock UI on sign-out; clear in-memory data, caches, webviews and background scans when switching identities.
- Existing v1.1 data must not be automatically assigned to whoever signs in first. Provide a deliberate, clearly labelled and backed-up one-time migration with user confirmation.

## Discovery and management

- Search subscription-related receipts, renewal notices, trials, charges and account notifications using authorized mailbox APIs; never claim to discover every subscription.
- Use structured fields where credible, preserve source evidence and distinguish verified, likely and unconfirmed items. Merge duplicates across messages but do not merge across accounts without the owner's confirmation.
- **Keep:** update an in-app review decision only.
- **Upgrade:** open a verified provider billing/plan page; automatic upgrades require a provider-supported authenticated API, explicit confirmation and a separately reviewed implementation.
- **Unsubscribe / cancel:** open verified provider cancellation settings or use a provider-specific API only where documented, authorized and confirmable. Unsubscribing from marketing emails is not cancellation of a paid subscription.
- Display confidence, currency, billing interval and evidence dates. Never fabricate future charge dates or aggregate unlike currencies.

## Release and privacy gates

1. Implement Microsoft identity + Graph adapter, including refresh, revoke and least-privilege consent.
2. Implement secure multi-profile vault, sign-in/out and explicit account-switching tests; fail closed where keychain protection is unavailable.
3. Build consent-based onboarding and visible account selection in the dashboard; show scanning progress and errors without leaking mailbox data to logs.
4. Add Gmail/Outlook mocked tests for account separation, token isolation, error and revoke flows; test normal/failed sign-in and cancellation actions.
5. Verify Windows x64 NSIS `.exe`, Kali/Debian x64 `.deb`, and Fedora x86_64 `.rpm` on actual target systems. CI-produced packages are not a substitute for real installation testing.
6. Arrange publisher OAuth client configuration (Google verification may apply to restricted Gmail scopes), a privacy policy, accessible account deletion/export controls and code-signing before public distribution.
7. Keep Hostinger website deployment unchanged. Open a draft pull request for review; do not merge into `main` until all release gates pass.

**Security warning:** Neither an email address nor the mere presence of an OAuth token proves that a user may read another local workspace. The app must authenticate the current user before decrypting and opening that workspace.

Official references:
- Google desktop OAuth: https://developers.google.com/identity/protocols/oauth2/native-app
- Microsoft desktop OAuth/PKCE: https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow
- Microsoft Graph delegated access: https://learn.microsoft.com/en-us/graph/auth-v2-user
- Electron-builder Linux RPM target: https://www.electron.build/v26/docs/linux/
