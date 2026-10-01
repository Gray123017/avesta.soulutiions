<?php
/**
 * AVESTA ENTERPRISES — the front step.
 *
 * Lending and IT Consulting are run as separate offices. Rather than guess
 * which one a visitor wants, this asks. Anyone arriving from a loan referral
 * or an IT flyer picks their door once; the choice is remembered so they land
 * straight there next time.
 */
require_once __DIR__ . '/auth.php';
// Public: this is the front door, and the page a search engine lands on.
$me = av_user();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="Avesta Enterprises — short-term loans and IT services across Zambia. Ndola-based, with terms stated up front.">
<link rel="canonical" href="https://www.avesta.solutions/">
<title>Avesta Enterprises — Lending &amp; IT Consulting</title>
<style>
:root{--green:#163E33;--green2:#215746;--gold:#D98E3B;--gold2:#F0B86F;--blue:#3B82F6;--ink:#13231e;--muted:#62706a;--paper:#f7f9f8;--white:#fff;--line:#dce5e1}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);background:var(--paper);line-height:1.6}a{color:inherit}.shell{width:min(1180px,calc(100% - 32px));margin:auto}.nav{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.96);border-bottom:1px solid var(--line);backdrop-filter:blur(10px)}.navin{min-height:72px;display:flex;align-items:center;gap:24px}.brand{display:flex;align-items:center;gap:11px;text-decoration:none;font-weight:900}.mark{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(145deg,var(--gold2),var(--gold));color:var(--green);box-shadow:0 5px 16px #163e3320}.links{margin-left:auto;display:flex;align-items:center;gap:20px;font-size:14px;font-weight:700}.links a{text-decoration:none}.pill{padding:10px 16px;border-radius:999px;background:var(--green);color:white}.hero{background:linear-gradient(135deg,var(--green),var(--green2));color:white;overflow:hidden}.hero .shell{min-height:min(680px,78vh);display:grid;grid-template-columns:1.15fr .85fr;align-items:center;gap:56px;padding-block:72px}.eyebrow{font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:var(--gold2)}h1{font-size:clamp(2.4rem,6vw,5.4rem);line-height:.98;letter-spacing:-.055em;margin:.25em 0}.lead{font-size:clamp(1rem,2vw,1.2rem);color:#e2ece8;max-width:660px}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:13px 18px;border-radius:11px;text-decoration:none;font-weight:850}.btn.gold{background:var(--gold);color:#14251f}.btn.light{background:#ffffff12;border:1px solid #ffffff38;color:white}.hero-card{padding:28px;border:1px solid #ffffff2e;background:#ffffff0d;border-radius:24px;box-shadow:0 28px 60px #071b1545}.hero-card h2{font-size:clamp(1.5rem,3vw,2.2rem);margin:0 0 8px}.hero-card p{color:#d8e5e0}.trust{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:22px}.trust div{padding:13px;background:#ffffff0c;border-radius:12px;font-size:12px}.section{padding:clamp(56px,8vw,96px) 0}.section-head{max-width:700px;margin-bottom:28px}.section-head h2{font-size:clamp(1.9rem,4vw,3rem);letter-spacing:-.035em;margin:0 0 8px}.section-head p{color:var(--muted)}.paths{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.path{background:white;border:1px solid var(--line);border-radius:20px;padding:clamp(24px,4vw,40px);box-shadow:0 10px 35px #163e3309}.path.loan{border-top:5px solid var(--gold)}.path.it{border-top:5px solid var(--blue)}.tag{font-size:11px;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.loan .tag{color:#a76018}.it .tag{color:#2563eb}.path h3{font-size:clamp(1.45rem,3vw,2.1rem);margin:8px 0}.path p{color:var(--muted)}.path ul{padding-left:18px;color:#44524c}.promo{background:#eef4f1}.promo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.promo article{background:white;padding:24px;border-radius:16px;border:1px solid var(--line)}.promo h3{margin-top:0}.footer{background:#102d25;color:#dce8e4;padding:38px 0}.footerin{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap}.footer a{color:#fff}@media(max-width:800px){.links a:not(.pill){display:none}.hero .shell{grid-template-columns:1fr;min-height:auto;padding-block:56px}.paths,.promo-grid{grid-template-columns:1fr}.trust{grid-template-columns:1fr 1fr}.hero-card{padding:22px}}@media(max-width:440px){.shell{width:min(100% - 22px,1180px)}.brand span:last-child{font-size:14px}.pill{padding:9px 12px}.trust{grid-template-columns:1fr}.actions .btn{width:100%}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
</style>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#163E33">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Avesta">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<link rel="icon" href="icons/icon-192.png" sizes="192x192">
</head>
<body>
<nav class="nav"><div class="shell navin"><a class="brand" href="index.php"><span class="mark">H</span><span>Harvester by Avesta</span></a><div class="links"><a href="#services">Services</a><a href="loans.php">Loans</a><a href="it.php">IT Support</a><a href="#contact">Contact</a><a class="pill" href="login.php">Login / Portal</a></div></div></nav>
<header class="hero"><div class="shell"><div><div class="eyebrow">Harvester • Avesta Enterprises</div><h1>Finance and technology that help you move forward.</h1><p class="lead">One professional gateway for practical short-term lending and dependable IT services across Zambia. Choose the service you need, understand the process clearly, and get support without unnecessary complexity.</p><div class="actions"><a class="btn gold" href="loans.php">Explore Loans →</a><a class="btn light" href="it.php">Get IT Support →</a></div></div><aside class="hero-card"><div class="eyebrow">Built around clarity</div><h2>Two services. One trusted platform.</h2><p>Harvester brings Avesta Lending and Avesta Consulting together while keeping each customer journey focused and easy to navigate.</p><div class="trust"><div><strong>Lending</strong><br>Clear terms</div><div><strong>IT</strong><br>Practical support</div><div><strong>Access</strong><br>Mobile first</div></div></aside></div></header>
<main><section class="section" id="services"><div class="shell"><div class="section-head"><div class="eyebrow" style="color:#a76018">Choose your service</div><h2>What can Harvester help you with?</h2><p>Each service has its own focused workspace, tools and support journey.</p></div><div class="paths"><article class="path loan"><div class="tag">Avesta Lending</div><h3>Loans & Financial Services</h3><p>Short-term finance with terms presented up front and online tools to help you understand repayment before applying.</p><ul><li>Loan information and repayment calculator</li><li>Online applications and status tracking</li><li>Borrower portal and payment records</li></ul><a class="btn" style="background:var(--gold);margin-top:10px" href="loans.php">Explore Loans →</a></article><article class="path it"><div class="tag">Avesta Consulting</div><h3>IT Services & Support</h3><p>Technical support for computers, networks, CCTV, cybersecurity, data, websites and day-to-day technology problems.</p><ul><li>Expandable service catalogue</li><li>Guided IT troubleshooting assistant</li><li>Support enquiries and escalation</li></ul><a class="btn" style="background:var(--blue);color:white;margin-top:10px" href="it.php">Explore IT Services →</a></article></div></div></section>
<section class="section promo"><div class="shell"><div class="section-head"><div class="eyebrow" style="color:#2563eb">Why Harvester</div><h2>Designed to make the next step obvious.</h2></div><div class="promo-grid"><article><h3>Clear information</h3><p>Important terms, service details and next actions are presented without clutter.</p></article><article><h3>Responsive everywhere</h3><p>The experience adapts across phones, tablets, laptops and desktops without stretching media or forcing horizontal scrolling.</p></article><article><h3>Support when needed</h3><p>IT customers can troubleshoot common issues and escalate to a human when the problem needs hands-on assistance.</p></article></div></div></section></main>
<footer class="footer" id="contact"><div class="shell footerin"><div><strong>Harvester by Avesta Enterprises</strong><br><small>Serving Zambia • Head office, Ndola</small></div><div><a href="legal.php?p=privacy">Privacy</a> · <a href="legal.php?p=terms">Terms</a> · <a href="legal.php?p=complaints">Complaints</a></div></div></footer>
<script>
/* Install-to-home-screen.
 *
 * The service worker only caches icons and the manifest — never a page and
 * never api.php, because every page here is rendered for a signed-in person
 * (see sw.js). The app installs and opens instantly; the data always comes
 * fresh from the server.
 *
 * Needs HTTPS. On plain http the browser ignores all of this, which is the
 * correct behaviour and costs nothing.
 */
(function () {
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('sw.js').catch(function () {});
    });
  }

  // Android fires this instead of showing its own prompt. Hold it, offer our
  // own button, and use it when they tap.
  var waiting = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    waiting = e;
    document.documentElement.setAttribute('data-installable', 'yes');
    var b = document.getElementById('install-app');
    if (b) b.hidden = false;
  });

  window.addEventListener('DOMContentLoaded', function () {
    var b = document.getElementById('install-app');
    if (!b) return;

    // Already installed, or opened from the home screen: nothing to offer.
    var standalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
    if (standalone || navigator.standalone) { b.remove(); return; }

    b.addEventListener('click', async function () {
      if (waiting) {
        waiting.prompt();
        var res = await waiting.userChoice;
        waiting = null;
        if (res && res.outcome === 'accepted') {
          b.hidden = true;
          if (window.avToast) avToast('Avesta added to your home screen.', 'ok');
        }
        return;
      }
      // iPhone has no install prompt — Safari requires the Share menu, so say so.
      var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
      var how = ios
        ? 'Tap the Share button at the bottom of Safari, then "Add to Home Screen".'
        : 'Open your browser menu and choose "Install app" or "Add to Home screen".';
      if (window.avToast) avToast(how, 'info', { duration: 9000 });
      else alert(how);
    });
  });

  window.addEventListener('appinstalled', function () {
    var b = document.getElementById('install-app');
    if (b) b.hidden = true;
  });
})();
</script>
</body>
</html>
