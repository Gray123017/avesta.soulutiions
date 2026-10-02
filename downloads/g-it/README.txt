G-IT - WINDOWS x64 INSTALLER

STATUS: unsigned test build. Compiled on Linux; not executed on Windows here.
Windows installation, pairing, reboot reporting, offline recovery and removal
must pass the included validation checklist before wider deployment.
Designed for Windows 10/11 Intel/AMD x64 PCs. ARM and 32-bit Windows are not
supported. Windows PowerShell 5.1 and administrator permission are required.
This is an EXE installer, not an MSI. No extra runtime is bundled or required.

INSTALL AND PAIR
1. Publish your G-IT server with HTTPS and obtain a one-time enrollment
   code from its dashboard. Use a test server/code for validation.
2. Double-click G-IT-Setup-x64.exe on a PC you are authorized to manage.
   Approve UAC, read the disclosure and check the authorization box.
   Cancel before installing if you do not approve. Silent /S install is refused.
3. The native setup window opens. Enter the HTTPS root URL (no /api or paths)
   and enrollment code. Read the collection notice, authorize reporting and
   choose Install and connect. Enrollment sends the initial health snapshot.
4. Reopen the window from Start > G-IT > G-IT Setup.

VISIBILITY AND SECURITY
Program files: C:\Program Files\FleetMonitor
Protected local state: C:\ProgramData\FleetMonitor
Task Scheduler entry: Fleet Monitor - Visible System Health Reporting
This task name is intentionally retained so existing installations remain
compatible; it is the G-IT reporting task.
The task is visible in Task Scheduler, runs as SYSTEM at boot and every five
minutes, including on battery. There are no hidden task or process flags.
Device credentials are encrypted using machine DPAPI; the local state directory
restricts access to administrators and SYSTEM. Credentials are not command-line
arguments and are not logged.
Latest upload status: C:\ProgramData\FleetMonitor\last-report.txt (admin access).
When offline, reports fail with a bounded status message and the next scheduled
run tries a fresh snapshot. No personal activity or offline history is queued.

COLLECTED
Computer hostname, OS version, manufacturer/model, BIOS and serial number,
processor, total memory, aggregate CPU/memory usage, system-drive free space,
disk-health status, default-route presence, Defender status and active threat
names. The server retains only the latest snapshot.
No malware scans, file deletion, disk repair, remote reboot or remote commands
are performed. Defender status does not guarantee all malware is detected.

NOT COLLECTED
Keystrokes, passwords, user identities, browsing history, file contents,
screenshots, application/process history or private user activity.

REMOVE LOCALLY
Use Windows Settings > Apps > Installed apps (Windows 11) or Apps & features
(Windows 10), select G-IT and Uninstall. The Start menu also includes
Uninstall G-IT. Confirm removal and approve administrator permission.
The Program Files and ProgramData folders and the scheduled-task identifier keep
their original FleetMonitor/Fleet Monitor names for backward compatibility with
existing installations. New Start Menu shortcuts are under G-IT. Removal cleans
the known G-IT and legacy Fleet Monitor shortcut files/folders.
Removal stops and unregisters the task, deletes local data and encrypted
credentials, and removes program files, shortcuts and the Windows uninstall
entry. If reporting cleanup fails, the installer remains available for retry.
The setup window's Uninstall this agent button stops reporting and removes
credentials only; use Windows Apps to remove the setup program too.
The server inventory record remains and becomes offline after 15 minutes.

FAILED PAIRING
Setup rolls back local reporting files and task registration if pairing fails.
If the server consumed a code before a local failure, its inventory record may
remain offline. Obtain a new code to retry. If cleanup failed, remove locally
first. Never reuse an installed PC's credential or another PC's enrollment code.

VALIDATION AND TRUST
See BUILD-AND-TEST.txt and source/tests for automated checks and manual tests.
SHA256SUMS.txt checks download integrity; it is NOT a publisher signature.
This build is NOT code-signed and may be blocked by Windows or your organization.
Do not disable antivirus, change system execution policy or bypass security
controls. Obtain organizational approval before using this unsigned package.
