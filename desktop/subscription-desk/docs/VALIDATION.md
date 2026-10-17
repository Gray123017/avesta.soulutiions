# Validation record

Build: Subscription Desk 1.0.1, 17 October 2026.

## Completed

- 13 automated core tests passed. They cover payment/cancellation/refund distinctions; ambiguous dates; chronological reconciliation; account separation; cancellation invalidating prior renewals; attachment exclusion; HTML and link sanitization; five-day reminders; safe evidence links; Desktop OAuth validation; legacy imports; memory-only credential fallback; PKCE and rejection of an invalid OAuth state; paginated Gmail scans and partial failures; and text-safe Excel/PDF exports.
- Desktop interaction tests passed in an actual Linux Electron process using a headless display: application launch, isolated renderer, sample-data loading, editing and persistence, search, timezone settings, Excel/PDF/JSON exports, atomic edit rejection, and cancellation of a provider-action confirmation.
- Interface checks passed at renderer widths of 820, 1050 and 1440 pixels. The page and main scroll area did not overflow horizontally. The sidebar toggle remained operable and its preference survived reloading. Record dialogs contained keyboard focus, opened with Space, closed with Escape and returned focus to the selected row. Reduced-motion mode, focused search after rendering, status filtering and export-dialog dismissal passed.
- Screenshots of Overview, compact subscriptions, Settings, the review panel and the export dialog were visually inspected. Excel, PDF and JSON exports were regenerated successfully. The prior 1.0.0 PDF text check found three readable pages.
- In the preceding 1.0.0 validation, the previous subscription register imported 28 records: 27 service records and one technical deadline. Three existing reminder references were preserved. No private records are embedded in the distributed application or source archive.
- The preceding 1.0.0 runtime dependency audit reported zero known vulnerabilities after pinning ExcelJS's UUID dependency to a patched version. This is a point-in-time dependency check, not an independent security audit.
- The Windows application binary is a PE32+ x86-64 executable. The Linux application is an ELF x86-64 executable. NSIS has a 32-bit installer bootstrap containing the 64-bit application.
- The `.deb` declares `Architecture: amd64`. Installer contents and all 11 packaged runtime files were checked against the tested source. A stale Linux build detected during verification was discarded and rebuilt in a fresh directory. Both build scripts now reject a bundle with an old version or different runtime source; that rejection was exercised.

## Boundaries

- This Linux container requires Chromium's `--no-sandbox` flag for headless testing and cannot support normal Unix singleton sockets. The test harness uses a development-only isolated data profile. The production app enables the renderer sandbox and single-instance behavior; no sandbox-disabling flag is shipped in a launcher.
- No real Google OAuth client, Google user consent, live Gmail mailbox or Calendar event was used by the new app. OAuth state/PKCE and API flow tests use local mocks. Complete a live sign-in and scan with your own OAuth setup before relying on the results.
- Windows installation and execution were not available in this Linux environment. The Windows installer is cross-built and unsigned; test it on a supported Windows 64-bit computer.
- Linux package installation, desktop secret-store behavior and distribution-specific AppArmor/user-namespace integration were not tested on a separate installed desktop system. Ubuntu/Debian derivatives may need compatible desktop libraries and sandbox policy.
- No provider payment, renewal, cancellation, newsletter unsubscription, email modification or new Calendar write was performed during app development.
- Detection is a conservative English-language rule engine. It is not guaranteed to discover every subscription or resolve every account identity. All detected records start unreviewed.

## Reproduce

```bash
npm ci
npm test
npm install --no-save playwright
xvfb-run -a npm run test:ui
npm run dist:linux
npm run dist:windows
```

For headless Linux environments with no X server, the smoke harness also accepts `SD_HEADLESS=1`. `SD_TEST_NO_SANDBOX=1` is for isolated test containers only and must not be used as a normal installation workaround.

The Windows build uses a custom unsigned NSIS installer compiled directly after electron-builder creates the x64 application. Its generated uninstaller deletes only packaged files and preserves user data and unrelated files in the selected directory.
