<?php
/**
 * AVESTA — legal.php
 * Privacy Policy, Terms & Conditions, Complaints Procedure.
 *
 * Deliberately PUBLIC, unlike the rest of the site.
 *
 * A privacy policy you must create an account to read is not a disclosure.
 * The Money-Lenders Act expects a borrower to see the terms before they are
 * bound by them, and the Data Protection Act expects a data subject to be
 * able to find out what is collected about them without first handing over
 * more. These pages contain no personal data and nothing confidential, so
 * there is nothing here to gate.
 *
 * Licence number: LICENCE_NO stays empty until the Ministry of Finance
 * issues one. While it is empty, nothing on this site claims to be licensed —
 * advertising a licence you do not yet hold is precisely what the Act's
 * advertising rules prohibit. Fill it in the day it arrives.
 */

const LICENCE_NO   = '';                 // Money Lenders Licence, once issued
const PACRA_NO     = '';                 // PACRA registration number
const TPIN         = '';                 // ZRA TPIN
const TRADING_NAME = 'Avesta Enterprises';
const ADDRESS      = 'House No. 3, Thom Avenue, Kansenshi, Ndola, Zambia';
const EMAIL        = 'info@avesta.solutions';
const PHONE_1      = '+260 971 013 108';
const PHONE_2      = '+260 769 974 200';
const UPDATED      = '19 September 2026';

$page = $_GET['p'] ?? 'privacy';
if (!in_array($page, ['privacy', 'terms', 'complaints'], true)) $page = 'privacy';

$titles = ['privacy' => 'Privacy Policy',
           'terms' => 'Terms & Conditions',
           'complaints' => 'Complaints Procedure'];

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $titles[$page] ?> — <?= TRADING_NAME ?></title>
<meta name="description" content="<?= $titles[$page] ?> for <?= TRADING_NAME ?>, a micro-lending and IT services business in Ndola, Zambia.">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#163E33">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<style>
:root{--navy:#163E33;--navy-2:#1F5344;--gold:#D98E3B;--gold-deep:#96591A;
  --cream:#F7F3EC;--paper:#fff;--line:#E3DED4;--ink:#12241E;--muted:#66766F}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--cream);
  color:var(--ink);line-height:1.7;padding-bottom:calc(40px + env(safe-area-inset-bottom))}
header{background:var(--navy);color:#fff;padding:26px 20px 22px}
.hw{max-width:780px;margin:0 auto;display:flex;align-items:center;gap:13px;flex-wrap:wrap}
.mark{width:44px;height:44px;border-radius:11px;background:linear-gradient(145deg,#E8AE6E,var(--gold-deep));
  display:grid;place-items:center;font-weight:800;color:var(--navy);font-size:15px}
.hw h1{font-size:17px;font-weight:800;letter-spacing:-.2px}
.hw p{font-size:11px;color:rgba(255,255,255,.62);letter-spacing:.5px;text-transform:uppercase}
.hw a.back{margin-left:auto;font-size:12px;color:var(--gold);text-decoration:none;font-weight:700}
nav.tabs{background:var(--paper);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10}
nav.tabs div{max-width:780px;margin:0 auto;display:flex;gap:2px;overflow-x:auto;padding:0 12px}
nav.tabs a{flex:0 0 auto;padding:14px 15px 12px;font-size:13px;font-weight:600;color:var(--muted);
  text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap}
nav.tabs a.on{color:var(--navy);font-weight:800;border-bottom-color:var(--gold)}
main{max-width:780px;margin:0 auto;padding:28px 20px 10px}
.updated{font-size:12px;color:var(--muted);margin-bottom:22px}
h2{font-size:20px;font-weight:800;color:var(--navy);margin:0 0 6px;letter-spacing:-.3px}
h3{font-size:14px;font-weight:800;color:var(--navy);margin:26px 0 8px}
p,li{font-size:14px;color:#3B4A44}
p{margin-bottom:12px}
ul{margin:0 0 14px 20px}
li{margin-bottom:7px}
.box{background:var(--paper);border:1px solid var(--line);border-left:3px solid var(--gold);
  border-radius:9px;padding:15px 17px;margin:18px 0}
.box strong{color:var(--navy)}
table{width:100%;border-collapse:collapse;margin:14px 0;font-size:13px;background:var(--paper)}
th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line)}
th{background:var(--navy);color:#fff;font-size:11px;letter-spacing:.6px;text-transform:uppercase}
footer{max-width:780px;margin:30px auto 0;padding:20px;border-top:1px solid var(--line);
  font-size:12px;color:var(--muted);line-height:1.8}
footer a{color:var(--gold-deep)}
@media(max-width:600px){h2{font-size:18px}main{padding:22px 16px 6px}}
</style>
</head>
<body>

<header>
  <div class="hw">
    <div class="mark">AE</div>
    <div>
      <h1><?= TRADING_NAME ?></h1>
      <p>Kansenshi &middot; Ndola &middot; Zambia</p>
    </div>
    <a class="back" href="index.php">&larr; Back to site</a>
  </div>
</header>

<nav class="tabs"><div>
  <a href="legal.php?p=privacy"    class="<?= $page==='privacy'?'on':'' ?>">Privacy Policy</a>
  <a href="legal.php?p=terms"      class="<?= $page==='terms'?'on':'' ?>">Terms &amp; Conditions</a>
  <a href="legal.php?p=complaints" class="<?= $page==='complaints'?'on':'' ?>">Complaints</a>
</div></nav>

<main>
<h2><?= $titles[$page] ?></h2>
<p class="updated">Last updated <?= UPDATED ?></p>

<?php if ($page === 'privacy'): ?>

  <p><?= TRADING_NAME ?> collects personal information in order to assess loan
     applications and provide IT services. This page explains what is collected,
     why, where it is kept and what rights you have over it.</p>

  <h3>What we collect</h3>
  <ul>
    <li><strong>Identity:</strong> your full name, NRC number, date of birth and a photograph of your NRC.</li>
    <li><strong>Contact:</strong> phone number, email address and physical address.</li>
    <li><strong>Financial:</strong> employer, occupation, income, payslips, bank statements and details of any collateral or guarantor.</li>
    <li><strong>Loan records:</strong> amounts, dates, repayments and balances.</li>
    <li><strong>Account:</strong> your username and a one-way encrypted form of your password. We never store your password itself and can never read it.</li>
    <li><strong>Activity:</strong> a record of sign-ins and of changes made to your application, including the date, time and IP address.</li>
  </ul>

  <h3>Why we collect it</h3>
  <p>To verify who you are, to decide whether a loan can responsibly be offered,
     to administer repayment, to meet our obligations under the Money-Lenders Act,
     and to keep the records a lender is required to keep. For IT work, to understand
     the problem and to contact you about it.</p>

  <h3>Where it is kept</h3>
  <p>Your information is stored on servers located in Zambia, as required by
     Section 70(1) of the Data Protection Act No. 3 of 2021. Documents you upload
     are held in a non-public folder on that server.</p>

  <h3>Who can see it</h3>
  <p>Only authorised <?= TRADING_NAME ?> staff, and only what their role requires.
     Every access and change is recorded. We do not sell your information, and we do
     not share it with anyone else except where the law requires it, or where you
     have asked us to (for example, an employer operating a salary-checkoff
     arrangement you have agreed to).</p>

  <h3>How long we keep it</h3>
  <p>For as long as your loan is running, and afterwards for the period the law
     requires records to be retained. Enquiries that do not lead to a loan are kept
     for two years and then removed.</p>

  <h3>Your rights</h3>
  <ul>
    <li>Ask what we hold about you, and receive a copy.</li>
    <li>Ask us to correct anything that is wrong.</li>
    <li>Ask us to delete information we no longer have a lawful reason to keep.</li>
    <li>Withdraw consent where we relied on it, though this may mean we cannot continue a loan.</li>
    <li>Complain to us, and to the Data Protection Commissioner if we do not resolve it.</li>
  </ul>
  <p>To exercise any of these, contact us on the details below. We will respond
     within 30 days.</p>

  <div class="box">
    <strong>If something goes wrong.</strong> If your information is ever exposed in
    a way that puts you at risk, we will tell you and report it to the Data Protection
    Commissioner within 24 hours of becoming aware, as the Act requires.
  </div>

<?php elseif ($page === 'terms'): ?>

  <p>These terms apply to loans provided by <?= TRADING_NAME ?> and to use of this
     website. Please read them before applying. The loan agreement you sign is the
     binding document; these terms explain it in plainer language.</p>

  <h3>Who we are</h3>
  <p><?= TRADING_NAME ?>, <?= ADDRESS ?>.
     <?php if (PACRA_NO !== ''): ?>Registered with PACRA under number <?= PACRA_NO ?>.<?php endif; ?>
     <?php if (LICENCE_NO !== ''): ?>Money Lenders Licence No. <?= LICENCE_NO ?>.<?php endif; ?></p>

  <h3>Our loans</h3>
  <table>
    <tr><th>Term</th><th>Flat interest</th><th>Repayment</th></tr>
    <tr><td>1 week</td><td>15%</td><td>Single lump sum</td></tr>
    <tr><td>2 weeks</td><td>20%</td><td>Single lump sum</td></tr>
    <tr><td>3 weeks</td><td>25%</td><td>Instalments or lump sum</td></tr>
    <tr><td>4 weeks</td><td>30%</td><td>Weekly instalments</td></tr>
  </table>
  <p>Interest is <strong>simple and flat</strong>, applied once to the principal.
     We do not charge compound interest. The total you will repay is stated in your
     agreement before you sign it, and it does not change.</p>

  <h3>What you must do</h3>
  <ul>
    <li>Give true and complete information. A loan obtained on false information may be recalled.</li>
    <li>Repay the agreed amount on the agreed dates.</li>
    <li>Tell us promptly if your circumstances change and you cannot pay.</li>
  </ul>

  <h3>If you pay late</h3>
  <p>A grace period of three days applies before any late charge. After that, a
     late penalty is applied as set out in your agreement. Interest on an overdue
     amount continues at the rate in the contract and is not increased.</p>
  <p>If you are struggling, tell us early. We would far rather agree a revised
     schedule than pursue a default.</p>

  <h3>Security and collateral</h3>
  <p>Where a loan is secured by salary checkoff, a guarantor or physical collateral,
     the arrangement is described in your agreement. Collateral is returned once the
     loan is settled in full.</p>

  <h3>Your agreement</h3>
  <p>Every loan is set out in a written agreement stating the amount, the interest,
     the total repayable, the dates and the security. You receive a signed copy within
     seven days. You may ask us for a statement of your loan at any time, and we will
     provide it.</p>

  <h3>Cancelling</h3>
  <p>You may repay early at any time. Because interest is flat and applied once, we
     will tell you the settlement figure on request.</p>

  <h3>Using this website</h3>
  <p>Your account is yours; do not share your password. Tell us at once if you
     think someone else has used it. We may suspend an account being used improperly.</p>

  <h3>Cookies</h3>
  <p>This site sets one cookie, to keep you signed in. It holds no personal
     information and is removed when you sign out. We do not use advertising or
     tracking cookies, and we run no third-party analytics.</p>

  <h3>Governing law</h3>
  <p>These terms and every loan agreement are governed by the laws of Zambia,
     including the Money-Lenders Act, CAP 398. Disputes are subject to the
     jurisdiction of the Zambian courts.</p>

<?php else: ?>

  <p>If something has gone wrong, we want to hear about it. Most problems are
     settled quickly once we know.</p>

  <h3>Step 1 — Tell us</h3>
  <p>Contact us by phone, WhatsApp, email or in person at the office. Give your
     name, your loan or enquiry reference if you have one, and what happened.
     You do not need to put it in writing, though it helps.</p>

  <h3>Step 2 — We acknowledge</h3>
  <p>We will confirm we have received your complaint within <strong>two working
     days</strong>, and tell you who is handling it.</p>

  <h3>Step 3 — We investigate</h3>
  <p>We will look into what happened, including the record of who did what and
     when, and give you a written answer within <strong>fourteen working days</strong>.
     If it is going to take longer, we will tell you why and when to expect an answer.</p>

  <h3>Step 4 — If you are not satisfied</h3>
  <p>Ask for the matter to be reviewed by management, stating why you disagree.
     A review is answered within a further fourteen working days.</p>

  <h3>Step 5 — Taking it further</h3>
  <p>If we still have not resolved it, you may take the matter to:</p>
  <ul>
    <li>The <strong>Ministry of Finance</strong>, which licenses money lenders under the Money-Lenders Act.</li>
    <li>The <strong>Data Protection Commissioner</strong>, for anything concerning your personal information.</li>
    <li>The <strong>Zambian courts</strong>, which may reopen a loan transaction they consider harsh or unconscionable.</li>
  </ul>

  <div class="box">
    <strong>Nothing in this procedure limits your legal rights.</strong> You are free
    to go to a court or a regulator at any time, whether or not you have complained
    to us first.
  </div>

<?php endif; ?>
</main>

<footer>
  <strong><?= TRADING_NAME ?></strong><br>
  <?= ADDRESS ?><br>
  <?= PHONE_1 ?> &middot; <?= PHONE_2 ?><br>
  <a href="mailto:<?= EMAIL ?>"><?= EMAIL ?></a> &middot;
  <a href="index.php">www.avesta.solutions</a><br>
  Monday to Saturday, 08:00&ndash;17:00
  <?php if (LICENCE_NO !== ''): ?>
    <br>Money Lenders Licence No. <?= LICENCE_NO ?>
  <?php endif; ?>
  <?php if (TPIN !== ''): ?> &middot; TPIN <?= TPIN ?><?php endif; ?>
</footer>

</body>
</html>
