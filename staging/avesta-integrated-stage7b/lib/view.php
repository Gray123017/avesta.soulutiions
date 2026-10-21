<?php
declare(strict_types=1);
if (!defined('AV7_BOOTSTRAP')) { http_response_code(404); exit; }
require_once dirname(__DIR__).'/appearance.php';
require_once dirname(__DIR__).'/guide.php';
function av7_e(string $s): string { return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function av7_role_label(string $role): string { return match($role) {'borrower'=>'Customer','staff'=>'Staff','admin'=>'Administrator','super_admin'=>'Super Administrator',default=>'Unavailable'}; }
function av7_head(string $title,?array $user=null): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="theme-color" content="#163e33"><title>'.av7_e($title).' · Avesta Stage 7B</title>';
    avesta_review_theme_bootstrap();
    echo '<link rel="icon" href="./icons/icon-192.png"><link rel="stylesheet" href="./assets/avesta/styles.css?v=20261018-stage7b-r1"><link rel="stylesheet" href="./assets/avesta/responsive.css?v=20261018-stage7b-r1">';
    avesta_review_theme_styles();
    echo '<link rel="stylesheet" href="./assets/avesta/auth-portal.css?v=20261018-stage7b-r1"><script src="./assets/avesta/auth-portal.js?v=20261018-stage7b-r1" defer></script>';
    // The guide only receives its public catalogue; it never receives $user or $rows.
    avesta_guide_assets();
    echo '</head><body class="ab-body"><a class="skip" href="#main">Skip to content</a><div class="ab-strip">PRIVATE STAGING · STAGE 7B · SERVER SIGN-IN / FICTIONAL RECORDS ONLY</div><header class="ab-header"><div class="ab-head-inner"><a class="brand" href="index.php" aria-label="Avesta Enterprises home"><span class="brand-mark">AE<span></span></span><span>AVESTA<small>ENTERPRISES</small></span></a><nav class="ab-desktop" aria-label="Main navigation"><a href="index.php?page=lending">Lending</a><a href="index.php?page=it">IT Consulting</a><a href="index.php?page=software">Software</a><a href="review.php">Review centre</a></nav><div class="ab-head-actions">';
    avesta_review_appearance_control();
    echo '<button type="button" class="ab-menu-toggle" id="ab-menu-toggle" aria-expanded="false" aria-controls="ab-menu">☰ <span>Menu</span></button></div></div><nav class="ab-menu" id="ab-menu" aria-label="Mobile navigation" hidden><a href="index.php">Home</a><a href="index.php?page=lending">Lending</a><a href="index.php?page=it">IT Consulting</a><a href="index.php?page=software">Software</a><a href="portal.php">Account portal</a><a href="review.php">Review centre</a></nav></header>';
}
function av7_foot(): void {
    echo '<footer class="ab-footer"><div class="ab-wrap"><strong>AVESTA ENTERPRISES</strong><p>Balanced Enterprise · Stage 7B private review</p><p>Business records are fictional and read-only. No production accounts, submissions, repayments, uploads, messages or downloads are connected.</p><a href="index.php">Return to this review site</a> <span>·</span> <a href="review.php">Review checklist</a><p class="ab-small">Leaving a page does not sign you out. Use the server Sign out button when finished.</p></div></footer>';
    avesta_guide_widget();
    echo '</body></html>';
}
function av7_fail(int $status,string $message): never {
    http_response_code($status);
    if (basename($_SERVER['SCRIPT_NAME']??'')==='auth-api.php') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>$message,'code'=>$status]);
    } else {
        av7_head('Access check');
        $heading=match($status){401=>'Please sign in again.',403=>'This access is not available.',404=>'Record not available.',429=>'Please wait before retrying.',503=>'Authentication paused or unavailable.',default=>'Check this request.'};
        echo '<main id="main" tabindex="-1" class="ab-wrap ab-error"><p class="ab-kicker">ACCOUNT ACCESS / HTTP '.$status.'</p><h1>'.av7_e($heading).'</h1><div class="ab-alert" role="alert"><p>'.av7_e($message).'</p></div><p>No protected record has been included in this response. Use only disposable Stage 7B credentials.</p><div class="ab-actions"><a class="ab-button" href="signin.php">Open test sign-in</a><a class="ab-button ab-outline" href="index.php">Back to the public preview</a></div><p class="ab-small">An initial 503 is expected while this candidate’s authentication is disabled. If storage or HTTPS checks fail, do not relax the security settings.</p></main>';
        av7_foot();
    }
    exit;
}
function av7_login_page(string $message=''): void {
    av7_head('Test account sign-in');
    echo '<main id="main" tabindex="-1" class="ab-wrap ab-login"><section class="ab-intro"><p class="ab-kicker">ONE IDENTITY. THE RIGHT WORKSPACE.</p><h1>Your account.<br><em>Your permitted view.</em></h1><p class="ab-lead">The Avesta portal now takes its identity from a server-validated session. Each workspace receives only the fictional records that this test account is permitted to read.</p><div class="ab-benefits"><div><span>01</span><p><strong>One sign-in</strong>Customer, staff and administrator views follow your test identity automatically.</p></div><div><span>02</span><p><strong>Checked on the server</strong>Ownership, assignment and account status are checked before records reach this page.</p></div><div><span>03</span><p><strong>The design you approved</strong>Same green and gold identity, with Light, Dark and device appearance throughout.</p></div></div><div class="ab-note"><strong>A controlled test, not a production launch.</strong> No real applications, payment records or customer documents are connected.</div></section><section class="ab-signin-card" aria-labelledby="signin-title"><p class="ab-kicker">DISPOSABLE STAGE 7B ACCOUNTS</p><h2 id="signin-title">Sign in to your workspace</h2><p>Use the new Stage 7B credential sheet. Stage 7A, Hostinger and production passwords will not work here.</p>';
    if($message!=='') echo '<div class="ab-alert" role="status">'.av7_e($message).'</div>';
    echo '<form method="post" action="login.php" autocomplete="off" class="ab-login-form"><input type="hidden" name="csrf" value="'.av7_e(av7_csrf()).'"><label for="username">Test username</label><input id="username" name="username" type="text" maxlength="64" required autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="demo.borrower.a"><label for="password">Disposable test password</label><input id="password" name="password" type="password" maxlength="128" required autocomplete="off"><button type="submit" class="ab-button">Sign in to account portal <span aria-hidden="true">→</span></button></form><div class="ab-gates"><strong>Two separate gates</strong><p>Hostinger’s password protects the staging folder. This form starts the new application session.</p></div><p class="ab-small">No public registration or password reset is enabled. Do not reuse genuine passwords.</p><a href="recovery.php" class="ab-text-link">Read the account-help boundary</a></section></main>';
    av7_foot();
}
function av7_workspace_page(array $u,array $allowed,string $view,?array $record): void {
    $role=av7_role_label($u['role']);
    $titles=['overview'=>$role.' overview','applications'=>'Applications','support'=>'Support requests','account'=>'My test account','privileged'=>'Privileged identity boundary'];
    $scope=match($u['role']){'borrower'=>'Your own fictional applications and support requests.','staff'=>'Only fictional work assigned to this test staff account.','admin'=>'Operational read-only access to all five fictional records.','super_admin'=>'Read-only test oversight with a distinct privileged identity.'};
    $title=$record?'Record details':$titles[$view];
    av7_head($title,$u);
    echo '<main id="main" tabindex="-1" class="ab-portal"><section class="ab-hero"><div class="ab-wrap"><p class="ab-kicker">SERVER-VERIFIED IDENTITY / '.av7_e($role).'</p><h1>Welcome, '.av7_e($u['name']).'.</h1><p>'.av7_e($scope).'</p></div></section><div class="ab-wrap ab-workarea"><div class="ab-scope-note"><strong>Read-only staging account.</strong> You are signed in—not using a role switcher. Records are synthetic; permissions here do not change the production staff policy.</div><div class="ab-layout"><aside class="ab-side"><p class="ab-kicker">WORKSPACE</p><nav aria-label="Account navigation">';
    foreach($titles as $id=>$label) {
        if($id==='privileged' && $u['role']!=='super_admin')continue;
        $label=$id==='overview'?'Overview':$label;
        echo '<a href="portal.php?view='.$id.'"'.($view===$id?' aria-current="page"':'').'>'.av7_e($label).'</a>';
    }
    echo '</nav><div class="ab-signed-as"><span>Signed in as</span><strong>'.av7_e($u['username']).'</strong><small>'.av7_e($u['role']).'</small></div><form method="post" action="logout.php" data-signout><input type="hidden" name="csrf" value="'.av7_e(av7_csrf()).'"><button class="ab-button ab-outline" type="submit">Sign out</button></form><p class="ab-small">To test another role, sign out and use its separate credentials.</p></aside><section class="ab-content" id="ab-protected" data-protected-view aria-labelledby="ab-title"><div class="ab-section-head"><div><p class="ab-kicker">'.($record?'FICTIONAL RECORD':'YOUR WORKSPACE').'</p><h2 id="ab-title">'.av7_e($title).'</h2></div><span class="ab-readonly">READ ONLY</span></div>';
    if($record!==null) { av7_detail_view($record); }
    elseif($view==='account') { av7_identity_view($u); }
    elseif($view==='privileged') { echo '<div class="ab-card"><h3>Super Administrator identity confirmed</h3><p>This view is restricted on the server to the <code>super_admin</code> test account. An ordinary administrator cannot access it.</p><p>Credential changes, role management and privileged mutations remain disabled.</p><a class="ab-text-link" href="auth-api.php?action=privileged-summary">Inspect read-only boundary JSON</a></div>'; }
    else {
        $apps=array_values(array_filter($allowed,fn($r)=>$r['kind']==='application'));
        $support=array_values(array_filter($allowed,fn($r)=>$r['kind']==='support'));
        $stats=[['Applications',count($apps)],['Support requests',count($support)],['All permitted records',count($allowed)]];
        echo '<div class="ab-stats">';foreach($stats as [$label,$value]) echo '<div class="ab-stat"><span>'.av7_e($label).'</span><strong>'.$value.'</strong><small>Fictional records in your scope</small></div>';echo '</div>';
        $rows=match($view){'applications'=>$apps,'support'=>$support,default=>$allowed};
        echo '<div class="ab-card ab-filter-card"><div class="ab-filterbar" aria-label="Filter permitted sample records"><label for="ab-search">Search visible records<input id="ab-search" type="search" maxlength="100" autocomplete="off" placeholder="Sample reference or title" disabled></label><label for="ab-filter-status">Status<select id="ab-filter-status" disabled><option value="all">All statuses</option>';
        $states=array_unique(array_column($rows,'status'));sort($states);foreach($states as $s) echo '<option value="'.av7_e($s).'">'.av7_e($s).'</option>';
        echo '</select></label><button id="ab-clear-filters" class="ab-button ab-outline" type="button" disabled>Clear</button></div><p id="ab-result-count" role="status" aria-live="polite">'.count($rows).' permitted sample record'.(count($rows)===1?'':'s').' shown</p><p class="ab-small">Filters apply only to the records already authorized by the server. Search text stays on this page.</p></div><div class="ab-record-list">';
        foreach($rows as $r)av7_card($r);
        echo '</div><p class="ab-empty" id="ab-empty"'.($rows?' hidden':'').'>No matching permitted records. Clear the filters or choose another section.</p><div class="ab-feature-boundary"><h3>Read first. Connect actions later.</h3><p>Application review, staff assignment, repayments and account edits are not enabled in this release. The five-step form and IT enquiry remain separate simulations and do not create these records.</p></div>';
    }
    echo '<p class="ab-small ab-expiry">15-minute inactivity limit · 2-hour session limit. Reload or open a protected page to revalidate access. Sign out when finished.</p></section></div></div></main>';
    av7_foot();
}
function av7_card(array $r): void {
    echo '<article class="ab-card ab-record" data-record-id="'.av7_e($r['id']).'" data-record-status="'.av7_e($r['status']).'"><div class="ab-record-top"><span class="ab-reference">'.av7_e($r['id']).'</span><span class="ab-status">'.av7_e($r['status']).'</span></div><h3>'.av7_e($r['title']).'</h3><p class="ab-record-kind">'.($r['kind']==='application'?'Application':'IT support').'</p><dl class="ab-record-facts"><div><dt>Customer</dt><dd>'.av7_e($r['owner_label']).'</dd></div><div><dt>Assigned to</dt><dd>'.av7_e($r['assignee_label']).'</dd></div></dl><p>'.av7_e($r['sample_note']).'</p><a class="ab-text-link" href="portal.php?id='.rawurlencode($r['id']).'">Read permitted record <span aria-hidden="true">↗</span></a></article>';
}
function av7_detail_view(array $r): void {
    echo '<article class="ab-card ab-detail"><p class="ab-reference">'.av7_e($r['id']).'</p><h3>'.av7_e($r['title']).'</h3><span class="ab-status">'.av7_e($r['status']).'</span><dl class="ab-detail-grid">';
    foreach(['Record type'=>$r['kind'],'Customer'=>$r['owner_label'],'Assigned to'=>$r['assignee_label'],'Current status'=>$r['status']] as $k=>$v)echo '<div><dt>'.av7_e($k).'</dt><dd>'.av7_e($v).'</dd></div>';
    echo '</dl><p>'.av7_e($r['sample_note']).'</p><div class="ab-note"><strong>No invented history or financial balance</strong><p>This read-only fixture contains no payment ledger, documents or timestamped workflow history. Those are not inferred from its status label.</p></div><a class="ab-button ab-outline" href="portal.php?view='.($r['kind']==='application'?'applications':'support').'">Back to permitted records</a></article>';
}
function av7_identity_view(array $u): void {
    echo '<section class="ab-card"><h3>Your authenticated test identity</h3><dl class="ab-detail-grid">';
    foreach(['Name'=>$u['name'],'Username'=>$u['username'],'Backend role'=>$u['role'],'Workspace'=>av7_role_label($u['role'])] as $k=>$v)echo '<div><dt>'.av7_e($k).'</dt><dd>'.av7_e($v).'</dd></div>';
    echo '</dl><p>Identity is resolved from the current account store on every protected request. This page has no controls to choose another identity, create accounts or reset passwords.</p><a class="ab-text-link" href="auth-api.php?action=me">Inspect my identity JSON</a><hr><h3>Account and recovery actions</h3><p>Real registration, password recovery and privileged account management are not connected. Keep the disposable credentials on your computer.</p><a href="recovery.php" class="ab-text-link">Read recovery notice</a></section>';
}
// Legacy direct records.php entry stays guarded and uses the same authenticated presentation.
function av7_record_page(array $u,array $rows,bool $single): void { av7_workspace_page($u,av7_visible($u),'overview',$single?($rows[0]??null):null); }
