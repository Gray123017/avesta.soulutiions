<?php
require_once __DIR__ . '/staging-guard.php';
require_once __DIR__ . '/appearance.php';
require_once __DIR__ . '/guide.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; connect-src 'none'; form-action 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'; worker-src 'none'");
?><!doctype html><html lang="en"><head><?php avesta_review_theme_bootstrap(); ?><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Avesta staging — demonstration only</title><style>body{font:16px/1.6 system-ui,Arial;background:#faf7f1;color:#173e33;margin:0;padding:7vh 20px}main{margin:auto;max-width:680px;background:white;border:1px solid #ddd;padding:28px;border-radius:12px}a{color:#173e33}h1{font-family:Georgia,serif}</style><?php avesta_review_theme_styles(); ?><?php avesta_guide_assets(); ?>
</head><body class="avesta-notice-page"><main><?php avesta_review_appearance_control(); ?><p>AVESTA · PROTECTED STAGING</p><h1>This service is disabled in staging</h1><p>Accounts, loan submissions, payments, downloads and live notifications are deliberately not connected. This environment is for reviewing the approved homepage, Lending and IT layouts, local calculations and fictional application/enquiry journeys only.</p><a href="/avesta-integrated-stage7b/index.php">Back to staging homepage</a></main><?php avesta_guide_widget(); ?>
</body></html>
