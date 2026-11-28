<?php
require_once __DIR__ . '/asset-version.php';
require_once __DIR__ . '/share-tags.php';
/* Every product the software hub lists.
 *
 * 'status' decides what the card offers, and it is the whole point of this
 * structure. On 28 November 2026 four of the six products on this page had a
 * Download button pointing at a file that was never committed: the asset
 * tracker ZIP, both Sentinel links and the MoneyTrack installer all returned
 * 404 to anybody who clicked them. A dead download on a business's own site
 * is worse than no download at all.
 *
 *   'available'      — the file is in this repository and really downloads.
 *                      A Download button is rendered ONLY for this, and only
 *                      after the file is confirmed to exist at render time.
 *   'on_request'     — built and working, but the build is not published
 *                      here. The card asks, rather than pretending.
 *   'in_development' — being written. Says so plainly.
 *
 * Nothing here may claim a version, a platform or a feature that does not
 * exist. A test checks every download link against the filesystem.
 */
$products = [
  [
    'id' => 'device-health',
    'name' => 'G.I.T Device Health',
    'short' => 'Device Health',
    'icon' => 'GH',
    'badge' => 'Test build',
    'status' => 'available',
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
  [
    'id' => 'sentinel',
    'name' => 'Avanto Sentinel',
    'short' => 'Sentinel',
    'icon' => 'AS',
    'badge' => 'Featured',
    'status' => 'on_request',
    'description' => 'Inventory operations for teams that need stock, purchasing, orders, requests and reporting in one shared local-network workflow.',
    'meta' => ['WINDOWS X64', 'LOCAL NETWORK', 'OPERATIONS'],
    'features' => [
      'Stock, purchasing, orders and team requests',
      'Shared across a local network',
      'Windows x64 deployment package',
    ],
  ],
  [
    'id' => 'asset-tracker',
    'name' => 'GIT Asset Tracker',
    'short' => 'Asset Tracker',
    'icon' => 'AT',
    'badge' => 'v1.5.1',
    'status' => 'on_request',
    'description' => 'Track computers, printers, networking equipment and other IT assets with searchable records, reporting and backup tools.',
    'meta' => ['WINDOWS', 'INVENTORY', 'REPORTING'],
    'features' => [
      'Inventory categories and change history',
      'Excel import/export and PDF reports',
      'JSON backup and recovery',
    ],
  ],
  [
    'id' => 'moneytrack',
    'name' => 'Avesta MoneyTrack',
    'short' => 'MoneyTrack',
    'icon' => 'MT',
    'badge' => 'v1.2.0',
    'status' => 'on_request',
    'description' => 'A personal money tracker in kwacha: income, spending and what is left, kept on your own machine.',
    'meta' => ['WINDOWS X64', 'ZMW', 'PERSONAL FINANCE'],
    'features' => [
      'Income and spending in kwacha',
      'Runs locally, no account needed',
      'Windows x64 build',
    ],
  ],
  [
    'id' => 'subscription-desk',
    'name' => 'Subscription Desk',
    'short' => 'Subscriptions',
    'icon' => 'SD',
    'badge' => 'v1.4.0',
    'status' => 'on_request',
    'description' => 'Connects to your Gmail or Outlook mailbox, finds the subscriptions you are paying for, and shows what falls due and when.',
    'meta' => ['WINDOWS X64', 'LINUX .DEB', 'EMAIL'],
    'features' => [
      'Finds subscriptions from your own mailbox',
      'Exact due date for each one',
      'Opens the provider\'s own cancel or change page',
    ],
  ],
  [
    'id' => 'netwatch',
    'name' => 'NetWatch',
    'short' => 'NetWatch',
    'icon' => 'NW',
    'badge' => 'In development',
    'status' => 'in_development',
    'description' => 'Watches an internet connection: speed, ping, public IP, and every outage and restore with the time it happened.',
    'meta' => ['WINDOWS X64', 'LINUX .DEB', 'MONITORING'],
    'features' => [
      'Speed and ping, tracked through the day',
      'Counts outages and restores, with times',
      'Notices when the line drops and comes back',
    ],
  ],
  [
    'id' => 'shieldscan',
    'name' => 'ShieldScan',
    'short' => 'ShieldScan',
    'icon' => 'SS',
    'badge' => 'In development',
    'status' => 'in_development',
    'description' => 'Scans a machine for malware and system errors, and sets out the fix for what it finds.',
    'meta' => ['WINDOWS X64', 'LINUX', 'SECURITY'],
    'features' => [
      'Malware and virus detection',
      'System error detection',
      'Tells you how to fix what it finds',
    ],
  ],
  [
    'id' => 'tonertrack',
    'name' => 'TonerTrack',
    'short' => 'TonerTrack',
    'icon' => 'TT',
    'badge' => 'In development',
    'status' => 'in_development',
    'description' => 'Tracks toner consumption across an office\'s printers through the year, built from the consumption workbook the office already keeps.',
    'meta' => ['WINDOWS X64', 'PRINTING', 'REPORTING'],
    'features' => [
      'Consumption per printer, over the year',
      'Built from an existing Excel workbook',
      'Windows x64 build',
    ],
  ],
  [
    'id' => 'bizpos',
    'name' => 'BizPOS',
    'short' => 'BizPOS',
    'icon' => 'BP',
    'badge' => 'v1.4.0',
    'status' => 'on_request',
    'description' => 'Point of sale for a retail counter, with ZRA item-wise reporting, payment methods, till float and a full activity log. Built for a Zambian trading business.',
    'meta' => ['WINDOWS', 'RETAIL', 'ZRA REPORTING'],
    'features' => [
      'ZRA item-wise reporting',
      'Payment methods and till float',
      'Activity log and permission controls',
    ],
  ],
];

/* The statuses, and what each one offers a visitor. */
const AV_STATUS = [
  'available'      => ['label' => 'Available now',  'tone' => 'ok'],
  'on_request'     => ['label' => 'On request',     'tone' => 'ask'],
  'in_development' => ['label' => 'In development', 'tone' => 'soon'],
];

/**
 * What a product's card offers.
 *
 * A Download button is rendered only when the status says available AND the
 * file is actually on disk. The second half is the belt to the braces: a
 * status left on 'available' after a build is removed would otherwise put a
 * 404 in front of a visitor again, which is the exact bug this replaces.
 */
function av_product_actions(array $p): array
{
    $href = (string) ($p['primary_href'] ?? '');
    $real = $href !== '' && is_file(__DIR__ . '/' . ltrim($href, '/'));
    $subject = rawurlencode('Avesta Software — ' . $p['name']);

    if (($p['status'] ?? '') === 'available' && $real) {
        $actions = [['label' => $p['primary_label'] ?? 'Download', 'href' => $href,
                     'download' => true, 'kind' => 'primary']];
        if (!empty($p['secondary_href'])) {
            $actions[] = ['label' => $p['secondary_label'] ?? 'Read the notes',
                          'href' => $p['secondary_href'],
                          'download' => !empty($p['secondary_download']), 'kind' => 'secondary'];
        }
        return $actions;
    }

    $ask = ($p['status'] ?? '') === 'in_development' ? 'Tell me when it is ready' : 'Ask for a copy';
    return [
        ['label' => $ask, 'kind' => 'primary',
         'href' => 'mailto:info@avesta.solutions?subject=' . $subject],
        ['label' => 'WhatsApp', 'kind' => 'secondary',
         'href' => 'https://wa.me/260769974200?text=' . rawurlencode('Hello Avesta, I am interested in ' . $p['name'] . '.')],
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php av_share_head('downloads', false, [
  'title' => 'Avesta Software · Applications & Downloads',
  'desc'  => 'Avesta Software hub for Avanto Sentinel, GIT Asset Tracker, G.I.T Device Health '
           . 'and Avesta MoneyTrack, with Windows downloads and setup support.',
]); ?>
<meta name="theme-color" content="#234f3f">
<link rel="icon" href="/icons/icon-192.png">
<link rel="stylesheet" href="<?= av_asset('/assets/avesta/software-hub.css') ?>">
<script src="<?= av_asset('/assets/avesta/software-tabs.js') ?>" defer></script>
<link rel="stylesheet" href="<?= av_asset('/assets/avesta/sitewide-motion.css') ?>">
<link rel="stylesheet" href="<?= av_asset('/assets/avesta/backdrop.css') ?>">
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
  <section class="hub-hero" data-backdrop="it" aria-labelledby="software-title">
    <div class="hub-hero-copy">
      <span class="hub-kicker">Avesta software ecosystem</span>
      <h1 id="software-title">Tools that keep your <span>operations visible.</span></h1>
      <p>One hub for the software Avesta builds: inventory and point of sale, IT asset tracking, device and network monitoring, subscriptions, printing and personal finance. Each card says plainly whether it is ready to download, available on request, or still being written.</p>
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
            <?php $av_s = AV_STATUS[$product['status']] ?? AV_STATUS['on_request']; ?>
            <span class="hub-status" data-tone="<?= $av_s['tone'] ?>"><?= htmlspecialchars($av_s['label']) ?></span>
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
            <?php foreach (av_product_actions($product) as $av_a): ?>
            <a class="hub-button <?= $av_a['kind'] ?>" href="<?= htmlspecialchars($av_a['href'], ENT_QUOTES) ?>"<?= !empty($av_a['download']) ? ' download' : '' ?><?= str_starts_with($av_a['href'], 'http') ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= htmlspecialchars($av_a['label']) ?></a>
            <?php endforeach; ?>
            <?php if (($product['status'] ?? '') !== 'available'): ?>
            <p class="hub-note"><?= $product['status'] === 'in_development'
              ? 'Not finished yet, so there is nothing to download.'
              : 'Working, but the build is not published here yet.' ?></p>
            <?php endif; ?>
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
