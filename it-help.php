<?php
/**
 * AVESTA — it-help.php
 * The knowledge behind the IT Services assistant.
 *
 * Deliberately not a language model. Three reasons:
 *
 *   1. Every answer here was written by someone who knows what Avesta will
 *      actually do about the problem. A model guesses, and it guesses in
 *      Avesta's voice, to a customer who will hold Avesta to it.
 *   2. Nothing here can tell a worried person to format a disk, delete
 *      system files or run diskpart. That single class of mistake can cost a
 *      customer their business records — the exact thing they came to you to
 *      protect.
 *   3. It costs nothing per message and needs no outbound HTTPS, so it works
 *      on any host and cannot be run up as a bill by a bot.
 *
 * If you later want a model to handle the questions this does not match, see
 * IT_HELP_MODEL in api.php — the seam is already there, and this file stays
 * as the first line of response.
 *
 * Each entry:
 *   match    words that suggest this problem (lowercase, matched loosely)
 *   title    what we think is going on
 *   answer   what it usually is, in plain words
 *   try      safe steps, in order. Never destructive.
 *   stop     when to stop and call us — protects the customer and is honest
 *            about what needs someone on site
 *   service  which of our services this belongs to, for the enquiry form
 */

const IT_HELP = [

// ── Startup and power ───────────────────────────────────────────────────────
[
 'match'  => ['wont start','won\'t start','not starting','no power','dead','wont boot',
              'won\'t boot','not booting','black screen','nothing happens','no display'],
 'title'  => 'Computer will not start',
 'answer' => 'Most of the time this is power reaching the machine, not the machine itself. '
           . 'On a laptop it is often the charger or battery; on a desktop, the wall socket, '
           . 'the cable or the power supply.',
 'try'    => [
   'Check the wall socket with something else you know works — a phone charger will do.',
   'On a laptop, take the battery out if it comes out, hold the power button for 20 seconds, put it back and try on mains only.',
   'Look for any light at all on the machine or the charger brick. Tell us what you see — it narrows things a lot.',
   'If a desktop has lights and fans but no picture, try a different screen cable or screen before assuming the computer is faulty.',
 ],
 'stop'   => 'If you smell burning, see scorching, or the machine trips your power when plugged in, '
           . 'unplug it and stop. Do not switch it on again.',
 'service'=> 'Computer Repair & Maintenance',
],

// ── Slow ────────────────────────────────────────────────────────────────────
[
 'match'  => ['slow','sluggish','freezing','freezes','frozen','freeze','hanging','hangs','hang','stuck','takes long','lagging','lag'],
 'title'  => 'Computer has become slow',
 'answer' => 'Usually one of four things: the disk is nearly full, too much starts up with Windows, '
           . 'the machine is short of memory for what it is being asked to do, or it is overheating '
           . 'and slowing itself down on purpose.',
 'try'    => [
   'Check free space on the C: drive. Under about 10% free and Windows struggles.',
   'Restart properly — Shut down, not just closing the lid. Many machines run for weeks without a real restart.',
   'Open Task Manager (Ctrl+Shift+Esc). On the Processes tab, click the CPU, Memory and Disk column headings in turn — whatever sits at the top is what is slowing you down. Then check the Startup tab and turn off things you do not need at every boot.',
   'Feel whether it is hot and whether the fan is loud or silent. A blocked fan is a common cause, and cleaning it is cheap.',
 ],
 'stop'   => 'If it is also making a clicking or grinding noise, stop using it and back up what matters now. '
           . 'That sound often means the drive is failing.',
 'service'=> 'Computer Repair & Maintenance',
],

// ── Internet and Wi-Fi ──────────────────────────────────────────────────────
[
 'match'  => ['no internet','internet not working','wifi','wi-fi','network down','cant connect',
              'can\'t connect','no connection','disconnecting','drops','dropping','keeps dropping','disconnects','slow internet'],
 'title'  => 'No internet, or it keeps dropping',
 'answer' => 'Worth separating three things: your device, your router, and the line coming in. '
           . 'Finding which one is at fault takes two minutes and saves a call-out.',
 'try'    => [
   'Check whether another device on the same Wi-Fi also has the problem. If only one device is affected, it is that device, not the network.',
   'Turn the router off at the wall, wait thirty seconds, and turn it back on. Give it two or three minutes to settle.',
   'If your phone works on mobile data but not on the Wi-Fi, the line or router is the problem, not your phone.',
   'For a room where it keeps dropping, note how far it is from the router and what walls are in between — that usually explains it, and an access point fixes it.',
   'A full signal bar is not the same as a good connection. The bars only show you are near a router; a crowded channel, too many devices, or an overloaded router can drop you at full strength.',
   'Try moving the router somewhere central and higher up, away from metal and thick walls. Moving it often does more than replacing it.',
 ],
 'stop'   => 'If your provider confirms the line is fine and it still drops, the fix is usually router '
           . 'placement or an access point. That needs someone on site to do properly.',
 'service'=> 'Networking & Wi-Fi Setup',
],

// ── Printers ────────────────────────────────────────────────────────────────
[
 'match'  => ['printer','printing','wont print','won\'t print','not printing','offline',
              'printer offline','showing offline','shows offline','says offline','use printer offline',
              'printer not responding','printer error','printer in error state','error state',
              'scanner','scan'],
 'title'  => 'Printer will not print',
 'answer' => 'A printer that shows "offline" while it is switched on has lost communication with '
           . 'the computer — it is very rarely broken. On a network the usual cause is a changed '
           . 'address or a Wi-Fi drop; on USB, the cable. Windows also has a setting that holds the '
           . 'printer offline deliberately, and it can switch itself on.',
 'try'    => [
   'On Windows 11, open the Get Help app from Start and search for printer problems. It runs Microsoft\'s own automated checks, and fixes a good share of cases without you touching anything else.',
   'Turn the printer off at the wall for thirty seconds, then on again. This fixes a surprising share of cases.',
   'Check the cable is firmly in at both ends, or for a wireless printer, that it is on the same Wi-Fi as the computer — easy to miss after a router change. If your office has a Wi-Fi extender or more than one network name, the computer and the printer must be on the same one.',
   'If the printer itself shows an error, look at the printer before the computer: paper, ink or toner, an open cover, or a jam. Windows reports these as a printer error, not as what they are.',
   'Open Settings, then Bluetooth & devices, then Printers & scanners, and choose your printer. Select Open print queue, and cancel anything stuck (the ... menu, then Cancel all) — and resist clicking Print over and over, because each click adds another copy and buries the one you want. If the window has a Printer menu, make sure "Use Printer Offline" is not ticked; Windows can tick it on its own after a connection drops. Back in Printers & scanners, turn off "Let Windows manage my default printer", which makes whichever printer you used last the default, then choose yours and select Set as default.',
   'Print the printer\'s own test page from its buttons — many printers can also print a Wi-Fi test report from their menu. If that works, the printer is fine and the problem is on the computer.',
   'If jobs still will not move, restart the print service: search Start for Services, find Print Spooler in the list, right-click it and choose Restart. This only restarts it and deletes nothing. If Windows says you need permission, that is an administrator\'s job.',
 ],
 'stop'   => 'If it prints its own test page but nothing from the computer, it is a driver or network '
           . 'setting. Tell us the printer model and we will sort it, often without visiting. If Windows '
           . 'says the print spooler has stopped, call us rather than following online fixes for it — '
           . 'they involve stopping a Windows service and clearing files inside the Windows folder, '
           . 'which is easy to get wrong.',
 'service'=> 'Printer & Scanner Setup',
],

// ── Malware, ransomware, data at risk ───────────────────────────────────────
[
 'match'  => ['virus','malware','hacked','ransomware','encrypted','locked my files','pop ups',
              'popups','strange','suspicious','scam','money gone'],
 'title'  => 'Possible virus or attack',
 'answer' => 'If files have been encrypted or renamed and a payment is demanded, that is ransomware. '
           . 'What you do in the first hour matters more than anything else.',
 'try'    => [
   'Disconnect the machine from the network and Wi-Fi now. Do not plug in a flash drive or external disk.',
   'Do not pay anything and do not reply to any message demanding payment.',
   'Leave the machine on but disconnected, and do not delete anything — deleting can destroy the evidence and the chance of recovery.',
   'Write down what you saw and when. It helps more than people expect.',
 ],
 'stop'   => 'Call us straight away on 0769 974 200. Do not run cleanup tools you find online — '
           . 'several of them destroy any chance of getting the files back.',
 'service'=> 'Cybersecurity',
],

// ── Lost files ──────────────────────────────────────────────────────────────
[
 'match'  => ['lost files','deleted','recover','recovery','gone','missing files','formatted',
              'cant find my','can\'t find my','corrupt','corrupted','corruption'],
 'title'  => 'Files are missing or deleted',
 'answer' => 'Deleted usually does not mean gone. It means the space is marked reusable — so the '
           . 'single most important thing is to stop writing anything new to that disk.',
 'try'    => [
   'Stop using the machine for anything else. Every minute of normal use lowers the chance of getting the files back.',
   'Check the Recycle Bin, and search the file name across the whole C: drive before assuming the worst.',
   'If the files were on a flash drive or camera card, take it out and leave it out.',
   'Do not install recovery software onto the same drive — installing it can overwrite the very files you want.',
 ],
 'stop'   => 'If the files matter, bring the machine or the drive to us before trying anything else. '
           . 'Recovery odds drop sharply with use.',
 'service'=> 'Data Protection, Backup & Recovery',
],

// ── Passwords and accounts ──────────────────────────────────────────────────
[
 'match'  => ['password','locked out','forgot','cant login','can\'t login','cant log in',
              'can\'t log in','cannot log in','account locked','cannot sign in','cant sign in'],
 'title'  => 'Locked out of a computer or account',
 'answer' => 'What can be done depends on what kind of account it is. A Microsoft account can be '
           . 'reset by you; a local Windows account and a work account cannot be, in the same way.',
 'try'    => [
   'For a Microsoft account, use the official reset at account.microsoft.com from your phone.',
   'For work email, ask whoever administers it. If that is us, call and we will verify you and reset it.',
   'Check Caps Lock and the keyboard language — more lockouts come from this than anyone admits.',
   'Do not keep guessing. Many systems lock harder after repeated failures.',
 ],
 'stop'   => 'We will never ask for your password, and neither will Microsoft. Anyone who does is '
           . 'trying to take the account.',
 'service'=> 'IT Training & Consultancy',
],

// ── CCTV ────────────────────────────────────────────────────────────────────
[
 'match'  => ['cctv','camera','cameras','dvr','nvr','not recording','no footage','cant view',
              'can\'t view','remote view'],
 'title'  => 'CCTV not recording or not viewable',
 'answer' => 'Three usual causes: the recorder\'s disk is full or failing, the camera has lost power '
           . 'or its cable, or remote viewing has stopped because the internet address changed.',
 'try'    => [
   'Check the recorder is on and whether it shows any error on screen.',
   'See whether all cameras are affected or only one. One camera points to that camera or its cable; all of them points to the recorder.',
   'Check whether playback works locally even if remote viewing does not — that separates a recording fault from a network one.',
   'Note how far back footage goes. If it is much shorter than it used to be, the disk is probably failing.',
 ],
 'stop'   => 'If the footage is needed for an incident, stop and call us before anything else, so the '
           . 'recording is not overwritten. Recorders overwrite the oldest footage automatically.',
 'service'=> 'CCTV Installation & Maintenance',
],

// ── Backups ─────────────────────────────────────────────────────────────────
[
 'match'  => ['backup','back up','backups','restore','copy of my data','protect my data'],
 'title'  => 'Backups',
 'answer' => 'A backup nobody has ever restored from is not yet a backup. The useful test is simple: '
           . 'if this building burned tonight, what would you still have tomorrow morning?',
 'try'    => [
   'Write down what would actually hurt to lose. It is usually a much shorter list than people expect.',
   'Keep one copy somewhere other than the building. A drive beside the computer does not survive fire or theft.',
   'Try restoring one file. That is the only way to know a backup works.',
   'Check when it last ran. Backups fail quietly and can be dead for months.',
 ],
 'stop'   => 'We can set up a backup that runs on its own and gets checked, so you are not relying on '
           . 'remembering.',
 'service'=> 'Data Protection, Backup & Recovery',
],

// ── Windows / software ──────────────────────────────────────────────────────
[
 'match'  => ['windows','install','reinstall','activate','licence','license','microsoft office',
              'ms office','office 365','office365',
              'excel','microsoft word','ms word','word document','update','updates',
              'blue screen','bsod','error code'],
 'title'  => 'Windows or software problem',
 'answer' => 'Software problems usually come with an error message, and that message is most of the '
           . 'answer. A blue screen in particular names the fault if you note the code.',
 'try'    => [
   'Write down the exact error, or photograph the screen. "It says an error" is hard to work with; the code is not.',
   'Note what you were doing when it happened, and whether it happens every time or now and then.',
   'Restart properly and see whether it recurs.',
   'If it started right after an update or a new program, say so — that is usually the cause.',
 ],
 'stop'   => 'Send us the error text or a photo. Most of these are diagnosed without touching the machine.',
 'service'=> 'Windows & Software Installation',
],

// ── Website ─────────────────────────────────────────────────────────────────
[
 'match'  => ['website','web site','site down','domain','hosting','email domain','ssl','https'],
 'title'  => 'Website or domain',
 'answer' => 'A site that has stopped working is usually hosting, an expired domain, or an expired '
           . 'certificate. Each looks different in the browser.',
 'try'    => [
   'Note exactly what the browser says — "not secure", "cannot be reached" and "404" mean quite different things.',
   'Check whether it fails on mobile data as well as on Wi-Fi.',
   'Check whether the domain is still paid up. An expired domain takes a site down completely.',
 ],
 'stop'   => 'Tell us the address and what you see. We can check hosting, domain and certificate from here.',
 'service'=> 'Website Design & Development',
],

// ════════════════════════════════════════════════════════════════════════════
// Added: faults and consultation questions.
//
// Topics were chosen by reviewing common small-office troubleshooting
// guidance; every answer below is written fresh for Avesta, in Avesta's
// voice, and fitted to Zambian conditions. No third-party text is reproduced.
// The same rule as above holds: nothing here tells anyone to do something
// that could cost them their data.
// ════════════════════════════════════════════════════════════════════════════

// ── Overheating and shutting down ───────────────────────────────────────────
[
 'match'  => ['shuts down','shutting down','shut down by itself','shuts off','shutting off',
              'turns off','turning off','switches off','switching off','restarts by itself',
              '=keeps restarting','overheating','overheat','too hot','very hot','fan loud',
              'fan noise','loud fan','burning smell'],
 'title'  => 'Computer shuts down by itself or runs hot',
 'answer' => 'A machine that switches itself off without warning is usually protecting itself '
           . 'from heat. Dust blocks the fan and vents, the processor gets too hot, and the '
           . 'computer cuts out before damage is done. The other common cause is a failing '
           . 'power supply.',
 'try'    => [
   'Feel the vents while it runs. Very hot air, or no air at all, points to blocked cooling.',
   'Keep laptops on a hard, flat surface. A bed or cushion blocks the vents underneath.',
   'Note whether it shuts down under load — a big spreadsheet, a video — or at random. Under load points to heat; at random points to power.',
   'Keep it out of direct sun and away from a window in the afternoon.',
 ],
 'stop'   => 'Do not open a laptop to clean it yourself — fan cleaning is quick for us and easy '
           . 'to get wrong. If you smell burning, unplug it and stop using it.',
 'service'=> 'Computer Repair & Maintenance',
],

// ── Keyboard, mouse, screen, USB ────────────────────────────────────────────
[
 'match'  => ['keyboard','mouse','usb','flash drive not showing','not detected','not recognised',
              'not recognized','monitor','no signal','second screen','hdmi','webcam','headset',
              'speakers','no sound','external hard drive','external drive','external disk',
              'hard drive not showing','drive not showing','projector','extend display',
              'duplicate display','two screens','dual monitor'],
 'title'  => 'Keyboard, mouse, screen or USB device not working',
 'answer' => 'When a device stops working the fault is far more often the cable, the port or '
           . 'the driver than the device itself — and all three are quick to rule out.',
 'try'    => [
   'Unplug it and plug it into a different port. Ports fail more often than the things plugged into them.',
   'Try the device on another computer. If it works there, the problem is with the first machine.',
   'For a screen showing "no signal", check the cable is pushed fully home at both ends and the screen is set to the right input.',
   'For wireless keyboards and mice, change the batteries first — it is the most common cause by a wide margin.',
   'Restart the computer after reconnecting, so Windows looks for the device afresh.',
 ],
 'stop'   => 'If Windows says a drive "needs to be formatted before you can use it", click '
           . 'Cancel. Do not click Format — it erases everything on the drive, and that message '
           . 'often appears when the drive is fine and only its index is damaged. Bring it to us. '
           . 'The same goes for any flash drive or external disk holding files that matter: '
           . 'stop trying it in different machines, because repeated attempts make recovery harder.',
 'service'=> 'Computer Repair & Maintenance',
],

// ── Connected but websites fail ─────────────────────────────────────────────
[
 'match'  => ['connected but no internet','some websites','website not loading','sites not loading',
              'cannot open the webpage','cannot open webpage','cant open webpage','cant open website',
              'cannot open website','webpage not opening','page not opening','page wont open',
              'page won\'t open','webpage wont load','cannot open the page','browser not loading',
              'page cannot be displayed','dns','ip conflict','ip address conflict',
              'limited connectivity','no internet access'],
 'title'  => 'Connected, but some or all websites will not load',
 'answer' => 'If the Wi-Fi shows connected but pages will not open, the connection to the router '
           . 'is fine and the problem is further along — often the router has lost its link to '
           . 'your provider, or two devices have ended up with the same address on the network.',
 'try'    => [
   'Check whether other devices on the same network can open websites. If none can, it is the router or the line.',
   'Restart the router: off at the wall, wait thirty seconds, on again, and give it three minutes.',
   'If only some websites fail, try the same site on mobile data. If it opens there, the problem is on your network, not the website.',
   'On the one affected computer, turn Wi-Fi off and on again, so it asks the router for a fresh address.',
   'Check you are on the network you think you are. It is easy to be on a guest network, a neighbour\'s, or your phone\'s hotspot, and some sites only open from the office network.',
   'If you use a VPN, disconnect it and try again — a VPN that is misbehaving can block sites while showing connected.',
   'Open the same page in a private window (Ctrl+Shift+N in Chrome and Edge). If it loads there, a browser extension such as an ad or pop-up blocker is stopping it — turn extensions off one at a time to find which.',
   'Try the page in a private window (Ctrl+Shift+N in Chrome or Edge). Private windows switch off most browser add-ons, so if the page opens there, an add-on is blocking it.',
   'If a site needs a pop-up to work, allow pop-ups for that one site from the icon in the address bar, rather than switching the pop-up blocker off for everything.',
 ],
 'stop'   => 'Two things not to do. Do not switch off your antivirus to reach a website — a site '
           . 'or download that asks you to is a warning sign, and it is a favourite line of scammers. '
           . 'And in an office, do not change the network adapter or DNS settings: they may have '
           . 'been set deliberately, and changing them can cut the computer off from the server '
           . 'and shared drives. If it keeps happening, the network needs looking at properly — '
           . 'a quick job for us.',
 'service'=> 'Networking & Wi-Fi Setup',
],

// ── Power cuts ──────────────────────────────────────────────────────────────
[
 'match'  => ['power cut','power outage','load shedding','loadshedding','load-shedding','zesco',
              'surge','power surge','lightning','ups','inverter','power keeps going',
              'electricity','after the power'],
 'title'  => 'Power cuts and protecting equipment',
 'answer' => 'Power going off mid-use, and surging back when it returns, is one of the commonest '
           . 'causes of dead power supplies and corrupted files in Zambia. The surge on '
           . 'restoration does more harm than the cut itself.',
 'try'    => [
   'Save your work often during load-shedding hours. Windows can lose whatever was open when power drops.',
   'When power goes off, switch equipment off at the wall. Turn it back on a minute or two after power returns, once it has settled.',
   'Plug computers and routers into a surge protector, not straight into the wall. A multiplug is not a surge protector unless it says so.',
   'For a desktop, a server or a CCTV recorder, a UPS gives enough time to shut down properly instead of losing power mid-write.',
 ],
 'stop'   => 'If a machine will not start after a power cut, do not keep switching it on and off. '
           . 'A damaged power supply can take other parts with it. Call us.',
 'service'=> 'Computer Repair & Maintenance',
],

// ── Consultation: upgrade or replace ────────────────────────────────────────
[
 'match'  => ['upgrade','=should i buy','buy a new','new computer','new laptop','=which laptop',
              'buying a laptop','buying a computer','replace my computer','ssd',
              'hard drive or ssd','more ram','add ram','memory upgrade','worth repairing',
              'worth fixing','old computer'],
 'title'  => 'Should I upgrade, repair or replace?',
 'answer' => 'For most office computers a few years old, the single biggest improvement is '
           . 'replacing the old spinning hard drive with a solid-state drive. It often makes a '
           . 'machine feel new for a fraction of the price of one. More memory helps next, if '
           . 'you work with many programs or browser tabs at once.',
 'try'    => [
   'Check what drive it has: open Task Manager (Ctrl+Shift+Esc), choose Performance, and look under Disk — it says SSD or HDD.',
   'Check its memory in Settings, then System, then About. Under 8 GB is tight for modern office work.',
   'Note its age. A solid-state drive rescues a slow machine; it cannot rescue one that is failing for other reasons.',
   'Write down what you actually use it for. The right answer depends far more on that than on the specifications.',
 ],
 'stop'   => 'Tell us the model and what you use it for, and we will tell you honestly whether an '
           . 'upgrade is worth it or whether the money is better put towards a replacement. '
           . 'We move your files and programs across either way.',
 'service'=> 'Computer Repair & Maintenance',
],

// ── Consultation: securing the network ──────────────────────────────────────
[
 'match'  => ['secure my','securing','secure the network','secure my wifi','wifi password',
              'router password','guest wifi','guest network','=who is on my wifi','protect my network',
              'network security','someone using my wifi','using my wifi','=on my wifi',
              'stealing my wifi','stealing wifi','hack my wifi','hacked my wifi'],
 'title'  => 'Securing an office or home network',
 'answer' => 'Most small networks are exposed by a handful of defaults that were never changed. '
           . 'Closing those takes an afternoon and removes most of the everyday risk.',
 'try'    => [
   'Change the router\'s admin password from the one printed on its sticker. Anyone who reads the sticker can change your settings.',
   'Use the strongest Wi-Fi security the router offers — WPA3, or WPA2 if that is the best available — and a long Wi-Fi password.',
   'Put visitors on a separate guest network, so their phones never share a network with your office machines.',
   'Turn on two-step sign-in for email and any account that handles money.',
   'Keep the router\'s own software up to date — it gets security fixes like anything else. But never start a router update during load-shedding hours or on unsteady power: if the power cuts mid-update, the router can be left permanently unusable. If in doubt, have us do it.',
 ],
 'stop'   => 'If you handle client records, personal data or money, the basics are a start rather '
           . 'than enough. We can set up proper separation and monitoring.',
 'service'=> 'Cybersecurity',
],

// ── Consultation: wired or wireless ─────────────────────────────────────────
[
 'match'  => ['wired or wireless','cable or wifi','cable or wi-fi','ethernet','network cable',
              'should i use wifi','lan cable','wifi or cable'],
 'title'  => 'Wired or Wi-Fi for my office?',
 'answer' => 'Usually both. Cable is faster, steadier and harder to intrude on; Wi-Fi is '
           . 'convenient where people move around. The sensible split is by what the device does.',
 'try'    => [
   'Cable anything that stays put and matters: desktops, printers, the CCTV recorder, and any server.',
   'Use Wi-Fi for laptops, phones, visitors and meeting rooms.',
   'If a room has poor Wi-Fi, an access point placed there beats turning the router up — a stronger signal from one spot rarely reaches round corners.',
   'Keep network cables away from mains power cables where you can, and do not staple through them.',
 ],
 'stop'   => 'Cabling an office properly is worth doing once. We can survey the space and '
           . 'recommend the fewest cable runs and access points that will cover it.',
 'service'=> 'Networking & Wi-Fi Setup',
],

// ── Consultation: looking after equipment ───────────────────────────────────
[
 'match'  => ['maintenance','look after','looking after','take care of','=keep my computer',
              'regular service','servicing','prevent problems','=how often','clean my computer'],
 'title'  => 'Looking after your computers',
 'answer' => 'Most failures give warning, and a little routine care catches them early. The '
           . 'aim is not perfection — it is not being surprised.',
 'try'    => [
   'Let Windows and your programs install their updates. Most attacks rely on machines that never updated.',
   'Keep vents clear and the machine off soft surfaces. In a dusty season, have the fans cleaned once or twice a year.',
   'Watch for early warnings: new noises from the drive, sudden slowness, or programs crashing that never used to.',
   'Back up what matters, and keep one copy away from the building.',
   'Retire machines that no longer get security updates. They are the easiest way in for an attacker.',
 ],
 'stop'   => 'We offer regular servicing for offices, so this happens on a schedule rather than '
           . 'when someone remembers.',
 'service'=> 'IT Training & Consultancy',
],

// ════════════════════════════════════════════════════════════════════════════
// Second batch: topics drawn from common help-desk call lists, answers written
// for Avesta. Several widely published "solutions" were deliberately overruled:
//
//   · An external drive Windows cannot read is NOT to be formatted. Windows
//     invites exactly that, and it destroys what is often someone's only backup.
//   · Low disk space is cleared with Windows' own tool, not by people choosing
//     files to delete — they delete the wrong ones.
//   · Scheduled defragmentation is outdated advice and wears solid-state drives
//     for nothing; Windows already handles it.
//   · A suspected infection is not something to fix by running tools found
//     online — see the virus entry above.
// ════════════════════════════════════════════════════════════════════════════

// ── Phishing ────────────────────────────────────────────────────────────────
[
 'match'  => ['phishing','suspicious email','strange email','fake email','scam email',
              'clicked a link','clicked the link','opened an attachment','email asking',
              'asking for my password','urgent email','email from the bank',
              'bank email','verify my account'],
 'title'  => 'A suspicious email or message',
 'answer' => 'Emails that create urgency — your account will close, a payment failed, verify '
           . 'now — are the commonest way businesses are broken into. They rarely look '
           . 'suspicious at first glance; that is the point of them.',
 'try'    => [
   'Do not click the link or open the attachment. If you already have, that is fine — it happens to everyone — but tell us now rather than later.',
   'Check the actual sender address, not the name shown. A bank does not write from a free email address.',
   'If it claims to be from your bank, Airtel, MTN or a supplier, contact them using a number you already have — never one in the message.',
   'If you typed a password after clicking, change that password straight away from a different device.',
 ],
 'stop'   => 'If you clicked and then entered a password or payment details, call us immediately. '
           . 'The first hour matters, and there is no embarrassment in it.',
 'service'=> 'Cybersecurity',
],

// ── Unsaved work ────────────────────────────────────────────────────────────
[
 'match'  => ['did not save','didnt save','didn\'t save','not saved','unsaved','lost my work',
              'lost my document','closed without saving','word crashed','excel crashed',
              'document disappeared','forgot to save'],
 'title'  => 'Lost work that was not saved',
 'answer' => 'Office programs keep hidden recovery copies while you work, so work that was never '
           . 'saved is often still there. The sooner you look, the better the odds.',
 'try'    => [
   'Reopen the same program. It often offers the recovered file on the left as soon as it starts.',
   'In Word or Excel, go to File, then Info, then Manage Document, then Recover Unsaved Documents.',
   'Do not start lots of new work in that program first. Recovery copies can be replaced.',
   'For the future, save to OneDrive or turn on AutoSave, so this cannot happen the same way twice.',
 ],
 'stop'   => 'If recovery shows nothing and the document matters, stop and call us before using '
           . 'the computer much more.',
 'service'=> 'Data Protection, Backup & Recovery',
],

// ── Disk full ───────────────────────────────────────────────────────────────
[
 'match'  => ['disk full','disk is full','low disk space','no space','out of space','storage full',
              'c drive full','c: drive full','drive is full','running out of space','not enough space'],
 'title'  => 'Disk full or low on space',
 'answer' => 'A nearly full drive slows everything down and stops updates installing. Windows '
           . 'has its own tool that clears space safely, which is far better than choosing '
           . 'files to delete by hand.',
 'try'    => [
   'Search Windows for "Disk Cleanup", pick the C: drive, and let it remove temporary files. It will not touch your documents.',
   'Empty the Recycle Bin — but check it first for anything you still need.',
   'Look in your Downloads folder. It is usually the largest thing people have forgotten about.',
   'Move old photos and videos to an external drive or cloud storage rather than deleting them.',
 ],
 'stop'   => 'Please do not delete files from Windows, Program Files or anything you do not '
           . 'recognise. If space is still short after the steps above, we can find what is '
           . 'using it safely.',
 'service'=> 'Computer Repair & Maintenance',
],

// ── Programs ────────────────────────────────────────────────────────────────
[
 'match'  => ['program wont open','program won\'t open','app wont open','app won\'t open',
              'not opening','keeps crashing','crashes','crashing','stopped working',
              'not responding','wont install','won\'t install','cannot install','cant install',
              'install failed'],
 'title'  => 'A program will not open, crashes or will not install',
 'answer' => 'Usually the program is out of date, is clashing with something else, or needs '
           . 'more than the computer has. The error message, if there is one, tells us which.',
 'try'    => [
   'Restart the computer properly, then try the program before opening anything else.',
   'Check for an update to the program itself — many crashes are fixed in a later version.',
   'Note exactly when it fails: on opening, on a particular action, or at random.',
   'If it will not install, check it is meant for your version of Windows, and that there is space on the drive.',
 ],
 'stop'   => 'Send us the exact error message or a photo of it. For accounting or business '
           . 'software holding your records, do not uninstall it to try again without talking '
           . 'to us first.',
 'service'=> 'Windows & Software Installation',
],

// ── Windows updates ─────────────────────────────────────────────────────────
[
 'match'  => ['update failed','updates failed','update failing','updates failing','update stuck',
              'stuck updating','updating for hours','update keeps failing','cannot update',
              'wont update','won\'t update','update error','working on updates'],
 'title'  => 'Windows updates failing or stuck',
 'answer' => 'Updates most often fail from a full drive or a connection that dropped mid-download. '
           . 'A screen saying it is working on updates can genuinely take a long time on an older '
           . 'machine.',
 'try'    => [
   'If it is on a "working on updates" screen, leave it — even for an hour or two. Switching off during an update is how machines end up unable to start.',
   'Check there is free space on the C: drive; updates need room to unpack.',
   'Make sure the connection is steady. Large updates over a weak or metered connection often fail part-way.',
   'Restart normally and let Windows try again. It frequently succeeds second time.',
 ],
 'stop'   => 'If it has sat unchanged on an update screen for several hours, or restarts into the '
           . 'same failure again and again, call us rather than switching it off repeatedly.',
 'service'=> 'Windows & Software Installation',
],

// ── Lost or stolen device ───────────────────────────────────────────────────
[
 'match'  => ['stolen','lost my phone','lost my laptop','phone stolen','laptop stolen',
              'lost phone','lost laptop','left my laptop','cannot find my phone','device stolen'],
 'title'  => 'Lost or stolen phone or laptop',
 'answer' => 'The device can be replaced. What matters in the first hours is what someone else '
           . 'can now reach from it — email, banking, and anything that sends login codes to it.',
 'try'    => [
   'Use Find My Device (Android) or Find My (Apple) from another device to locate it, lock it, and erase it remotely if you will not get it back.',
   'Change your email password first, then banking and anything else that was signed in.',
   'Call Airtel, MTN or Zamtel to block the SIM, so nobody can receive your login codes.',
   'Report it to the police and keep the reference — you will need it for insurance.',
 ],
 'stop'   => 'If it held work email or client records, tell us straight away. We can sign it out '
           . 'of company accounts remotely.',
 'service'=> 'Cybersecurity',
],

// ── Printing wrongly ────────────────────────────────────────────────────────
[
 'match'  => ['paper jam','paper jammed','jammed','no colour','no color','colour','color',
              'printing colour','printing color','not printing colour',
              'not printing color','black and white only','wrong size','wrong paper size',
              'print queue','stuck in the queue','prints blank','blank pages','printing faded',
              'streaks'],
 'title'  => 'Printing, but printing wrongly',
 'answer' => 'When a printer works but the result is wrong, the cause is nearly always a setting '
           . 'or the consumables — ink, toner or paper — rather than a fault.',
 'try'    => [
   'For a jam, switch the printer off first, then pull the paper out slowly in the direction it normally travels. Tearing it leaves pieces inside.',
   'For no colour, check the print settings are not set to greyscale, and check the colour ink or toner level.',
   'For the wrong size, make sure the paper size chosen when printing matches what is actually in the tray.',
   'For jobs stuck in the queue, open the print queue, cancel them all, and send the document again.',
 ],
 'stop'   => 'If paper tears inside and you cannot see the pieces, stop there — pulling at hidden '
           . 'paper can damage the rollers.',
 'service'=> 'Printer & Scanner Setup',
],

// ── Shared folders ──────────────────────────────────────────────────────────
[
 'match'  => ['shared folder','shared drive','network drive','mapped drive','cannot access the server',
              'cant access the server','server not accessible','access denied','shared printer',
              'cannot see the other computer','network share'],
 'title'  => 'Cannot open a shared folder or network drive',
 'answer' => 'Shared folders depend on three things lining up: the computer holding the files is '
           . 'on, both machines are on the same network, and your account is allowed in.',
 'try'    => [
   'Check the computer or server holding the files is switched on and awake.',
   'Check your computer is on the office network, not a phone hotspot or guest Wi-Fi.',
   'Restart your computer and sign in again — the connection is often re-made at sign-in.',
   'If it says access is denied, the folder is reachable but your account is not permitted. That needs whoever manages the shares.',
 ],
 'stop'   => 'If it affects everyone in the office at once, the problem is the server or the '
           . 'network rather than any one computer. Call us.',
 'service'=> 'Networking & Wi-Fi Setup',
],

// ════════════════════════════════════════════════════════════════════════════
// Third batch. Two widely given tips deliberately left out:
//   · Clearing the Windows Update cache — a technician's job, not a chatbot's.
//   · Recreating the Outlook profile — with older POP mail setups the new
//     profile can make years of mail appear to vanish. It is usually still
//     there, but the panic that follows makes things worse.
// ════════════════════════════════════════════════════════════════════════════

// ── Email ───────────────────────────────────────────────────────────────────
[
 'match'  => ['email','emails','e-mail','outlook','not receiving emails','not sending emails',
              'email not sending','email not working','emails not coming','cannot send email',
              'cant send email','wont send','will not send','not sending','outlook not syncing','email not syncing','mail not syncing',
              'emails not syncing','mailbox full','inbox full',
              'stuck in outbox','outbox'],
 'title'  => 'Email not sending, receiving or syncing',
 'answer' => 'Email failures are rarely the mail server. Most come from a changed or expired '
           . 'password, a full mailbox, or a connection that dropped — all quick to rule out.',
 'try'    => [
   'Check you can open a website. If not, it is the connection, not the email.',
   'Sign in to the same mailbox on your phone or in a web browser. If mail is there, the account is fine and the problem is the app on this computer.',
   'If you changed your password recently, Outlook may still be using the old one. Close it fully and reopen it; it should ask again.',
   'Check whether the mailbox is full — a full mailbox stops new mail arriving without much warning. Empty Deleted Items and move old mail with large attachments out.',
   'For a message stuck in the Outbox, check its attachments. Very large files are the usual reason a message refuses to leave.',
 ],
 'stop'   => 'Please do not remove the email account or the Outlook profile to "start again" '
           . 'without talking to us. On some setups your stored mail lives only on that computer, '
           . 'and removing the account can make it look as though it has all gone.',
 'service'=> 'IT Training & Consultancy',
],

// ── VPN ─────────────────────────────────────────────────────────────────────
[
 'match'  => ['vpn','vpn connected','vpn not working','vpn connected but no internet',
              'vpn wont connect','vpn won\'t connect','remote access','work from home connection'],
 'title'  => 'VPN connected, but nothing works',
 'answer' => 'When the VPN says connected but websites or work systems will not open, the tunnel '
           . 'is up but traffic is being sent the wrong way through it. That is a configuration '
           . 'matter rather than something you have done.',
 'try'    => [
   'Disconnect the VPN and check the internet works without it. If it does not, the problem is your connection, not the VPN.',
   'Reconnect once, cleanly. If it works for a while and then fails again, note roughly how long it lasts.',
   'Try a different connection — a phone hotspot is the quickest test. Some public and hotel Wi-Fi blocks VPNs.',
 ],
 'stop'   => 'If it fails again after one clean reconnect, the settings need looking at. Repeated '
           . 'reconnecting will not fix a routing problem — send us what you see.',
 'service'=> 'Networking & Wi-Fi Setup',
],

// ── Method ──────────────────────────────────────────────────────────────────
[
 'match'  => ['where do i start','how do i troubleshoot','how to troubleshoot','troubleshooting steps',
              'basic troubleshooting','general troubleshooting','first thing to check',
              'what should i check first','what to check first','something is wrong with my computer'],
 'title'  => 'Where to start with any problem',
 'answer' => 'Look before you click. When something breaks, the instinct is to restart it '
           . 'repeatedly or click things in a hurry, and that sometimes makes it worse. '
           . 'A minute spent noticing saves an hour of guessing.',
 'try'    => [
   'Ask what changed. A new program, an update, a moved cable or a power cut shortly before is very often the whole explanation.',
   'Ask whether it is one device or several. One device points to that device; several at once point to the network, the power or a shared service.',
   'Read the error message in full, and photograph it. It usually names the problem.',
   'Check the simple things before the complicated ones: power, cables, the internet connection.',
   'Restart once, properly. If it comes back, stop restarting — note what happens instead.',
 ],
 'stop'   => 'If you have been through these and it is still not clear, describe what you found '
           . 'and send it to us. Those notes make a fix much quicker.',
 'service'=> 'IT Training & Consultancy',
],

// ── Consultation: do we need IT support ─────────────────────────────────────
[
 'match'  => ['=do i need it support','=do we need it support','=should i get it support','=should we get it support',
              'support contract','it support contract','monthly support','retainer',
              '=managed it','managed services','keeps happening again','problems keep coming back',
              'same problem again','=how much does it support cost'],
 'title'  => 'Do we need regular IT support?',
 'answer' => 'Not every business does. But a few signs usually mean fixing things as they break '
           . 'is costing more than it looks — in lost hours rather than invoices.',
 'try'    => [
   'Problems come back every month or so, or a "fix" never quite holds.',
   'Someone in the office spends part of every week on IT instead of their own job.',
   'Security warnings, pop-ups or odd emails keep appearing.',
   'Nobody is sure whether backups are actually working.',
   'The whole business stops if one particular computer or the internet goes down.',
 ],
 'stop'   => 'If two or more of those sound familiar, a monthly arrangement is usually cheaper '
           . 'than emergency call-outs. We are happy to look at your setup and tell you honestly '
           . 'which suits you — including if you do not need us regularly.',
 'service'=> 'IT Training & Consultancy',
],

// ════════════════════════════════════════════════════════════════════════════
// Fourth batch, from a university exam helpdesk sheet. Most of that sheet was
// specific to its own campus (network names, DNS server addresses, an exam
// button) and does not apply here. Two of its suggestions were overruled:
//   · Disabling antivirus to reach a page — a classic scam instruction.
//   · Changing DNS in adapter settings — can cut an office machine off from
//     its server and shared drives.
// ════════════════════════════════════════════════════════════════════════════


// ── Wi-Fi switched off ──────────────────────────────────────────────────────
// Adapted from a university exam-day guide. Its campus DNS addresses, network
// names and "disable your antivirus" step are deliberately not carried over.
[
 'match'  => ['find a wireless network','find wireless network','find any wifi','find the wifi',
              'no wifi networks','no wireless networks','no networks available','no networks',
              'wifi not showing','wireless not showing','wifi option missing','wifi icon missing',
              'wifi disappeared','wifi turned off','wireless turned off','wifi is off',
              'airplane mode','aeroplane mode','flight mode','cannot see wifi','cant see wifi',
              'cannot see the wifi','no wifi option','wifi button',
              'cannot find wifi','cant find wifi','can\'t find wifi','cannot find wireless','cant find wireless','cannot find a wireless network','no networks showing','wifi networks not showing','wifi missing','wifi option gone','no wifi icon','cannot see any wifi','cant see any wifi','cannot see my wifi','cant see my wifi'],
 'title'  => 'Cannot see any Wi-Fi networks',
 'answer' => 'If the computer shows no networks at all while phones in the same room can see '
           . 'them, the computer\'s own Wi-Fi is almost always switched off — by a setting, a '
           . 'key or a switch. It is rarely broken.',
 'try'    => [
   'Check airplane mode is off. Click the network, sound and battery icons at the bottom right; airplane mode appears there, and it switches Wi-Fi off with it.',
   'In the same panel, check the Wi-Fi button itself is on.',
   'Many laptops have a key that turns wireless on and off — often marked with an aerial or a plane symbol, sometimes used with the Fn key. Older laptops may have a small physical switch on the side or front.',
   'Check the adapter itself is enabled: Settings, then Network & internet, then Advanced network settings. If Wi-Fi is listed as disabled, enable it.',
   'Restart the computer. If Wi-Fi was turned off in a way Windows did not register, this usually brings it back.',
   'Check a phone can see the network from the same spot. If the phone cannot either — or you can see other people\'s networks but not your own — the problem is the router, not this computer.',
 ],
 'stop'   => 'If there is still no Wi-Fi option at all after a restart — not just no networks, but '
           . 'no Wi-Fi setting anywhere — the wireless card may have been disabled or failed. '
           . 'That is quick for us to check.',
 'service'=> 'Networking & Wi-Fi Setup',
],

// ════════════════════════════════════════════════════════════════════════════
// Fifth batch, from Microsoft's own printer guidance. Held back from it:
//   · Clearing and resetting the print spooler — it means stopping a Windows
//     service and deleting files inside the Windows folder. The disk-space
//     answer tells people never to delete there; this must not contradict it.
// Added that Microsoft does not say: printer drivers come from the maker's own
// site only. Searching for drivers is a common route to fake download sites.
// ════════════════════════════════════════════════════════════════════════════

// ── Adding or reinstalling a printer ────────────────────────────────────────
[
 'match'  => ['add a printer','add printer','adding a printer','install a printer','install printer',
              'installing a printer','new printer','set up printer','set up a printer','setup printer',
              'printer setup','connect printer','connect a printer','connect the printer',
              'reinstall printer','reinstall the printer','remove printer','printer driver',
              'printer drivers','driver for my printer','printer not found','printer is not found',
              'printer is not recognised','printer is not recognized','printer is not detected',
              'printer is not showing','printer not recognised',
              'printer not recognized','printer not detected','cannot find printer','cant find printer',
              'printer not showing'],
 'title'  => 'Adding a printer, or reinstalling one',
 'answer' => 'Windows finds most printers by itself once they are switched on and connected. If a '
           . 'printer has been misbehaving, removing it and adding it again is safe — nothing '
           . 'you have printed or saved is lost — and it clears a lot of stubborn problems.',
 'try'    => [
   'Make sure the printer is on and connected: by cable, or on the same Wi-Fi as the computer. Many wireless printers have a button with a blue wireless light that must be on, and a menu option that tests the wireless connection.',
   'Open Settings, then Bluetooth & devices, then Printers & scanners, and choose Add device. Give Windows a minute to look, then pick your printer from the list.',
   'If it is not listed, choose Add manually and follow the option that fits how the printer is connected.',
   'To reinstall one that is misbehaving, select it in Printers & scanners, choose Remove, then Add device again.',
   'If it needs a driver, get it only from the printer maker\'s own website, for your exact model. Do not use "driver updater" programs or download sites from search adverts — they are a common way malware gets in.',
   'Print a test page to confirm it works.',
 ],
 'stop'   => 'If Windows still cannot find it, tell us the make and model and whether it connects by '
           . 'cable or Wi-Fi. Printer setup is often something we can finish remotely.',
 'service'=> 'Printer & Scanner Setup',
],

// ════════════════════════════════════════════════════════════════════════════
// Fifth batch: basic network commands, and the scam that abuses them.
//
// Only read-only commands are given to customers. ipconfig /release and /renew
// are left out on purpose: someone who runs release and never reaches renew is
// offline until a restart, and turning Wi-Fi off and on does the same safely.
// tracert, nslookup, netstat and arp are for technicians — and netstat in
// particular is the prop fake "Microsoft support" callers use to frighten
// people, so it gets a warning rather than an instruction.
// ════════════════════════════════════════════════════════════════════════════

// ── Network commands ────────────────────────────────────────────────────────
[
 'match'  => ['ipconfig','ping','tracert','nslookup','netstat','arp','getmac','hostname',
              'flushdns','flush dns','command prompt','cmd','cmd commands','network commands',
              'networking commands','test my connection','check my connection','test the connection',
              'my ip address','what is my ip','ip address','mac address','computer name',
              'default gateway','169.254'],
 'title'  => 'Checking your connection with a few simple commands',
 'answer' => 'A few commands tell you in seconds whether a problem is this computer, the router, '
           . 'or the internet itself. They only read information — none of them change anything. '
           . 'Open them by searching Start for "cmd" and choosing Command Prompt.',
 'try'    => [
   'Type  ping 8.8.8.8  and press Enter. Lines starting "Reply from" mean you are reaching the internet. "Request timed out" means you are not.',
   'Then type  ping google.com . If 8.8.8.8 replied but this says it "could not find host", the internet works and only the name lookup is failing.',
   'For a name-lookup failure, type  ipconfig /flushdns  and try the website again. It clears out old lookups and is completely safe.',
   'Type  ipconfig  and look for "IPv4 Address". If it starts with 169.254, the computer never got an address from the router — restart the router.',
   'In the same output, "Default Gateway" is your router. Type  ping  followed by that number: if it does not reply, the problem is between you and the router.',
   'If we ask which computer you are on, type  hostname  and tell us what it shows.',
 ],
 'stop'   => 'Commands such as tracert, nslookup, netstat and arp produce output that is hard to '
           . 'read — run them only if we ask, and send us a photo of the screen. And if anyone who '
           . 'called you out of the blue asks you to open Command Prompt, stop: that is a scam.',
 'service'=> 'Networking & Wi-Fi Setup',
],

// ── Tech-support scam ───────────────────────────────────────────────────────
[
 'match'  => ['microsoft called','call from microsoft','called from microsoft','someone called me',
              'tech support call','tech support scam','scam call','support scam',
              'said my computer is hacked','said my computer has a virus','said my computer was hacked',
              'anydesk','teamviewer','ultraviewer','asked for remote access','wanted remote access',
              'gave them remote access','gave remote access','asked me to install','asked me to open cmd',
              'told me to open cmd','told me to run','pop up says call','popup says call',
              'call this number','support number on screen'],
 'title'  => 'Someone called or messaged saying your computer has a problem',
 'answer' => 'Microsoft, your bank and your internet provider do not call or message out of the '
           . 'blue to fix your computer. A caller who says they have detected a virus or a hack is '
           . 'running a scam. The usual script is to have you open Command Prompt or Event Viewer, '
           . 'point at perfectly normal output as "proof", then get you to install a remote-access '
           . 'app such as AnyDesk so they can reach your bank or mobile money.',
 'try'    => [
   'Hang up, or stop replying. You do not need to be polite, and you do not need to argue.',
   'Do not install anything they ask for, and do not read out any code or PIN you are sent.',
   'A pop-up saying your computer is locked and to call a number is part of the same scam. Close the browser — if it will not close, restart the computer. Do not call the number.',
   'If you have already let them in, disconnect from the internet now, and do not sign in to banking or mobile money on that computer.',
   'From a different phone or computer, change your email and banking passwords, and tell your bank and your mobile money provider what happened.',
 ],
 'stop'   => 'If they had remote access, even briefly, call us straight away on 0769 974 200. We '
           . 'will check what was installed and remove it. There is no shame in it — these '
           . 'callers are practised, and they target careful people too.',
 'service'=> 'Cybersecurity',
],
];

/* ════════════════════════════════════════════════════════════════════════════
 * MATCHING
 *
 * What someone types goes through the same steps as every match phrase, so the
 * two meet on equal terms:
 *
 *   1. Normalise   lower-case; "can't" / "cant" -> "cannot"; "wi-fi" / "wi fi"
 *                  -> "wifi"; "pop-up" -> "popup"; and so on.
 *   2. Tokenise    split into words.
 *   3. Stem        "shutting", "shuts" and "shut" all become "shut", so each
 *                  verb form no longer has to be listed by hand.
 *   4. Correct     a word the assistant has never seen is compared with the
 *                  words it knows; "printr" becomes "printer". Corrections
 *                  count for less than exact words, and are reported back, so a
 *                  guess never passes itself off as certainty.
 *   5. Match       each phrase must appear in order, with up to two other
 *                  words in between — "someone IS using my wifi" still matches
 *                  "someone using my wifi".
 *
 * The best topic answers. Any other topic scoring close behind is offered as
 * "also relevant", so a question about two problems gets both. A vague
 * question that several topics fit equally gets a choice, not a guess.
 * ════════════════════════════════════════════════════════════════════════════ */

/** Words that carry no meaning of their own. They never add weight to a phrase. */
const IT_HELP_STOP = ['a','an','the','is','am','are','was','were','be','been','being','my','our',
  'your','his','her','its','it','this','that','these','those','to','of','on','in','at','for','with',
  'and','or','but','so','i','me','we','us','you','they','them','he','she','will','would','do','does',
  'did','have','has','had','can','could','should','just','really','very','please','help','hi',
  'hello','there','how','what','why','when','where','which','who','get','got','keep','keeps'];

function itHelpNormalise(string $t): string {
    $t = strtolower($t);
    $t = str_replace(["\u{2019}", "\u{2018}", '`', "\u{00B4}"], "'", $t);
    $contract = [
        "can't" => 'cannot', 'cant' => 'cannot', 'can not' => 'cannot',
        "won't" => 'will not', 'wont' => 'will not',
        "don't" => 'do not', 'dont' => 'do not', "didn't" => 'did not', 'didnt' => 'did not',
        "doesn't" => 'does not', 'doesnt' => 'does not', "isn't" => 'is not', 'isnt' => 'is not',
        "wasn't" => 'was not', 'wasnt' => 'was not', "couldn't" => 'could not', 'couldnt' => 'could not',
        "haven't" => 'have not', 'havent' => 'have not', "aren't" => 'are not', 'arent' => 'are not',
        "i'm" => 'i am', "it's" => 'it is',
    ];
    foreach ($contract as $from => $to) {
        $t = preg_replace('/(?<![a-z])' . preg_quote($from, '/') . '(?![a-z])/', $to, $t);
    }
    $compound = [
        '/\bwi[\s\-]?fi\b/'           => 'wifi',
        '/\be[\s\-]mail/'             => 'email',
        '/\blog[\s\-]in\b/'           => 'login',
        '/\bsign[\s\-]in\b/'          => 'signin',
        '/\bset[\s\-]up\b/'           => 'setup',
        '/\bback[\s\-]ups?\b/'        => 'backup',
        '/\bpop[\s\-]?ups?\b/'        => 'popup',
        '/\bload[\s\-]?shedding\b/'   => 'loadshedding',
        '/\bshutdown\b/'              => 'shut down',
        '/\bpc\b/'                    => 'computer',
        '/\bmodem\b/'                 => 'router',
        '/\b(smart|cell|mobile)\s?phone\b/' => 'phone',
    ];
    foreach ($compound as $re => $to) $t = preg_replace($re, $to, $t);
    return $t;
}

function itHelpStem(string $w): string {
    if (strlen($w) <= 3 || ctype_digit($w)) return $w;
    $undouble = function ($x) {
        $n = strlen($x);
        if ($n >= 3 && $x[$n-1] === $x[$n-2] && strpos('lsz', $x[$n-1]) === false
            && strpos('aeiou', $x[$n-1]) === false) return substr($x, 0, -1);
        return $x;
    };
    if (strlen($w) >= 6 && substr($w, -3) === 'ing')      $w = $undouble(substr($w, 0, -3));
    elseif (strlen($w) >= 5 && substr($w, -3) === 'ied')  $w = substr($w, 0, -3) . 'y';
    elseif (strlen($w) >= 5 && substr($w, -3) === 'ies')  $w = substr($w, 0, -3) . 'y';
    elseif (strlen($w) >= 5 && substr($w, -2) === 'ed')   $w = $undouble(substr($w, 0, -2));
    elseif (strlen($w) >= 5 && substr($w, -2) === 'es')   $w = substr($w, 0, -2);
    elseif (substr($w, -1) === 's' && !preg_match('/(ss|us|is)$/', $w)) $w = substr($w, 0, -1);
    if (strlen($w) >= 4 && substr($w, -1) === 'e') $w = substr($w, 0, -1);
    return $w;
}

function itHelpTokens(string $text): array {
    $parts = preg_split('/[^a-z0-9]+/', itHelpNormalise($text), -1, PREG_SPLIT_NO_EMPTY);
    return array_map('itHelpStem', $parts);
}

/** Every phrase, tokenised once. */
function itHelpIndex(): array {
    static $idx = null;
    if ($idx !== null) return $idx;
    $idx = ['phrases' => [], 'vocab' => []];
    foreach (IT_HELP as $i => $e) {
        $seen = [];
        foreach ($e['match'] as $phrase) {
            // A phrase starting "=" is matched word for word, filler included,
            // for the rare case where the small words ARE the meaning:
            // "who is on my wifi" must not shrink to plain "wifi".
            $exact = $phrase !== '' && $phrase[0] === '=';
            $tok = itHelpTokens($exact ? substr($phrase, 1) : $phrase);
            if (!$tok) continue;
            // "email", "emails" and "e-mail" all normalise to the same word.
            // Counting each would score one mention three times over.
            // ...and "disk full" / "disk is full" are one phrase once matching
            // tolerates the extra word, so filler is ignored in the key.
            $key = $exact ? '=' . implode(' ', $tok)
                          : (implode(' ', array_values(array_diff($tok, IT_HELP_STOP))) ?: implode(' ', $tok));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            // Match on the words that carry meaning; filler in between is
            // allowed. Dedupe and matching must agree on this, or a phrase kept
            // as "do I need..." fails to match "do WE need...".
            $core = $exact ? $tok : (array_values(array_diff($tok, IT_HELP_STOP)) ?: $tok);
            $idx['phrases'][$i][] = ['text' => ltrim($phrase, '='), 'tok' => $core, 'exact' => $exact,
                                     'weight' => 1 + 2 * (count($core) - 1)];
            foreach ($tok as $w) $idx['vocab'][$w] = true;
        }
    }
    return $idx;
}

/**
 * A word the assistant does not know, close to exactly one word it does.
 * Same first letter and a small edit distance, and a unique nearest match —
 * two equally close candidates means we cannot tell, so no correction.
 */
function itHelpCorrect(array $tokens, array &$fixes): array {
    $vocab = itHelpIndex()['vocab'];
    $out = [];
    foreach ($tokens as $i => $w) {
        if (isset($vocab[$w]) || strlen($w) < 4 || ctype_digit($w) || in_array($w, IT_HELP_STOP, true)) {
            $out[] = $w; continue;
        }
        $max = strlen($w) >= 8 ? 2 : 1;
        $best = null; $bestD = 99; $tie = false; $cands = [];
        foreach ($vocab as $v => $_) {
            // PHP turns numeric keys such as "169" into integers
            $v = (string) $v;
            if ($v[0] !== $w[0] || abs(strlen($v) - strlen($w)) > $max || strlen($v) < 4) continue;
            $d = levenshtein($w, $v);
            if ($d > $max) continue;
            if ($d < $bestD) { $best = $v; $bestD = $d; $tie = false; $cands = [$v]; }
            elseif ($d === $bestD && $v !== $best) { $tie = true; $cands[] = $v; }
        }
        // A dropped letter is the commonest phone typo ("printr"), so when two
        // words are equally close, a single candidate LONGER than what was
        // typed wins. If there is no such single word, we still do not guess.
        if ($tie) {
            $longer = array_values(array_filter($cands, fn($c) => strlen($c) > strlen($w)));
            if (count($longer) === 1) { $best = $longer[0]; $tie = false; }
        }
        if ($best !== null && !$tie) { $out[] = $best; $fixes[$i] = $best; }
        else $out[] = $w;
    }
    return $out;
}

/** Phrase words in order, allowing up to three other words between each. */
function itHelpFind(array $q, array $p, int $gap = 3): ?array {
    $n = count($q); $k = count($p);
    for ($s = 0; $s < $n; $s++) {
        if ($q[$s] !== $p[0]) continue;
        $used = [$s]; $pos = $s; $ok = true;
        for ($j = 1; $j < $k; $j++) {
            $found = false;
            for ($x = $pos + 1; $x <= min($n - 1, $pos + 1 + $gap); $x++) {
                if ($q[$x] === $p[$j]) { $used[] = $x; $pos = $x; $found = true; break; }
            }
            if (!$found) { $ok = false; break; }
        }
        if ($ok) return $used;
    }
    return null;
}

/** Scores every topic. */
function itHelpRank(string $question, array &$fixes = []): array {
    $fixes = [];
    $raw = itHelpTokens($question);
    if (!$raw || strlen(trim($question)) < 3) return [];
    $q = itHelpCorrect($raw, $fixes);
    $idx = itHelpIndex();
    $ranked = [];
    foreach (IT_HELP as $i => $e) {
        $score = 0.0; $hits = [];
        foreach ($idx['phrases'][$i] ?? [] as $ph) {
            $used = itHelpFind($q, $ph['tok'], $ph['exact'] ? 0 : 3);
            if ($used === null) continue;
            $w = $ph['weight'];
            $guessed = count(array_intersect($used, array_keys($fixes))) > 0;
            if ($guessed) $w *= count($ph['tok']) === 1 ? 0.5 : 0.75;
            $score += $w;
            $hits[] = ['text' => $ph['text'], 'weight' => $w, 'used' => $used];
        }
        if ($score > 0) $ranked[] = ['i' => $i, 'score' => $score, 'hits' => $hits];
    }
    // Highest first; equal scores keep topic order, which is stable and testable
    usort($ranked, function ($a, $b) {
        return $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i'];
    });
    return $ranked;
}

/** Best topic, or null. Kept for callers that only need the one answer. */
function itHelpMatch(string $question): ?array {
    $r = itHelpRank($question);
    return ($r && $r[0]['score'] >= 1) ? IT_HELP[$r[0]['i']] : null;
}

/** The full reading of a question: answer, what it picked up, and alternatives. */
function itHelpAnswer(string $question): array {
    $fixes = [];
    $r = itHelpRank($question, $fixes);
    $plain = preg_split('/[^a-z0-9]+/', itHelpNormalise($question), -1, PREG_SPLIT_NO_EMPTY);

    // Someone asking the IT assistant for money is in the wrong office, not
    // asking a question nobody can answer. Point them to it.
    $lend = count(array_intersect(array_map('itHelpStem', $plain),
        array_map('itHelpStem', ['loan', 'loans', 'borrow', 'lend', 'lender', 'credit',
                                 'repayment', 'repay', 'cash advance']))) > 0;

    if (!$r || $r[0]['score'] < 1) {
        return ['matched' => false, 'lending' => $lend, 'corrected' => []];
    }
    $top = $r[0];

    // Tied topics that rest on the SAME words mean the question is too vague
    // to call: ask. Tied topics on DIFFERENT words mean two problems: answer
    // the first and offer the second below.
    $wordsOf = function ($x) { $u = []; foreach ($x['hits'] as $h) foreach ($h['used'] as $p) $u[$p] = 1; return $u; };
    $tied = array_values(array_filter($r, fn($x) => $x['score'] == $top['score']));
    if ($top['score'] < 2 && count($tied) >= 2) {
        $topWords = $wordsOf($top);
        $sameWords = array_values(array_filter(array_slice($tied, 1),
            fn($x) => array_intersect_key($wordsOf($x), $topWords)));
        if ($sameWords) {
            return ['matched' => false, 'lending' => $lend, 'corrected' => [],
                    'choices' => array_map(fn($x) => IT_HELP[$x['i']]['title'],
                                           array_slice(array_merge([$top], $sameWords), 0, 4))];
        }
    }

    // Which of their words the answer rests on
    $usedTop = [];
    foreach ($top['hits'] as $h) foreach ($h['used'] as $p) $usedTop[$p] = true;
    ksort($usedTop);

    // Echo what they typed. Words close together, with only filler between,
    // read as one phrase ("disk is full", not "disk · full").
    $pos = array_keys($usedTop);
    $keywords = []; $run = [];
    foreach ($pos as $n => $p) {
        if ($run) {
            $gap = array_slice($plain, end($run) + 1, $p - end($run) - 1);
            if (count($gap) > 2 || array_diff($gap, IT_HELP_STOP)) { $keywords[] = $run; $run = []; }
        }
        $run[] = $p;
    }
    if ($run) $keywords[] = $run;
    $negators = ['will','do','did','does','is','was','are','could','have'];
    $keywords = array_map(function ($run) use ($plain, $fixes, $negators) {
        $from = $run[0];
        // "will not start" keeps its "will", so it can become "won't start"
        if (($plain[$from] ?? '') === 'not' && $from > 0 && in_array($plain[$from - 1], $negators, true)) $from--;
        $words = [];
        for ($p = $from; $p <= end($run); $p++) {
            $words[] = isset($fixes[$p]) ? itHelpReadable($fixes[$p]) : ($plain[$p] ?? '');
        }
        $txt = implode(' ', $words);
        // Whole words only: a plain substring swap would turn "this not" into "thisn't"
        foreach (['will not' => "won't", 'cannot' => "can't", 'do not' => "don't",
                  'did not' => "didn't", 'does not' => "doesn't", 'is not' => "isn't",
                  'was not' => "wasn't", 'are not' => "aren't", 'could not' => "couldn't",
                  'have not' => "haven't"] as $from => $to) {
            $txt = preg_replace('/(?<![a-z])' . preg_quote($from, '/') . '(?![a-z])/', $to, $txt);
        }
        return $txt;
    }, $keywords);
    $keywords = array_slice(array_values(array_unique(array_filter($keywords))), 0, 4);

    // Only corrections the answer actually relied on are worth mentioning
    $corrected = [];
    foreach ($fixes as $p => $to) {
        if (isset($usedTop[$p])) $corrected[] = ['typed' => $plain[$p] ?? '', 'read_as' => itHelpReadable($to)];
    }

    // A second topic, but only if it answers a DIFFERENT part of the question
    $also = [];
    foreach (array_slice($r, 1) as $x) {
        if (count($also) >= 2) break;
        $fresh = 0.0;
        foreach ($x['hits'] as $h) {
            if (!array_intersect($h['used'], array_keys($usedTop))) $fresh += $h['weight'];
        }
        if ($fresh >= 1) $also[] = IT_HELP[$x['i']]['title'];
    }

    return ['matched' => true, 'entry' => IT_HELP[$top['i']], 'keywords' => $keywords,
            'also' => $also, 'corrected' => $corrected, 'lending' => false];
}

/** A stem turned back into the word people would recognise. */
function itHelpReadable(string $stem): string {
    static $memo = [];
    if (isset($memo[$stem])) return $memo[$stem];
    foreach (IT_HELP as $e) foreach ($e['match'] as $m) {
        foreach (preg_split('/[^a-z0-9]+/', itHelpNormalise($m), -1, PREG_SPLIT_NO_EMPTY) as $orig) {
            if (itHelpStem($orig) === $stem) return $memo[$stem] = $orig;
        }
    }
    return $memo[$stem] = $stem;
}

/** A topic by its exact title — used when someone taps a suggested topic. */
function itHelpByTitle(string $title): ?array {
    foreach (IT_HELP as $e) if ($e['title'] === $title) return $e;
    return null;
}

/** The topics offered as starting points, so nobody faces an empty box. */
function itHelpTopics(): array {
    $out = [];
    foreach (IT_HELP as $e) $out[] = $e['title'];
    return $out;
}
