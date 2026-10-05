<?php
$products = [
  [
    'id' => 'asset-tracker',
    'name' => 'GIT Asset Tracker',
    'short' => 'Asset Tracker',
    'icon' => 'AT',
    'badge' => 'v1.5.1',
    'description' => 'Track computers, printers, networking equipment and other IT assets with searchable records, reporting and backup tools.',
    'meta' => ['WINDOWS', 'INVENTORY', 'REPORTING'],
    'features' => [
      'Inventory categories and change history',
      'Excel import/export and PDF reports',
      'JSON backup and recovery',
    ],
    'primary_label' => 'Download ZIP',
    'primary_href' => '/downloads/asset-tracker/GIT-Asset-Tracker-1.5.1-Windows.zip',
    'primary_download' => true,
    'secondary_label' => 'Setup support',
    'secondary_href' => 'mailto:info@avesta.solutions?subject=GIT%20Asset%20Tracker%201.5.1',
  ],
  [
    'id' => 'sentinel',
    'name' => 'Avanto Sentinel',
    'short' => 'Sentinel',
    'icon' => 'AS',
    'badge' => 'Featured',
    'description' => 'Inventory operations for teams that need stock, purchasing, orders, requests and reporting in one shared local-network workflow.',
    'meta' => ['WINDOWS X64', 'LOCAL NETWORK', 'OPERATIONS'],
    'features' => [
      'Dedicated Sentinel product interface',
      'Stock, purchasing, orders and team requests',
      'Windows x64 deployment package',
    ],
    'primary_label' => 'Open Sentinel',
    'primary_href' => '/sentinel/',
    'secondary_label' => 'Download package',
    'secondary_href' => '/sentinel/downloads/Avanto-Sentinel-Windows-x64.rar',
    'secondary_download' => true,
  ],
  [
    'id' => 'moneytrack',
    'name' => 'Avesta MoneyTrack',
    'short' => 'MoneyTrack',
    'icon' => 'MT',
    'badge' => 'v1.1.0 · Pending installer',
    'description' => 'Avesta finance and money-tracking application. The catalogue entry is available now; installation remains pending until the complete Windows installer is verified.',
    'meta' => ['WINDOWS', 'FINANCE', 'V1.1.0'],
    'features' => [
      'Money and finance tracking workflow',
      'Avesta software catalogue integration',
      'Installer acceptance required before release',
    ],
    'primary_label' => 'Installer pending verification',
    'primary_href' => '#moneytrack-release-status',
    'secondary_label' => 'Setup support',
    'secondary_href' => 'mailto:info@avesta.solutions?subject=Avesta%20MoneyTrack%201.1.0',
  ],
  [
    'id' => 'device-health',
    'name' => 'G.I.T Device Health',
    'short' => 'Device Health',
    'icon' => 'GH',
    'badge' => 'Test build',
    'description' => 'Authorized device-health reporting for IT teams that need visibility into performance, disk status, network reachability and Defender health.',
    'meta' => ['WINDOWS 10/11', 'X64', 'MONITORING'],
    'features' => [
      'CPU, memory, disk and network health',
      'Microsoft Defender status reporting',
      'Visible scheduled health reports',
    ],
    'primary_label' => 'Download installer',
    'primary_href' => '/downloads/g-it/G-IT-Setup-x64.exe',
    'primary_download' => true,
    'secondary_label' => 'Read setup notes',
    'secondary_href' => '/downloads/g-it/README.txt',
  ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Avesta Software · Applications & Downloads</title>
<meta name="description" content="Avesta Software hub for Avanto Sentinel, GIT Asset Tracker and G.I.T Device Health Monitoring, with Windows downloads and setup support.">
<meta name="theme-color" content="#234f3f">
<link rel="icon" href="/icons/icon-192.png">
<link rel="stylesheet" href="/assets/avesta/software-hub.css?v=20261004d">
<script src="/assets/avesta/software-tabs.js?v=20261004d" defer></script>
</head>
<body class="software-hub-page">
<div class="hub-shell">
<header class="hub-topbar">
  <div class="hub-topbar-inner">
    <a class="hub-brand" href="/" aria-label="Avesta Enterprises home">
      <span class="hub-brand-mark">AE</span>
      <span>AVESTA <small>SOFTWARE</small></span>
    </a>
    <nav class="hub-nav" aria-label="Software navigation">
      <a href="#products">Products</a>
      <a href="/index.php?page=it">IT Services</a>
      <a href="mailto:info@avesta.solutions?subject=Avesta%20Software%20support">Support</a>
      <a class="hub-home" href="/">Back to Avesta</a>
    </nav>
  </div>
</header>

<main class="hub-main">
  <section class="hub-hero" aria-labelledby="software-title">
    <div class="hub-hero-copy">
      <span class="hub-kicker">Avesta software ecosystem</span>
      <h1 id="software-title">Tools that keep your <span>operations visible.</span></h1>
      <p>One Avesta software hub for inventory operations, IT asset tracking and device-health monitoring. Select a product tab to view only the information you need.</p>
      <div class="hub-hero-actions">
        <a class="hub-button primary" href="#products">Explore software</a>
        <a class="hub-button secondary" href="mailto:info@avesta.solutions?subject=Avesta%20Software%20consultation">Request setup support</a>
      </div>
    </div>
    <aside class="hub-hero-panel" aria-label="Software catalogue status">
      <div class="hub-status-title">Catalogue status</div>
      <div class="hub-status-grid">
        <div class="hub-stat"><strong><?= count($products) ?></strong><span>Avesta software products</span></div>
        <div class="hub-stat"><strong>Tabbed</strong><span>Compact product interface</span></div>
        <div class="hub-stat"><strong>1</strong><span>Detail panel at a time</span></div>
        <div class="hub-stat"><strong>x64</strong><span>Primary Windows target</span></div>
      </div>
      <p class="hub-panel-note">Future software entries inherit the same tab, information panel and action layout automatically.</p>
    </aside>
  </section>

  <section id="products" aria-labelledby="products-title" class="hub-catalogue">
    <div class="hub-section-head">
      <div><span class="hub-kicker">Product catalogue</span><h2 id="products-title">Avesta applications</h2></div>
      <p>Choose one product. Its description, features and actions open below without adding extra page length.</p>
    </div>

    <div class="hub-product-tabs" role="tablist" aria-label="Avesta software products">
      <?php foreach ($products as $index => $product): ?>
      <button
        class="software-tab hub-product-tab"
        type="button"
        role="tab"
        id="tab-<?= htmlspecialchars($product['id']) ?>"
        aria-controls="<?= htmlspecialchars($product['id']) ?>"
        aria-selected="false"
        tabindex="<?= $index === 0 ? '0' : '-1' ?>">
        <span class="hub-tab-icon"><?= htmlspecialchars($product['icon']) ?></span>
        <span class="hub-tab-copy"><strong><?= htmlspecialchars($product['short']) ?></strong><small><?= htmlspecialchars($product['badge']) ?></small></span>
      </button>
      <?php endforeach; ?>
    </div>

    <div id="software-prompt" class="hub-product-prompt">
      Select a software tab to view its features, version details and download or support actions.
    </div>

    <div class="hub-product-panels">
      <?php foreach ($products as $product): ?>
      <article
        class="hub-product-panel<?= $product['id'] === 'sentinel' ? ' featured' : '' ?>"
        role="tabpanel"
        id="<?= htmlspecialchars($product['id']) ?>"
        aria-labelledby="tab-<?= htmlspecialchars($product['id']) ?>"
        hidden>
        <div class="hub-product-panel-main">
          <div class="hub-product-top">
            <div class="hub-product-icon"><?= htmlspecialchars($product['icon']) ?></div>
            <span class="hub-chip"><?= htmlspecialchars($product['badge']) ?></span>
          </div>
          <h3><?= htmlspecialchars($product['name']) ?></h3>
          <p class="hub-product-desc"><?= htmlspecialchars($product['description']) ?></p>
          <div class="hub-meta">
            <?php foreach ($product['meta'] as $meta): ?><span><?= htmlspecialchars($meta) ?></span><?php endforeach; ?>
          </div>
        </div>
        <div class="hub-product-panel-side">
          <ul class="hub-feature-list">
            <?php foreach ($product['features'] as $feature): ?><li><?= htmlspecialchars($feature) ?></li><?php endforeach; ?>
          </ul>
          <div class="hub-product-actions">
            <a class="hub-button primary" href="<?= htmlspecialchars($product['primary_href']) ?>"<?= !empty($product['primary_download']) ? ' download' : '' ?>><?= htmlspecialchars($product['primary_label']) ?></a>
            <a class="hub-button secondary" href="<?= htmlspecialchars($product['secondary_href']) ?>"<?= !empty($product['secondary_download']) ? ' download' : '' ?>><?= htmlspecialchars($product['secondary_label']) ?></a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="hub-support" aria-labelledby="support-title">
    <div><h2 id="support-title">Need help choosing or deploying?</h2><p>Avesta can help with installation, local-network setup, configuration, validation and user onboarding for supported products.</p></div>
    <a class="hub-button primary" href="mailto:info@avesta.solutions?subject=Avesta%20Software%20support">Contact software support</a>
  </section>

  <footer class="hub-footer">
    <span>Avesta Enterprises · Software &amp; IT Solutions</span>
    <span><a href="mailto:info@avesta.solutions">info@avesta.solutions</a> · <a href="/">avesta.solutions</a></span>
  </footer>
</main>
</div>
</body>
</html>
