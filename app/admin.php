<?php
/**
 * Second line of defence. .htaccess is not honoured by nginx, and some hosts
 * ignore it entirely — so this file guards itself rather than trusting the
 * web server to hide it. Fetching it directly still hits the login.
 */
require_once __DIR__ . '/../auth.php';
$me = av_require_login(['super_admin', 'admin', 'staff'], '../login.php');
echo '<script>window.AV_USER=' . json_encode([
    'id' => $me['id'], 'name' => $me['name'], 'username' => $me['username'], 'role' => $me['role'],
], JSON_UNESCAPED_SLASHES) . ';</script>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Avesta Enterprises — Admin Portal</title>
<style>
:root{
  --navy:#163E33;--navy2:#0F2E24;--gold:#D98E3B;--gold2:#E8AE6E;
  --green:#198754;--red:#c0392b;--orange:#e67e22;--blue:#2980b9;
  --lgray:#F7F3EC;--mgray:#e0e0e0;--dgray:#444;--white:#fff;
  --sidebar:260px;--radius:10px;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:Arial,sans-serif;background:#eef1f5;color:var(--dgray);min-height:100vh;-webkit-font-smoothing:antialiased}
::selection{background:var(--gold);color:var(--navy)}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(217,142,59,.45);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--navy)}

/* ── GLOBAL MOTION BASELINE ─────────────────────────────────────────────── */
button,.nav-item,.record-card,.doc-tile,.stat-card{transition:transform .15s ease,box-shadow .2s ease,background-color .2s ease,border-color .2s ease,opacity .2s ease}
button:not(:disabled):active{transform:scale(.97)}

/* ── SIDEBAR ── */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sidebar);background:var(--navy2);display:flex;flex-direction:column;z-index:220;overflow-y:auto;transition:transform .3s cubic-bezier(.22,.61,.36,1)}
.sidebar-header{padding:20px 18px 16px;border-bottom:1px solid rgba(255,255,255,.08)}
.sb-logo{display:flex;align-items:center;gap:8px;margin-bottom:4px}
.sb-ae{background:var(--gold);color:var(--navy);font-weight:700;font-size:13pt;padding:3px 8px;border-radius:4px;font-family:Georgia,serif}
.sb-name{color:white;font-family:Georgia,serif;font-size:10pt;font-weight:700}
.sb-tag{color:rgba(255,255,255,.4);font-size:6.5pt;letter-spacing:1.5px;text-transform:uppercase;margin-top:2px}
.sb-admin-badge{margin-top:10px;display:inline-block;background:rgba(217,142,59,.15);border:1px solid rgba(217,142,59,.4);color:var(--gold);font-size:7pt;letter-spacing:1.5px;text-transform:uppercase;padding:3px 10px;border-radius:20px}
.sidebar-nav{flex:1;padding:12px 0}
.nav-section{padding:8px 18px 4px;color:rgba(255,255,255,.3);font-size:6pt;letter-spacing:2px;text-transform:uppercase;font-weight:600;margin-top:8px}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 18px;color:rgba(255,255,255,.65);cursor:pointer;transition:all .15s;border-left:3px solid transparent;font-size:9.5pt;text-decoration:none}
.nav-item:hover{background:rgba(255,255,255,.06);color:white}
.nav-item.active{background:rgba(217,142,59,.12);color:var(--gold);border-left-color:var(--gold)}
.nav-icon{font-size:13pt;min-width:20px;text-align:center}
.nav-badge{background:var(--red);color:white;font-size:7pt;padding:2px 6px;border-radius:20px;margin-left:auto;font-weight:700}
.sidebar-footer{padding:14px 18px;border-top:1px solid rgba(255,255,255,.08)}
.lock-btn{display:flex;align-items:center;gap:8px;color:rgba(255,255,255,.5);font-size:8.5pt;cursor:pointer;background:none;border:none;font-family:Arial,sans-serif;padding:0;transition:color .2s;width:100%}
.lock-btn:hover{color:var(--gold)}

/* ── MAIN ── */
.main{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column}
.topbar{background:rgba(255,255,255,.97);backdrop-filter:blur(8px);padding:0 16px 0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(0,0,0,.07);box-shadow:0 2px 8px rgba(0,0,0,.06);position:sticky;top:0;z-index:170;overflow:visible}
.topbar-title{font-size:13pt;font-weight:700;color:var(--navy)}
.topbar-right{display:flex;align-items:center;gap:14px}
.topbar-search{padding:8px 14px;border:1.5px solid var(--mgray);border-radius:20px;font-size:9pt;outline:none;width:220px;transition:border-color .2s}
.topbar-search:focus{border-color:var(--gold)}
.topbar-date{color:#888;font-size:8.5pt}
.content{flex:1;padding:28px;overflow-y:auto}

/* ── PAGES ── */
.page{display:none}
.page.active{display:block;opacity:1;animation:pageIn .4s cubic-bezier(.22,.61,.36,1) backwards}
@keyframes pageIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}

/* ── STAT CARDS ── */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:28px}
.stat-card{background:white;border-radius:14px;padding:22px 20px;box-shadow:0 4px 16px rgba(0,0,0,.07);border-top:4px solid var(--gold);position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.1)}
.stat-card::after{content:attr(data-icon);position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:32pt;opacity:.08}
.sc-value{font-size:26pt;font-weight:700;color:var(--navy);line-height:1}
.sc-label{font-size:8pt;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-top:6px}
.sc-sub{font-size:8pt;color:#aaa;margin-top:4px}
.stat-card.green{border-top-color:var(--green)}.stat-card.green .sc-value{color:var(--green)}
.stat-card.red{border-top-color:var(--red)}.stat-card.red .sc-value{color:var(--red)}
.stat-card.orange{border-top-color:var(--orange)}.stat-card.orange .sc-value{color:var(--orange)}
.stat-card.blue{border-top-color:var(--blue)}.stat-card.blue .sc-value{color:var(--blue)}

/* ── TABLE ── */
.card{background:white;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.07),0 1px 4px rgba(0,0,0,.04);overflow:hidden;margin-bottom:24px}
.card-header{padding:16px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.card-title{font-size:11pt;font-weight:700;color:var(--navy)}
.card-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.btn{padding:8px 16px;border-radius:8px;font-size:8.5pt;font-weight:700;cursor:pointer;border:none;font-family:Arial,sans-serif;transition:all .2s cubic-bezier(.22,.61,.36,1);display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:var(--navy);color:white}.btn-primary:hover{background:var(--gold);color:var(--navy)}
.btn-gold{background:var(--gold);color:var(--navy);box-shadow:0 2px 10px rgba(217,142,59,.3)}.btn-gold:hover{background:var(--gold2);box-shadow:0 4px 14px rgba(217,142,59,.4);transform:translateY(-1px)}
.btn-ghost{background:transparent;border:1.5px solid var(--mgray);color:#666}.btn-ghost:hover{border-color:#999}
.btn-danger{background:var(--red);color:white}.btn-danger:hover{background:#a93226}
.btn-sm{padding:5px 10px;font-size:7.5pt}
.filter-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px 20px;background:#fafafa;border-bottom:1px solid #f0f0f0}
.filter-select{padding:7px 10px;border:1.5px solid var(--mgray);border-radius:6px;font-size:8.5pt;outline:none;background:white}
.filter-select:focus{border-color:var(--gold)}
.filter-label{font-size:8pt;color:#888;white-space:nowrap}
table{width:100%;border-collapse:collapse;font-size:8.5pt}
th{background:#f8f8f8;color:var(--navy);padding:10px 14px;text-align:left;font-size:7.5pt;text-transform:uppercase;letter-spacing:.5px;font-weight:700;white-space:nowrap;border-bottom:2px solid var(--mgray)}
td{padding:11px 14px;border-bottom:1px solid #f5f5f5;vertical-align:middle;color:#444}
tr:hover td{background:#fafbfc}
tr:last-child td{border-bottom:none}
.td-name{font-weight:700;color:var(--navy)}
.td-amount{font-weight:700;color:var(--navy);font-family:Georgia,serif}

/* ── STATUS BADGE ── */
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:7pt;font-weight:700;letter-spacing:.5px;text-transform:uppercase;white-space:nowrap}
@keyframes pulse-dot{0%,100%{box-shadow:0 0 0 2px rgba(37,211,102,.3)}50%{box-shadow:0 0 0 5px rgba(37,211,102,.1)}}
.badge-new{background:#e8f4f8;color:#2980b9}
.badge-review{background:#fef9e7;color:#e67e22}
.badge-approved{background:#eafaf1;color:#198754}
.badge-disbursed{background:#d5f5e3;color:#0e6b3c}
.badge-repaid{background:#d1f2eb;color:#0a5c4a}
.badge-rejected{background:#fdf2f2;color:#c0392b}

/* ── STATUS SELECT ── */
.status-sel{border:1px solid var(--mgray);border-radius:4px;padding:4px 8px;font-size:8pt;outline:none;background:white;cursor:pointer}
.status-sel:focus{border-color:var(--gold)}

/* ── EMPTY STATE ── */
.empty-state{text-align:center;padding:60px 20px;color:#bbb}
.empty-icon{font-size:48pt;margin-bottom:12px}
.empty-title{font-size:12pt;color:#999;margin-bottom:6px;font-weight:600}
.empty-sub{font-size:9pt}

/* ── DETAIL MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9000;align-items:flex-start;justify-content:center;padding:24px;overflow-y:auto}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:14px;width:100%;max-width:820px;overflow:hidden;margin:auto}
.modal-header{background:var(--navy);color:white;padding:18px 24px;display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:12pt;font-weight:700}
.modal-close{background:rgba(255,255,255,.15);border:none;color:white;width:32px;height:32px;border-radius:50%;font-size:14pt;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1}
.modal-close:hover{background:rgba(255,255,255,.25)}
.modal-body{padding:24px;max-height:75vh;overflow-y:auto}
.modal-section{margin-bottom:22px}
.modal-section-title{font-size:9pt;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid #f0f0f0;padding-bottom:6px;margin-bottom:12px}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px 20px}
.detail-item label{font-size:7pt;color:#aaa;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:2px}
.detail-item span{font-size:9.5pt;color:#333;font-weight:600;word-break:break-word}
.doc-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-top:8px}
.doc-tile{border:2px dashed #ddd;border-radius:8px;padding:14px 8px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa}
.doc-tile:hover{border-color:var(--gold);background:#fffbf5}
.doc-tile.has-doc{border-style:solid;border-color:var(--green);background:#f0fff4}
.doc-tile-icon{font-size:20pt;margin-bottom:6px}
.doc-tile-label{font-size:7pt;color:#555;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.doc-tile-status{font-size:7pt;margin-top:4px}
.sig-preview{border:1px solid #e8e8e8;border-radius:6px;padding:10px;text-align:center;background:#f9f9f9;margin-top:6px}
.sig-preview img{max-height:60px;max-width:100%}
.modal-footer{padding:16px 24px;background:#f8f8f8;border-top:1px solid #f0f0f0;display:flex;gap:10px;justify-content:flex-end}

/* ── DOC VIEWER ── */
#doc-viewer{display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;align-items:center;justify-content:center;padding:20px}
#doc-viewer.open{display:flex}
.dv-wrap{background:white;border-radius:12px;max-width:92vw;max-height:90vh;overflow:hidden;position:relative;min-width:320px;display:flex;flex-direction:column}
.dv-header{background:var(--navy);padding:12px 18px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-shrink:0}
.dv-title{color:white;font-weight:700;font-size:9.5pt;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dv-body{overflow:auto;text-align:center;padding:16px;flex:1}

/* ── CHARTS / MINI BARS ── */
.mini-bar-wrap{display:flex;flex-direction:column;gap:8px;margin-top:4px}
.mini-bar-row{display:flex;align-items:center;gap:10px;font-size:8pt}
.mini-bar-label{min-width:90px;color:#666;white-space:nowrap}
.mini-bar-track{flex:1;background:#f0f0f0;border-radius:4px;height:10px;overflow:hidden}
.mini-bar-fill{height:100%;background:linear-gradient(to right,var(--navy),var(--gold));border-radius:4px;transition:width .5s ease}
.mini-bar-val{min-width:50px;text-align:right;color:var(--navy);font-weight:700}

/* ── SPIN ANIMATION ── */
@keyframes spin { to { transform: rotate(360deg); } }

/* ── SYNC BANNER ── */
#sync-banner { pointer-events: none; }

/* ── MODAL SECTION ENHANCEMENTS ── */
.modal-section-title{font-size:8.5pt;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid var(--gold);padding-bottom:6px;margin-bottom:12px}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px 20px}
.detail-item label{font-size:7pt;color:#aaa;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:2px}
.detail-item span{font-size:9.5pt;color:#333;font-weight:600;word-break:break-word;line-height:1.4}
.rv-divider-admin{grid-column:1/-1;font-size:8pt;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:.8px;padding:8px 0 4px;border-top:1px dashed #e0e0e0;margin-top:4px}

/* ── PRINT ── */
@media print{
  .sidebar,.topbar,.modal-overlay,.btn,.card-actions,.filter-row{display:none!important}
  .main{margin-left:0}
  .content{padding:0}
  body{background:white}
}

/* ── RESPONSIVE ── */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);z-index:1002;visibility:hidden;pointer-events:none;box-shadow:10px 0 32px rgba(0,0,0,.28)}
  .sidebar.open{transform:translateX(0);visibility:visible;pointer-events:auto}
  .main{margin-left:0}
  .stats-row{grid-template-columns:1fr 1fr}
  .detail-grid{grid-template-columns:1fr 1fr}
}


/* Keep the mobile header compact and notification settings within the viewport. */
#wa-panel{max-height:calc(100dvh - 160px);overflow-y:auto!important}
@media(max-width:768px){
  .topbar{height:auto;min-height:60px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:10px 12px}
  .topbar>div:first-child{min-width:0}
  .topbar-title{font-size:11pt;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .topbar-right{grid-column:1/-1;width:100%;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}
  .topbar-search{width:100%;min-width:0;min-height:42px;font-size:16px}
  #wa-btn{min-width:44px;min-height:42px}
  .topbar-date{grid-column:1/-1;width:100%;font-size:7.5pt}
  .content{padding:16px 12px}
  #wa-panel{position:fixed!important;top:12px!important;right:12px!important;left:12px!important;width:auto!important;max-height:calc(100dvh - 24px);z-index:1100!important;border-radius:14px!important}
}
@media(max-width:520px){
  .stats-row,.detail-grid{grid-template-columns:1fr}
  .card-header{align-items:flex-start}
  .card-actions{width:100%}
  .card-actions .btn{flex:1 1 140px}
}

/* ── ADMIN FINE POLISH ─────────────────────────────────────────────────────── */
.modal-overlay{backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}
.modal{border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,.25)}
.filter-row input,.filter-row select{border:1.5px solid #e0e0e0;border-radius:8px;padding:8px 12px;font-size:9pt;outline:none;transition:border-color .2s,box-shadow .2s;background:white}
.filter-row input:focus,.filter-row select:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(217,142,59,.12)}
.status-sel:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(217,142,59,.12)}
.nav-section{padding:16px 18px 6px;font-size:7pt;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:1.2px;font-weight:700}
/* ── Currency converter (page-fx) ── */
.afx-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.afx-f label{display:block;font-size:8pt;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.afx-f input,.afx-f select{width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:10pt;color:var(--navy);background:#fff;outline:none;font-family:inherit}
.afx-f input{font-weight:700;font-size:12pt}
.afx-f input:focus,.afx-f select:focus{border-color:var(--gold)}
.afx-swap{width:38px;height:38px;flex:0 0 38px;border-radius:50%;border:1.5px solid #ddd;background:var(--lgray);color:var(--navy);font-size:13pt;line-height:1;cursor:pointer}
.afx-swap:hover{border-color:var(--gold);color:var(--gold)}
.afx-out{margin-top:18px;background:var(--navy);color:#fff;border-radius:8px;padding:16px 18px}
.afx-out>div:first-child{display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap}
.afx-out-lbl{font-size:8pt;font-weight:700;letter-spacing:.7px;text-transform:uppercase;opacity:.6}
.afx-out-val{font-size:19pt;font-weight:800;color:var(--gold2);letter-spacing:-.4px;overflow-wrap:anywhere}
.afx-out-pair{margin-top:8px;padding-top:9px;border-top:1px solid rgba(255,255,255,.13);font-size:8.5pt;opacity:.75}
.afx-board-head{margin-top:24px;margin-bottom:9px;font-size:8pt;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.6px}
.afx-board{width:100%;border-collapse:collapse;font-size:9pt}
.afx-board th{text-align:left;padding:8px 10px;background:var(--lgray);color:var(--navy);font-size:7.5pt;text-transform:uppercase;letter-spacing:.5px;border-bottom:1.5px solid #e5e0d6}
.afx-board td{padding:9px 10px;border-bottom:1px solid #f0f0f0;color:#444}
.afx-board .num{text-align:right;font-variant-numeric:tabular-nums}
.afx-board tr:hover td{background:#fcfaf6}
.afx-board .cc{font-weight:700;color:var(--navy)}
.afx-board .cn{color:#999;font-size:8pt}
.afx-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#ccc;margin-right:7px;vertical-align:middle}
.afx-dot.good{background:var(--green)}.afx-dot.bad{background:var(--red)}.afx-dot.warn{background:var(--gold)}
@keyframes afxPulse{0%,100%{opacity:.3}50%{opacity:1}}
.afx-dot.busy{background:var(--gold);animation:afxPulse 1s infinite}
@media(max-width:600px){.afx-swap{transform:rotate(90deg);margin:0 auto}.afx-out-val{font-size:16pt}}
</style>

<!-- ═══════════════════════════════════════════════════════════════════════
     AVESTA UI KIT — shared design layer
     Tokens, toasts, dialogs, skeletons, empty states, motion, mobile.
     Namespaced .av-* so it cannot collide with existing page styles.
     ═══════════════════════════════════════════════════════════════════ -->
<style id="av-ui-kit">
:root{
  /* ── Ink & brand. Navy and gold are Avesta's; the rest are derived ── */
  --av-ink:#0C231C;
  --av-navy:#163E33;
  --av-navy-soft:#1E5244;
  --av-navy-wash:#EDF2F0;
  --av-gold:#D98E3B;
  --av-gold-deep:#A9661F;
  --av-gold-wash:#FDF3E4;
  --av-cream:#F7F3EC;
  --av-paper:#FFFFFF;
  --av-rule:#E4DED3;
  --av-muted:#66766F;

  /* ── Semantic ── */
  --av-ok:#1B7A4F;      --av-ok-wash:#E8F5EE;
  --av-warn:#946219;    --av-warn-wash:#FDF4E3;
  --av-danger:#B5352B;  --av-danger-wash:#FBECEA;
  --av-info:#2B6C8C;    --av-info-wash:#E9F2F6;

  /* ── Elevation: layered, low-opacity. One light source, top-left ── */
  --av-e1:0 1px 2px rgba(12,35,28,.06), 0 1px 1px rgba(12,35,28,.04);
  --av-e2:0 2px 4px rgba(12,35,28,.06), 0 4px 12px rgba(12,35,28,.05);
  --av-e3:0 4px 8px rgba(12,35,28,.07), 0 12px 28px rgba(12,35,28,.08);
  --av-e4:0 8px 16px rgba(12,35,28,.09), 0 24px 56px rgba(12,35,28,.14);
  --av-ring:0 0 0 3px rgba(217,142,59,.28);

  /* ── Radius & motion ── */
  --av-r-sm:6px; --av-r:10px; --av-r-lg:16px; --av-r-pill:999px;
  --av-fast:120ms; --av-mid:220ms; --av-slow:380ms;
  --av-ease:cubic-bezier(.22,1,.36,1);
  --av-spring:cubic-bezier(.34,1.4,.64,1);
}

/* Money and any figure the eye compares down a column reads as tabular. */
.av-num,.r-val,.sc-value,.num,.fxc-res-val,#fx_out,#afx_out,
.mini-bar-val,.rv-val,.afx-out-val{font-variant-numeric:tabular-nums}

/* ═══ TOASTS ═══════════════════════════════════════════════════════════ */
#av-toasts{
  position:fixed;z-index:99999;display:flex;flex-direction:column;gap:10px;
  pointer-events:none;
  left:50%;transform:translateX(-50%);
  bottom:calc(18px + env(safe-area-inset-bottom));
  width:min(440px,calc(100vw - 24px));
}
@media(min-width:820px){
  #av-toasts{left:auto;right:24px;top:24px;bottom:auto;transform:none;width:400px}
}
.av-toast{
  pointer-events:auto;position:relative;overflow:hidden;
  display:flex;gap:12px;align-items:flex-start;
  background:var(--av-paper);border:1px solid var(--av-rule);
  border-left:4px solid var(--av-navy);
  border-radius:var(--av-r);box-shadow:var(--av-e3);
  padding:13px 40px 13px 14px;cursor:pointer;
  animation:avToastIn var(--av-mid) var(--av-spring) both;
}
@media(min-width:820px){.av-toast{animation-name:avToastInRight}}
.av-toast.av-out{animation:avToastOut 180ms ease-in forwards}
@keyframes avToastIn{from{opacity:0;transform:translateY(16px) scale(.97)}to{opacity:1;transform:none}}
@keyframes avToastInRight{from{opacity:0;transform:translateX(24px) scale(.97)}to{opacity:1;transform:none}}
@keyframes avToastOut{to{opacity:0;transform:scale(.96)}}
.av-toast-ico{
  flex:0 0 22px;height:22px;border-radius:50%;display:grid;place-items:center;
  font-size:12px;font-weight:800;color:var(--av-paper);background:var(--av-navy);
  margin-top:1px;
}
.av-toast-body{flex:1;min-width:0}
.av-toast-title{font-size:13.5px;font-weight:700;color:var(--av-ink);line-height:1.35}
.av-toast-msg{font-size:12.5px;color:var(--av-muted);line-height:1.5;margin-top:2px;
  white-space:pre-line;overflow-wrap:anywhere}
.av-toast-x{
  position:absolute;top:8px;right:8px;width:24px;height:24px;border:0;border-radius:6px;
  background:transparent;color:#9AA8A2;font-size:16px;line-height:1;cursor:pointer;
}
.av-toast-x:hover{background:var(--av-navy-wash);color:var(--av-ink)}
.av-toast-bar{position:absolute;left:0;bottom:0;height:2px;background:var(--av-navy);opacity:.35;
  animation:avToastBar linear forwards}
@keyframes avToastBar{from{width:100%}to{width:0}}
.av-toast.ok{border-left-color:var(--av-ok)}
.av-toast.ok .av-toast-ico,.av-toast.ok .av-toast-bar{background:var(--av-ok)}
.av-toast.warn{border-left-color:var(--av-warn)}
.av-toast.warn .av-toast-ico,.av-toast.warn .av-toast-bar{background:var(--av-warn)}
.av-toast.danger{border-left-color:var(--av-danger)}
.av-toast.danger .av-toast-ico,.av-toast.danger .av-toast-bar{background:var(--av-danger)}
.av-toast.info{border-left-color:var(--av-info)}
.av-toast.info .av-toast-ico,.av-toast.info .av-toast-bar{background:var(--av-info)}

/* ═══ DIALOGS ══════════════════════════════════════════════════════════ */
.av-dlg-back{
  position:fixed;inset:0;z-index:99998;display:flex;align-items:center;justify-content:center;
  padding:20px;background:rgba(9,26,21,.55);backdrop-filter:blur(4px);
  -webkit-backdrop-filter:blur(4px);
  animation:avFade var(--av-fast) ease-out both;
}
@keyframes avFade{from{opacity:0}to{opacity:1}}
.av-dlg-back.av-out{animation:avFade var(--av-fast) ease-in reverse forwards}
.av-dlg{
  background:var(--av-paper);border-radius:var(--av-r-lg);box-shadow:var(--av-e4);
  width:100%;max-width:420px;overflow:hidden;
  animation:avDlgIn var(--av-mid) var(--av-spring) both;
}
@keyframes avDlgIn{from{opacity:0;transform:translateY(14px) scale(.95)}to{opacity:1;transform:none}}
.av-dlg-head{display:flex;gap:14px;align-items:flex-start;padding:22px 22px 0}
.av-dlg-ico{
  flex:0 0 40px;height:40px;border-radius:12px;display:grid;place-items:center;font-size:19px;
  background:var(--av-navy-wash);color:var(--av-navy);
}
.av-dlg.danger .av-dlg-ico{background:var(--av-danger-wash);color:var(--av-danger)}
.av-dlg.warn .av-dlg-ico{background:var(--av-warn-wash);color:var(--av-warn)}
.av-dlg h4{margin:0;font-size:16px;font-weight:700;color:var(--av-ink);line-height:1.35}
.av-dlg p{margin:6px 0 0;font-size:13.5px;color:var(--av-muted);line-height:1.6;white-space:pre-line}
.av-dlg-in{margin:16px 22px 0}
.av-dlg-in input{
  width:100%;padding:11px 13px;border:1.5px solid var(--av-rule);border-radius:var(--av-r-sm);
  font-size:14px;color:var(--av-ink);font-family:inherit;background:var(--av-paper);
}
.av-dlg-in input:focus{outline:none;border-color:var(--av-gold);box-shadow:var(--av-ring)}
.av-dlg-foot{display:flex;gap:9px;justify-content:flex-end;padding:20px 22px 22px;margin-top:18px}
.av-dlg-foot button{
  border:1.5px solid transparent;border-radius:var(--av-r-sm);padding:10px 18px;
  font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;
  transition:transform var(--av-fast) var(--av-ease),filter var(--av-fast),background var(--av-fast);
}
.av-dlg-foot button:active{transform:scale(.97)}
.av-dlg-cancel{background:var(--av-paper);border-color:var(--av-rule)!important;color:var(--av-ink)}
.av-dlg-cancel:hover{background:var(--av-navy-wash)}
.av-dlg-go{background:var(--av-navy);color:#fff}
.av-dlg-go:hover{filter:brightness(1.15)}
.av-dlg.danger .av-dlg-go{background:var(--av-danger)}
@media(max-width:520px){
  .av-dlg-back{align-items:flex-end;padding:0}
  .av-dlg{max-width:none;border-radius:var(--av-r-lg) var(--av-r-lg) 0 0;
    padding-bottom:env(safe-area-inset-bottom)}
  @keyframes avDlgIn{from{opacity:0;transform:translateY(100%)}to{opacity:1;transform:none}}
  .av-dlg-foot{flex-direction:column-reverse}
  .av-dlg-foot button{width:100%;padding:14px}
}

/* ═══ SKELETONS ════════════════════════════════════════════════════════ */
.av-sk{
  background:linear-gradient(90deg,#EFEBE3 25%,#F8F5EF 50%,#EFEBE3 75%);
  background-size:220% 100%;animation:avShimmer 1.3s linear infinite;
  border-radius:var(--av-r-sm);display:block;
}
@keyframes avShimmer{from{background-position:220% 0}to{background-position:-20% 0}}
.av-sk-line{height:11px;margin:7px 0}
.av-sk-line.w40{width:40%}.av-sk-line.w60{width:60%}.av-sk-line.w80{width:80%}

/* ═══ EMPTY STATES ═════════════════════════════════════════════════════ */
.av-empty{
  text-align:center;padding:52px 24px;color:var(--av-muted);
  animation:avRise var(--av-slow) var(--av-ease) both;
}
.av-empty-ico{
  width:56px;height:56px;margin:0 auto 16px;border-radius:16px;display:grid;place-items:center;
  font-size:24px;background:var(--av-navy-wash);color:var(--av-navy);
}
.av-empty h4{margin:0 0 6px;font-size:15px;font-weight:700;color:var(--av-ink)}
.av-empty p{margin:0 auto;font-size:13px;line-height:1.6;max-width:320px}
.av-empty .av-empty-act{
  margin-top:18px;display:inline-block;background:var(--av-navy);color:#fff;border:0;
  border-radius:var(--av-r-sm);padding:11px 20px;font-size:13px;font-weight:700;cursor:pointer;
  font-family:inherit;
}
.av-empty .av-empty-act:hover{filter:brightness(1.15)}

/* ═══ MOTION ═══════════════════════════════════════════════════════════ */
@keyframes avRise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.av-reveal{opacity:0;transform:translateY(18px)}
.av-reveal.av-in{opacity:1;transform:none;
  transition:opacity var(--av-slow) var(--av-ease),transform var(--av-slow) var(--av-ease)}

/* Buttons everywhere get a press response and a real focus ring */
button,.btn,input[type=submit]{
  transition:transform var(--av-fast) var(--av-ease),
             box-shadow var(--av-mid) var(--av-ease),
             background var(--av-fast),filter var(--av-fast),border-color var(--av-fast);
}
button:active:not(:disabled),.btn:active:not(:disabled){transform:scale(.975)}
:focus-visible{outline:2px solid var(--av-gold);outline-offset:2px;border-radius:4px}

/* Ripple origin for primary actions */
.av-ripple{position:relative;overflow:hidden}
.av-ripple>.av-rip{
  position:absolute;border-radius:50%;transform:scale(0);pointer-events:none;
  background:rgba(255,255,255,.42);animation:avRip 520ms var(--av-ease) forwards;
}
@keyframes avRip{to{transform:scale(2.6);opacity:0}}

/* Busy state for any button mid-request */
.av-busy{position:relative;color:transparent!important;pointer-events:none}
.av-busy::after{
  content:"";position:absolute;inset:0;margin:auto;width:16px;height:16px;border-radius:50%;
  border:2px solid currentColor;border-top-color:transparent;color:#fff;
  animation:avSpin .6s linear infinite;
}
@keyframes avSpin{to{transform:rotate(360deg)}}

/* ═══ THE SEAL — signature element ═════════════════════════════════════
   This product is a loan agreement, so the two figures that carry the
   commitment (total repayable, converted amount) are set like a stamp.
   Used in exactly two places so it stays meaningful.                    */
.av-seal{position:relative;display:inline-block;padding:2px 2px 2px 0}
.av-seal::before{
  content:"";position:absolute;inset:-9px -14px;border-radius:var(--av-r-sm);
  border:1.5px solid rgba(217,142,59,.42);
  background:linear-gradient(180deg,rgba(217,142,59,.10),rgba(217,142,59,.03));
  pointer-events:none;
}
.av-seal::after{
  content:"";position:absolute;inset:-5px -10px;border-radius:4px;
  border:1px dashed rgba(217,142,59,.34);pointer-events:none;
}

/* ═══ SCROLLBAR ════════════════════════════════════════════════════════ */
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-track{background:transparent}
*::-webkit-scrollbar-thumb{background:#C9D3CE;border-radius:99px;border:3px solid transparent;
  background-clip:content-box}
*::-webkit-scrollbar-thumb:hover{background:#A9B7B1;background-clip:content-box}

@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;
    transition-duration:.01ms!important}
  .av-reveal{opacity:1;transform:none}
}
</style>

<!-- ═══ AVESTA — ADMIN PORTAL REFINEMENT ═══════════════════════════════ -->
<style id="av-admin-polish">
body{
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;
}

/* ── Sidebar ────────────────────────────────────────────────────────── */
.nav-item{
  position:relative;border-radius:0 var(--av-r-sm) var(--av-r-sm) 0;
  transition:background var(--av-fast),color var(--av-fast),
             padding-left var(--av-mid) var(--av-ease);
}
.nav-item::before{
  content:"";position:absolute;left:0;top:6px;bottom:6px;width:3px;border-radius:0 3px 3px 0;
  background:var(--av-gold);transform:scaleY(0);transform-origin:center;
  transition:transform var(--av-mid) var(--av-spring);
}
.nav-item:hover{background:rgba(255,255,255,.06)}
.nav-item.active::before,.nav-item:hover::before{transform:scaleY(1)}
.nav-item.active{background:rgba(217,142,59,.14)}
.nav-icon{transition:transform var(--av-mid) var(--av-spring);display:inline-block}
.nav-item:hover .nav-icon{transform:scale(1.16)}
.nav-section{letter-spacing:.16em;opacity:.55}
.nav-badge{
  font-variant-numeric:tabular-nums;
  transition:transform var(--av-mid) var(--av-spring),background var(--av-fast);
}
.nav-item:hover .nav-badge{transform:scale(1.1)}

/* ── Cards ──────────────────────────────────────────────────────────── */
.card{
  box-shadow:var(--av-e1);border-radius:var(--av-r-lg)!important;
  transition:box-shadow var(--av-mid) var(--av-ease);
}
.card:hover{box-shadow:var(--av-e2)}
.card-header{border-bottom:1px solid var(--av-rule)}
.card-title{letter-spacing:-.01em}

/* ── Stat cards: the numbers are the dashboard, so give them room
      and a quiet accent rather than competing colour blocks. ────────── */
.stat-card{
  position:relative;overflow:hidden;box-shadow:var(--av-e1);
  border-radius:var(--av-r-lg)!important;
  transition:transform var(--av-mid) var(--av-ease),box-shadow var(--av-mid) var(--av-ease);
}
.stat-card::before{
  content:"";position:absolute;left:0;top:0;bottom:0;width:3px;
  background:var(--av-gold);opacity:.85;
}
.stat-card::after{
  content:"";position:absolute;right:-28px;top:-28px;width:90px;height:90px;border-radius:50%;
  background:radial-gradient(circle,rgba(217,142,59,.11),transparent 70%);pointer-events:none;
}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--av-e3)}
.sc-value{
  font-variant-numeric:tabular-nums;letter-spacing:-.03em;
  animation:avRise var(--av-slow) var(--av-ease) both;
}
.sc-label{letter-spacing:.1em;text-transform:uppercase}
.sc-sub{color:var(--av-muted)}

/* ── Table ──────────────────────────────────────────────────────────── */
.card table{border-collapse:separate;border-spacing:0}
.card thead th{
  position:sticky;top:0;z-index:2;background:var(--av-cream);
  border-bottom:1.5px solid var(--av-rule);
  letter-spacing:.09em;text-transform:uppercase;font-size:7.5pt;
  backdrop-filter:blur(6px);
}
#app-tbody tr{transition:background var(--av-fast),box-shadow var(--av-fast)}
#app-tbody tr:hover{background:var(--av-gold-wash)}
#app-tbody td{border-bottom:1px solid #F0ECE4;vertical-align:middle}
#app-tbody tr:last-child td{border-bottom:0}
.td-name{font-weight:700;color:var(--av-ink)}
.num{font-variant-numeric:tabular-nums}
.av-sk-row td{padding:14px 10px!important}

/* ── Status pills ───────────────────────────────────────────────────── */
.status-sel,select.status-sel{
  border-radius:var(--av-r-pill)!important;
  padding:5px 26px 5px 12px!important;font-weight:700;font-size:8pt;
  border-width:1.5px!important;cursor:pointer;
  transition:filter var(--av-fast),box-shadow var(--av-fast),transform var(--av-fast);
  appearance:none;-webkit-appearance:none;
  background-image:linear-gradient(45deg,transparent 50%,currentColor 50%),
                   linear-gradient(135deg,currentColor 50%,transparent 50%);
  background-position:right 12px center,right 7px center;
  background-size:5px 5px,5px 5px;background-repeat:no-repeat;
}
.status-sel:hover{filter:brightness(.97);transform:translateY(-1px)}
.status-sel:focus{outline:none;box-shadow:var(--av-ring)}

/* ── Filters ────────────────────────────────────────────────────────── */
.filter-select,#global-search{
  border-radius:var(--av-r-sm)!important;
  transition:border-color var(--av-fast),box-shadow var(--av-fast);
}
.filter-select:focus,#global-search:focus{
  outline:none;border-color:var(--av-gold)!important;box-shadow:var(--av-ring);
}
.filter-label{letter-spacing:.08em;text-transform:uppercase}

/* ── Buttons ────────────────────────────────────────────────────────── */
.btn{
  border-radius:var(--av-r-sm)!important;font-weight:700;letter-spacing:.02em;
  box-shadow:var(--av-e1);
}
.btn:hover:not(:disabled){box-shadow:var(--av-e2);filter:brightness(1.07)}
.btn-ghost{box-shadow:none}
.btn-ghost:hover{box-shadow:none;background:var(--av-navy-wash)}

/* ── Mini bars ──────────────────────────────────────────────────────── */
.mini-bar-track{border-radius:var(--av-r-pill);overflow:hidden;background:#EEEAE2}
.mini-bar-fill{
  border-radius:var(--av-r-pill);
  background:linear-gradient(90deg,var(--av-gold-deep),var(--av-gold));
  transition:width var(--av-slow) var(--av-ease);
}
.mini-bar-val{font-variant-numeric:tabular-nums}

/* ── Converter ──────────────────────────────────────────────────────── */
.afx-f input,.afx-f select{
  border-radius:var(--av-r-sm)!important;
  transition:border-color var(--av-fast),box-shadow var(--av-fast);
}
.afx-f input:focus,.afx-f select:focus{
  outline:none;border-color:var(--av-gold)!important;box-shadow:var(--av-ring);
}
.afx-swap{transition:transform var(--av-mid) var(--av-spring),border-color var(--av-fast)}
.afx-swap:hover{transform:rotate(180deg);border-color:var(--av-gold)}
.afx-out{
  border-radius:var(--av-r)!important;
  background:linear-gradient(150deg,#1B4A3C,var(--av-navy) 62%)!important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.07);
}
.afx-out-val{
  position:relative;display:inline-block;
  font-variant-numeric:tabular-nums;letter-spacing:-.02em;
}
.afx-out-val::before{
  content:"";position:absolute;inset:-7px -12px;border-radius:var(--av-r-sm);
  border:1.5px solid rgba(232,174,110,.34);background:rgba(232,174,110,.07);
  pointer-events:none;
}
#afx_board tr{transition:background var(--av-fast)}
#afx_board tr:hover{background:var(--av-gold-wash)}
#afx_board .cc{font-weight:800;color:var(--av-navy)}
#afx_board .cn{color:var(--av-muted)}
.afx-board-head{letter-spacing:.1em;text-transform:uppercase}
.afx-dot{transition:background var(--av-fast)}

/* ── Modals ─────────────────────────────────────────────────────────── */
#backup-modal{backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}
#backup-modal>div{
  box-shadow:var(--av-e4)!important;
  animation:avDlgIn var(--av-mid) var(--av-spring) both;
}
#backup-modal button{transition:filter var(--av-fast),transform var(--av-fast) var(--av-ease)}

/* ── Sync banner ────────────────────────────────────────────────────── */
.sync-banner,#sync-banner{
  border-radius:var(--av-r-sm)!important;box-shadow:var(--av-e1);
  animation:avRise var(--av-mid) var(--av-ease) both;
}

/* ── Detail grid ────────────────────────────────────────────────────── */
.detail-grid{border-radius:var(--av-r)}
.detail-grid>div{transition:background var(--av-fast)}

/* ── Mobile ─────────────────────────────────────────────────────────── */
@media(max-width:900px){
  .nav-item{min-height:46px;display:flex;align-items:center}
  .stat-card{padding:16px}
  .sc-value{font-size:17pt}
  .card thead th{position:static;backdrop-filter:none}
  input,select{font-size:16px}   /* no iOS zoom-on-focus */
}
@media print{
  .sidebar,#av-toasts,.av-dlg-back,#backup-modal,.card-actions{display:none!important}
  .page{display:block!important}
  .card{box-shadow:none;border:1px solid #999}
}
</style>

<script>
/* ── Avesta network helpers ─────────────────────────────────────────────
   Defined in <head> so every consumer has them regardless of script order.

   A host with PHP disabled, a missing api.php, or a hosting error page all
   answer with HTML. Calling .json() on that throws the unhelpful
   "Unexpected token '<', "<!DOCTYPE "... is not valid JSON". These report
   what actually went wrong, and give the request a timeout that works. */
window.avReadJson = async function (res) {
  var body = null;
  if (res && typeof res.text === 'function') {
    try { body = await res.text(); } catch (e) { body = null; }
  }
  if (body === null) return res.json();            // stub or opaque response
  if (/^\s*<(!doctype|html|\?xml)/i.test(body)) {
    throw new Error(res.status === 404
      ? 'api.php was not found on the server'
      : 'The server sent a web page instead of data \u2014 check that PHP is running');
  }
  try { return JSON.parse(body); }
  catch (e) { throw new Error('The server sent data this page could not read'); }
};

window.avFetchJson = async function (url, opts, ms) {
  opts = opts || {};
  var ctrl = null, timer = null;
  if (typeof AbortController === 'function') {
    ctrl = new AbortController();
    opts.signal = ctrl.signal;
    timer = setTimeout(function () { try { ctrl.abort(); } catch (e) {} }, ms || 12000);
  }
  try {
    var res = await fetch(url, opts);
    return { res: res, data: await window.avReadJson(res) };
  } finally { if (timer) clearTimeout(timer); }
};
</script>
<style>
.boz-panel{margin-top:20px;border:1px solid var(--av-line,#E3DED4);border-radius:12px;
  padding:18px;background:var(--av-paper,#fff);border-top:3px solid var(--av-gold,#D98E3B)}
.boz-head{display:flex;gap:12px;align-items:flex-start;margin-bottom:14px}
.boz-badge{flex:0 0 auto;font-size:7.5pt;font-weight:800;letter-spacing:.9px;text-transform:uppercase;
  background:var(--av-navy,#163E33);color:var(--av-gold,#D98E3B);padding:5px 10px;border-radius:999px}
.boz-head strong{display:block;font-size:11pt;color:var(--av-navy,#163E33);margin-bottom:3px}
.boz-head p{margin:0;font-size:8.5pt;color:var(--av-muted,#66766F);line-height:1.55}
.boz-current{font-size:9pt;padding:10px 12px;border-radius:8px;margin-bottom:14px;
  background:var(--av-cream,#F7F3EC);color:var(--av-navy,#163E33)}
.boz-current.stale{background:var(--av-warn-wash,#FDF4E3);color:var(--av-warn,#946219)}
.boz-current.none{background:var(--av-danger-wash,#FBEAE8);color:var(--av-danger,#C0392B)}
.boz-grid{display:grid;gap:12px;grid-template-columns:repeat(2,1fr)}
@media(min-width:680px){.boz-grid{grid-template-columns:repeat(4,1fr)}}
.boz-grid label{display:block;font-size:7.5pt;font-weight:700;letter-spacing:.7px;text-transform:uppercase;
  color:var(--av-muted,#66766F);margin-bottom:5px}
.boz-grid input{width:100%;padding:9px 11px;border:1.5px solid var(--av-line,#E3DED4);border-radius:7px;
  font-size:10.5pt;font-weight:600;color:var(--av-navy,#163E33);background:#fff;font-family:inherit}
.boz-grid input:focus{outline:none;border-color:var(--av-gold,#D98E3B);
  box-shadow:0 0 0 3px rgba(217,142,59,.16)}
.boz-foot{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:14px}
.boz-save{background:var(--av-navy,#163E33);color:var(--av-gold,#D98E3B);border:none;border-radius:8px;
  padding:11px 20px;font-size:9pt;font-weight:700;letter-spacing:.4px;cursor:pointer;font-family:inherit}
.boz-save:hover:not(:disabled){background:#1F5344}
.boz-save:disabled{opacity:.55;cursor:default}
.boz-link{font-size:8.5pt;font-weight:600;color:var(--av-gold-deep,#B26F26);text-decoration:none}
.boz-link:hover{text-decoration:underline}
.boz-mid{font-size:8.5pt;color:var(--av-muted,#66766F);margin-left:auto}
</style>
<style>
/* === LOAN BOOK ========================================================= */
.book{background:var(--av-paper,#fff);border:1px solid var(--av-line,#E3DED4);
  border-radius:12px;padding:18px;margin:20px 0;border-top:3px solid var(--av-gold,#D98E3B)}
.book-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}
.book-head h3{font-size:12pt;color:var(--av-navy,#163E33);margin:0 0 2px}
.book-head p{margin:0;font-size:8.5pt;color:var(--av-muted,#66766F)}
.book-refresh{font-size:8pt;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
  color:var(--av-navy,#163E33);background:var(--av-cream,#F7F3EC);
  border:1.5px solid var(--av-line,#E3DED4);border-radius:7px;padding:8px 13px;
  cursor:pointer;font-family:inherit;white-space:nowrap}
.book-refresh:hover:not(:disabled){border-color:var(--av-gold,#D98E3B);color:var(--av-gold-deep,#B26F26)}
.book-grid{display:grid;gap:10px;grid-template-columns:repeat(2,1fr)}
@media(min-width:760px){.book-grid{grid-template-columns:repeat(4,1fr)}}
.bk{background:var(--av-cream,#F7F3EC);border-radius:9px;padding:13px 14px;
  border-left:3px solid var(--av-navy,#163E33);display:flex;flex-direction:column;gap:3px}
.bk.danger{border-left-color:var(--av-danger,#C0392B);background:var(--av-danger-wash,#FBEAE8)}
.bk.warn{border-left-color:var(--av-warn,#946219);background:var(--av-warn-wash,#FDF4E3)}
.bk-lbl{font-size:7.5pt;font-weight:700;letter-spacing:.7px;text-transform:uppercase;
  color:var(--av-muted,#66766F)}
.bk-val{font-size:15pt;font-weight:800;color:var(--av-navy,#163E33);letter-spacing:-.4px;
  font-variant-numeric:tabular-nums}
.bk.danger .bk-val{color:var(--av-danger,#C0392B)}
.bk.warn .bk-val{color:var(--av-warn,#946219)}
.bk-sub{font-size:8pt;color:var(--av-muted,#66766F)}
.book-bar{height:8px;border-radius:99px;background:var(--av-cream,#F7F3EC);
  overflow:hidden;margin-top:16px;border:1px solid var(--av-line,#E3DED4)}
.book-bar-fill{height:100%;width:0;border-radius:99px;
  background:linear-gradient(90deg,var(--av-gold,#D98E3B),var(--av-ok,#1E7A4C));
  transition:width .5s cubic-bezier(.2,.8,.2,1)}
.book-note{margin:8px 0 0;font-size:8.5pt;color:var(--av-muted,#66766F)}
@media(prefers-reduced-motion:reduce){.book-bar-fill{transition:none}}
</style>
<style>
/* === ACCOUNTS ========================================================== */
.acct-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:16px}
.acct-stats{display:grid;gap:10px;grid-template-columns:repeat(2,1fr);margin-bottom:18px}
@media(min-width:760px){.acct-stats{grid-template-columns:repeat(5,1fr)}}
.acct-stat{background:var(--av-cream,#F7F3EC);border-radius:9px;padding:12px 14px;
  border-left:3px solid var(--av-navy,#163E33)}
.acct-stat b{display:block;font-size:15pt;font-weight:800;color:var(--av-navy,#163E33);
  font-variant-numeric:tabular-nums}
.acct-stat span{font-size:7.5pt;font-weight:700;letter-spacing:.7px;text-transform:uppercase;
  color:var(--av-muted,#66766F)}
.acct-resets{background:var(--av-warn-wash,#FDF4E3);border:1px solid rgba(148,98,25,.35);
  border-radius:11px;padding:16px 18px;margin-bottom:18px}
.acct-resets h3{font-size:11pt;color:var(--av-warn,#946219);margin:0 0 4px}
.acct-resets > p{font-size:9pt;color:var(--av-muted,#66766F);margin:0 0 12px;line-height:1.55}
.acct-reset-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;
  background:#fff;border-radius:8px;padding:10px 12px;margin-bottom:8px;font-size:9.5pt}
.acct-reset-row b{color:var(--av-navy,#163E33)}
.acct-reset-row .when{font-size:8.5pt;color:var(--av-muted,#66766F)}
.acct-pill{display:inline-block;font-size:7.5pt;font-weight:700;letter-spacing:.4px;
  text-transform:uppercase;padding:3px 8px;border-radius:999px}
.acct-pill.super_admin{background:rgba(22,62,51,.14);color:var(--av-navy,#163E33);border:1px solid rgba(22,62,51,.22)}
.acct-pill.admin{background:rgba(217,142,59,.18);color:var(--av-gold-deep,#96591A)}
.acct-pill.staff{background:rgba(91,155,255,.18);color:#1F5FA8}
.acct-pill.borrower{background:rgba(30,122,76,.14);color:#1E7A4C}
.acct-pill.off{background:var(--av-danger-wash,#FBEAE8);color:var(--av-danger,#C0392B)}
.acct-pill.temp{background:var(--av-warn-wash,#FDF4E3);color:var(--av-warn,#946219)}
.acct-temp{background:var(--av-navy,#163E33);color:#fff;border-radius:10px;padding:16px 18px;
  margin-top:10px;font-size:10pt;line-height:1.6}
.acct-temp code{display:block;font-size:16pt;font-weight:800;letter-spacing:1px;
  color:var(--av-gold-light,#E8AE6E);margin:8px 0;font-family:ui-monospace,Menlo,Consolas,monospace}
</style>
<link rel="stylesheet" href="/assets/avesta/responsive.css?v=20261003">
<script src="/assets/avesta/device-layout.js?v=20261003" defer></script>
</head>
<body>

<!-- ═══ APP ═══ -->
<div id="app">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sb-logo">
        <div class="sb-ae">AE</div>
        <div>
          <div class="sb-name">Avesta Enterprises</div>
          <div class="sb-tag">Admin Portal</div>
        </div>
      </div>
      <div class="sb-admin-badge">🔐 Admin</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">Main</div>
      <a class="nav-item active" onclick="showPage('dashboard')" href="javascript:void(0)">
        <span class="nav-icon">📊</span> Dashboard
      </a>
      <a class="nav-item" onclick="showPage('applications')" href="javascript:void(0)">
        <span class="nav-icon">📋</span> All Applications
        <span class="nav-badge" id="nav-new-count">0</span>
      </a>
      <div class="nav-section">By Status</div>
      <a class="nav-item" onclick="showPage('applications');filterByStatus('New Application')" href="javascript:void(0)">
        <span class="nav-icon">🆕</span> New Applications <span id="nb-new" class="nav-badge" style="display:none"></span>
      </a>
      <a class="nav-item" onclick="showPage('applications');filterByStatus('Under Review')" href="javascript:void(0)">
        <span class="nav-icon">🔍</span> Under Review <span id="nb-review" class="nav-badge" style="background:#e67e22;display:none"></span>
      </a>
      <a class="nav-item" onclick="showPage('applications');filterByStatus('Approved')" href="javascript:void(0)">
        <span class="nav-icon">✅</span> Approved <span id="nb-approved" class="nav-badge" style="background:#198754;display:none"></span>
      </a>
      <a class="nav-item" onclick="showPage('applications');filterByStatus('Disbursed')" href="javascript:void(0)">
        <span class="nav-icon">💰</span> Disbursed <span id="nb-disbursed" class="nav-badge" style="background:#2980b9;display:none"></span>
      </a>
      <a class="nav-item" onclick="showPage('applications');filterByStatus('Repaid')" href="javascript:void(0)">
        <span class="nav-icon">🏁</span> Repaid <span id="nb-repaid" class="nav-badge" style="background:#0e6b3c;display:none"></span>
      </a>
      <a class="nav-item" onclick="showPage('applications');filterByStatus('Rejected')" href="javascript:void(0)">
        <span class="nav-icon">❌</span> Rejected <span id="nb-rejected" class="nav-badge" style="background:#c0392b;display:none"></span>
      </a>
      <div class="nav-section">Tools</div>
      <a class="nav-item" onclick="fetchRemoteRecords(false)" href="javascript:void(0)">
        <span class="nav-icon">🔄</span> Sync Now
      </a>
      <a class="nav-item" onclick="showPage('fx')" href="javascript:void(0)">
        <span class="nav-icon">💱</span> Currency Rates
      </a>
      <a class="nav-item admin-only" onclick="openBackupModal()" href="javascript:void(0)">
        <span class="nav-icon">📦</span> Backup / Restore
      </a>
      <a class="nav-item" onclick="exportCSV()" href="javascript:void(0)">
        <span class="nav-icon">📤</span> Export CSV
      </a>
      <a class="nav-item" onclick="window.print()" href="javascript:void(0)">
        <span class="nav-icon">🖨️</span> Print Report
      </a>
      <div class="nav-section" id="nav-accounts-section" hidden>People</div>
      <a class="nav-item" id="nav-accounts" hidden onclick="showPage('accounts')" href="javascript:void(0)">
        <span class="nav-icon">&#128100;</span> Accounts
      </a>
      <div class="nav-section">Settings</div>
      <a class="nav-item" href="/recovery.php?setup=1"><span class="nav-icon">✉️</span> Recovery email</a>
      <a class="nav-item" href="/login.php?change=1"><span class="nav-icon">🔑</span> Change password</a>
      <a class="nav-item admin-only" onclick="showPage('settings')" href="javascript:void(0)">
        <span class="nav-icon">⚙️</span> Sync Settings
      </a>
    </nav>
    <div class="sidebar-footer">
      <button class="lock-btn" onclick="doLogout()">🔒 Lock &amp; Sign Out</button>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">

    <!-- TOP BAR -->
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:10px">
      <button id="sidebar-toggle" onclick="toggleSidebar()" style="display:none;align-items:center;justify-content:center;background:var(--navy);border:none;cursor:pointer;padding:8px 10px;border-radius:8px;color:var(--gold);font-size:16pt;line-height:1;min-width:42px;min-height:42px;flex-shrink:0" title="Open menu" aria-label="Open sidebar menu" aria-controls="sidebar" aria-expanded="false">☰</button>
      <div class="topbar-title" id="topbar-title">Dashboard</div>
      <span id="live-dot" title="Live sync active" style="width:8px;height:8px;background:#25D366;border-radius:50%;display:inline-block;box-shadow:0 0 0 2px rgba(37,211,102,.3);animation:pulse-dot 2s infinite"></span>
    </div>
      <div class="topbar-right">
        <input type="text" class="topbar-search" placeholder="🔍 Search applicants…" id="global-search" oninput="globalSearch()">
        <button onclick="toggleWaPanel()" title="WhatsApp Notifications" style="background:none;border:1.5px solid #ddd;border-radius:8px;padding:7px 12px;cursor:pointer;font-size:11pt;color:#25D366" id="wa-btn">📱</button>
        <span class="topbar-date" id="topbar-date"></span>
      </div>
      <!-- Notification Settings Panel -->
      <div id="wa-panel" style="display:none;position:absolute;top:64px;right:24px;background:white;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.2);width:340px;z-index:200;border:1.5px solid #e0e0e0;overflow:hidden">
        <div style="background:#163E33;padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
          <div style="color:white;font-weight:700;font-size:10pt">🔔 Notification Settings</div>
          <button onclick="toggleWaPanel(true)" style="background:rgba(255,255,255,.15);border:none;color:white;padding:3px 8px;border-radius:4px;cursor:pointer;font-size:11pt">✕</button>
        </div>
        <div style="display:flex;border-bottom:2px solid #f0f0f0">
          <button onclick="showNotifyTab('wa')" id="ntab-wa" style="flex:1;padding:10px;border:none;background:white;font-size:8pt;font-weight:700;color:#163E33;border-bottom:2px solid #163E33;cursor:pointer;margin-bottom:-2px">📱 WhatsApp</button>
          <button onclick="showNotifyTab('email')" id="ntab-email" style="flex:1;padding:10px;border:none;background:white;font-size:8pt;font-weight:600;color:#888;cursor:pointer">📧 Email</button>
          <button onclick="showNotifyTab('browser')" id="ntab-browser" style="flex:1;padding:10px;border:none;background:white;font-size:8pt;font-weight:600;color:#888;cursor:pointer">🖥 Browser</button>
        </div>
        <div id="ntab-content-wa" style="padding:16px 18px">
          <div id="wa-status" style="font-size:8pt;margin-bottom:12px;padding:8px 10px;border-radius:6px;background:#f5f5f5;color:#666">⚠️ Not configured yet</div>
          <div style="background:#e8f5e9;border-radius:7px;padding:10px 12px;margin-bottom:12px;font-size:8pt;color:#1b5e20;line-height:1.8">
            <strong>Setup (one time):</strong><br>
            1. Click the green button below to open WhatsApp<br>
            2. Send the pre-filled message to the bot<br>
            3. Bot replies with your API key in ~2 min<br>
            4. Paste your number + key below and Save
          </div>
          <button onclick="openWaFallback()" style="width:100%;background:#25D366;color:white;border:none;padding:10px;border-radius:6px;font-size:9pt;font-weight:700;cursor:pointer;margin-bottom:12px">🌐 Manage Numbers on TextMeBot</button>
          <label style="font-size:7pt;color:#888;font-weight:700;display:block;margin-bottom:3px;text-transform:uppercase" for="wa-number-input">Your WhatsApp Number</label>
          <input id="wa-number-input" type="tel" placeholder="260971013108" value="260971013108" style="width:100%;padding:9px 11px;border:1.5px solid #ddd;border-radius:6px;font-size:9pt;margin-bottom:10px;outline:none">
          <label style="font-size:7pt;color:#888;font-weight:700;display:block;margin-bottom:3px;text-transform:uppercase" for="wa-key-input">TextMeBot API Key</label>
          <input id="wa-key-input" type="text" placeholder="Enter API key after deployment" style="width:100%;padding:9px 11px;border:1.5px solid #ddd;border-radius:6px;font-size:9pt;margin-bottom:12px;outline:none">
          <div style="display:flex;gap:8px">
            <button onclick="saveWaSettings()" style="flex:1;background:#163E33;color:white;border:none;padding:9px;border-radius:6px;font-size:9pt;font-weight:700;cursor:pointer">💾 Save</button>
            <button onclick="testWa()" style="flex:1;background:#D98E3B;color:#163E33;border:none;padding:9px;border-radius:6px;font-size:9pt;font-weight:700;cursor:pointer">🧪 Test</button>
          </div>
        </div>
        <div id="ntab-content-email" style="display:none;padding:16px 18px">
          <div style="background:#e3f2fd;border-radius:7px;padding:10px 12px;margin-bottom:14px;font-size:8pt;color:#0d47a1;line-height:1.7">
            <strong>Email alerts:</strong> When a new application arrives, a pre-filled email opens automatically — just click Send. No setup needed beyond saving your address.
          </div>
          <label style="font-size:7pt;color:#888;font-weight:700;display:block;margin-bottom:3px;text-transform:uppercase" for="notify-email-input">Your Email Address</label>
          <input id="notify-email-input" type="email" placeholder="you@gmail.com" style="width:100%;padding:9px 11px;border:1.5px solid #ddd;border-radius:6px;font-size:9pt;margin-bottom:12px;outline:none">
          <button onclick="saveEmailSettings()" style="width:100%;background:#163E33;color:white;border:none;padding:10px;border-radius:6px;font-size:9pt;font-weight:700;cursor:pointer;margin-bottom:8px">💾 Save Email</button>
          <button onclick="testEmailNotify()" style="width:100%;background:#D98E3B;color:#163E33;border:none;padding:10px;border-radius:6px;font-size:9pt;font-weight:700;cursor:pointer">🧪 Send Test Email</button>
          <div id="email-notify-status" style="margin-top:10px;font-size:8pt;color:#888"></div>
        </div>
        <div id="ntab-content-browser" style="display:none;padding:16px 18px">
          <div style="background:#f3e5f5;border-radius:7px;padding:10px 12px;margin-bottom:14px;font-size:8pt;color:#4a148c;line-height:1.7">
            <strong>Browser notifications:</strong> Instant desktop/phone popup when a borrower submits — even when the admin tab is in the background. Works on Chrome, Firefox, Edge.
          </div>
          <div id="browser-notif-status" style="font-size:8pt;color:#666;margin-bottom:12px;padding:8px;background:#f5f5f5;border-radius:6px">Checking status...</div>
          <button onclick="enableBrowserNotif()" style="width:100%;background:#163E33;color:white;border:none;padding:10px;border-radius:6px;font-size:9pt;font-weight:700;cursor:pointer;margin-bottom:8px">🔔 Enable Browser Notifications</button>
          <button onclick="testBrowserNotif()" style="width:100%;background:#D98E3B;color:#163E33;border:none;padding:10px;border-radius:6px;font-size:9pt;font-weight:700;cursor:pointer">🧪 Send Test Notification</button>
        </div>
      </div>

    </div>

    <!-- CONTENT -->
    <div class="content">

      <!-- ── DASHBOARD PAGE ── -->
      <!-- ═══ ACCOUNTS (admins only) ══════════════════════════════════════ -->
      <div class="page" id="page-accounts">
        <div class="acct-head">
          <div>
            <h2 class="page-title">Accounts</h2>
            <p class="page-sub" id="acct-sub">Everyone who has registered with Avesta.</p>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" id="acct-refresh">Refresh</button>
        </div>

        <div class="acct-stats" id="acct-stats"></div>

        <div class="acct-resets" id="acct-resets" hidden>
          <h3>Password help requested</h3>
          <p>These people could not sign in and asked for help. Confirm who they are on the phone
             &mdash; name, NRC and the number on the account &mdash; before resetting anything.</p>
          <div id="acct-reset-list"></div>
        </div>

        <div id="acct-temp-output" aria-live="polite"></div>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Name</th><th>Username</th><th>Recovery email</th><th>Role</th><th>Registered</th>
                <th>Last signed in</th><th>Applications</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody id="acct-tbody"></tbody>
          </table>
        </div>
        <div class="acct-resets" style="margin-top:20px"><h3>Recent account recovery activity</h3><div id="acct-recovery-history"></div></div>
      </div>

      <div class="page active" id="page-dashboard">
        <div class="stats-row" id="stats-row">
          <div class="stat-card" data-icon="📋">
            <div class="sc-value" id="s-total">0</div>
            <div class="sc-label">Total Applications</div>
            <div class="sc-sub" id="s-today">0 today</div>
          </div>
          <div class="stat-card orange" data-icon="🆕">
            <div class="sc-value" id="s-new">0</div>
            <div class="sc-label">New / Pending</div>
            <div class="sc-sub">Awaiting review</div>
          </div>
          <div class="stat-card green" data-icon="✅">
            <div class="sc-value" id="s-approved">0</div>
            <div class="sc-label">Approved</div>
            <div class="sc-sub" id="s-approved-amt">ZMW 0</div>
          </div>
          <div class="stat-card blue" data-icon="💰">
            <div class="sc-value" id="s-disbursed">0</div>
            <div class="sc-label">Disbursed</div>
            <div class="sc-sub" id="s-disbursed-amt">ZMW 0</div>
          </div>
          <div class="stat-card" data-icon="💵">
            <div class="sc-value" id="s-total-amt">ZMW 0</div>
            <div class="sc-label">Total Requested</div>
            <div class="sc-sub">All applications</div>
          </div>
          <div class="stat-card red" data-icon="❌">
            <div class="sc-value" id="s-rejected">0</div>
            <div class="sc-label">Rejected</div>
            <div class="sc-sub" id="s-repaid">0 repaid</div>
          </div>
        </div>

        <!-- ═══ LOAN BOOK ═══════════════════════════════════════════════ -->
        <div class="book">
          <div class="book-head">
            <div>
              <h3>Loan book</h3>
              <p id="book-sub">Working it out&hellip;</p>
            </div>
            <button type="button" class="book-refresh" id="book-refresh">Refresh</button>
          </div>

          <div class="book-grid">
            <div class="bk"><span class="bk-lbl">Out on loan</span>
              <span class="bk-val" id="bk-out">&mdash;</span>
              <span class="bk-sub" id="bk-out-sub">&nbsp;</span></div>
            <div class="bk"><span class="bk-lbl">Collected</span>
              <span class="bk-val" id="bk-col">&mdash;</span>
              <span class="bk-sub" id="bk-col-sub">&nbsp;</span></div>
            <div class="bk danger"><span class="bk-lbl">Overdue</span>
              <span class="bk-val" id="bk-late">&mdash;</span>
              <span class="bk-sub" id="bk-late-sub">&nbsp;</span></div>
            <div class="bk warn"><span class="bk-lbl">Due this week</span>
              <span class="bk-val" id="bk-soon">&mdash;</span>
              <span class="bk-sub" id="bk-soon-sub">&nbsp;</span></div>
          </div>

          <div class="book-bar" title="Share of what is owed that has been collected">
            <div class="book-bar-fill" id="bk-bar"></div>
          </div>
          <p class="book-note" id="bk-note">&nbsp;</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap">
          <!-- Loan amounts by duration -->
          <div class="card">
            <div class="card-header"><div class="card-title">📊 Loans by Duration</div></div>
            <div style="padding:20px"><div class="mini-bar-wrap" id="chart-duration"></div></div>
          </div>
          <!-- Recent applications -->
          <div class="card">
            <div class="card-header">
              <div class="card-title">🕐 Recent Applications</div>
              <button class="btn btn-ghost btn-sm" onclick="showPage('applications')">View All →</button>
            </div>
            <div id="recent-list" style="padding:0"></div>
          </div>
        </div>

        <!-- Disbursement methods breakdown -->
        <div class="card" style="margin-top:20px">
          <div class="card-header"><div class="card-title">💳 Disbursement Methods Breakdown</div></div>
          <div style="padding:20px"><div class="mini-bar-wrap" id="chart-methods"></div></div>
        </div>
      <!-- ── BORROWER NOTIFICATIONS ─────────────────────────────────────────── -->
      <div class="card" id="borrower-notifications" style="margin-top:14px">
        <div class="card-header" style="background:#163E33">
          <div class="card-title" style="color:white">📱 Borrower Notifications</div>
          <div style="font-size:8pt;color:rgba(255,255,255,.6)">Notify borrowers when their loan status changes</div>
        </div>
        <div style="padding:18px 18px 14px">
          <div style="background:#e8f5e9;border-radius:8px;padding:12px 14px;font-size:8pt;color:#1b5e20;line-height:1.9;margin-bottom:14px">
            <strong>How it works:</strong><br>
            When you change a borrower's status to <strong>Under Review, Approved, Disbursed</strong> or <strong>Rejected</strong>, an automatic WhatsApp message is sent to their registered phone number using the same TextMeBot API key saved in Notification Settings.<br><br>
            ✅ <strong>Approved</strong> — Congratulations message + disbursement notice<br>
            💰 <strong>Disbursed</strong> — Confirmation + repayment reminder<br>
            🔄 <strong>Under Review</strong> — Application received confirmation<br>
            ❌ <strong>Rejected</strong> — Polite decline with contact details
          </div>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f8f8f8;border-radius:7px;border:1px solid #e0e0e0">
            <span style="font-size:14pt">📱</span>
            <div style="flex:1">
              <div style="font-size:9pt;font-weight:700;color:#163E33">Auto-notify borrowers on status change</div>
              <div style="font-size:8pt;color:#888;margin-top:2px">Requires TextMeBot in Notification Settings (WhatsApp tab)</div>
            </div>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
              <input type="checkbox" id="borrower-notif-enabled" aria-label="Notify borrowers automatically" onchange="saveBorrowerNotifPref()"
                style="width:18px;height:18px;accent-color:var(--navy);cursor:pointer">
              <span style="font-size:9pt;font-weight:700;color:#163E33">Enabled</span>
            </label>
          </div>
          <div id="borrower-notif-status" style="font-size:8pt;color:#888;margin-top:10px;text-align:center"></div>
        </div>
      </div>
      </div>

      <!-- ── APPLICATIONS PAGE ── -->
      <div class="page" id="page-applications">
        <div class="card">
          <div class="card-header">
            <div class="card-title">All Loan Applications</div>
            <div class="card-actions">
              <button class="btn btn-primary btn-sm admin-only" onclick="openBackupModal()">📦 Backup / Restore</button>
              <button class="btn btn-gold btn-sm" onclick="exportCSV()">📤 Export CSV</button>
              <button class="btn btn-ghost btn-sm" onclick="clearFilters()">Clear Filters</button>
              <span id="app-count" style="font-size:8.5pt;color:#888"></span>
            </div>
          </div>
          <div class="filter-row">
            <span class="filter-label">Status:</span>
            <select class="filter-select" id="filter-status" aria-label="Filter applications by status" onchange="applyFilters()">
              <option value="">All Statuses</option>
              <option>New Application</option>
              <option>Under Review</option>
              <option>Approved</option>
              <option>Disbursed</option>
              <option>Repaid</option>
              <option>Rejected</option>
            </select>
            <span class="filter-label">Duration:</span>
            <select class="filter-select" id="filter-dur" aria-label="Filter applications by loan duration" onchange="applyFilters()">
              <option value="">All Durations</option>
              <option>1 Week</option><option>2 Weeks</option>
              <option>3 Weeks</option><option>4 Weeks</option>
            </select>
            <span class="filter-label">Sort:</span>
            <select class="filter-select" id="filter-sort" aria-label="Sort applications" onchange="applyFilters()">
              <option value="newest">Newest First</option>
              <option value="oldest">Oldest First</option>
              <option value="amount-high">Amount ↑</option>
              <option value="amount-low">Amount ↓</option>
              <option value="name">Name A–Z</option>
            </select>
          </div>
          <div style="overflow-x:auto">
            <table id="app-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Borrower</th>
                  <th>Loan</th>
                  <th>Total Repayable</th>
                  <th>Term</th>
                  <th>Documents</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="app-tbody"></tbody>
            </table>
            <div id="app-empty" class="empty-state" style="display:none">
              <div class="empty-icon">📭</div>
              <div class="empty-title">No applications found</div>
              <div class="empty-sub">Submitted applications from the loan form will appear here</div>
            </div>
          </div>
        </div>
      </div>


      <!-- ── CURRENCY / FX PAGE ── -->
      <div class="page" id="page-fx">
        <div class="card" style="max-width:760px">
          <div class="card-header">
            <div class="card-title">💱 Currency Converter</div>
            <div class="card-actions">
              <button id="afx-refresh" onclick="afxSync()"
                style="background:#D98E3B;color:#163E33;border:none;padding:8px 16px;border-radius:6px;font-size:8.5pt;font-weight:700;cursor:pointer">🔄 Refresh Rates</button>
            </div>
          </div>
          <div style="padding:22px 24px 24px">

            <div id="afx-status" style="font-size:8.5pt;color:#888;margin-bottom:18px">
              <span class="afx-dot"></span>Loading rates…
            </div>

            <div class="afx-row">
              <div class="afx-f" style="flex:1.3 1 150px">
                <label for="afx_amt">Amount</label>
                <input type="text" id="afx_amt" inputmode="decimal" value="10000" autocomplete="off">
              </div>
              <div class="afx-f" style="flex:1 1 130px">
                <label for="afx_from">From</label>
                <select id="afx_from"></select>
              </div>
              <button type="button" class="afx-swap" onclick="afxSwap()" title="Swap" aria-label="Swap currencies">⇄</button>
              <div class="afx-f" style="flex:1 1 130px">
                <label for="afx_to">To</label>
                <select id="afx_to"></select>
              </div>
            </div>

            <div class="afx-out">
              <div>
                <span class="afx-out-lbl">Converted</span>
                <span class="afx-out-val" id="afx_out">—</span>
              </div>
              <div class="afx-out-pair" id="afx_pair">—</div>
            </div>

            <div class="afx-board-head">Kwacha reference board</div>
            <table class="afx-board">
              <thead>
                <tr><th>Currency</th><th class="num">1 unit = ZMW</th><th class="num">K1,000 =</th></tr>
              </thead>
              <tbody id="afx_board">
                <tr><td colspan="3" style="text-align:center;color:#aaa;padding:18px">Loading…</td></tr>
              </tbody>
            </table>

            <!-- ═══ OFFICIAL BANK OF ZAMBIA RATE ═══════════════════════════ -->
            <div class="boz-panel">
              <div class="boz-head">
                <span class="boz-badge">Official</span>
                <div>
                  <strong>Bank of Zambia rate</strong>
                  <p>boz.zm publishes no readable API, so the official rate is entered here. Use this figure for anything ZRA or audit facing.</p>
                </div>
              </div>

              <div class="boz-current" id="boz_current">Checking current rate&hellip;</div>

              <div class="boz-grid">
                <div>
                  <label for="boz_buy">Buy / bid</label>
                  <input type="text" id="boz_buy" inputmode="decimal" placeholder="19.5746" autocomplete="off">
                </div>
                <div>
                  <label for="boz_sell">Sell / ask</label>
                  <input type="text" id="boz_sell" inputmode="decimal" placeholder="19.6246" autocomplete="off">
                </div>
                <div>
                  <label for="boz_date">Rate date</label>
                  <input type="date" id="boz_date" autocomplete="off">
                </div>
                <div>
                  <label for="boz_token">Update token <span style="font-weight:600;text-transform:none;letter-spacing:0">(optional)</span></label>
                  <input type="password" id="boz_token" placeholder="Not needed while signed in" autocomplete="off">
                </div>
              </div>

              <div class="boz-foot">
                <button type="button" class="boz-save" id="boz_save">Save official rate</button>
                <a href="https://www.boz.zm/markets-securities" target="_blank" rel="noopener noreferrer"
                   class="boz-link">Open boz.zm &#8599;</a>
                <span class="boz-mid" id="boz_mid"></span>
              </div>
            </div>

            <p style="font-size:8pt;color:#999;line-height:1.7;margin-top:14px">
              Rates are cached server-side in <code>rates_cache.json</code> and refresh hourly.
              For figures that go onto a loan agreement or statement, use the
              <code>fxConvert()</code> helper in <code>api.php</code> and store the rate used
              alongside the amount — never re-convert a stored figure later.
            </p>
          </div>
        </div>
      </div>

      <!-- ── SETTINGS PAGE ── -->
      <div class="page" id="page-settings">
        <div class="card" style="max-width:640px">
          <div class="card-header">
            <div class="card-title">⚙️ Records Sync — PHP API</div>
          </div>
          <div style="padding:24px">
            <div style="background:#e8f5e9;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:8.5pt;color:#1b5e20;line-height:1.9">
              <strong>How it works:</strong><br>
              1. <code>api.php</code> lives in the same folder as this file on your server.<br>
              2. Every borrower submission is saved to <code>records_data.json</code> on the server automatically — no setup needed.<br>
              3. Any device that opens this admin portal or the staff tab fetches records from <code>api.php</code> directly — same domain, no login or CORS issues.<br>
              4. Status changes and deletes sync back to the server file instantly.
            </div>

            <label style="font-size:8pt;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px" for="script-url-input">API Endpoint</label>
            <input id="script-url-input" type="text" placeholder="api.php"
              style="width:100%;padding:11px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:9pt;outline:none;margin-bottom:14px"
              onfocus="this.style.borderColor='#D98E3B'" onblur="this.style.borderColor='#ddd'">
            <p style="font-size:7.5pt;color:#aaa;margin:-10px 0 14px;line-height:1.6">Leave as <code>api.php</code> unless you moved the file to a different path. Only change this if your admin portal is hosted on a different domain than <code>api.php</code>.</p>

            <div style="display:flex;gap:10px;margin-bottom:20px">
              <button onclick="saveScriptUrl()" style="background:#163E33;color:white;border:none;padding:10px 22px;border-radius:7px;font-size:9pt;font-weight:700;cursor:pointer">💾 Save & Sync</button>
              <button onclick="fetchRemoteRecords(false)" style="background:#D98E3B;color:#163E33;border:none;padding:10px 22px;border-radius:7px;font-size:9pt;font-weight:700;cursor:pointer">🔄 Test Connection</button>
            </div>
            <div id="script-url-status" style="font-size:8.5pt;color:#888;margin-bottom:24px"></div>

            <div style="border-top:1px solid #f0f0f0;padding-top:20px">
              <div style="font-weight:700;color:#163E33;font-size:10pt;margin-bottom:10px">📋 Setup Checklist</div>
              <ul style="font-size:8.5pt;color:#555;line-height:2;padding-left:20px;margin:0">
                <li>Upload <code>api.php</code> to the same folder as <code>index.html</code> and <code>admin.html</code></li>
                <li>Visit <code>yourdomain.com/api.php?action=ping</code> in your browser — should show <code>{"ok":true,...}</code></li>
                <li>Make sure the folder is writable (cPanel File Manager → folder permissions → 755 or 775)</li>
                <li>A file called <code>records_data.json</code> will appear automatically next to <code>api.php</code> once the first application is submitted</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /app -->

<!-- ═══ DETAIL MODAL ═══ -->
<div class="modal-overlay" id="detail-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-title">Application Details</div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modal-body"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal()">Close</button>
      <button class="btn btn-primary" id="notify-borrower-btn" onclick="notifyBorrowerFromModal()" title="Send WhatsApp status update to borrower">📱 Notify Borrower</button>
      <button class="btn btn-gold" onclick="printRecord()">🖨 Print</button>
    </div>
  </div>
</div>

<!-- ═══ DOC VIEWER ═══ -->
<div id="doc-viewer" onclick="if(event.target===this)closeDV()">
  <div class="dv-wrap">
    <div class="dv-header">
      <div class="dv-title" id="dv-title"></div>
      <div style="display:flex;gap:8px;flex-shrink:0">
        <a id="dv-dl" download style="background:var(--gold);color:var(--navy);padding:6px 12px;border-radius:5px;font-size:8pt;font-weight:700;text-decoration:none">⬇ Download</a>
        <button onclick="closeDV()" style="background:rgba(255,255,255,.2);border:none;color:white;padding:6px 10px;border-radius:5px;cursor:pointer;font-size:11pt">✕</button>
      </div>
    </div>
    <div class="dv-body" id="dv-body"></div>
  </div>
</div>

<script>
// ─── CONFIG ──────────────────────────────────────────────────────────────────
// Access is authenticated by av_require_login on the server.
const STORAGE_KEY = 'avesta_submission_'; // Must match loan form storage key
// API endpoint (api.php) — same one used by the loan form for submissions.
// Stored in localStorage so it can be set from the UI without editing code.
function getScriptUrl() {
  return localStorage.getItem('avesta_script_url') || 'api.php';
}

// ─── AUTH ────────────────────────────────────────────────────────────────────

// ─── IDLE AUTO-LOCK (5 minutes) ──────────────────────────────────────────────
const IDLE_TIMEOUT_MS = 5 * 60 * 1000; // 5 minutes
const IDLE_WARNING_MS = 4 * 60 * 1000; // warn at 4 minutes
let idleTimer = null;
let idleWarnTimer = null;
let idleToastShown = false;

function resetIdleTimer() {
  clearTimeout(idleTimer);
  clearTimeout(idleWarnTimer);
  // Dismiss warning toast if it was showing
  if (idleToastShown) {
    idleToastShown = false;
    const t = document.getElementById('admin-toast');
    if (t) t.style.opacity = '0';
  }
  idleWarnTimer = setTimeout(() => {
    idleToastShown = true;
    showToast('Session will lock in 1 minute due to inactivity.', 'gold');
  }, IDLE_WARNING_MS);
  idleTimer = setTimeout(() => {
    idleToastShown = false;
    doLogout();
    showToast('Session locked after 5 minutes of inactivity.', 'red');
  }, IDLE_TIMEOUT_MS);
}

function startIdleWatch() {
  ['mousemove','mousedown','keydown','touchstart','scroll','click'].forEach(evt =>
    document.addEventListener(evt, resetIdleTimer, { passive: true })
  );
  resetIdleTimer();
}

function stopIdleWatch() {
  clearTimeout(idleTimer);
  clearTimeout(idleWarnTimer);
  ['mousemove','mousedown','keydown','touchstart','scroll','click'].forEach(evt =>
    document.removeEventListener(evt, resetIdleTimer)
  );
}

function doLogin() {
  if (!window.AV_USER || !['super_admin', 'admin', 'staff'].includes(window.AV_USER.role)) {
    window.location.href = '../login.php';
    return;
  }
  document.getElementById('app').style.display = 'block';
  if (window.AV_USER.role === 'staff') {
    document.querySelectorAll('.admin-only').forEach(function (el) { el.remove(); });
  }
  init();
  startIdleWatch();
}
document.addEventListener('DOMContentLoaded', doLogin);

function toggleSidebar(force_close) {
  const sb = document.querySelector('.sidebar');
  const ov = document.getElementById('sidebar-overlay');
  if (!sb || !ov) return;
  const btn = document.getElementById('sidebar-toggle');
  const isOpen = sb.classList.contains('open');
  if (force_close || isOpen) {
    sb.classList.remove('open');
    if (btn) btn.setAttribute('aria-expanded', 'false');
    ov.style.display = 'none';
    ov.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  } else {
    toggleWaPanel(true);
    sb.classList.add('open');
    if (btn) btn.setAttribute('aria-expanded', 'true');
    ov.style.display = 'block';
    ov.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
}

function updateSidebarToggle() {
  const btn = document.getElementById('sidebar-toggle');
  if (!btn) return;
  const isMobile = window.innerWidth <= 768;
  btn.style.display = isMobile ? 'inline-flex' : 'none';
  if (!isMobile) toggleSidebar(true);
}

// Init sidebar behaviour after DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  window.addEventListener('resize', updateSidebarToggle);
  updateSidebarToggle();
  // Close sidebar when any nav item is tapped on mobile
  document.querySelectorAll('.nav-item').forEach(function(el) {
    el.addEventListener('click', function() {
      if (window.innerWidth <= 768) toggleSidebar(true);
    });
  });
});

function doLogout() {
  stopIdleWatch();
  window.location.href = '../logout.php';
}

// ─── DATA ────────────────────────────────────────────────────────────────────
let allRecords = [];
let filteredRecords = [];
let currentRecord = null;

const DOC_LABELS = {
  doc_nrc_front:      { icon:'🪪', label:'NRC — Front' },
  doc_nrc_back:       { icon:'🪪', label:'NRC — Back' },
  doc_passport:       { icon:'🖼️', label:'Passport Photo' },
  doc_collateral1:    { icon:'📦', label:'Collateral Photo 1' },
  doc_collateral2:    { icon:'📦', label:'Collateral Photo 2' },
  doc_collateral3:    { icon:'📦', label:'Collateral Photo 3' },
  doc_salary1:        { icon:'📄', label:'Salary Proof 1' },
  doc_salary2:        { icon:'📄', label:'Salary Proof 2' },
  doc_payslip:        { icon:'💼', label:'Payslip' },
  doc_bank_statement: { icon:'🏦', label:'Bank Statement' },
  doc_utility_bill:   { icon:'🏠', label:'Utility Bill' },
  doc_employer_letter:{ icon:'📋', label:'Employer Letter' },
  doc_gtr_nrc:        { icon:'🪪', label:'Guarantor NRC' },
};

const STATUS_CLASSES = {
  'New Application':'badge-new','Under Review':'badge-review',
  'Approved':'badge-approved','Disbursed':'badge-disbursed',
  'Repaid':'badge-repaid','Rejected':'badge-rejected'
};

// ─── NORMALISE RECORD FIELDS ─────────────────────────────────────────────────
function normalise(r) {
  r.full_name      = r.full_name      || r.b_name              || '—';
  r.national_id    = r.national_id    || r.b_id                || '—';
  r.phone          = r.phone          || r.b_phone             || '—';
  r.address        = r.address        || r.b_address           || '—';
  r.email          = r.email          || r.b_email             || '—';
  r.occupation     = r.occupation     || r.b_occupation        || '—';
  r.monthly_income = r.monthly_income || r.b_income            || '—';
  r.date_of_birth  = r.date_of_birth  || r.b_dob               || '—';
  r.loan_amount    = r.loan_amount    || r.loan_amt             || '0';
  r.loan_purpose   = r.loan_purpose                            || '—';
  r.loan_duration  = r.loan_duration  || r.sel_dur              || '—';
  r.interest_rate  = r.interest_rate  || r.int_rate             || '—';
  r.total_repayment= r.total_repayment|| r.total_repay          || '—';
  r.repay_method   = r.repay_method   || r.radio_repay_method   || '—';
  r.receive_method = r.receive_method || r.radio_disburse        || '—';
  r.status         = r.status                                   || 'New Application';
  return r;
}

// ─── LOAD RECORDS: Server (primary) + localStorage (cache/fallback) ────────
let isFetchingRemote = false;

function loadRecords() {
  // Always load from localStorage cache first (instant)
  allRecords = [];
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    if (k && k.startsWith(STORAGE_KEY)) {
      try {
        const r = JSON.parse(localStorage.getItem(k));
        r._key = k;
        allRecords.push(normalise(r));
      } catch(e) {}
    }
  }
  allRecords.sort((a,b) => new Date(b.submitted_at||0) - new Date(a.submitted_at||0));
  filteredRecords = [...allRecords];
}

// Fetch all records from the server and merge into localStorage cache

async function fetchRemoteRecords(silent) {
  const url = getScriptUrl();

  // Always load local cache first — never block the UI on network
  loadRecords();
  applyFilters();
  renderDashboard();
  // On a cold start there is nothing cached, so show the shape of the table
  // rather than an empty box that looks like an error.
  if (!allRecords.length && typeof avSkeletonRows === 'function') {
    const sk = document.getElementById('app-tbody');
    const ek = document.getElementById('app-empty');
    if (ek) ek.style.display = 'none';
    avSkeletonRows(sk, 6, 8);
  } else {
    renderTable();
  }
  updateNavBadge();

  if (!url) {
    showSyncBanner('error', 'api.php not found — make sure it is uploaded to the same folder as this file');
    return;
  }
  if (isFetchingRemote) return;
  isFetchingRemote = true;
  if (!silent) showSyncBanner('syncing');

  try {
    // avFetchJson wires the abort signal to the request, so the timeout
    // actually cancels a hung call, and reports HTML error pages plainly.
    const json = (await window.avFetchJson(
      url + '?action=getRecords&t=' + Date.now(), {}, 10000)).data;
    if (!json.records || !Array.isArray(json.records)) throw new Error('Server returned: ' + JSON.stringify(json).slice(0,120));
    let newCount = 0;
    json.records.forEach(r => {
      if (!r._key) r._key = STORAGE_KEY + (r.submitted_at || Date.now()).toString().replace(/[^a-z0-9]/gi,'_');
      normalise(r);
      const existing = localStorage.getItem(r._key);
      if (!existing) {
        localStorage.setItem(r._key, JSON.stringify(r));
        newCount++;
      } else {
        try {
          const local = JSON.parse(existing);
          const merged = Object.assign({}, r, {
            ...Object.fromEntries(Object.entries(local).filter(([k]) => k.endsWith('_base64'))),
            status: r.status || local.status || 'New Application'
          });
          merged._key = r._key;
          localStorage.setItem(r._key, JSON.stringify(merged));
        } catch(e) {}
      }
    });
    loadRecords();
    applyFilters();
    renderDashboard();
    renderTable();
    updateNavBadge();
    showSyncBanner('ok', json.records.length + ' record' + (json.records.length!==1?'s':'') + ' synced' + (newCount>0?' · '+newCount+' new':''));
    if (newCount > 0) showToast('🔔 ' + newCount + ' new record(s) synced from server!', 'green');
  } catch(e) {
    const msg = e.name === 'AbortError' ? 'Request timed out after 10s' : e.message;
    showSyncBanner('error', 'Sheets sync failed: ' + msg + ' — showing ' + allRecords.length + ' cached record' + (allRecords.length!==1?'s':''));
    console.warn('fetchRemoteRecords error:', e);
  } finally {
    isFetchingRemote = false;
    // Always replace the loading skeleton, success or failure — otherwise a
    // failed cold-start sync leaves shimmer bars on screen indefinitely.
    renderTable();
  }
}

// Push a status change back to the server
async function pushStatusToRemote(r) {
  const url = getScriptUrl();
  if (!url) return;
  try {
    await fetch(url + '?action=updateStatus', {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body: new URLSearchParams({key:r._key, status:r.status, submitted_at:r.submitted_at || ''})
    });
  } catch(e) { console.warn('Status push failed:', e); }
}

// Push a delete to the server
async function pushDeleteToRemote(key, submitted_at) {
  const url = getScriptUrl();
  if (!url) return;
  try {
    await fetch(url + '?action=deleteRecord', {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body: new URLSearchParams({key:key, submitted_at:submitted_at || ''})
    });
  } catch(e) { console.warn('Delete push failed:', e); }
}

function saveRecord(r) {
  try { localStorage.setItem(r._key, JSON.stringify(r)); } catch(e) {}
}

// ─── SYNC STATUS BANNER ───────────────────────────────────────────────────────
function showSyncBanner(state, msg) {
  let el = document.getElementById('sync-banner');
  if (!el) {
    el = document.createElement('div');
    el.id = 'sync-banner';
    el.style.cssText = 'position:fixed;top:60px;left:260px;right:0;z-index:40;padding:6px 20px;font-size:8pt;font-weight:600;text-align:center;transition:opacity .5s;display:flex;align-items:center;justify-content:center;gap:8px';
    document.body.appendChild(el);
  }
  clearTimeout(el._t);
  if (state === 'syncing') {
    el.style.background = '#163E33'; el.style.color = '#D98E3B'; el.style.opacity = '1';
    el.innerHTML = '<span style="animation:spin 1s linear infinite;display:inline-block">⟳</span> Syncing with server…';
  } else if (state === 'ok') {
    el.style.background = '#d4edda'; el.style.color = '#155724'; el.style.opacity = '1';
    el.innerHTML = '✅ ' + (msg || 'Synced');
    el._t = setTimeout(() => { el.style.opacity = '0'; }, 3000);
  } else if (state === 'error') {
    el.style.background = '#fff3cd'; el.style.color = '#856404'; el.style.opacity = '1';
    el.innerHTML = '⚠️ ' + (msg || 'Sync error');
    el._t = setTimeout(() => { el.style.opacity = '0'; }, 6000);
  }
}

// ─── INIT ────────────────────────────────────────────────────────────────────
function init() {
  document.getElementById('topbar-date').textContent = new Date().toLocaleDateString('en-ZM', {weekday:'short',year:'numeric',month:'short',day:'numeric'});
  loadRecords();
  applyFilters();
  renderDashboard();
  renderTable();
  updateNavBadge();

  loadBorrowerNotifPref(); // load borrower notification preference
  // ── Fetch from server immediately ────────────────────────────────────
  fetchRemoteRecords(false);

  // ── localStorage event (same-device cross-tab) ────────────────────────
  window.addEventListener('storage', function(e) {
    if (e.key && e.key.startsWith(STORAGE_KEY)) {
      liveRefresh('New submission received from applicant form');
    }
  });

  // ── Remote polling every 30 seconds (catches submissions from any device) ─
  let _lastRemoteHash = '';
  setInterval(async function() {
    // Also check local storage for same-device submissions
    const localHash = recordsHash();
    if (localHash !== _lastRemoteHash) {
      _lastRemoteHash = localHash;
      const prevCount = allRecords.length;
      loadRecords();
      if (allRecords.length > prevCount) {
        const toNotify = allRecords.filter(r => r._notify_pending);
        const targets  = toNotify.length ? toNotify : (allRecords[0] ? [allRecords[0]] : []);
        targets.forEach(r => {
          sendWhatsAppAlert(r);
          sendBrowserNotif(r);
          if (r._notify_pending) { r._notify_pending = false; saveRecord(r); }
        });
        liveRefresh('🔔 New application just submitted!');
        showToast('New application received. Review it from the dashboard.', 'green');
      } else {
        liveRefresh(null);
      }
    }
    // Silently sync remote every 30s
    await fetchRemoteRecords(true);
  }, 30000);
}

function recordsHash() {
  let h = 0;
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    if (k && k.startsWith(STORAGE_KEY)) h += k.length + (localStorage.getItem(k)||'').length;
  }
  return h;
}

function liveRefresh(toastMsg) {
  loadRecords();
  applyFilters();
  renderDashboard();
  renderTable();
  updateNavBadge();
  if (toastMsg) showToast(toastMsg, 'green');
}

function showToast(msg, color) {
  let t = document.getElementById('admin-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'admin-toast';
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;padding:14px 20px;border-radius:10px;font-size:9.5pt;font-weight:700;color:white;box-shadow:0 6px 24px rgba(0,0,0,.25);transition:opacity .4s;max-width:320px;line-height:1.5';
    document.body.appendChild(t);
  }
  t.style.background = color === 'green' ? '#163E33' : color === 'red' ? '#c0392b' : '#D98E3B';
  t.style.opacity = '1';
  t.textContent = msg;
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.style.opacity = '0'; }, 4000);
}

// ─── NAVIGATION ──────────────────────────────────────────────────────────────
function showPage(id) {
  if (window.AV_USER && window.AV_USER.role === 'staff' && (id === 'settings' || id === 'accounts')) {
    avToast('This section is available to administrators only.', 'warn'); id = 'dashboard';
  }
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  const titles = {dashboard:'Dashboard', applications:'All Applications', settings:'Settings — Sync', fx:'Currency Rates'};
  document.getElementById('topbar-title').textContent = titles[id] || id;
  // Mark the matching nav item active
  document.querySelectorAll('.nav-item').forEach(n => {
    if (n.getAttribute('onclick') && n.getAttribute('onclick').includes("showPage('" + id + "')") &&
        !n.getAttribute('onclick').includes('filterByStatus')) {
      n.classList.add('active');
    }
  });
  if (id === 'applications') { loadRecords(); applyFilters(); renderTable(); }
  if (id === 'settings') { showPage_settings_init(); }
  if (id === 'dashboard') renderDashboard();
  if (id === 'fx') afxInit();
  if (window.innerWidth <= 768) toggleSidebar(true);
}

function updateNavBadge() {
  const statuses = {
    'New Application': 'nb-new',
    'Under Review':    'nb-review',
    'Approved':        'nb-approved',
    'Disbursed':       'nb-disbursed',
    'Repaid':          'nb-repaid',
    'Rejected':        'nb-rejected'
  };
  Object.entries(statuses).forEach(([status, id]) => {
    const count = allRecords.filter(r => r.status === status).length;
    const el = document.getElementById(id);
    if (el) { el.textContent = count; el.style.display = count > 0 ? '' : 'none'; }
  });
  // main new-count badge
  const newCount = allRecords.filter(r => r.status === 'New Application').length;
  const nc = document.getElementById('nav-new-count');
  if (nc) { nc.textContent = newCount; nc.style.display = newCount > 0 ? '' : 'none'; }
}

// ─── DASHBOARD ───────────────────────────────────────────────────────────────
function fmt(n) {
  return 'ZMW ' + parseFloat(n||0).toLocaleString('en-ZM',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function renderDashboard() {
  loadRecords();
  const today = new Date().toLocaleDateString('en-ZM');
  const todayCount = allRecords.filter(r => {
    try { return new Date(r.submitted_at).toLocaleDateString('en-ZM') === today; } catch(e){ return false; }
  }).length;

  const byStatus = s => allRecords.filter(r => r.status === s);
  const sumAmt   = arr => arr.reduce((t,r) => t + parseFloat(r.loan_amount||0), 0);
  const totalAmt = sumAmt(allRecords);

  document.getElementById('s-total').textContent      = allRecords.length;
  document.getElementById('s-today').textContent      = todayCount + ' today';
  document.getElementById('s-new').textContent        = byStatus('New Application').length;
  document.getElementById('s-approved').textContent   = byStatus('Approved').length;
  document.getElementById('s-approved-amt').textContent = fmt(sumAmt(byStatus('Approved')));
  document.getElementById('s-disbursed').textContent  = byStatus('Disbursed').length;
  document.getElementById('s-disbursed-amt').textContent = fmt(sumAmt(byStatus('Disbursed')));
  document.getElementById('s-total-amt').textContent  = fmt(totalAmt);
  document.getElementById('s-rejected').textContent   = byStatus('Rejected').length;
  document.getElementById('s-repaid').textContent     = byStatus('Repaid').length + ' repaid';

  // Duration chart
  const durs = {'1 Week':0,'2 Weeks':0,'3 Weeks':0,'4 Weeks':0,'Other':0};
  allRecords.forEach(r => {
    const d = (r.loan_duration||'').slice(0,7);
    if (d.startsWith('1')) durs['1 Week']++;
    else if (d.startsWith('2')) durs['2 Weeks']++;
    else if (d.startsWith('3')) durs['3 Weeks']++;
    else if (d.startsWith('4')) durs['4 Weeks']++;
    else durs['Other']++;
  });
  const maxDur = Math.max(...Object.values(durs), 1);
  document.getElementById('chart-duration').innerHTML = Object.entries(durs)
    .filter(([,v]) => v > 0)
    .map(([k,v]) => `<div class="mini-bar-row">
      <span class="mini-bar-label">${k}</span>
      <div class="mini-bar-track"><div class="mini-bar-fill" style="width:${Math.round(v/maxDur*100)}%"></div></div>
      <span class="mini-bar-val">${v}</span>
    </div>`).join('') || '<div style="color:#bbb;font-size:9pt;padding:8px 0">No data yet</div>';

  // Methods chart
  const methods = {};
  allRecords.forEach(r => {
    const m = r.receive_method || '—';
    methods[m] = (methods[m]||0) + 1;
  });
  const maxM = Math.max(...Object.values(methods), 1);
  document.getElementById('chart-methods').innerHTML = Object.entries(methods)
    .sort((a,b)=>b[1]-a[1])
    .map(([k,v]) => `<div class="mini-bar-row">
      <span class="mini-bar-label">${k}</span>
      <div class="mini-bar-track"><div class="mini-bar-fill" style="width:${Math.round(v/maxM*100)}%"></div></div>
      <span class="mini-bar-val">${v}</span>
    </div>`).join('') || '<div style="color:#bbb;font-size:9pt;padding:8px 0">No data yet</div>';

  // Recent list
  const recent = allRecords.slice(0,6);
  document.getElementById('recent-list').innerHTML = recent.length ? recent.map(r =>
    `<div onclick="openModal('${r._key}')" style="padding:12px 20px;border-bottom:1px solid #f5f5f5;cursor:pointer;transition:background .15s" onmouseover="this.style.background='#fafbfc'" onmouseout="this.style.background=''">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <div>
          <div style="font-weight:700;color:var(--navy);font-size:9.5pt">${r.full_name}</div>
          <div style="font-size:8pt;color:#888;margin-top:2px">${r.loan_duration||'—'} · ${r.submitted_at||'—'}</div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          <span style="font-weight:700;color:var(--navy);font-size:9pt">${fmt(r.loan_amount)}</span>
          <span class="badge ${STATUS_CLASSES[r.status]||'badge-new'}">${r.status||'New'}</span>
        </div>
      </div>
    </div>`
  ).join('') : '<div class="empty-state" style="padding:32px"><div class="empty-icon" style="font-size:28pt">📭</div><div class="empty-title">No applications yet</div></div>';
}

// ─── APPLICATIONS TABLE ───────────────────────────────────────────────────────
function applyFilters() {
  const status = document.getElementById('filter-status').value;
  const dur    = document.getElementById('filter-dur').value;
  const sort   = document.getElementById('filter-sort').value;
  const search = document.getElementById('global-search').value.toLowerCase();

  filteredRecords = allRecords.filter(r => {
    if (status && r.status !== status) return false;
    if (dur && !(r.loan_duration||'').startsWith(dur.slice(0,1))) return false;
    if (search) {
      const hay = [r.full_name,r.national_id,r.phone,r.address,r.loan_purpose,r.email].join(' ').toLowerCase();
      if (!hay.includes(search)) return false;
    }
    return true;
  });

  if (sort === 'newest')           filteredRecords.sort((a,b) => new Date(b.submitted_at||0) - new Date(a.submitted_at||0));
  else if (sort === 'oldest')      filteredRecords.sort((a,b) => new Date(a.submitted_at||0) - new Date(b.submitted_at||0));
  else if (sort === 'amount-high') filteredRecords.sort((a,b) => parseFloat(b.loan_amount||0) - parseFloat(a.loan_amount||0));
  else if (sort === 'amount-low')  filteredRecords.sort((a,b) => parseFloat(a.loan_amount||0) - parseFloat(b.loan_amount||0));
  else if (sort === 'name')        filteredRecords.sort((a,b) => (a.full_name||'').localeCompare(b.full_name||''));
}

function clearFilters() {
  document.getElementById('filter-status').value = '';
  document.getElementById('filter-dur').value = '';
  document.getElementById('filter-sort').value = 'newest';
  document.getElementById('global-search').value = '';
  // Reset nav — only highlight the All Applications nav item
  document.querySelectorAll('.nav-item').forEach(n => {
    n.classList.remove('active');
    const oc = n.getAttribute('onclick') || '';
    if (oc === "showPage('applications')") n.classList.add('active');
  });
  document.getElementById('topbar-title').textContent = 'All Applications';
  loadRecords();
  applyFilters();
  renderTable();
}

function globalSearch() {
  // Switch to applications page if not already there
  if (!document.getElementById('page-applications').classList.contains('active')) {
    showPage('applications');
    return; // showPage will call renderTable
  }
  loadRecords();
  applyFilters();
  renderTable();
}

function filterByStatus(s) {
  // Ensure we are on the applications page
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById('page-applications').classList.add('active');
  document.getElementById('topbar-title').textContent = s || 'All Applications';
  // Mark nav active
  document.querySelectorAll('.nav-item').forEach(n => {
    n.classList.remove('active');
    const oc = n.getAttribute('onclick') || '';
    if (oc.includes("filterByStatus('" + s + "')")) n.classList.add('active');
  });
  // Set filter dropdown and render
  document.getElementById('filter-status').value = s;
  loadRecords();
  applyFilters();
  renderTable();
}

function renderTable() {
  const tbody = document.getElementById('app-tbody');
  const empty = document.getElementById('app-empty');
  document.getElementById('app-count').textContent = filteredRecords.length + ' of ' + allRecords.length + ' records';

  if (!filteredRecords.length) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    // An empty screen should say which kind of empty it is, and offer the way out.
    const filtered = allRecords.length > 0;
    const ico = empty.querySelector('.empty-icon');
    const ttl = empty.querySelector('.empty-title');
    const sub = empty.querySelector('.empty-sub');
    if (ico) ico.textContent = filtered ? '\uD83D\uDD0D' : '\uD83D\uDCED';
    if (ttl) ttl.textContent = filtered ? 'No records match these filters'
                                        : 'No applications yet';
    if (sub) sub.textContent = filtered
      ? 'Try a different status, duration, or search term.'
      : 'Applications submitted from the loan form land here automatically.';
    let act = empty.querySelector('.av-empty-act');
    if (!act) {
      act = document.createElement('button');
      act.type = 'button'; act.className = 'av-empty-act';
      empty.appendChild(act);
    }
    act.textContent = filtered ? 'Clear filters' : 'Check for new applications';
    act.onclick = filtered
      ? function () { if (typeof clearFilters === 'function') clearFilters(); }
      : function () { fetchRemoteRecords(false); };
    return;
  }
  empty.style.display = 'none';

  tbody.innerHTML = filteredRecords.map((r, i) => {
    const docCount = Object.keys(DOC_LABELS).filter(k => (r[k+'_base64'] && r[k+'_base64'].length > 0) || r[k+'_url']).length;
    const status = r.status || 'New Application';
    return `<tr>
      <td style="color:#aaa;font-size:8pt">${i+1}</td>
      <td><div class="td-name">${escHtml(r.full_name)}</div><div style="font-size:7.5pt;color:#888">${escHtml(r.phone||r.national_id||'—')}</div></td>
      <td class="td-amount">${fmt(r.loan_amount)}</td>
      <td class="td-amount">${r.total_repayment && r.total_repayment !== '—' ? fmt(r.total_repayment) : '—'}</td>
      <td style="font-size:8.5pt;white-space:nowrap">${escHtml((r.loan_duration||'—').slice(0,10))}</td>
      <td style="text-align:center">
        ${docCount > 0 ? `<span style="background:#eafaf1;color:#198754;font-size:7.5pt;font-weight:700;padding:3px 8px;border-radius:20px">📎 ${docCount}</span>` : '<span style="color:#ddd;font-size:8pt">—</span>'}
      </td>
      <td>
        <select class="status-sel" onchange="updateStatus('${r._key}',this.value)">
          ${['New Application','Under Review','Approved','Disbursed','Repaid','Rejected'].map(s =>
            `<option value="${s}" ${status===s?'selected':''}>${s}</option>`).join('')}
        </select>
      </td>
      <td>
        <button class="btn btn-primary btn-sm" onclick="openModal('${r._key}')">View</button>
        <button class="btn btn-danger btn-sm" style="margin-top:3px" onclick="deleteRecord('${r._key}')">Delete</button>
      </td>
    </tr>`;
  }).join('');
}

function updateStatus(key, newStatus) {
  const r = allRecords.find(r => r._key === key);
  if (!r) return;
  const prevStatus = r.status;
  r.status = newStatus;
  saveRecord(r);
  pushStatusToRemote(r); // sync to server
  updateNavBadge();
  renderDashboard();
  applyFilters();
  renderTable();
  showToast('Application status updated: ' + newStatus, 'gold');
  // Notify borrower on key status changes
  if (newStatus !== prevStatus) notifyBorrower(r, newStatus);
}

// ── BORROWER NOTIFICATION ────────────────────────────────────────────────────
// Uses TextMeBot to send a WhatsApp message directly to the borrower's phone.
// NOTE: For production, a proper SMS gateway (e.g. Airtel/MTN bulk SMS API)
// would be more reliable — but TextMeBot works great for Zambian WhatsApp numbers.
function notifyBorrower(r, newStatus) {
  // Check if borrower notifications are enabled
  const enabled = JSON.parse(localStorage.getItem('avesta_borrower_notif_enabled') || 'false');
  if (!enabled) return;
  // Only notify on meaningful status transitions
  const notifyStatuses = ['Approved','Disbursed','Rejected','Under Review'];
  if (!notifyStatuses.includes(newStatus)) return;

  const borrowerPhone = (r.phone || r.b_phone || '').replace(/[^0-9]/g,'');
  if (!borrowerPhone) return;

  // Get admin's TextMeBot key (reuse same key to send to borrower's number)
  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (!saved.key) return;

  const name   = (r.full_name || 'Valued Customer').split(' ')[0];
  const amt    = 'ZMW ' + parseFloat(r.loan_amount||0).toLocaleString();
  const ref    = (r._key||'').replace('avesta_submission_','AE-').slice(0,14).toUpperCase();

  const messages = {
    'Under Review':
      'Dear ' + name + ', your loan application (' + ref + ') has been received and is currently under review by Avesta Enterprises. We will update you shortly. Thank you.',
    'Approved':
      'Congratulations ' + name + '! Your loan of ' + amt + ' (Ref: ' + ref + ') has been APPROVED by Avesta Enterprises. Disbursement will be processed shortly. Please contact us if you have any questions.',
    'Disbursed':
      'Dear ' + name + ', your loan of ' + amt + ' (Ref: ' + ref + ') has been DISBURSED. Please confirm receipt and remember your repayment schedule. Thank you for choosing Avesta Enterprises - Ndola.',
    'Rejected':
      'Dear ' + name + ', we regret to inform you that your loan application (Ref: ' + ref + ') was not approved at this time. Please contact Avesta Enterprises for more information. Thank you.'
  };

  const text = messages[newStatus] || ('Your loan application status: ' + newStatus + '. Ref: ' + ref + ' - Avesta Enterprises.');
  const url = 'https://api.textmebot.com/send.php?recipient=' + borrowerPhone +
    '&apikey=' + saved.key + '&text=' + encodeURIComponent(text);

  // Fire and forget via Image ping (no CORS issue)
  const img = new Image();
  img.src = url;
  img.onerror = img.onload = () => {};
  showToast('📱 Borrower notified via WhatsApp (' + borrowerPhone.slice(-4).padStart(borrowerPhone.length,'*') + ')', 'green');
  console.log('Borrower notification sent to', borrowerPhone, 'for status:', newStatus);
}

function saveBorrowerNotifPref() {
  const enabled = document.getElementById('borrower-notif-enabled').checked;
  localStorage.setItem('avesta_borrower_notif_enabled', JSON.stringify(enabled));
  const st = document.getElementById('borrower-notif-status');
  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (enabled && (!saved.number || !saved.key)) {
    st.style.color = '#c0392b';
    st.textContent = '⚠️ TextMeBot not configured yet — set up WhatsApp notifications above first';
  } else {
    st.style.color = enabled ? '#198754' : '#888';
    st.textContent = enabled
      ? '✅ Borrowers will be notified automatically when status changes'
      : '🔕 Borrower notifications are off';
  }
}

function loadBorrowerNotifPref() {
  const enabled = JSON.parse(localStorage.getItem('avesta_borrower_notif_enabled') || 'false');
  const cb = document.getElementById('borrower-notif-enabled');
  if (cb) cb.checked = enabled;
  saveBorrowerNotifPref();
}

async function deleteRecord(key) {
  const rec = allRecords.find(x => x._key === key);
  const who = rec && rec.full_name ? rec.full_name : 'this application';
  const go = await avConfirm('Delete ' + who + '?',
    'The record and its uploaded documents are removed from the server. This cannot be undone.',
    { okText: 'Delete record', cancelText: 'Keep it' });
  if (!go) return;
  const r = allRecords.find(x => x._key === key);
  const submitted_at = r ? r.submitted_at : '';
  localStorage.removeItem(key);
  allRecords = allRecords.filter(r => r._key !== key);
  filteredRecords = filteredRecords.filter(r => r._key !== key);
  pushDeleteToRemote(key, submitted_at); // sync delete to server
  renderDashboard();
  updateNavBadge();
  renderTable();
}

// ─── DETAIL MODAL ─────────────────────────────────────────────────────────────
function openModal(key) {
  const r = allRecords.find(r => r._key === key);
  if (!r) return;
  currentRecord = r;
  document.getElementById('modal-title').textContent = r.full_name + ' — Application Detail';

  const docCount = Object.keys(DOC_LABELS).filter(k => (r[k+'_base64'] && r[k+'_base64'].length > 0) || r[k+'_url']).length;

  let html = '';

  // Status control
  html += `<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:14px 16px;background:#f8f8f8;border-radius:8px;flex-wrap:wrap">
    <span style="font-size:8.5pt;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Status:</span>
    <select class="status-sel" style="font-size:9.5pt;padding:6px 12px" onchange="updateStatus('${r._key}',this.value);renderDashboard();renderTable()">
      ${['New Application','Under Review','Approved','Disbursed','Repaid','Rejected'].map(s=>
        `<option value="${s}" ${(r.status||'New Application')===s?'selected':''}>${s}</option>`).join('')}
    </select>
    <span style="margin-left:auto;font-size:8pt;color:#aaa">Submitted: ${escHtml(r.submitted_at||'—')}</span>
  </div>`;

  // ── STATUS BAR ────────────────────────────────────────────────────────────
  html += `<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:14px 16px;background:#f8f8f8;border-radius:8px;border-left:4px solid var(--gold);flex-wrap:wrap">
    <span style="font-size:8pt;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Status:</span>
    <select class="status-sel" style="font-size:9.5pt;padding:6px 12px;font-weight:700" onchange="updateStatus('${r._key}',this.value);renderDashboard();renderTable()">
      ${['New Application','Under Review','Approved','Disbursed','Repaid','Rejected'].map(s=>
        `<option value="${s}" ${(r.status||'New Application')===s?'selected':''}>${s}</option>`).join('')}
    </select>
    <span style="margin-left:auto;font-size:8pt;color:#aaa">Ref: <strong style="color:var(--navy)">${escHtml((r._key||'').replace('avesta_submission_','AE-').slice(0,16))}</strong> &nbsp;|&nbsp; Submitted: ${escHtml(r.submitted_at||'—')}</span>
  </div>`;

  // ── BORROWER INFORMATION ───────────────────────────────────────────────────
  html += section('👤 Borrower Information', `<div class="detail-grid">
    ${di('Full Name', r.full_name)}
    ${di('NRC / National ID', r.national_id)}
    ${di('Date of Birth', r.date_of_birth)}
    ${di('Phone', r.phone)}
    ${di('Email', r.email)}
    ${di('Occupation / Employer', r.occupation)}
    ${di('Monthly Income', r.monthly_income ? 'ZMW ' + parseFloat(r.monthly_income||0).toLocaleString() : '—')}
    ${di('Effective Date', r.effective_date || '—')}
    ${di('Physical Address', r.address, true)}
  </div>`);

  // ── LOAN DETAILS ───────────────────────────────────────────────────────────
  html += section('💰 Loan Details', `<div class="detail-grid">
    ${di('Loan Amount', r.loan_amount ? 'ZMW ' + parseFloat(r.loan_amount||0).toLocaleString() : '—')}
    ${di('Amount in Words', r.loan_words || r.loan_amt_words || '—')}
    ${di('Purpose of Loan', r.loan_purpose, true)}
    ${di('Disbursement Method', r.receive_method || r.radio_disburse || '—')}
    ${di('Disbursement Date', r.disburse_date || '—')}
  </div>`);

  // ── REPAYMENT TERMS ────────────────────────────────────────────────────────
  html += section('📅 Repayment Terms', `<div class="detail-grid">
    ${di('Loan Duration', r.loan_duration || '—')}
    ${di('Interest Rate', r.interest_rate ? r.interest_rate + '%' : '—')}
    ${di('Total Interest', r.total_interest ? 'ZMW ' + parseFloat(r.total_interest||0).toLocaleString() : r.total_int ? 'ZMW ' + parseFloat(r.total_int||0).toLocaleString() : '—')}
    ${di('Total Repayment', r.total_repayment ? 'ZMW ' + parseFloat(r.total_repayment||0).toLocaleString() : '—')}
    ${di('Repayment Schedule', r.repay_schedule || '—')}
    ${di('Repayment Method', r.repay_method || r.radio_repay_method || '—')}
    ${di('No. of Installments', r.installments || r.num_inst || '—')}
    ${di('Installment Amount', (r.installment_amt||r.inst_amt) ? 'ZMW ' + parseFloat(r.installment_amt||r.inst_amt||0).toLocaleString() : '—')}
    ${di('First Payment Date', r.first_payment || '—')}
    ${di('Last Payment Date', r.last_payment || '—')}
    ${di('Late Payment Rate', r.late_payment_rate || r.late_rate_display || '—')}
    ${di('Weeks Overdue', r.weeks_overdue || '0')}
    ${di('Late Fee Amount', r.late_fee ? 'ZMW ' + parseFloat(r.late_fee||0).toLocaleString() : '—')}
  </div>`);

  // ── SECURITY / GUARANTEE ───────────────────────────────────────────────────
  const secType = r.security_type || r['radio_security_type'] || '';
  let secBody = `<div class="detail-grid">
    ${di('Security Type', secType || '—')}
    ${di('Collateral Items', r.collateral_items || '—', true)}`;
  if (r.checkoff_employer || r.co_employer) {
    secBody += `${diDivider('Salary Check-off / Payroll Deduction')}
    ${di('Employer / Company', r.checkoff_employer || r.co_employer || '—')}
    ${di('HR / Payroll Contact', r.checkoff_hr_name || r.co_hr_name || '—')}
    ${di('HR Phone', r.checkoff_hr_phone || r.co_hr_phone || '—')}
    ${di('Payroll / Employee No.', r.checkoff_payroll_no || r.co_payroll_no || '—')}`;
  }
  if (r.guarantor_name || r.gtr_name) {
    secBody += `${diDivider('Guarantor Details')}
    ${di('Guarantor Full Name', r.guarantor_name || r.gtr_name || '—')}
    ${di('Guarantor NRC / Passport', r.guarantor_id || r.gtr_id || '—')}
    ${di('Guarantor Phone', r.guarantor_phone || r.gtr_phone || '—')}
    ${di('Guarantor Relationship', r.guarantor_relationship || r.gtr_relationship || '—')}
    ${di('Guarantor Occupation', r.guarantor_occupation || r.gtr_occupation || '—')}
    ${di('Guarantor Monthly Income', (r.guarantor_income||r.gtr_income) ? 'ZMW ' + parseFloat(r.guarantor_income||r.gtr_income||0).toLocaleString() : '—')}`;
  }
  if (r.references && r.references !== '—') {
    secBody += `${diDivider('References')}${di('References', r.references, true)}`;
  }
  secBody += `</div>`;
  html += section('🔒 Security / Guarantee', secBody);

  // ── GPS LOCATION ───────────────────────────────────────────────────────────
  const hasGps = r.gps_latitude && r.gps_latitude !== '—';
  html += section('📍 GPS Location', `<div class="detail-grid">
    ${di('Latitude', r.gps_latitude || '—')}
    ${di('Longitude', r.gps_longitude || '—')}
    ${di('Accuracy', r.gps_accuracy_m && r.gps_accuracy_m !== '—' ? r.gps_accuracy_m + ' m' : '—')}
    <div class="detail-item"><label>Map Link</label><span>${hasGps
      ? `<a href="https://maps.google.com/?q=${r.gps_latitude},${r.gps_longitude}" target="_blank" style="color:var(--gold);font-weight:700">🗺 View on Google Maps ↗</a>`
      : '<span style="color:#ccc">Not captured</span>'}</span></div>
  </div>`);

  // ── SUPPORTING DOCUMENTS ───────────────────────────────────────────────────
  html += `<div class="modal-section">
    <div class="modal-section-title">📎 Supporting Documents <span style="background:${docCount>0?'#eafaf1':'#f5f5f5'};color:${docCount>0?'#198754':'#aaa'};font-size:7.5pt;padding:2px 8px;border-radius:20px;font-weight:700;margin-left:6px">${docCount} uploaded</span></div>
    <div class="doc-tiles">
      ${Object.entries(DOC_LABELS).map(([k,meta]) => {
        const has = !!((r[k+'_base64'] && r[k+'_base64'].length > 0) || r[k+'_url']);
        const fname = r[k+'_filename'] || meta.label;
        const mime  = r[k+'_mimetype'] || '';
        return '<div class="doc-tile ' + (has?'has-doc':'') + '"' + (has?' data-document-field="'+k+'" role="button" tabindex="0"':'') + '>' +
          '<div class="doc-tile-icon">' + meta.icon + '</div>' +
          '<div class="doc-tile-label">' + meta.label + '</div>' +
          '<div class="doc-tile-status" style="color:' + (has?'#198754':'#ccc') + '">' + (has?'✅ View / Download':'Not uploaded') + '</div>' +
        '</div>';
      }).join('')}
    </div>
  </div>`;

  // ── SIGNATURES & WITNESS ───────────────────────────────────────────────────
  const hasSig = r.borrower_signature && r.borrower_signature !== '—';
  html += section('✍️ Signatures & Witness', `<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap">
    <div>
      <div style="font-size:8pt;color:#aaa;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Borrower Signature</div>
      ${hasSig
        ? `<div class="sig-preview" style="text-align:center;background:#f9f9f9;border:1px solid #eee;border-radius:6px;padding:10px"><img src="${r.borrower_signature}" alt="Borrower signature" style="max-height:70px;max-width:100%;object-fit:contain"></div>`
        : `<div style="background:#f9f9f9;border:1px dashed #ddd;border-radius:6px;padding:16px;text-align:center;color:#bbb;font-size:8.5pt;font-style:italic">No signature captured</div>`}
      <div style="margin-top:10px;font-size:8.5pt;color:#555;line-height:2">
        ${di('Printed Name', r.borrower_printed_name || r.sig_borrower_name || '—')}
        ${di('Date Signed', r.borrower_sign_date || r.sig_borrower_date || '—')}
      </div>
    </div>
    <div>
      <div style="font-size:8pt;color:#aaa;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Witness</div>
      <div class="detail-grid" style="grid-template-columns:1fr">
        ${di('Witness Name', r.witness_name || r.wit_name || '—')}
        ${di('Witness Phone', r.witness_phone || r.wit_phone || '—')}
        ${di('Witness Date', r.witness_date || r.wit_date || '—')}
      </div>
    </div>
  </div>`);

  // ── OFFICE USE ─────────────────────────────────────────────────────────────
  if (r.ou_ref || r.ou_staff || r.ou_date) {
    html += section('🗂 Office Use Only', `<div class="detail-grid">
      ${di('Loan Reference #', r.ou_ref || '—')}
      ${di('Processed By', r.ou_staff || '—')}
      ${di('Date Processed', r.ou_date || '—')}
    </div>`);
  }

  document.getElementById('modal-body').innerHTML = html;
  document.getElementById('modal-body').dataset.recordKey = r._key || '';
  document.getElementById('detail-modal').classList.add('open');
}

function section(title, content) {
  return `<div class="modal-section"><div class="modal-section-title">${title}</div>${content}</div>`;
}
function di(label, value, fullWidth) {
  const v = value && String(value).trim() && String(value) !== '—' ? escHtml(String(value)) : '<span style="color:#ccc">—</span>';
  return `<div class="detail-item" ${fullWidth?'style="grid-column:1/-1"':''}><label>${label}</label><span>${v}</span></div>`;
}
function diDivider(label) {
  return `<div class="rv-divider-admin">${label}</div>`;
}

function notifyBorrowerFromModal() {
  const key = document.getElementById('modal-body').dataset.recordKey;
  if (!key) return;
  const r = allRecords.find(x => x._key === key);
  if (!r) return;
  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (!saved.key) {
    showToast('WhatsApp notifications are not configured. Open Notification Settings to continue.', 'gold');
    return;
  }
  const borrowerPhone = (r.phone || r.b_phone || '').replace(/[^0-9]/g,'');
  if (!borrowerPhone) {
    showToast('No phone number is available for this borrower.', 'gold');
    return;
  }
  // Temporarily enable and notify regardless of toggle (manual override)
  const prevEnabled = localStorage.getItem('avesta_borrower_notif_enabled');
  localStorage.setItem('avesta_borrower_notif_enabled', 'true');
  notifyBorrower(r, r.status || 'Under Review');
  localStorage.setItem('avesta_borrower_notif_enabled', prevEnabled || 'false');
  showToast('📱 Notification sent to ' + borrowerPhone.slice(-4).padStart(8,'*'), 'green');
}

function closeModal() {
  document.getElementById('detail-modal').classList.remove('open');
  currentRecord = null;
}

function printRecord() {
  window.print();
}

// ─── DOC VIEWER ───────────────────────────────────────────────────────────────
function activateDocumentTile(event) {
  if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
  const tile = event.target.closest('[data-document-field]');
  if (!tile || !tile.closest('#modal-body')) return;
  event.preventDefault();
  const key = document.getElementById('modal-body').dataset.recordKey;
  const r = allRecords.find(record => record._key === key);
  const field = tile.dataset.documentField;
  if (!r || !DOC_LABELS[field]) return;
  openDV(key, field, r[field + '_filename'] || DOC_LABELS[field].label, r[field + '_mimetype'] || '');
}
document.addEventListener('click', activateDocumentTile);
document.addEventListener('keydown', activateDocumentTile);

function openDV(key, docKey, filename, mimetype) {
  const r = allRecords.find(r => r._key === key);
  if (!r) return;
  const b64    = r[docKey + '_base64'];
  const docUrl = r[docKey + '_url']; // server-hosted file (works across devices)

  let src;
  if (b64) {
    src = b64.startsWith('data:') ? b64 : `data:${mimetype};base64,${b64}`;
  } else if (docUrl) {
    // Never the raw file path: documents/ is closed to the web. The endpoint
    // checks this person may see it, and logs that they did.
    src = getScriptUrl() + '?action=doc&key=' + encodeURIComponent(key)
        + '&field=' + encodeURIComponent(docKey);
  } else {
    return;
  }

  document.getElementById('dv-title').textContent = filename;
  document.getElementById('dv-dl').href = docUrl && !b64 ? src + '&download=1' : src;
  document.getElementById('dv-dl').download = filename;
  const body = document.getElementById('dv-body');
  if (mimetype.startsWith('image/') || /\.(jpe?g|png|gif|webp)$/i.test(filename)) {
    body.innerHTML = `<img src="${src}" style="max-width:100%;max-height:70vh;border-radius:6px">`;
  } else if (mimetype === 'application/pdf' || /\.pdf$/i.test(filename)) {
    body.innerHTML = `<embed src="${src}" type="application/pdf" width="700" height="500" style="max-width:100%;max-height:70vh;border-radius:6px">`;
  } else {
    body.innerHTML = `<p style="padding:40px;color:#888;font-size:10pt">Preview not available for this file type.<br>Use the Download button above.</p>`;
  }
  document.getElementById('doc-viewer').classList.add('open');
}
function closeDV() {
  document.getElementById('doc-viewer').classList.remove('open');
  document.getElementById('dv-body').innerHTML = '';
}

// ─── SETTINGS PAGE ────────────────────────────────────────────────────────────
function showPage_settings_init() {
  const saved = localStorage.getItem('avesta_script_url') || getScriptUrl();
  const el = document.getElementById('script-url-input');
  if (el) el.value = saved;
  const st = document.getElementById('script-url-status');
  if (st) st.textContent = saved ? '✅ URL configured: ' + saved.slice(0,60) + '…' : '⚠️ No URL saved yet.';
}

function saveScriptUrl() {
  const val = (document.getElementById('script-url-input').value || '').trim();
  const st  = document.getElementById('script-url-status');
  if (!val) {
    if (st) { st.style.color='#c0392b'; st.textContent='⚠️ Enter the path to api.php (usually just "api.php").'; }
    return;
  }
  localStorage.setItem('avesta_script_url', val);
  if (st) { st.style.color='#198754'; st.textContent='✅ Saved! Syncing now…'; }
  showToast('API endpoint saved. Syncing records…', 'green');
  fetchRemoteRecords(false);
}

// ─── CSV EXPORT ───────────────────────────────────────────────────────────────
function exportCSV() {
  if (!allRecords.length) { avToast('Nothing to export yet. Applications appear here once someone applies.', 'info'); return; }
  const cols = ['submitted_at','full_name','national_id','date_of_birth','phone','email','address','occupation','monthly_income','loan_amount','loan_purpose','loan_duration','interest_rate','total_repayment','repay_method','receive_method','disburse_date','status','gps_latitude','gps_longitude'];
  const headers = cols.map(c => '"' + c.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) + '"').join(',');
  const rows = allRecords.map(r => cols.map(c => '"' + String(r[c]||'').replace(/"/g,'""') + '"').join(','));
  const csv = [headers, ...rows].join('\n');
  const blob = new Blob([csv], {type:'text/csv'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'Avesta_Applications_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
}

// ─── UTILS ────────────────────────────────────────────────────────────────────
function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
  return String(s||'').replace(/'/g,'&apos;').replace(/"/g,'&quot;');
}

// Close modal on overlay click
document.getElementById('detail-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Keyboard
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeModal(); closeDV(); toggleWaPanel(true); toggleSidebar(true); }
});

// Click outside WA panel to close
document.addEventListener('click', e => {
  const panel = document.getElementById('wa-panel');
  const btn   = document.getElementById('wa-btn');
  if (panel && panel.style.display !== 'none' && !panel.contains(e.target) && e.target !== btn) {
    panel.style.display = 'none';
  }
});

function toggleWaPanel(forceClose) {
  const p = document.getElementById('wa-panel');
  if (!p) return;
  if (forceClose || p.style.display !== 'none') { p.style.display = 'none'; p.setAttribute('aria-hidden', 'true'); return; }
  // Load saved settings
  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  let savedWa = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (!savedWa.number) savedWa.number = '260971013108';
  if (!savedWa.key)    savedWa.key    = '';
  document.getElementById('wa-number-input').value = savedWa.number;
  document.getElementById('wa-key-input').value    = savedWa.key;
  updateWaStatus();
  p.style.display = 'block';
  p.setAttribute('aria-hidden', 'false');
}

// ── NOTIFICATION SYSTEM ──────────────────────────────────────────────────────

// Tab switcher
function showNotifyTab(tab) {
  ['wa','email','browser'].forEach(t => {
    document.getElementById('ntab-content-' + t).style.display = t === tab ? 'block' : 'none';
    const btn = document.getElementById('ntab-' + t);
    if (btn) {
      btn.style.color      = t === tab ? '#163E33' : '#888';
      btn.style.fontWeight = t === tab ? '700' : '600';
      btn.style.borderBottom = t === tab ? '2px solid #163E33' : 'none';
    }
  });
  if (tab === 'browser') updateBrowserNotifStatus();
  if (tab === 'wa') updateWaStatus();
  if (tab === 'email') {
    const saved = JSON.parse(localStorage.getItem('avesta_notify_email')||'{}');
    const el = document.getElementById('notify-email-input');
    if (el && saved.email) el.value = saved.email;
  }
}

// ── WHATSAPP (TextMeBot) ───────────────────────────────────────────────────
function openWaFallback() {
  // TextMeBot addphone page requires the API key as a URL parameter
  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  const key = (saved.key || '').trim();
  window.open('https://api.textmebot.com/addphone.php?apikey=' + encodeURIComponent(key), '_blank');
}

function saveWaSettings() {
  const number = (document.getElementById('wa-number-input').value||'').trim();
  const key    = (document.getElementById('wa-key-input').value||'').trim();
  if (!number || !key) {
    updateWaStatus('⚠️ Please enter both your number and API key');
    return;
  }
  localStorage.setItem('avesta_wa_settings', JSON.stringify({number, key}));
  updateWaStatus();
  showToast('WhatsApp notification settings saved.', 'green');
  toggleWaPanel(true);
}

function updateWaStatus(msg) {
  const el = document.getElementById('wa-status');
  if (!el) return;
  if (msg) { el.style.background='#fff3cd'; el.style.color='#856404'; el.textContent = msg; return; }
  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (saved.number && saved.key) {
    el.style.background = '#d4edda';
    el.style.color = '#155724';
    el.textContent = '✅ Active — alerts go to ' + saved.number;
  } else {
    el.style.background = '#f5f5f5';
    el.style.color = '#666';
    el.textContent = '⚠️ Not set up yet — follow the steps above';
  }
}

function sendWhatsAppAlert(r) {
  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (!saved.number || !saved.key) return;
  const number = saved.number.replace(/[^0-9]/g,'');
  const key    = saved.key.trim();
  const name   = (r.full_name    || 'Unknown').slice(0,30);
  const amount = 'ZMW ' + parseFloat(r.loan_amount||0).toLocaleString();
  const phone  = r.phone || '-';
  const dur    = (r.loan_duration || '-').slice(0,10);
  const ref    = (r._key||'').replace('avesta_submission_','AE-').slice(0,14);
  const text   = 'New Loan Application - Avesta%0AApplicant: ' + 
                 encodeURIComponent(name) + '%0AAmount: ' + 
                 encodeURIComponent(amount) + '%0ADuration: ' + 
                 encodeURIComponent(dur) + '%0APhone: ' + 
                 encodeURIComponent(phone) + '%0ARef: ' + ref;
  const url = 'https://api.textmebot.com/send.php?recipient=' + number + '&apikey=' + key + '&text=' + text;
  const img = new Image();
  img.src = url;
  console.log('WA alert fired for', name);
}

function testWa() {
  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (!saved.number || !saved.key) {
    alert('Please save your WhatsApp number and API key first.');
    return;
  }
  const number = saved.number.replace(/[^0-9]/g,'');
  const key    = saved.key.trim();
  const text   = encodeURIComponent('Test from Avesta Admin - notifications are working!');
  const url    = 'https://api.textmebot.com/send.php?recipient=' + number + '&apikey=' + key + '&text=' + text;
  const img    = new Image();
  img.src = url;
  // Open URL in new tab so admin can see any error response
  const tab = window.open(url, '_blank');
  if (tab) setTimeout(() => { try { tab.close(); } catch(e){} }, 5000);
  showToast('Test message sent. Check WhatsApp shortly.', 'green');
}

// ── EMAIL FALLBACK ──────────────────────────────────────────────────────────
function saveEmailSettings() {
  const email = (document.getElementById('notify-email-input').value||'').trim();
  if (!email || !email.includes('@')) {
    document.getElementById('email-notify-status').textContent = '⚠️ Enter a valid email address';
    return;
  }
  localStorage.setItem('avesta_notify_email', JSON.stringify({email}));
  document.getElementById('email-notify-status').textContent = '✅ Email saved: ' + email;
  showToast('Email notification settings saved.', 'green');
}

function sendEmailAlert(r) {
  const saved = JSON.parse(localStorage.getItem('avesta_notify_email')||'{}');
  if (!saved.email) return;
  const subject = encodeURIComponent('New Loan Application - ' + (r.full_name||'Unknown') + ' - Avesta Enterprises');
  const nl = '%0A';
  const body = encodeURIComponent('New loan application received.') + nl + nl +
    encodeURIComponent('Applicant: ' + (r.full_name||'-')) + nl +
    encodeURIComponent('NRC: '       + (r.national_id||'-')) + nl +
    encodeURIComponent('Phone: '     + (r.phone||'-')) + nl +
    encodeURIComponent('Amount: ZMW '+ parseFloat(r.loan_amount||0).toLocaleString()) + nl +
    encodeURIComponent('Duration: '  + (r.loan_duration||'-')) + nl +
    encodeURIComponent('Purpose: '   + (r.loan_purpose||'-')) + nl +
    encodeURIComponent('Submitted: ' + (r.submitted_at||'-')) + nl + nl +
    encodeURIComponent('Log in to admin.html to review this application.');
  window.open('mailto:' + saved.email + '?subject=' + subject + '&body=' + body);
}

function testEmailNotify() {
  const saved = JSON.parse(localStorage.getItem('avesta_notify_email')||'{}');
  if (!saved.email) { alert('Please save your email address first.'); return; }
  const sub  = encodeURIComponent('Test - Avesta Admin Email Notifications Working');
  const body = encodeURIComponent('This is a test from your Avesta Enterprises Admin Portal. Email notifications are configured correctly.');
  window.open('mailto:' + saved.email + '?subject=' + sub + '&body=' + body);
}

// ── BROWSER PUSH NOTIFICATIONS ──────────────────────────────────────────────
function updateBrowserNotifStatus() {
  const el = document.getElementById('browser-notif-status');
  if (!el) return;
  if (!('Notification' in window)) {
    el.textContent = '❌ Your browser does not support notifications.';
    return;
  }
  const perm = Notification.permission;
  if (perm === 'granted') {
    el.style.background = '#d4edda'; el.style.color = '#155724';
    el.textContent = '✅ Browser notifications enabled and active';
  } else if (perm === 'denied') {
    el.style.background = '#f8d7da'; el.style.color = '#721c24';
    el.textContent = '❌ Blocked — go to browser settings → Site Settings → Notifications → Allow this site';
  } else {
    el.style.background = '#fff3cd'; el.style.color = '#856404';
    el.textContent = '⚠️ Not enabled yet — click Enable below';
  }
}

function enableBrowserNotif() {
  if (!('Notification' in window)) { alert('Your browser does not support notifications.'); return; }
  Notification.requestPermission().then(perm => {
    updateBrowserNotifStatus();
    if (perm === 'granted') {
      localStorage.setItem('avesta_browser_notif', 'true');
      showToast('Browser notifications enabled.', 'green');
      testBrowserNotif();
    } else {
      showToast('Browser notification permission was not granted.', 'red');
    }
  });
}

function sendBrowserNotif(r) {
  if (!('Notification' in window) || Notification.permission !== 'granted') return;
  if (localStorage.getItem('avesta_browser_notif') !== 'true') return;
  const name   = r.full_name || 'Unknown Applicant';
  const amount = 'ZMW ' + parseFloat(r.loan_amount||0).toLocaleString();
  new Notification('🔔 New Loan Application — Avesta', {
    body: name + ' applied for ' + amount + '. Tap to review.',
    icon: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2220%22 fill=%22%23163E33%22/><text y=%22.9em%22 font-size=%2280%22>🏦</text></svg>',
    tag: 'avesta-new-app',
    requireInteraction: true
  });
}

function testBrowserNotif() {
  if (!('Notification' in window) || Notification.permission !== 'granted') {
    enableBrowserNotif(); return;
  }
  new Notification('🧪 Test — Avesta Admin', {
    body: 'Browser notifications are working! You will be alerted when applications arrive.',
    tag: 'avesta-test'
  });
  showToast('Test notification sent.', 'green');
}

// ─── BACKUP / RESTORE MODAL ──────────────────────────────────────────────────
let adminImportData = null;

function openBackupModal() {
  if (!window.AV_USER || !['admin', 'super_admin'].includes(window.AV_USER.role)) {
    avToast('Backup and restore is available to administrators only.', 'warn'); return;
  }
  const m = document.getElementById('backup-modal');
  m.style.display = 'flex';
  document.getElementById('admin-export-status').textContent = '';
  document.getElementById('admin-import-status').textContent = '';
  switchBackupTab('export');
  // Show record count in export tab
  const count = allRecords.length;
  document.getElementById('admin-export-status').textContent =
    count + ' record' + (count !== 1 ? 's' : '') + ' available to export';
}

function closeBackupModal() {
  document.getElementById('backup-modal').style.display = 'none';
  adminClearImport();
}

function switchBackupTab(tab) {
  document.getElementById('btab-content-export').style.display = tab === 'export' ? 'block' : 'none';
  document.getElementById('btab-content-import').style.display = tab === 'import' ? 'block' : 'none';
  const exp = document.getElementById('btab-export');
  const imp = document.getElementById('btab-import');
  exp.style.color      = tab === 'export' ? '#163E33' : '#888';
  exp.style.fontWeight = tab === 'export' ? '700' : '600';
  exp.style.borderBottom = tab === 'export' ? '3px solid #163E33' : 'none';
  imp.style.color      = tab === 'import' ? '#163E33' : '#888';
  imp.style.fontWeight = tab === 'import' ? '700' : '600';
  imp.style.borderBottom = tab === 'import' ? '3px solid #163E33' : 'none';
}

// ── EXPORT ────────────────────────────────────────────────────────────────────
function adminExportJSON(includeDocs) {
  if (!allRecords.length) {
    document.getElementById('admin-export-status').textContent = '⚠️ No records to export.';
    return;
  }
  const records = allRecords.map(r => {
    if (includeDocs) return Object.assign({}, r);
    const clean = {};
    Object.keys(r).forEach(k => { if (!k.endsWith('_base64')) clean[k] = r[k]; });
    return clean;
  });
  const payload = {
    _avesta_backup: true,
    version: '2',
    exported_at: new Date().toISOString(),
    exported_by: 'Avesta Admin Portal',
    device: navigator.userAgent.slice(0, 60),
    record_count: records.length,
    include_docs: includeDocs,
    records
  };
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  const tag = includeDocs ? 'full' : 'data';
  a.download = 'Avesta_Backup_' + tag + '_' + new Date().toISOString().slice(0, 10) + '.json';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(a.href), 3000);
  document.getElementById('admin-export-status').textContent =
    '✅ Downloaded ' + records.length + ' record' + (records.length !== 1 ? 's' : '') +
    (includeDocs ? ' with documents.' : ' (data only).');
}

// ── IMPORT — drag & drop helpers ──────────────────────────────────────────────
function adminDragOver(e) {
  e.preventDefault();
  document.getElementById('admin-drop-zone').style.background = '#fff3e0';
  document.getElementById('admin-drop-zone').style.borderColor = '#163E33';
}
function adminDragLeave(e) {
  document.getElementById('admin-drop-zone').style.background = '#fffbf5';
  document.getElementById('admin-drop-zone').style.borderColor = '#D98E3B';
}
function adminDrop(e) {
  e.preventDefault();
  adminDragLeave(e);
  const file = e.dataTransfer.files[0];
  if (file) adminParseFile(file);
}
function adminImportFile(input) {
  if (input.files && input.files[0]) adminParseFile(input.files[0]);
}

function adminParseFile(file) {
  if (!file.name.endsWith('.json')) {
    document.getElementById('admin-import-status').style.color = '#c0392b';
    document.getElementById('admin-import-status').textContent = '❌ Please upload a .json backup file.';
    return;
  }
  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const parsed = JSON.parse(e.target.result);
      if (!parsed.records || !Array.isArray(parsed.records)) throw new Error('Invalid format');
      adminImportData = parsed;
      adminShowImportPreview(parsed);
    } catch(err) {
      document.getElementById('admin-import-status').style.color = '#c0392b';
      document.getElementById('admin-import-status').textContent = '❌ Invalid backup file: ' + err.message;
    }
  };
  reader.readAsText(file);
}

function adminShowImportPreview(parsed) {
  const preview = document.getElementById('admin-import-preview');
  const summary = document.getElementById('admin-import-summary');
  const list    = document.getElementById('admin-import-list');
  const btn     = document.getElementById('admin-import-btn');
  const clrBtn  = document.getElementById('admin-import-clear');
  const existing = new Set(allRecords.map(r => r._key));
  let newCount = 0, updateCount = 0;
  parsed.records.forEach(r => {
    if (!r._key) r._key = 'avesta_submission_' + (r.submitted_at||Date.now()).toString().replace(/[^a-z0-9]/gi,'_');
    if (!existing.has(r._key)) newCount++;
    else updateCount++;
  });
  summary.textContent = `📋 ${parsed.record_count || parsed.records.length} records in file · ${newCount} new · ${updateCount} existing (status will update if newer)`;
  summary.style.color = '#163E33';
  list.innerHTML = parsed.records.slice(0, 12).map(r =>
    `<div style="padding:4px 0;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;gap:8px">
      <span style="font-weight:600">${escHtml(r.full_name || r.b_name || '—')}</span>
      <span style="color:#888">ZMW ${parseFloat(r.loan_amount||r.loan_amt||0).toLocaleString()} · ${escHtml(r.status||'New Application')} · ${escHtml((r.submitted_at||'').slice(0,16))}</span>
    </div>`
  ).join('') + (parsed.records.length > 12 ? `<div style="color:#aaa;padding:6px 0;font-size:8pt">…and ${parsed.records.length - 12} more</div>` : '');
  preview.style.display = 'block';
  btn.style.display = 'block';
  clrBtn.style.display = 'inline-block';
  document.getElementById('admin-import-status').textContent = '';
}

function adminConfirmImport() {
  if (!adminImportData) return;
  const btn = document.getElementById('admin-import-btn');
  btn.disabled = true; btn.textContent = '⏳ Importing…';
  let added = 0, updated = 0, skipped = 0;
  adminImportData.records.forEach(r => {
    if (!r._key) r._key = 'avesta_submission_' + (r.submitted_at||Date.now()).toString().replace(/[^a-z0-9]/gi,'_');
    // Normalise field names
    r.full_name  = r.full_name  || r.b_name     || '—';
    r.national_id= r.national_id|| r.b_id       || '—';
    r.phone      = r.phone      || r.b_phone    || '—';
    r.loan_amount= r.loan_amount|| r.loan_amt   || '0';
    r.status     = r.status                     || 'New Application';
    const existing = localStorage.getItem(r._key);
    if (!existing) {
      localStorage.setItem(r._key, JSON.stringify(r));
      added++;
    } else {
      try {
        const local = JSON.parse(existing);
        // Merge: keep local base64 docs, prefer incoming status if different
        const merged = Object.assign({}, r, {
          ...Object.fromEntries(Object.entries(local).filter(([k]) => k.endsWith('_base64'))),
          status: r.status || local.status || 'New Application'
        });
        // Only update if something actually changed
        if (JSON.stringify(merged) !== JSON.stringify(local)) {
          localStorage.setItem(r._key, JSON.stringify(merged));
          updated++;
        } else {
          skipped++;
        }
      } catch(e) { skipped++; }
    }
  });
  // Reload
  loadRecords();
  applyFilters();
  renderDashboard();
  renderTable();
  updateNavBadge();
  btn.disabled = false; btn.textContent = '✅ Import Records';
  const st = document.getElementById('admin-import-status');
  st.style.color = '#198754';
  st.textContent = `✅ Done — ${added} added, ${updated} updated, ${skipped} unchanged.`;
  showToast(`📥 Import complete: ${added} new, ${updated} updated`, 'green');
  adminImportData = null;
}

function adminClearImport() {
  adminImportData = null;
  document.getElementById('admin-import-preview').style.display = 'none';
  document.getElementById('admin-import-btn').style.display = 'none';
  document.getElementById('admin-import-clear').style.display = 'none';
  document.getElementById('admin-import-status').textContent = '';
  document.getElementById('admin-import-file').value = '';
  document.getElementById('admin-drop-zone').style.background = '#fffbf5';
  document.getElementById('admin-drop-zone').style.borderColor = '#D98E3B';
}

// Close on backdrop click.
// Bound on DOMContentLoaded because #backup-modal is defined further down the
// page than this script — binding immediately returns null, and the resulting
// TypeError aborts the rest of this script block (which killed afxState and
// the currency converter along with it).
document.addEventListener('DOMContentLoaded', function () {
  var bm = document.getElementById('backup-modal');
  if (bm) bm.addEventListener('click', function (e) {
    if (e.target === this) closeBackupModal();
  });
});

// ─── LOAD WA SETTINGS ON STARTUP ─────────────────────────────────────────────
// Load WA settings on startup
(function loadWaRuntime() {
  // Do not ship notification credentials in browser code. Configure them after deployment.
  let saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (saved.number && saved.key) {
    window.WA_NUMBER  = saved.number;
    window.WA_API_KEY = saved.key;
    window.WA_ENABLED = true;
  }
})();

// ─── CURRENCY CONVERTER (page-fx) ────────────────────────────────────────────
const AFX_FAVS = ['USD','ZAR','GBP','EUR','CNY','AED','INR','TZS','MWK','BWP','KES','JPY'];
const AFX_SEED = {ZMW:19.60, USD:1, EUR:0.92, GBP:0.79, ZAR:18.1, CNY:7.2}; // BoZ mid, Sep 2026
const AFX_KEY  = 'avesta_admin_fx';
let afxState = {from:'ZMW', to:'USD', amt:10000, table:null, at:null, label:null, official:false};
let afxBusy  = false;

let afxNamer = null;
try { afxNamer = new Intl.DisplayNames(['en'], {type:'currency'}); } catch(e) {}
function afxName(c){
  try { const n = afxNamer && afxNamer.of(c); return (n && n !== c) ? n : c; } catch(e){ return c; }
}
function afxMoney(n, code){
  if (!isFinite(n)) return '—';
  const o = {style:'currency', currency:code, currencyDisplay:'narrowSymbol'};
  try { return new Intl.NumberFormat('en-US', o).format(n); }
  catch(e){
    try { o.currencyDisplay = 'symbol'; return new Intl.NumberFormat('en-US', o).format(n); }
    catch(e2){ return code + ' ' + n.toLocaleString('en-US',{maximumFractionDigits:2}); }
  }
}
function afxNum(v){ const n = parseFloat(String(v).replace(/[^0-9.]/g,'')); return isFinite(n) ? n : 0; }
function afxAgo(ts){
  const m = Math.round((Date.now()-ts)/60000);
  if (m < 1) return 'just now';
  if (m < 60) return m + ' min ago';
  const h = Math.round(m/60);
  return h < 24 ? h + 'h ago' : Math.round(h/24) + ' days ago';
}
function afxStatus(state, text){
  const el = document.getElementById('afx-status');
  if (!el) return;
  el.style.color = state === 'good' ? '#198754' : state === 'bad' ? '#c0392b' : '#888';
  el.innerHTML = '<span class="afx-dot ' + state + '"></span>' + text;
}
function afxTable(){ return afxState.table || AFX_SEED; }
function afxRate(){
  const t = afxTable();
  if (!t[afxState.from] || !t[afxState.to]) return NaN;
  return t[afxState.to] / t[afxState.from];   // cross-rate through USD
}
function afxSave(){
  try { localStorage.setItem(AFX_KEY, JSON.stringify(afxState)); } catch(e){}
}
function afxLoad(){
  try {
    const d = JSON.parse(localStorage.getItem(AFX_KEY) || 'null');
    if (!d) return;
    if (d.from) afxState.from = d.from;
    if (d.to) afxState.to = d.to;
    if (d.table && d.table.USD) afxState.table = d.table;
    afxState.at = d.at || null;
    afxState.label = d.label || null;
    afxState.official = !!d.official;
  } catch(e){}
}
function afxFillSelects(){
  const t = afxTable();
  const codes = Object.keys(t).sort();
  const favs  = ['ZMW'].concat(AFX_FAVS).filter(c => codes.indexOf(c) > -1);
  const rest  = codes.filter(c => favs.indexOf(c) === -1);
  [['afx_from', afxState.from], ['afx_to', afxState.to]].forEach(function(p){
    const sel = document.getElementById(p[0]);
    if (!sel) return;
    sel.innerHTML = '';
    [['Common', favs], ['All currencies', rest]].forEach(function(g){
      if (!g[1].length) return;
      const grp = document.createElement('optgroup');
      grp.label = g[0];
      g[1].forEach(function(c){
        const o = document.createElement('option');
        o.value = c;
        o.textContent = c + ' — ' + afxName(c);
        grp.appendChild(o);
      });
      sel.appendChild(grp);
    });
    sel.value = p[1];
  });
}
function afxBoard(){
  const body = document.getElementById('afx_board');
  if (!body) return;
  const t = afxTable();
  const zmw = t.ZMW;
  if (!zmw) { body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#aaa;padding:18px">No rates loaded</td></tr>'; return; }
  const rows = AFX_FAVS.filter(c => t[c]).map(function(c){
    const perUnit = zmw / t[c];              // 1 unit of c, in kwacha
    const k1000   = 1000 / perUnit;          // K1,000 in that currency
    return '<tr><td><span class="cc">' + c + '</span><br><span class="cn">' +
           afxName(c) + '</span></td><td class="num">' +
           perUnit.toLocaleString('en-US',{minimumFractionDigits:2, maximumFractionDigits:4}) +
           '</td><td class="num">' + afxMoney(k1000, c) + '</td></tr>';
  });
  body.innerHTML = rows.join('') ||
    '<tr><td colspan="3" style="text-align:center;color:#aaa;padding:18px">No rates loaded</td></tr>';
}
function afxRender(){
  const out = document.getElementById('afx_out');
  if (!out) return;
  const r = afxRate();
  const v = afxState.amt * r;
  out.textContent = afxMoney(v, afxState.to);
  document.getElementById('afx_pair').textContent = isFinite(r)
    ? '1 ' + afxState.from + ' = ' + (r < 0.01 ? r.toPrecision(4) : r.toFixed(4)) + ' ' + afxState.to +
      '   •   1 ' + afxState.to + ' = ' + (1/r < 0.01 ? (1/r).toPrecision(4) : (1/r).toFixed(4)) + ' ' + afxState.from
    : 'No rate available for this pair — try refreshing.';
  const btn = document.getElementById('afx-refresh');
  if (btn) btn.disabled = afxBusy;
  afxSave();
}
function afxSwap(){
  const f = afxState.from;
  afxState.from = afxState.to;
  afxState.to = f;
  document.getElementById('afx_from').value = afxState.from;
  document.getElementById('afx_to').value = afxState.to;
  afxRender();
}
async function afxSync(){
  if (afxBusy) return;
  afxBusy = true; afxRender();
  afxStatus('busy', 'Fetching latest rates…');
  try {
    const url = getScriptUrl();
    const got = await window.avFetchJson(url + '?action=rates&t=' + Date.now(),
                                        {cache:'no-store'}, 12000);
    const res = got.res, d = got.data;
    if (!res.ok || d.ok === false) throw new Error(d.error || 'Rate service unavailable');
    if (!d.table || !d.table.USD) throw new Error('Rate table incomplete');

    afxState.table = d.table;
    afxState.label = 'Market rates';
    afxState.official = false;

    if (d.boz && d.boz.official && d.boz.zmw_per_usd > 0) {
      afxState.table.ZMW = d.boz.zmw_per_usd;   // one override covers every kwacha pair
      afxState.label = 'Bank of Zambia (kwacha) + market';
      afxState.official = true;
    }
    afxState.at = Date.now();
    afxStatus(d.stale ? 'warn' : 'good',
      afxState.label + ' • ' + Object.keys(afxState.table).length + ' currencies • ' +
      (d.stale ? 'server cache' : 'updated ' + afxAgo(afxState.at)));
    afxFillSelects();
  } catch (e) {
    let msg = (e && e.message) ? e.message : 'Could not load rates';
    if (!navigator.onLine) msg = 'You are offline — showing saved rates';
    else if (/failed to fetch|networkerror|load failed|aborted/i.test(msg))
      msg = 'Could not reach api.php — showing saved rates';
    afxStatus('bad', msg + (afxState.at ? ' • rates from ' + afxAgo(afxState.at) : ''));
  }
  afxBusy = false;
  afxBoard();
  afxRender();
}
function afxInit(){
  if (document.getElementById('afx_amt') && !document.getElementById('afx_amt')._afxBound) {
    afxLoad();
    document.getElementById('afx_amt').value = afxState.amt;
    document.getElementById('afx_amt').addEventListener('input', function(){
      afxState.amt = afxNum(this.value); afxRender();
    });
    document.getElementById('afx_from').addEventListener('change', function(){
      if (this.value === afxState.to) { afxState.to = afxState.from; document.getElementById('afx_to').value = afxState.to; }
      afxState.from = this.value; afxRender();
    });
    document.getElementById('afx_to').addEventListener('change', function(){
      if (this.value === afxState.from) { afxState.from = afxState.to; document.getElementById('afx_from').value = afxState.from; }
      afxState.to = this.value; afxRender();
    });
    document.getElementById('afx_amt')._afxBound = true;
    afxFillSelects(); afxBoard(); afxRender();
    if (afxState.at) afxStatus('', (afxState.label || 'Saved rates') + ' • updated ' + afxAgo(afxState.at));
  }
  if (!afxState.at || (Date.now() - afxState.at) > 3600000) afxSync();
}
</script>

<!-- ═══ BACKUP / RESTORE MODAL ═══ -->
<div id="backup-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9500;align-items:flex-start;justify-content:center;padding:24px;overflow-y:auto">
  <div style="background:white;border-radius:14px;width:100%;max-width:640px;overflow:hidden;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <!-- Header -->
    <div style="background:#163E33;padding:16px 22px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="color:white;font-weight:700;font-size:11pt">📦 Backup &amp; Restore Records</div>
        <div style="color:rgba(255,255,255,.55);font-size:8pt;margin-top:2px">Export to share with other devices · Import to merge records</div>
      </div>
      <button onclick="closeBackupModal()" style="background:rgba(255,255,255,.15);border:none;color:white;width:32px;height:32px;border-radius:50%;font-size:13pt;cursor:pointer;line-height:1">✕</button>
    </div>
    <!-- Tabs -->
    <div style="display:flex;border-bottom:2px solid #f0f0f0">
      <button onclick="switchBackupTab('export')" id="btab-export" style="flex:1;padding:12px;border:none;background:white;font-size:9pt;font-weight:700;color:#163E33;border-bottom:3px solid #163E33;cursor:pointer;margin-bottom:-2px">📤 Export / Download</button>
      <button onclick="switchBackupTab('import')" id="btab-import" style="flex:1;padding:12px;border:none;background:white;font-size:9pt;font-weight:600;color:#888;cursor:pointer">📥 Import / Upload</button>
    </div>
    <!-- EXPORT TAB -->
    <div id="btab-content-export" style="padding:24px">
      <p style="font-size:8.5pt;color:#555;margin-bottom:18px;line-height:1.7">Download all records as a file to back up, share with another device, or hand off to another staff member.</p>
      <!-- JSON with docs -->
      <div style="border:1.5px solid #e0e0e0;border-radius:10px;padding:18px;margin-bottom:14px">
        <div style="display:flex;align-items:flex-start;gap:14px">
          <div style="font-size:22pt">📄</div>
          <div style="flex:1">
            <div style="font-weight:700;color:#163E33;font-size:10pt">Full Backup (JSON with Documents)</div>
            <div style="font-size:8pt;color:#888;margin-top:3px;line-height:1.6">Includes all application data <strong>and</strong> uploaded files (NRC, payslips, etc). Use this to fully restore records on another device. File will be larger.</div>
          </div>
          <button onclick="adminExportJSON(true)" style="background:#163E33;color:white;border:none;padding:10px 18px;border-radius:7px;font-size:8.5pt;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0">⬇ Download</button>
        </div>
      </div>
      <!-- JSON no docs -->
      <div style="border:1.5px solid #e0e0e0;border-radius:10px;padding:18px;margin-bottom:14px">
        <div style="display:flex;align-items:flex-start;gap:14px">
          <div style="font-size:22pt">📋</div>
          <div style="flex:1">
            <div style="font-weight:700;color:#163E33;font-size:10pt">Data Only Backup (JSON, no documents)</div>
            <div style="font-size:8pt;color:#888;margin-top:3px;line-height:1.6">Application data only — no file attachments. Much smaller file. Good for sharing a list of applicants or syncing status between devices.</div>
          </div>
          <button onclick="adminExportJSON(false)" style="background:#1F5645;color:white;border:none;padding:10px 18px;border-radius:7px;font-size:8.5pt;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0">⬇ Download</button>
        </div>
      </div>
      <!-- CSV -->
      <div style="border:1.5px solid #e0e0e0;border-radius:10px;padding:18px">
        <div style="display:flex;align-items:flex-start;gap:14px">
          <div style="font-size:22pt">📊</div>
          <div style="flex:1">
            <div style="font-weight:700;color:#163E33;font-size:10pt">Spreadsheet Export (CSV)</div>
            <div style="font-size:8pt;color:#888;margin-top:3px;line-height:1.6">Open in Excel, Google Sheets, or any spreadsheet app. Good for reporting and analysis. Does not include uploaded documents.</div>
          </div>
          <button onclick="exportCSV()" style="background:#D98E3B;color:#163E33;border:none;padding:10px 18px;border-radius:7px;font-size:8.5pt;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0">⬇ Download</button>
        </div>
      </div>
      <div id="admin-export-status" style="margin-top:14px;font-size:8.5pt;color:#198754;font-weight:600;text-align:center"></div>
    </div>
    <!-- IMPORT TAB -->
    <div id="btab-content-import" style="display:none;padding:24px">
      <p style="font-size:8.5pt;color:#555;margin-bottom:16px;line-height:1.7">Upload a JSON backup file to merge records into this device. Existing records are kept — duplicates are skipped, newer status updates win.</p>
      <!-- Drop zone -->
      <div id="admin-drop-zone" ondragover="adminDragOver(event)" ondragleave="adminDragLeave(event)" ondrop="adminDrop(event)"
        style="border:2.5px dashed #D98E3B;border-radius:10px;padding:32px 20px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:16px;background:#fffbf5"
        onclick="document.getElementById('admin-import-file').click()">
        <div style="font-size:28pt;margin-bottom:8px">📂</div>
        <div style="font-weight:700;color:#163E33;font-size:10pt;margin-bottom:4px">Drop JSON file here or click to browse</div>
        <div style="font-size:8pt;color:#888">Accepts .json backup files exported from this portal or the staff tab</div>
        <input type="file" id="admin-import-file" aria-label="Choose a backup file to import" accept=".json,application/json" style="display:none" onchange="adminImportFile(this)">
      </div>
      <div id="admin-import-preview" style="display:none;border:1.5px solid #e0e0e0;border-radius:8px;padding:14px;margin-bottom:14px;background:#f9f9f9">
        <div id="admin-import-summary" style="font-size:9pt;color:#163E33;font-weight:600;margin-bottom:10px"></div>
        <div id="admin-import-list" style="max-height:180px;overflow-y:auto;font-size:8.5pt;color:#555"></div>
      </div>
      <div style="display:flex;gap:10px">
        <button id="admin-import-btn" onclick="adminConfirmImport()" style="display:none;flex:1;background:#163E33;color:white;border:none;padding:12px;border-radius:7px;font-size:9.5pt;font-weight:700;cursor:pointer">✅ Import Records</button>
        <button id="admin-import-clear" onclick="adminClearImport()" style="display:none;background:transparent;color:#888;border:1.5px solid #ddd;padding:12px 18px;border-radius:7px;font-size:9pt;cursor:pointer">Clear</button>
      </div>
      <div id="admin-import-status" style="margin-top:12px;font-size:8.5pt;font-weight:600;text-align:center"></div>
    </div>
  </div>
</div>

<div id="sidebar-overlay" onclick="toggleSidebar(true)" aria-hidden="true" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1001;backdrop-filter:blur(2px)"></div>

<!-- ═══ AVESTA UI KIT — behaviour ═══════════════════════════════════════ -->
<script id="av-ui-kit-js">
(function () {
  'use strict';
  if (window.avToast) return;                 // never double-install

  /* ── host ─────────────────────────────────────────────────────────── */
  function host() {
    var h = document.getElementById('av-toasts');
    if (!h) {
      h = document.createElement('div');
      h.id = 'av-toasts';
      h.setAttribute('role', 'status');
      h.setAttribute('aria-live', 'polite');
      (document.body || document.documentElement).appendChild(h);
    }
    return h;
  }

  /* Work out the tone from the message itself, so every existing
     alert() call site gets the right treatment with no edits. */
  var GLYPH = { ok: '\u2713', warn: '!', danger: '\u00d7', info: 'i' };
  function tone(msg) {
    var m = String(msg);
    if (/\u2705|\u2714|success|saved|updated|cleared|complete|sent|done|added/i.test(m)) return 'ok';
    if (/\u274c|\u26d4|fail|error|denied|invalid|could not|cannot|unable|too large|exceeds/i.test(m)) return 'danger';
    if (/\u26a0|warning|careful|please |first\.|no records|cancelled/i.test(m)) return 'warn';
    return 'info';
  }
  /* Split "Title. Rest of the detail" so the toast has a real hierarchy. */
  function split(msg) {
    var m = String(msg).replace(/^[\s\u2705\u2714\u274c\u26a0\u26d4\ufe0f]+/, '').trim();
    var nl = m.indexOf('\n');
    if (nl > 0 && nl < 70) return [m.slice(0, nl).trim(), m.slice(nl + 1).trim()];
    var dot = m.search(/[.!?]\s/);
    if (dot > 8 && dot < 62) return [m.slice(0, dot + 1).trim(), m.slice(dot + 2).trim()];
    return m.length > 74 ? [m.slice(0, 70).trim() + '\u2026', m] : [m, ''];
  }

  window.avToast = function (msg, type, ms) {
    if (msg === undefined || msg === null || msg === '') return;
    var t = type || tone(msg);
    var parts = split(msg);
    var life = ms || (t === 'danger' ? 7000 : t === 'warn' ? 5500 : 4200);

    var el = document.createElement('div');
    el.className = 'av-toast ' + t;
    var ico = document.createElement('div');
    ico.className = 'av-toast-ico'; ico.textContent = GLYPH[t] || 'i';
    var body = document.createElement('div');
    body.className = 'av-toast-body';
    var ttl = document.createElement('div');
    ttl.className = 'av-toast-title'; ttl.textContent = parts[0];
    body.appendChild(ttl);
    if (parts[1]) {
      var sub = document.createElement('div');
      sub.className = 'av-toast-msg'; sub.textContent = parts[1];
      body.appendChild(sub);
    }
    var x = document.createElement('button');
    x.className = 'av-toast-x'; x.type = 'button';
    x.setAttribute('aria-label', 'Dismiss'); x.textContent = '\u00d7';
    var bar = document.createElement('div');
    bar.className = 'av-toast-bar'; bar.style.animationDuration = life + 'ms';

    el.appendChild(ico); el.appendChild(body); el.appendChild(x); el.appendChild(bar);

    var timer, done = false;
    function close() {
      if (done) return; done = true;
      clearTimeout(timer);
      el.classList.add('av-out');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 200);
    }
    el.__avClose = close;
    x.addEventListener('click', function (e) { e.stopPropagation(); close(); });
    el.addEventListener('click', close);
    el.addEventListener('mouseenter', function () {
      clearTimeout(timer); bar.style.animationPlayState = 'paused';
    });
    el.addEventListener('mouseleave', function () {
      bar.style.animationPlayState = 'running'; timer = setTimeout(close, 1600);
    });

    var h = host();
    // Cap the stack at three. On a phone a fourth toast covers the content
    // the person is trying to read, so the oldest steps aside first.
    // Ones already animating out don't count against the cap.
    var live = Array.prototype.filter.call(h.children, function (c) {
      return !c.classList.contains('av-out');
    });
    while (live.length >= 3) {
      var oldest = live.shift();
      if (oldest.__avClose) oldest.__avClose();
      else if (oldest.parentNode) oldest.parentNode.removeChild(oldest);
    }
    h.appendChild(el);
    while (h.children.length > 4) h.removeChild(h.firstChild);   // never stack forever
    timer = setTimeout(close, life);
    return close;
  };

  /* Every existing alert() now renders as a toast — no call sites changed. */
  var nativeAlert = window.alert;
  window.alert = function (m) { try { window.avToast(m); } catch (e) { nativeAlert(m); } };
  window.avNativeAlert = nativeAlert;

  /* ── dialogs ──────────────────────────────────────────────────────── */
  function dialog(o) {
    return new Promise(function (resolve) {
      var back = document.createElement('div');
      back.className = 'av-dlg-back';
      var dlg = document.createElement('div');
      dlg.className = 'av-dlg' + (o.tone ? ' ' + o.tone : '');
      dlg.setAttribute('role', 'dialog');
      dlg.setAttribute('aria-modal', 'true');

      var head = document.createElement('div');
      head.className = 'av-dlg-head';
      var ico = document.createElement('div');
      ico.className = 'av-dlg-ico';
      ico.textContent = o.tone === 'danger' ? '\u26a0' : o.tone === 'warn' ? '\u26a0' : '?';
      var txt = document.createElement('div');
      var h4 = document.createElement('h4'); h4.textContent = o.title || 'Are you sure?';
      txt.appendChild(h4);
      if (o.body) { var p = document.createElement('p'); p.textContent = o.body; txt.appendChild(p); }
      head.appendChild(ico); head.appendChild(txt);
      dlg.appendChild(head);

      var input = null;
      if (o.input) {
        var wrap = document.createElement('div');
        wrap.className = 'av-dlg-in';
        input = document.createElement('input');
        input.type = 'text';
        input.placeholder = o.placeholder || '';
        input.setAttribute('aria-label', o.placeholder || 'Confirmation');
        wrap.appendChild(input); dlg.appendChild(wrap);
      }

      var foot = document.createElement('div');
      foot.className = 'av-dlg-foot';
      var cancel = document.createElement('button');
      cancel.type = 'button'; cancel.className = 'av-dlg-cancel';
      cancel.textContent = o.cancelText || 'Cancel';
      var go = document.createElement('button');
      go.type = 'button'; go.className = 'av-dlg-go av-ripple';
      go.textContent = o.okText || 'Confirm';
      foot.appendChild(cancel); foot.appendChild(go);
      dlg.appendChild(foot);
      back.appendChild(dlg);
      (document.body || document.documentElement).appendChild(back);

      var prev = document.activeElement;
      setTimeout(function () { (input || go).focus(); }, 60);

      function shut(val) {
        document.removeEventListener('keydown', onKey);
        back.classList.add('av-out');
        setTimeout(function () {
          if (back.parentNode) back.parentNode.removeChild(back);
          if (prev && prev.focus) { try { prev.focus(); } catch (e) {} }
        }, 130);
        resolve(val);
      }
      function onKey(e) {
        if (e.key === 'Escape') shut(o.input ? null : false);
        else if (e.key === 'Enter' && (!o.input || document.activeElement === input)) accept();
        else if (e.key === 'Tab') {                       // keep focus inside
          var f = dlg.querySelectorAll('button,input');
          var first = f[0], last = f[f.length - 1];
          if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
          else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
      }
      function accept() { shut(o.input ? (input.value || '') : true); }

      cancel.addEventListener('click', function () { shut(o.input ? null : false); });
      go.addEventListener('click', accept);
      back.addEventListener('click', function (e) { if (e.target === back) shut(o.input ? null : false); });
      document.addEventListener('keydown', onKey);
    });
  }

  window.avConfirm = function (title, body, opts) {
    opts = opts || {};
    return dialog({ title: title, body: body, tone: opts.tone || 'danger',
                    okText: opts.okText || 'Delete', cancelText: opts.cancelText || 'Keep it' });
  };
  window.avPrompt = function (title, body, placeholder, opts) {
    opts = opts || {};
    return dialog({ title: title, body: body, input: true, placeholder: placeholder,
                    tone: opts.tone || 'danger', okText: opts.okText || 'Confirm',
                    cancelText: opts.cancelText || 'Cancel' });
  };

  /* ── ripple on solid actions ──────────────────────────────────────── */
  document.addEventListener('pointerdown', function (e) {
    var b = e.target && e.target.closest &&
            e.target.closest('.btn-primary,.calc-btn,.calc-apply-btn,.av-dlg-go,.wiz-next,.submit-btn,.av-ripple');
    if (!b || b.disabled) return;
    if (!b.classList.contains('av-ripple')) b.classList.add('av-ripple');
    var r = b.getBoundingClientRect(), size = Math.max(r.width, r.height);
    var s = document.createElement('span');
    s.className = 'av-rip';
    s.style.width = s.style.height = size + 'px';
    s.style.left = (e.clientX - r.left - size / 2) + 'px';
    s.style.top = (e.clientY - r.top - size / 2) + 'px';
    b.appendChild(s);
    setTimeout(function () { if (s.parentNode) s.parentNode.removeChild(s); }, 540);
  }, true);

  /* ── scroll reveal ────────────────────────────────────────────────── */
  window.avReveal = function (scope) {
    if (!('IntersectionObserver' in window)) return;
    var sel = '.feat,.method-card,.stat-card,.card,.rate-tier,.nc-card,.step-card,.fxc,.calc-wrap';
    var nodes = (scope || document).querySelectorAll(sel);
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en, i) {
        if (!en.isIntersecting) return;
        var el = en.target;
        setTimeout(function () { el.classList.add('av-in'); }, Math.min(i, 6) * 55);
        io.unobserve(el);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: .06 });
    nodes.forEach(function (n) {
      if (n.classList.contains('av-reveal')) return;
      n.classList.add('av-reveal'); io.observe(n);
    });
  };

  /* ── helpers pages can call ───────────────────────────────────────── */

  window.avBusy = function (btn, on) {
    if (!btn) return;
    if (on) { btn.classList.add('av-busy'); btn.disabled = true; }
    else { btn.classList.remove('av-busy'); btn.disabled = false; }
  };
  window.avSkeletonRows = function (tbody, rows, cols) {
    if (!tbody) return;
    var html = '';
    for (var r = 0; r < (rows || 5); r++) {
      html += '<tr class="av-sk-row">';
      for (var c = 0; c < (cols || 6); c++) {
        html += '<td><span class="av-sk av-sk-line ' +
                (c === 1 ? 'w80' : c === 0 ? 'w40' : 'w60') + '"></span></td>';
      }
      html += '</tr>';
    }
    tbody.innerHTML = html;
  };
  window.avEmpty = function (title, body, actionLabel, onAction) {
    var d = document.createElement('div');
    d.className = 'av-empty';
    var i = document.createElement('div');
    i.className = 'av-empty-ico'; i.textContent = '\u25CE';
    var h = document.createElement('h4'); h.textContent = title;
    var p = document.createElement('p'); p.textContent = body || '';
    d.appendChild(i); d.appendChild(h); d.appendChild(p);
    if (actionLabel) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'av-empty-act'; b.textContent = actionLabel;
      if (onAction) b.addEventListener('click', onAction);
      d.appendChild(b);
    }
    return d;
  };

  function boot() { try { window.avReveal(); } catch (e) {} }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>


<!-- ═══ OFFICIAL BoZ RATE PANEL ═════════════════════════════════════════ -->
<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  if (!$('boz_save')) return;

  // The token is remembered per device so it isn't retyped daily. It is only
  // as private as this browser — treat it as a convenience, not a secret store.
  var TOKEN_KEY = 'avesta_boz_token';
  try {
    var saved = localStorage.getItem(TOKEN_KEY);
    if (saved) $('boz_token').value = saved;
  } catch (e) {}

  function midOf() {
    var b = parseFloat($('boz_buy').value), s = parseFloat($('boz_sell').value);
    if (isFinite(b) && isFinite(s) && b > 0 && s > 0) return (b + s) / 2;
    if (isFinite(b) && b > 0) return b;
    if (isFinite(s) && s > 0) return s;
    return NaN;
  }
  function showMid() {
    var m = midOf();
    $('boz_mid').textContent = isFinite(m) ? 'Mid: K' + m.toFixed(4) + ' per USD' : '';
  }
  $('boz_buy').addEventListener('input', showMid);
  $('boz_sell').addEventListener('input', showMid);

  function daysBetween(a, b) { return Math.round((a - b) / 86400000); }

  async function showCurrent() {
    var el = $('boz_current');
    try {
      var d = (await window.avFetchJson(getScriptUrl() + '?action=rates&t=' + Date.now(),
                                        { cache: 'no-store' }, 10000)).data;
      if (d && d.boz && d.boz.official) {
        var asOf = d.boz.as_of;
        var age = asOf ? daysBetween(Date.now(), new Date(asOf + 'T00:00:00').getTime()) : null;
        el.className = 'boz-current' + (age !== null && age > 3 ? ' stale' : '');
        el.textContent = 'Currently K' + Number(d.boz.zmw_per_usd).toFixed(4) +
          ' per USD, dated ' + asOf +
          (age !== null ? (age <= 0 ? ' (today)' : age === 1 ? ' (yesterday)'
                            : ' \u2014 ' + age + ' days old') : '');
        if (!$('boz_buy').value && d.boz.buy)  $('boz_buy').value  = d.boz.buy;
        if (!$('boz_sell').value && d.boz.sell) $('boz_sell').value = d.boz.sell;
      } else {
        el.className = 'boz-current none';
        el.textContent = 'No official rate stored \u2014 the converter is using market rates. ' +
                         'Enter today\u2019s figures from boz.zm below.';
      }
    } catch (e) {
      el.className = 'boz-current none';
      el.textContent = 'Could not read the current rate: ' + (e.message || 'request failed');
    }
  }

  $('boz_save').addEventListener('click', async function () {
    var btn = this;
    var buy = parseFloat($('boz_buy').value), sell = parseFloat($('boz_sell').value);
    var mid = midOf(), token = $('boz_token').value.trim();

    if (!isFinite(mid))      { avToast('Enter at least a buy or a sell rate.', 'warn'); return; }
    if (mid < 5 || mid > 200) {
      avToast('K' + mid.toFixed(2) + ' per dollar looks wrong \u2014 check the figures before saving.', 'warn');
      return;
    }

    if (!await avConfirm('Save K' + mid.toFixed(4) + ' per USD as the official rate? ' +
                         'Every kwacha conversion on the site will use it.',
                         { title: 'Update official rate', confirmLabel: 'Save rate' })) return;

    avBusy(btn, true);
    try {
      var got = await window.avFetchJson(getScriptUrl() + '?action=setBoz', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token: token,
          buy:   isFinite(buy)  ? buy  : undefined,
          sell:  isFinite(sell) ? sell : undefined,
          as_of: $('boz_date').value || undefined
        })
      }, 12000);

      if (!got.res.ok || got.data.ok === false) throw new Error(got.data.error || 'Save failed');

      try { localStorage.setItem(TOKEN_KEY, token); } catch (e) {}
      avToast(got.data.message || 'Official rate saved', 'ok');
      await showCurrent();
      if (typeof afxSync === 'function') afxSync();   // refresh the converter
    } catch (e) {
      avToast(e.message || 'Could not save the rate', 'danger');
    }
    avBusy(btn, false);
  });

  // Default the date to today, and load the stored rate when the page opens
  var t = new Date();
  $('boz_date').value = t.getFullYear() + '-' +
    String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
  showCurrent();
})();
</script>

<!-- ═══ LOAN BOOK + REPAYMENTS ═══════════════════════════════════════════ -->
<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  if (!$('book-refresh')) return;

  function K(n) {
    return 'K' + Number(n || 0).toLocaleString('en-US',
      { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  async function loadBook() {
    var btn = $('book-refresh');
    avBusy(btn, true);
    try {
      var d = (await window.avFetchJson(getScriptUrl() + '?action=loanBook&t=' + Date.now(),
                                        { cache: 'no-store' }, 12000)).data;
      if (!d || d.ok === false) throw new Error((d && d.error) || 'Could not read the loan book');
      var b = d.book;

      $('bk-out').textContent  = K(b.outstanding);
      $('bk-out-sub').textContent = b.active + ' active loan' + (b.active === 1 ? '' : 's');
      $('bk-col').textContent  = K(b.collected);
      $('bk-col-sub').textContent = b.collection_rate + '% of ' + K(b.due) + ' owed';
      $('bk-late').textContent = K(b.overdue_amount);
      $('bk-late-sub').textContent = b.overdue_count + ' loan' + (b.overdue_count === 1 ? '' : 's') + ' past due';
      $('bk-soon').textContent = K(b.due_this_week);
      $('bk-soon-sub').textContent = b.due_this_week_count + ' falling due';

      $('bk-bar').style.width = Math.min(100, b.collection_rate) + '%';
      $('book-sub').textContent = b.loans + ' loan' + (b.loans === 1 ? '' : 's') +
        ' with figures \u2014 ' + K(b.lent) + ' lent, ' + b.settled + ' settled';

      var notes = [];
      if (b.overdue_count > 0) {
        notes.push(b.overdue_count + ' loan' + (b.overdue_count === 1 ? ' is' : 's are') +
                   ' past the agreed date.');
      }
      if (b.overpaid_count > 0) {
        // Surfaced rather than folded into "collected" — usually a typo,
        // occasionally money that should go back to the borrower.
        notes.push(K(b.overpaid) + ' paid above what was owed on ' +
                   b.overpaid_count + ' loan' + (b.overpaid_count === 1 ? '' : 's') +
                   ' \u2014 worth checking.');
      }
      if (!notes.length && b.loans > 0) notes.push('Nothing overdue.');
      $('bk-note').textContent = notes.join(' ');
    } catch (e) {
      $('book-sub').textContent = e.message || 'Could not read the loan book';
      $('bk-note').textContent = '';
    }
    avBusy(btn, false);
  }

  $('book-refresh').addEventListener('click', loadBook);

  // Record a payment against a loan. Exposed globally so the applications
  // table can call it from a row button.
  window.recordPayment = async function (key, borrowerName, balance) {
    // avPrompt(title, body, placeholder, opts) — positional, and its default
    // tone is 'danger', which is wrong for taking a customer's money.
    var amount = await avPrompt(
      'Record a payment',
      'From ' + (borrowerName || 'this borrower') + '. Outstanding balance is ' + K(balance) + '.',
      'Amount in kwacha',
      { tone: 'info', okText: 'Record payment', cancelText: 'Cancel' });
    if (!amount) return;

    var amt = parseFloat(String(amount).replace(/[^0-9.]/g, ''));
    if (!isFinite(amt) || amt <= 0) { avToast('Enter an amount greater than zero.', 'warn'); return; }
    if (amt > balance + 0.005 &&
        !await avConfirm('More than the balance',
                         K(amt) + ' is more than the ' + K(balance) + ' outstanding. Record it anyway?',
                         { tone: 'warn', okText: 'Record it', cancelText: 'Cancel' })) return;

    try {
      var got = await window.avFetchJson(getScriptUrl() + '?action=addPayment', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: key, amount: amt, method: 'Cash' })
      }, 12000);
      if (!got.res.ok || got.data.ok === false) throw new Error(got.data.error || 'Could not record it');
      avToast(got.data.message, 'ok');
      if (got.data.summary && got.data.summary.settled) {
        avToast('Loan settled in full.', 'ok');
      }
      loadBook();
      if (typeof fetchRemoteRecords === 'function') fetchRemoteRecords(true);
    } catch (e) {
      avToast(e.message || 'Could not record the payment', 'danger');
    }
  };

  // Load once the dashboard is on screen
  var seen = false;
  var origShow = window.showPage;
  window.showPage = function (p) {
    if (typeof origShow === 'function') origShow.apply(this, arguments);
    if (p === 'dashboard' && !seen) { seen = true; loadBook(); }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { seen = true; loadBook(); });
  } else { seen = true; loadBook(); }
})();
</script>

<!-- ═══ ACCOUNTS ═════════════════════════════════════════════════════════ -->
<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  if (!$('acct-refresh')) return;

  // The page exists in the markup for everyone, but only an admin is shown the
  // way in and only an admin gets past the server check.
  var role = (window.AV_USER && window.AV_USER.role) || 'staff';
  if (role !== 'admin' && role !== 'super_admin') {
    ['nav-accounts', 'nav-accounts-section', 'page-accounts'].forEach(function (id) {
      var el = $(id); if (el && el.parentNode) el.parentNode.removeChild(el);
    });
    return;
  }
  $('nav-accounts').hidden = false;
  $('nav-accounts-section').hidden = false;

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;     // textContent: names are people's own
    return e;
  }
  function when(iso) {
    if (!iso) return '—';
    var d = new Date(iso);
    return isNaN(d) ? '—' : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }
  function ago(iso) {
    if (!iso) return '';
    var days = Math.floor((Date.now() - new Date(iso)) / 86400000);
    return days <= 0 ? 'today' : days === 1 ? 'yesterday' : days + ' days ago';
  }

  async function load() {
    avBusy($('acct-refresh'), true);
    try {
      var d = (await window.avFetchJson(getScriptUrl() + '?action=accounts&t=' + Date.now(),
                                        { cache: 'no-store' }, 12000)).data;
      if (!d || d.ok === false) throw new Error((d && d.error) || 'Could not read the accounts');
      render(d);
    } catch (e) {
      $('acct-sub').textContent = e.message || 'Could not read the accounts';
    }
    avBusy($('acct-refresh'), false);
  }

  function render(d) {
    var c = d.counts || {};
    $('acct-sub').textContent = d.total + ' account' + (d.total === 1 ? '' : 's') +
      (d.open_resets ? ' — ' + d.open_resets + ' waiting for a password reset' : '');

    var stats = $('acct-stats');
    stats.textContent = '';
    [['Borrowers', c.borrower || 0], ['Staff', c.staff || 0], ['Admins', c.admin || 0], ['Super Admins', c.super_admin || 0],
     ['Never signed in', c.never_signed_in || 0], ['Switched off', c.inactive || 0]
    ].forEach(function (p) {
      var box = el('div', 'acct-stat');
      box.appendChild(el('b', null, String(p[1])));
      box.appendChild(el('span', null, p[0]));
      stats.appendChild(box);
    });

    var history = $('acct-recovery-history');
    history.textContent = '';
    var labels = {'user.self_password_reset':'Password reset by email', 'user.password_reset':'Administrator reset', 'recovery.mail_failed':'Email sending failed', 'recovery.email_verified':'Recovery email verified'};
    (d.recovery_events || []).forEach(function (event) {
      history.appendChild(el('div', 'acct-reset-row', (labels[event.action] || event.action) + ' · ' + event.target + ' · ' + new Date(event.ts).toLocaleString('en-GB') + ' · By ' + ((event.detail && event.detail.by) || event.user)));
    });
    if (!history.childNodes.length) history.textContent = 'No recovery activity recorded yet.';

    // Those waiting for help, first
    var pending = (d.accounts || []).filter(function (u) { return u.reset_pending; });
    $('acct-resets').hidden = pending.length === 0;
    var list = $('acct-reset-list');
    list.textContent = '';
    pending.forEach(function (u) {
      var row = el('div', 'acct-reset-row');
      var who = el('div');
      who.appendChild(el('b', null, u.name || u.username));
      who.appendChild(document.createTextNode('  ' + u.username));
      who.appendChild(el('div', 'when', 'Asked ' + ago(u.reset_pending) + ' · ' + when(u.reset_pending)));
      row.appendChild(who);
      var go = el('button', 'btn btn-sm', 'Reset password');
      go.type = 'button';
      go.addEventListener('click', function () { reset(u); });
      row.appendChild(go);
      list.appendChild(row);
    });

    var tb = $('acct-tbody');
    tb.textContent = '';
    (d.accounts || []).forEach(function (u) {
      var tr = el('tr');
      tr.appendChild(el('td', null, u.name || '—'));
      tr.appendChild(el('td', null, u.username));
      var email = el('td');
      email.appendChild(document.createTextNode(u.email || 'Not added'));
      email.appendChild(el('div', 'when', u.email_verified_at ? 'Verified' : 'Not verified'));
      tr.appendChild(email);

      var role = el('td');
      role.appendChild(el('span', 'acct-pill ' + u.role, u.role));
      tr.appendChild(role);

      tr.appendChild(el('td', null, when(u.created_at)));

      var seen = el('td');
      seen.appendChild(document.createTextNode(u.last_login ? when(u.last_login) : 'Never'));
      if (u.last_login) seen.appendChild(el('div', 'when', ago(u.last_login)));
      tr.appendChild(seen);

      var apps = el('td');
      apps.appendChild(document.createTextNode(String(u.applications || 0)));
      if (u.latest_status) apps.appendChild(el('div', 'when', u.latest_status));
      tr.appendChild(apps);

      var st = el('td');
      if (!u.active) st.appendChild(el('span', 'acct-pill off', 'Switched off'));
      else if (u.must_change) st.appendChild(el('span', 'acct-pill temp', 'Temp password'));
      else st.appendChild(document.createTextNode('Active'));
      tr.appendChild(st);

      var act = el('td');
      var canManage = u.id !== (window.AV_USER && window.AV_USER.id) &&
        (u.role !== 'super_admin' || role === 'super_admin');
      if (canManage) {
        var r = el('button', 'btn btn-ghost btn-sm', 'Reset password');
        r.type = 'button';
        r.addEventListener('click', function () { reset(u); });
        act.appendChild(r);

        var t = el('button', 'btn btn-ghost btn-sm', u.active ? 'Switch off' : 'Switch on');
        t.type = 'button';
        t.style.marginLeft = '6px';
        t.addEventListener('click', function () { toggle(u); });
        act.appendChild(t);
      } else {
        act.appendChild(el('span', 'when', u.id === (window.AV_USER && window.AV_USER.id) ? 'This is you' : 'Super Admin only'));
      }
      tr.appendChild(act);
      tb.appendChild(tr);
    });
  }

  async function reset(u) {
    if (!await avConfirm('Reset this password?',
        'A new temporary password will be created for ' + (u.name || u.username) +
        '. Their current password stops working straight away, and they must choose a new one ' +
        'when they next sign in. Only do this once you are sure who you are speaking to.',
        { tone: 'warn', okText: 'Reset it', cancelText: 'Cancel' })) return;
    try {
      var got = await window.avFetchJson(getScriptUrl() + '?action=resetPassword', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: u.id })
      }, 12000);
      if (!got.res.ok || got.data.ok === false) throw new Error(got.data.error || 'Could not reset it');

      // Shown once. It is not stored in readable form anywhere, so if it is
      // lost the reset is simply done again.
      var box = el('div', 'acct-temp');
      box.appendChild(document.createTextNode('Temporary password for ' + (u.name || u.username) + ':'));
      box.appendChild(el('code', null, got.data.temporary_password));
      box.appendChild(document.createTextNode(
        'Read it out now — it is not shown again and is not stored anywhere. They must change it ' +
        'when they sign in.'));
      var host = $('acct-temp-output');
      host.textContent = '';
      host.appendChild(box);
      setTimeout(function () { if (box.parentNode) box.parentNode.removeChild(box); }, 300000);
      avToast('Password reset', 'ok');
      load();
    } catch (e) {
      avToast(e.message || 'Could not reset it', 'danger');
    }
  }

  async function toggle(u) {
    var off = !!u.active;
    if (!await avConfirm(off ? 'Switch off this account?' : 'Switch this account back on?',
        off ? (u.name || u.username) + ' will not be able to sign in. Their application records stay.'
            : (u.name || u.username) + ' will be able to sign in again.',
        { tone: off ? 'danger' : 'info', okText: off ? 'Switch off' : 'Switch on', cancelText: 'Cancel' })) return;
    try {
      var got = await window.avFetchJson(getScriptUrl() + '?action=setUserActive', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: u.id, active: !u.active })
      }, 12000);
      if (!got.res.ok || got.data.ok === false) throw new Error(got.data.error || 'Could not change it');
      load();
    } catch (e) {
      avToast(e.message || 'Could not change it', 'danger');
    }
  }

  $('acct-refresh').addEventListener('click', load);

  var loaded = false;
  var orig = window.showPage;
  window.showPage = function (p) {
    if (typeof orig === 'function') orig.apply(this, arguments);
    if (p === 'accounts' && !loaded) { loaded = true; load(); }
  };
})();
</script>
</body>
</html>
