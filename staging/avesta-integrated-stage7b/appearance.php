<?php
// Shared presentation helper for this isolated staging revision. Not authentication.
if (!defined('AV7_BOOTSTRAP')) require_once __DIR__ . '/staging-guard.php';
function avesta_review_theme_bootstrap(): void {
    echo '<meta name="color-scheme" content="light dark">';
    // Blocking, external same-origin initializer runs before body paint; CSP-compatible.
    echo '<script src="/avesta-integrated-stage7b/assets/avesta/appearance.js?v=20261018-stage7b-r1"></script>';
}
function avesta_review_theme_styles(): void {
    echo '<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/appearance.css?v=20261018-stage7b-r1">';
    echo '<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/site-polish.css?v=20261018-stage7b-r1">';
}
function avesta_review_appearance_control(): void {
    echo <<<'HTML'
<details class="av-appearance" data-appearance-control>
 <summary aria-label="Appearance settings"><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8"/><path d="M12 4v16"/></svg><span>Theme</span></summary>
 <div class="av-appearance-panel">
  <p class="av-appearance-heading">Make Avesta yours</p>
  <label class="av-appearance-field">Appearance<select data-appearance-select disabled><option value="system">Use device setting</option><option value="light">Light</option><option value="dark">Dark</option></select></label>
  <label class="av-appearance-check"><input type="checkbox" data-motion-toggle disabled><span>Reduce visual motion</span></label>
  <p class="av-appearance-help">Your device’s reduced-motion setting is always respected.</p>
  <p class="av-appearance-status" data-appearance-status role="status" aria-live="polite">Enable JavaScript to choose an appearance.</p>
  <button type="button" data-appearance-close class="av-appearance-close">Done</button>
 </div>
</details>
HTML;
}
