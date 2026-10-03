<?php
// Public presentation; existing PHP account and loan workflows remain authoritative.
require_once __DIR__ . '/auth.php';
// Public: this is the front door, and the page a search engine lands on.
$me = av_user();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Avesta Enterprises · Lending & IT Consulting</title>
<meta name="description" content="Short-term lending and practical IT services across Zambia. Avesta Enterprises, Kansenshi, Ndola.">
<meta name="robots" content="index,follow">
<meta name="theme-color" content="#163E33">
<link rel="icon" href="/icons/icon-192.png"><link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/avesta/styles.css?v=20261003-mobile-tabs">
<script type="module" src="/assets/avesta/app.js?v=20261003-mobile-tabs"></script>
<script type="module" src="/assets/avesta/assistant.js?v=20261003-chat-minimize"></script>
<link rel="manifest" href="/manifest.json">
<link rel="stylesheet" href="/assets/avesta/responsive.css?v=20261003">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="header">
 <div class="header-inner">
  <a class="brand" href="/" aria-label="Avesta Enterprises home"><span class="brand-mark">AE<span></span></span><span>AVESTA<small>ENTERPRISES</small></span></a>
  <nav class="desktop-nav" aria-label="Main navigation">
   <a href="/index.php?page=lending" data-nav="lending">Lending</a><a href="/index.php?page=it" data-nav="it">IT Consulting</a><a href="/index.php?page=about" data-nav="about">About Avesta</a><a href="/index.php?page=contact" data-nav="contact">Contact</a>
  </nav>
  <div class="header-actions"><a href="/login.php" class="portal-link">Sign in / Portal</a><a class="button small" href="/apply.php">Apply for a loan</a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Open navigation"><span></span><span></span></button></div>
 </div>
 <nav id="mobile-nav" class="mobile-nav" aria-label="Mobile navigation" hidden><a href="/">Home</a><a href="/index.php?page=lending">Lending</a><a href="/index.php?page=how-it-works">How it works</a><a href="/index.php?page=calculator">Loan calculator</a><a href="/index.php?page=currency">Currency converter</a><a href="/apply.php">Loan application</a><a href="/index.php?page=it">IT Consulting</a><a href="/index.php?page=about">About Avesta</a><a href="/index.php?page=contact">Contact</a><a href="/login.php">Sign in / Portal</a></nav>
</header>
<div id="subnav"></div>
<main id="main" tabindex="-1"><div class="loading">Welcome to Avesta Enterprises. <a href="/loans.php">Lending</a> · <a href="/it.php">IT support</a> · <a href="/login.php">Sign in</a></div></main>
<footer class="footer">
 <div class="wrap footer-grid"><div><a class="brand" href="/"><span class="brand-mark">AE<span></span></span><span>AVESTA<small>ENTERPRISES</small></span></a><p>Finance when you need it.<br>Technology that keeps you moving.</p><span class="footer-note">Kansenshi, Ndola · Serving Zambia</span></div><div class="footer-group"><h2><button type="button" class="compact-toggle footer-toggle" data-compact-toggle aria-expanded="true" aria-controls="footer-section-0"><span>Explore</span><span class="compact-sign" aria-hidden="true">−</span></button></h2><div class="footer-details" id="footer-section-0"><a href="/index.php?page=lending">Avesta Lending</a><a href="/index.php?page=calculator">Loan calculator</a><a href="/index.php?page=it">Avesta Consulting</a><a href="/index.php?page=about">About Avesta</a></div></div><div class="footer-group"><h2><button type="button" class="compact-toggle footer-toggle" data-compact-toggle aria-expanded="true" aria-controls="footer-section-1"><span>Let’s talk</span><span class="compact-sign" aria-hidden="true">−</span></button></h2><div class="footer-details" id="footer-section-1"><a href="tel:+260971013108">0971 013 108</a><a href="tel:+260769974200">0769 974 200</a><a href="mailto:info@avesta.solutions">info@avesta.solutions</a><span>Mon–Sat, 08:00–17:00</span></div></div><div class="footer-group"><h2><button type="button" class="compact-toggle footer-toggle" data-compact-toggle aria-expanded="true" aria-controls="footer-section-2"><span>Visit us</span><span class="compact-sign" aria-hidden="true">−</span></button></h2><div class="footer-details" id="footer-section-2"><p>House No. 3, Thom Avenue<br>Kansenshi, Ndola<br>Zambia</p><a href="/index.php?page=contact">Contact & directions</a></div></div></div>
 <div class="wrap footer-bottom"><span>© <span id="year"></span> Avesta Enterprises</span><nav aria-label="Policies"><a href="/legal.php?p=privacy">Privacy</a><a href="/legal.php?p=terms">Terms</a><a href="/legal.php?p=complaints">Complaints</a></nav><span class="private-label">Avesta Enterprises</span></div>
</footer>
<div id="toast" role="status" aria-live="polite"></div>
<dialog id="dialog"><div id="dialog-content"></div><button class="dialog-close" aria-label="Close dialog" type="button">×</button></dialog>
<noscript><p class="wrap">Please enable JavaScript to use the calculators, application and portal. Call <a href="tel:+260971013108">0971 013 108</a> or email <a href="mailto:info@avesta.solutions">info@avesta.solutions</a> for help.</p></noscript>
</body>
</html>
