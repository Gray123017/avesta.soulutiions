<?php
require_once __DIR__ . '/staging-guard.php';
require_once __DIR__ . '/appearance.php';
require_once __DIR__ . '/guide.php';
// Isolated public design review. All real account and loan operations are disabled.
// Isolated staging frontend: intentionally excludes production authentication and databases.
// Public: this is the front door, and the page a search engine lands on.
$me = null;
// Embed only the same public data already available as static JSON. Rendering
// should not wait for another mobile-network round trip after the HTML loads.
// A missing public catalogue must not expose PHP warnings or crash unrelated pages.
function publicCatalogue(string $name): ?array {
    $path = __DIR__ . '/assets/avesta/' . $name;
    if (!is_readable($path)) return null;
    $bytes = @file_get_contents($path);
    if ($bytes === false) return null;
    $data = json_decode($bytes, true);
    return is_array($data) && ($data === [] || array_keys($data) === range(0, count($data) - 1)) ? $data : null;
}
$public_data = json_encode([
    'services' => publicCatalogue('services.json'),
    'help' => publicCatalogue('help.json'),
    'software' => publicCatalogue('software.json')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; form-action 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'; worker-src 'none'");
?><!doctype html>
<html lang="en">
<head><?php avesta_review_theme_bootstrap(); ?>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Avesta Enterprises · Lending & IT Consulting</title>
<meta name="description" content="Short-term lending and practical IT services across Zambia. Avesta Enterprises, Kansenshi, Ndola.">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="theme-color" content="#163E33">
<link rel="icon" href="/avesta-integrated-stage7b/icons/icon-192.png"><link rel="apple-touch-icon" href="/avesta-integrated-stage7b/icons/apple-touch-icon.png">
<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/styles.css?v=20261003-steady-navigation">
<script type="module" src="/avesta-integrated-stage7b/assets/avesta/app.js?v=20261018-stage7b-r1"></script>
<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/responsive.css?v=20261003">
<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/balanced-enterprise-stage1.css?v=20261018-stage7b-r1">
<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/balanced-enterprise-stage2.css?v=20261018-stage7b-r1">
<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/consulting.css?v=20261018-stage7b-r1">
<?php avesta_review_theme_styles(); ?>
<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/software.css?v=20261018-stage7b-r1">
<?php avesta_guide_assets(); ?>
</head>
<body>
<div class="staging-integration-banner" role="status">AVESTA STAGING · Stage 7B · Integrated review & local G.I.T Guide · Demonstration only: no real applications, tickets, messages, payments or downloads</div>
<a class="skip" href="#main">Skip to content</a>
<div class="be-topline"><div class="wrap"><span>Kansenshi, Ndola · Serving Zambia</span><span class="be-hours">Monday–Saturday, 08:00–17:00</span></div></div>
<header class="header">
 <div class="header-inner">
  <a class="brand" href="/avesta-integrated-stage7b/index.php" aria-label="Avesta Enterprises home"><span class="brand-mark">AE<span></span></span><span>AVESTA<small>ENTERPRISES</small></span></a>
  <nav class="desktop-nav" aria-label="Main navigation">
   <a href="/avesta-integrated-stage7b/index.php?page=lending" data-nav="lending">Lending</a><a href="/avesta-integrated-stage7b/index.php?page=it" data-nav="it">IT Consulting</a><a href="/avesta-integrated-stage7b/index.php?page=software" data-nav="software">Software</a><a href="/avesta-integrated-stage7b/index.php?page=about" data-nav="about">About Avesta</a><a href="/avesta-integrated-stage7b/index.php?page=contact" data-nav="contact">Contact</a>
  </nav>
  <div class="header-actions"><?php avesta_review_appearance_control(); ?><a href="/avesta-integrated-stage7b/portal.php" class="portal-link">Account portal</a><a class="button small" href="/avesta-integrated-stage7b/apply.php">Try application demo</a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Open navigation"><span></span><span></span></button></div>
 </div>
 <nav id="mobile-nav" class="mobile-nav" aria-label="Mobile navigation" hidden><a href="/avesta-integrated-stage7b/index.php">Home</a><a href="/avesta-integrated-stage7b/index.php?page=lending">Lending</a><a href="/avesta-integrated-stage7b/index.php?page=how-it-works">How it works</a><a href="/avesta-integrated-stage7b/index.php?page=calculator">Loan calculator</a><a href="/avesta-integrated-stage7b/index.php?page=currency">Currency converter</a><a href="/avesta-integrated-stage7b/apply.php">Loan application</a><a href="/avesta-integrated-stage7b/index.php?page=it">IT Consulting</a><a href="/avesta-integrated-stage7b/index.php?page=software" data-nav="software">Software</a><a href="/avesta-integrated-stage7b/index.php?page=about">About Avesta</a><a href="/avesta-integrated-stage7b/index.php?page=contact">Contact</a><a href="/avesta-integrated-stage7b/portal.php">Account portal</a></nav>
</header>
<div id="subnav"></div>
<main id="main" tabindex="-1"><div class="loading">Welcome to Avesta Enterprises. <a href="/avesta-integrated-stage7b/loans.php">Lending</a> · <a href="/avesta-integrated-stage7b/it.php">IT support</a> · <a href="/avesta-integrated-stage7b/signin.php">Sign in</a></div></main>
<footer class="footer">
 <div class="wrap footer-grid"><div><a class="brand" href="/avesta-integrated-stage7b/index.php"><span class="brand-mark">AE<span></span></span><span>AVESTA<small>ENTERPRISES</small></span></a><p>Finance when you need it.<br>Technology that keeps you moving.</p><span class="footer-note">Kansenshi, Ndola · Serving Zambia</span></div><div class="footer-group"><h2><button type="button" class="compact-toggle footer-toggle" data-compact-toggle aria-expanded="true" aria-controls="footer-section-0"><svg class="footer-section-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="m16 8-2.5 5.5L8 16l2.5-5.5L16 8Z"/></svg><span>Explore</span><span class="compact-sign" aria-hidden="true">−</span></button></h2><div class="footer-details" id="footer-section-0"><a href="/avesta-integrated-stage7b/index.php?page=lending">Avesta Lending</a><a href="/avesta-integrated-stage7b/index.php?page=calculator">Loan calculator</a><a href="/avesta-integrated-stage7b/index.php?page=it">Avesta Consulting</a><a href="/avesta-integrated-stage7b/index.php?page=about">About Avesta</a></div></div><div class="footer-group"><h2><button type="button" class="compact-toggle footer-toggle" data-compact-toggle aria-expanded="true" aria-controls="footer-section-1"><svg class="footer-section-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-8l-6 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M7 9h10M7 13h6"/></svg><span>Let’s talk</span><span class="compact-sign" aria-hidden="true">−</span></button></h2><div class="footer-details" id="footer-section-1"><a href="tel:+260971013108">0971 013 108</a><a href="tel:+260769974200">0769 974 200</a><a href="mailto:info@avesta.solutions">info@avesta.solutions</a><span>Mon–Sat, 08:00–17:00</span></div></div><div class="footer-group"><h2><button type="button" class="compact-toggle footer-toggle" data-compact-toggle aria-expanded="true" aria-controls="footer-section-2"><svg class="footer-section-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span>Visit us</span><span class="compact-sign" aria-hidden="true">−</span></button></h2><div class="footer-details" id="footer-section-2"><p>House No. 3, Thom Avenue<br>Kansenshi, Ndola<br>Zambia</p><a href="/avesta-integrated-stage7b/index.php?page=contact">Contact & directions</a></div></div></div>
 <div class="wrap footer-bottom"><span>© <span id="year"></span> Avesta Enterprises</span><nav aria-label="Policies"><a href="/avesta-integrated-stage7b/review.php">Review centre</a><a href="/avesta-integrated-stage7b/legal.php?p=privacy">Privacy</a><a href="/avesta-integrated-stage7b/legal.php?p=terms">Terms</a><a href="/avesta-integrated-stage7b/legal.php?p=complaints">Complaints</a></nav><span class="private-label">Avesta Enterprises</span></div>
</footer>
<div id="toast" role="status" aria-live="polite"></div>
<dialog id="dialog"><div id="dialog-content"></div><button class="dialog-close" aria-label="Close dialog" type="button">×</button></dialog>
<noscript><p class="wrap">Please enable JavaScript to use the calculators, application and portal. Call <a href="tel:+260971013108">0971 013 108</a> or email <a href="mailto:info@avesta.solutions">info@avesta.solutions</a> for help.</p></noscript>
<script id="avesta-public-data" type="application/json"><?= $public_data ?></script>
<?php avesta_guide_widget(); ?>
</body>
</html>
