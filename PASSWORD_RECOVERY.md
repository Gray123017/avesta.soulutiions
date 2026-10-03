# Avesta account recovery

Existing users sign in and open **Recovery email**, enter their current password and email, then verify the eight-digit code received there. A changed email becomes active only after verification; the previous verified email remains usable until then. New account and first-admin creation accept an optional email, which still requires verification.

**Forgotten password → Reset with an email code** accepts a registered email or username. It sends only to the account's verified email. Codes expire after ten minutes, allow five attempts, and work once. New codes supersede old ones. Requests are limited to five per account/purpose per hour and thirty per source IP per hour, with a one-minute resend interval. Successful resets invalidate earlier sessions and pending codes tied to the previous password.

The sender is `info@avesta.solutions`, using server-side PHP `mail()`. `AVESTA_RECOVERY_FROM` may override the sender with a valid configured address. An active mailbox and an available mail function do not prove inbox delivery: verify an email on the live server and check inbox/spam before relying on recovery. The signed-in verification page reports a sending failure; forgotten-password requests keep a neutral response so account existence is not disclosed. No passwords, SMTP credentials or cleartext codes are stored in recovery state or audit records.

**Accounts** remains restricted to administrators. It lists registration details, recovery emails and verification status, pending help requests, and recent email-verification/reset, sending-failure and administrator-reset events. An administrator verifies the person's identity before issuing a temporary password. The password is displayed once, removed after five minutes, and must be changed at next sign-in. A second authorized administrator can help an admin who cannot sign in; the system does not allow unauthenticated administrative resets.

If email is unavailable, **Request administrator help** retains the existing office-assisted process. WhatsApp links contact the office; automatic WhatsApp OTP delivery is not configured by this update.

Validation runs against isolated copies with a fake mail transport. They cover all three account roles, verification, code storage, expiry, reuse, guessing/resend limits, delivery failure, session invalidation, administrator fallback and actual HTTP forms. No live users or emails are created by the tests.
