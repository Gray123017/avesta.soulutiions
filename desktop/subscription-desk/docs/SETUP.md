# Subscription Desk — first-run guide

**A desktop subscription tracker for Windows x64 and Linux amd64.** This private build supports Gmail and Google Workspace mailboxes. It does not include a pre-registered Google sign-in client.

## Install

- **Windows 64-bit:** run `Subscription-Desk-1.0.1-win-x64.exe`. This installer is unsigned and has not been run on Windows in this build environment. Verify the supplied checksum and follow your computer’s installation policy.
- **Debian / Ubuntu 64-bit:** open a terminal in your download folder and run `sudo apt install ./Subscription-Desk-1.0.1-linux-amd64.deb`. Open Subscription Desk from your applications menu as your normal desktop user.

## New in 1.0.1

The refreshed interface includes clearer text, consistent icons, a separate scan toolbar, an expandable sidebar and responsive layouts down to 820 × 640. Use the button at the top left to collapse navigation; your choice is remembered. The subscription review panel supports Tab navigation and Escape to close. Short transitions respect your system’s reduced-motion setting.

## Connect Gmail once

1. In [Google Cloud Console](https://console.cloud.google.com/), create or select a project. Enable **Gmail API** and **Google Calendar API**.
2. Configure the OAuth consent screen. For private testing, add your Google email as a test user.
3. Create an OAuth client of type **Desktop app** and download its JSON. An API key or Web app client is not a substitute.
4. In Subscription Desk, open **Settings & connection → Import client JSON**. Set your timezone, for example **Africa/Lusaka**. Enable Calendar access if you want Google reminders, then save preferences.
5. Click **Sign in with Google**. Complete Google’s login and permission screens in your browser, then return to the app. Your email password is never entered into the app.
6. Choose **Past 13 months** or **All email history**, then click **Scan Gmail**. Review the results and the scan coverage note.

Google may require a new sign-in after seven days while an External OAuth app remains in Testing. A public one-click sign-in release requires publisher setup and Google’s applicable verification. [Official Google desktop setup guide](https://developers.google.com/identity/protocols/oauth2/native-app).

## Use your existing tracker immediately

Use **Import register** to select your previously delivered `Gray_Subscription_Deadline_Register.json`. It imports the 27 service records and technical deadline, retaining evidence and existing reminder references. These records are not bundled into the installers.

You can also choose **Explore sample data** without connecting email. Sample records are clearly marked and cannot create real reminders.

## Review, pay, renew or cancel

Open a subscription, check its account/project and evidence, record how often you use it, and choose **Keep**, **Review** or **Cancel**. Confirm an exact deadline if you can verify it, then save.

**Review payment**, **Review renewal** and **Review cancellation** open the provider’s account website after confirmation. You complete the actual action on the provider’s site. The app does not charge a card, change auto-renew, or automatically cancel a service. Newsletter unsubscribe is never treated as paid-service cancellation.

Email notices do not prove today’s account status. Compare the app with provider dashboards and bank/card statements. Missing or ambiguous dates stay unconfirmed. Unknown providers can be added manually; their original paid receipt is the safest starting point for finding account management.

## Reminders and exports

For a reviewed, exact future date, choose **Add to Google Calendar**. The app creates a private review event at 09:00, five days before the deadline, in your selected timezone. If that reminder date has already passed, review the service now. Existing imported reminder dates are protected against duplication.

The export button at the top right creates **Excel**, **PDF**, **JSON backup** or **.ics calendar** files. Google Calendar reminders can notify you while the desktop app is closed. Email scans only run while it is open.

## Privacy and testing

Subscription data and excerpts stay locally on the device as plain text. Google tokens use OS secure storage when available; otherwise they remain in memory until the app closes. Protect your device and exported files. No card data, analytics or third-party AI service is used.

Automated core and Linux desktop interaction tests passed. Live Google account access and Windows installation still require testing on your computer. The source archive contains the full code, build scripts, licence and detailed validation notes.
