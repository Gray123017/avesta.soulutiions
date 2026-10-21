<?php
// Shared local-guide markup; no authentication, API, logging or mutable storage.
if (!defined('AV7_BOOTSTRAP')) require_once __DIR__ . '/staging-guard.php';
function avesta_guide_assets(): void {
    echo '<link rel="stylesheet" href="/avesta-integrated-stage7b/assets/avesta/guide.css?v=20261018-stage7b-r1">';
    echo '<script type="module" src="/avesta-integrated-stage7b/assets/avesta/guide.js?v=20261018-stage7b-r1"></script>';
}
function avesta_guide_widget(): void {
    $public = [];
    foreach (['help', 'services'] as $name) {
        $path = __DIR__ . '/assets/avesta/' . $name . '.json';
        $bytes = is_readable($path) ? @file_get_contents($path, false, null, 0, 262145) : false;
        $public[$name] = $bytes !== false && strlen($bytes) <= 262144 ? json_decode($bytes, true) : null;
    }
    echo '<script type="application/json" id="av-guide-data">' . json_encode($public, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . '</script>';
    echo <<<'HTML'
<div class="av-guide" id="av-guide">
 <button type="button" class="ag-launch" id="ag-launch" aria-expanded="false" aria-controls="ag-panel" hidden><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="14" rx="3"/><path d="M8 21h8M12 17v4M7 9l3 3 7-7"/></svg><span>G.I.T Guide<small>Local demo</small></span></button>
 <section class="ag-panel" id="ag-panel" role="region" aria-labelledby="ag-title" hidden>
  <div class="ag-header"><div><span class="ag-eyebrow">AVESTA / GUIDED HELP</span><h2 id="ag-title" tabindex="-1">G.I.T Assistant</h2><p>Saved guidance · No live AI connection</p></div><div class="ag-controls"><button type="button" id="ag-minimize" aria-label="Minimize guide, keeping this tab’s conversation">−</button><button type="button" id="ag-close" aria-label="Close guide and clear conversation">×</button></div></div>
  <div class="ag-scroll" id="ag-scroll"><p class="ag-privacy" id="ag-privacy">Use fictional examples only. This guide cannot read your form, account or device. Nothing is sent or saved by the guide.</p><div class="ag-log" id="ag-log" role="log" aria-live="polite" aria-relevant="additions text" aria-label="Local guidance conversation"></div></div>
  <div class="ag-quick" role="group" aria-label="Sample guide topics"><button type="button" data-ag-query="How it works">Loan steps</button><button type="button" data-ag-query="Printer offline">Printer offline</button><button type="button" data-ag-query="Software tools">Software tools</button><button type="button" data-ag-query="Account portal">Account portal</button></div>
  <form id="ag-form" class="ag-form" autocomplete="off" novalidate><label for="ag-query">Ask about a fictional example</label><textarea id="ag-query" rows="2" maxlength="300" autocomplete="off" spellcheck="false" aria-describedby="ag-privacy ag-input-note" placeholder="For example: printer offline"></textarea><div class="ag-form-actions"><button type="submit" class="ag-primary">Find guidance</button><button type="button" id="ag-clear" class="ag-clear">Clear</button><span id="ag-count">0 / 300</span></div><p id="ag-input-note">Enter adds a line. Use Find guidance to search. Close or leave the page to clear; minimize keeps this tab’s conversation.</p><p id="ag-status" role="status" aria-live="polite"></p></form>
 </section>
</div>
HTML;
}
