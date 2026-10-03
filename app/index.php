<?php
/**
 * Second line of defence. .htaccess is not honoured by nginx, and some hosts
 * ignore it entirely — so this file guards itself rather than trusting the
 * web server to hide it. Fetching it directly still hits the login.
 */
require_once __DIR__ . '/../auth.php';

/* The marketing pages are public so the business can be found on Google. A
 * site nobody can reach is a site nobody discovers. What stays behind the
 * login is everything that involves a person's details: the loan application,
 * the portal, documents and records. Visitors who are not signed in get the
 * same pages with those parts removed from the document entirely. */
$me = av_user();
$avPublic = ($me === null);
// Which office is being served. loans.php and it.php each set this before
// requiring the page; opened directly it falls back to lending.
$avSite = defined('AV_SITE') ? AV_SITE : 'lending';

// ── WHAT A SEARCH ENGINE SEES ───────────────────────────────────────────────
// Each office needs its own title, description and canonical address. They all
// used to say the same thing, and the canonical pointed at the home page —
// which tells Google that loans.php and it.php are copies of it, and it drops
// them from the results. The IT page is the one people search for by name.
$avBase = 'https://www.avesta.solutions/';
$avSeo = $avSite === 'it' ? [
    'url'   => $avBase . 'it.php',
    'title' => 'IT Services in Ndola & Across Zambia | Avesta Consulting',
    'desc'  => 'Computer repair, networks and Wi-Fi, CCTV installation, cybersecurity, backups, '
             . 'websites and IT training for businesses and homes across Zambia. We tell you what '
             . 'a job costs before any work begins.',
    'keys'  => 'IT support Ndola, computer repair Ndola, CCTV installation Zambia, networking Copperbelt, '
             . 'cybersecurity Zambia, IT services Lusaka, website design Zambia',
    'type'  => 'ProfessionalService',
    'og'    => 'Avesta Consulting — IT services across Zambia',
] : [
    'url'   => $avBase . 'loans.php',
    'title' => 'Short-Term Loans in Ndola & Across Zambia | Avesta Lending',
    'desc'  => 'Short-term personal and business loans from one to four weeks, anywhere in Zambia. '
             . 'Terms stated up front, repayment calculator, and disbursement by cash, mobile money '
             . 'or bank transfer.',
    'keys'  => 'loans Zambia, quick loans Ndola, personal loans Copperbelt, business loans Lusaka, '
             . 'short term loans Zambia, mobile money loans',
    'type'  => 'FinancialService',
    'og'    => 'Avesta Lending — know what you will repay',
];


echo '<script>window.AV_SITE=' . json_encode($avSite)
   . ';window.AV_PUBLIC=' . ($avPublic ? 'true' : 'false')
   . ';window.AV_USER=' . ($avPublic ? 'null' : json_encode([
    'name' => $me['name'], 'username' => $me['username'], 'role' => $me['role'],
], JSON_UNESCAPED_SLASHES)) . ';</script>';
?>
  <!DOCTYPE html>
<html lang="en" prefix="og: https://ogp.me/ns#">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($avSeo['title']) ?></title>
<meta name="description" content="<?= htmlspecialchars($avSeo['desc']) ?>">
<meta name="keywords" content="<?= htmlspecialchars($avSeo['keys']) ?>">
<meta name="author" content="Avesta Enterprises">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="#163E33">
<link rel="canonical" href="<?= htmlspecialchars($avSeo['url']) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Avesta Enterprises">
<meta property="og:title" content="<?= htmlspecialchars($avSeo['og']) ?>">
<meta property="og:description" content="<?= htmlspecialchars($avSeo['desc']) ?>">
<meta property="og:url" content="<?= htmlspecialchars($avSeo['url']) ?>">
<meta property="og:locale" content="en_ZM">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($avSeo['og']) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($avSeo['desc']) ?>">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='4' fill='%23163E33'/%3E%3Ctext x='16' y='22' font-family='Arial' font-weight='bold' font-size='16' fill='%23D98E3B' text-anchor='middle'%3EAE%3C/text%3E%3C/svg%3E">
<link rel="apple-touch-icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 180 180'%3E%3Crect width='180' height='180' rx='20' fill='%23163E33'/%3E%3Ctext x='90' y='126' font-family='Arial' font-weight='bold' font-size='90' fill='%23D98E3B' text-anchor='middle'%3EAE%3C/text%3E%3C/svg%3E">
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"<?= $avSeo['type'] ?>","name":"Avesta Enterprises","description":"Fast, flexible personal and business loans across Zambia.","url":"https://www.avesta.solutions/","telephone":["+260971013108","+260769974200"],"email":"info@avesta.solutions","address":{"@type":"PostalAddress","streetAddress":"House No. 3, Thom Avenue","addressLocality":"Kansenshi, Ndola","addressCountry":"ZM"},"openingHours":"Mo-Sa 08:00-17:00","currenciesAccepted":"ZMW","paymentAccepted":"Cash, Bank Transfer, Mobile Money","areaServed":{"@type":"City","name":"Ndola"}}
</script>
<meta name="format-detection" content="telephone=yes">
<style>
:root{
  /* AVESTA ENTERPRISES — one parent, two arms.
     Navy is the spine and never changes: it is what makes the two halves of
     the business read as one company. Each arm then carries its own accent —
     gold for Lending, blue for Consulting — so a visitor always knows which
     side of Avesta they are looking at without being told.
     Each accent comes in two weights because a colour bright enough to sit on
     navy is never dark enough for body text on white. */
  --navy:#163E33; --navy-2:#1F5344; --ink:#12241E;
  --gold:#D98E3B;        /* Lending — 4.45:1 on navy  */
  --gold-light:#E8AE6E;
  --gold-deep:#96591A;   /* Lending — 5.61:1 on white */
  --tech:#5B9BFF;        /* Consulting — 4.28:1 on navy  */
  --tech-light:#8FBBFF;
  --tech-deep:#1F5FA8;   /* Consulting — 6.44:1 on white */
  --lgray:#F7F3EC; --mgray:#E0E0E0; --dgray:#444; --white:#fff;
  --radius:10px;
  --shadow:0 4px 24px rgba(0,0,0,.08);
  --shadow-sm:0 2px 8px rgba(0,0,0,.06);
  /* The active arm. Sections override these two and everything follows. */
  --accent:var(--gold); --accent-deep:var(--gold-deep); --accent-light:var(--gold-light);
}
/* The consulting arm swaps the accent for its own; navy stays put. */
[data-arm="consulting"]{
  --accent:var(--tech); --accent-deep:var(--tech-deep); --accent-light:var(--tech-light);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:Arial,sans-serif;color:var(--dgray);background:var(--white);min-height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
::selection{background:var(--gold);color:var(--navy)}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--gold);border-radius:99px;opacity:.7}
::-webkit-scrollbar-thumb:hover{background:var(--navy)}
:focus-visible{outline:2.5px solid var(--gold);outline-offset:3px;border-radius:4px}
input:focus-visible,select:focus-visible,textarea:focus-visible{outline:none}

/* ── GLOBAL MOTION BASELINE ─────────────────────────────────────────────── */
button,a,.dur-tile,.method-card,input,select,.upload-item,.record-card,.doc-tile,.rv-doc-tile{transition:transform .15s ease,box-shadow .2s ease,background-color .2s ease,border-color .2s ease,opacity .2s ease}
button:not(:disabled):active,a.map-link-btn:active{transform:scale(.97)}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}

/* ── NAV ─────────────────────────────────────────────────────────────────── */
nav{position:fixed;top:0;width:100%;background:rgba(22,62,51,.97);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:60px;z-index:1000;border-bottom:2px solid rgba(217,142,59,.6);box-shadow:0 2px 20px rgba(0,0,0,.2)}
.nav-brand{display:flex;align-items:center;gap:10px;cursor:pointer;text-decoration:none}
.logo-box{background:var(--gold);color:var(--navy);font-weight:bold;font-size:14pt;padding:4px 10px;border-radius:3px}
.logo-text{color:white;font-size:11pt;letter-spacing:2px;text-transform:uppercase}
.nav-tabs{display:flex;gap:0;height:60px}
.nav-tab{display:flex;align-items:center;padding:0 18px;color:rgba(255,255,255,.65);text-decoration:none;font-size:9pt;letter-spacing:1px;text-transform:uppercase;cursor:pointer;border:none;background:none;font-family:Arial,sans-serif;transition:color .25s ease,border-color .25s ease;border-bottom:3px solid transparent;margin-bottom:-3px;white-space:nowrap;position:relative}
.nav-tab:hover{color:var(--gold)}
.nav-tab.active{color:white;border-bottom-color:var(--gold)}
.nav-tab:not(.nav-tab-apply):active{transform:translateY(1px)}
.nav-tab-apply{background:var(--gold);color:var(--navy)!important;font-weight:bold;border-radius:4px;margin:auto 0 auto 8px;padding:8px 16px;height:auto;border-bottom:none!important}
.nav-tab-apply:hover{background:var(--gold-light)!important}
.nav-tab-apply.active{background:var(--gold-light);border-bottom:none!important}
.nav-toggle{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px;background:none;border:none}
.nav-toggle span{display:block;width:22px;height:2px;background:white;border-radius:2px;transition:all .3s}
.nav-mobile{display:none;position:fixed;top:60px;left:0;width:100%;background:var(--navy);border-bottom:3px solid var(--gold);flex-direction:column;padding:8px 0;z-index:999}
.nav-mobile button,.nav-mobile a{display:block;width:100%;padding:13px 28px;color:rgba(255,255,255,.8);text-decoration:none;font-size:10pt;letter-spacing:1px;text-transform:uppercase;border:none;background:none;text-align:left;cursor:pointer;font-family:Arial,sans-serif;border-bottom:1px solid rgba(255,255,255,.06)}
.nav-mobile button:hover,.nav-mobile a:hover{color:var(--gold);background:rgba(255,255,255,.04)}
.nav-mobile button.active{color:var(--gold)}
.nav-mobile.open{display:flex}

/* ── PAGE SHELL ──────────────────────────────────────────────────────────── */
.page-content{margin-top:60px;flex:1}
.tab-panel{display:none}
.tab-panel.active{display:block;opacity:1;animation:tabIn .45s cubic-bezier(.22,.61,.36,1) backwards}
@keyframes tabIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ── HERO (Home) ─────────────────────────────────────────────────────────── */
.hero{min-height:calc(100vh - 60px);background:linear-gradient(135deg,var(--navy) 55%,#1a4d38 100%);display:flex;align-items:center;justify-content:center;padding:60px 32px 40px;text-align:center;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;top:-80px;right:-80px;width:420px;height:420px;background:radial-gradient(circle,rgba(217,142,59,.18) 0%,transparent 70%);border-radius:50%;pointer-events:none}
.hero::after{content:'';position:absolute;bottom:-60px;left:-60px;width:320px;height:320px;background:radial-gradient(circle,rgba(217,142,59,.1) 0%,transparent 70%);border-radius:50%;pointer-events:none}
.hero-badge,.hero h1,.hero p,.hero-btns,.hero-stats{animation:heroReveal .7s cubic-bezier(.22,.61,.36,1) backwards}
.hero-badge{animation-delay:.05s}
.hero h1{animation-delay:.15s}
.hero p{animation-delay:.28s}
.hero-btns{animation-delay:.4s}
.hero-stats{animation-delay:.52s}
@keyframes heroReveal{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){.hero-badge,.hero h1,.hero p,.hero-btns,.hero-stats{animation:none;opacity:1}}
.hero::before{content:'';position:absolute;top:-100px;right:-100px;width:500px;height:500px;background:rgba(217,142,59,.08);border-radius:50%}
.hero::after{content:'';position:absolute;bottom:-80px;left:-80px;width:350px;height:350px;background:rgba(217,142,59,.06);border-radius:50%}
.hero-watermark{position:absolute;inset:0;z-index:0;opacity:.06;background-image:
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='180' viewBox='0 0 300 180'%3E%3Crect x='8' y='8' width='180' height='84' rx='4' fill='none' stroke='%23ffffff' stroke-width='2'/%3E%3Crect x='14' y='14' width='168' height='72' rx='2' fill='none' stroke='%23ffffff' stroke-width='1'/%3E%3Ccircle cx='52' cy='50' r='26' fill='none' stroke='%23ffffff' stroke-width='1.5'/%3E%3Ctext x='38' y='59' font-family='Georgia,serif' font-size='28' font-weight='bold' fill='%23ffffff'%3EK%3C/text%3E%3Ctext x='148' y='34' font-family='Arial' font-size='13' font-weight='bold' fill='%23ffffff'%3EZMW%3C/text%3E%3Ctext x='150' y='78' font-family='Georgia,serif' font-size='20' font-weight='bold' fill='%23ffffff'%3E50%3C/text%3E%3Cpath d='M100 20 l6 6 -6 6 -6 -6 z' fill='none' stroke='%23ffffff' stroke-width='1'/%3E%3Cpath d='M100 68 l6 6 -6 6 -6 -6 z' fill='none' stroke='%23ffffff' stroke-width='1'/%3E%3Crect x='210' y='100' width='80' height='38' rx='3' fill='none' stroke='%23ffffff' stroke-width='1.5' transform='rotate(8 250 119)'/%3E%3Ctext x='222' y='126' font-family='Georgia,serif' font-size='16' font-weight='bold' fill='%23ffffff' transform='rotate(8 250 119)'%3EK100%3C/text%3E%3C/svg%3E");
  background-repeat:repeat;background-size:300px 180px;pointer-events:none}
.hero-content{position:relative;z-index:1;max-width:700px}
.hero-badge{display:inline-block;background:rgba(217,142,59,.2);color:var(--gold);border:1px solid var(--gold);padding:6px 16px;border-radius:20px;font-size:9pt;letter-spacing:2px;text-transform:uppercase;margin-bottom:24px}
.hero h1{font-size:36pt;color:white;line-height:1.2;margin-bottom:16px}
.hero h1 span{color:var(--gold)}
.hero p{color:rgba(255,255,255,.75);font-size:12pt;line-height:1.7;margin-bottom:32px}
.hero-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
.btn-primary{background:var(--gold);color:var(--navy);font-weight:800;padding:14px 34px;border-radius:10px;text-decoration:none;font-size:11pt;transition:all .25s cubic-bezier(.22,.61,.36,1);display:inline-block;border:none;cursor:pointer;font-family:Arial,sans-serif;box-shadow:0 4px 16px rgba(217,142,59,.35);letter-spacing:.2px}
.btn-primary:hover{background:var(--gold-light);transform:translateY(-2px)}
.btn-outline{border:2px solid rgba(255,255,255,.55);color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-size:11pt;transition:all .25s cubic-bezier(.22,.61,.36,1);display:inline-block;background:rgba(255,255,255,.06);cursor:pointer;font-family:Arial,sans-serif;backdrop-filter:blur(4px)}
.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.hero-stats{display:flex;gap:32px;justify-content:center;margin-top:48px;flex-wrap:wrap}
.stat{text-align:center}
.stat-num{font-size:26pt;font-weight:800;color:var(--gold);letter-spacing:-.5px;line-height:1}
.stat-lbl{font-size:8pt;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;margin-top:4px}

/* Home quick-nav cards */
.home-cards{padding:60px 32px;background:var(--lgray)}
.home-cards-title{text-align:center;margin-bottom:36px}
.home-cards-title h2{font-size:22pt;color:var(--navy);margin-bottom:8px}
.home-cards-title p{color:#777;font-size:11pt}
.cards-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:20px;max-width:1300px;margin:0 auto}
.nav-card{background:white;border-radius:14px;padding:32px 24px;text-align:center;border:1px solid rgba(0,0,0,.06);border-bottom:4px solid var(--gold);cursor:pointer;transition:all .25s cubic-bezier(.22,.61,.36,1);box-shadow:0 4px 16px rgba(0,0,0,.07)}
.nav-card:hover{transform:translateY(-6px) scale(1.01);box-shadow:0 12px 32px rgba(0,0,0,.14)}
.nav-card .nc-icon{font-size:28pt;margin-bottom:14px}
.nav-card h3{color:var(--navy);font-size:13pt;margin-bottom:8px}
.nav-card p{font-size:9pt;color:#666;line-height:1.6;margin-bottom:18px}
.nc-btn{display:inline-block;background:var(--navy);color:white;padding:9px 22px;border-radius:5px;font-size:9pt;font-weight:bold;text-transform:uppercase;letter-spacing:.5px;transition:background .2s}
.nav-card:hover .nc-btn{background:var(--gold);color:var(--navy)}

/* Rate preview on home */
.rate-preview{padding:50px 32px;background:var(--navy);text-align:center}
.rate-preview h2{color:white;font-size:20pt;margin-bottom:6px}
.rate-preview p{color:rgba(255,255,255,.6);font-size:10pt;margin-bottom:28px}
.rate-pills{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.rate-pill{background:rgba(255,255,255,.07);border:1.5px solid rgba(217,142,59,.4);border-radius:12px;padding:20px 28px;min-width:130px;cursor:pointer;transition:all .2s}
.rate-pill:hover{background:rgba(217,142,59,.2);border-color:var(--gold);transform:translateY(-3px)}
.rp-week{color:rgba(255,255,255,.7);font-size:8.5pt;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.rp-rate{color:var(--gold);font-size:26pt;font-weight:bold;line-height:1}
.rp-label{color:rgba(255,255,255,.5);font-size:8pt;margin-top:4px}

/* ── HOW IT WORKS ────────────────────────────────────────────────────────── */
.page-section{padding:70px 32px}
.section-tag{display:inline-block;background:rgba(217,142,59,.15);color:var(--gold);font-size:8pt;letter-spacing:2px;text-transform:uppercase;padding:4px 12px;border-radius:20px;margin-bottom:12px}
.section-title{font-size:23pt;color:var(--navy);margin-bottom:12px;font-weight:800;letter-spacing:-.3px}
.section-sub{color:#777;font-size:11pt;line-height:1.6;max-width:560px}
.center{text-align:center}
.center .section-sub{margin:0 auto}
#tab-how{background:var(--lgray)}
.steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-top:48px}
.step{background:white;border-radius:10px;padding:32px 24px;text-align:center;border-bottom:3px solid var(--gold);box-shadow:0 4px 20px rgba(0,0,0,.06);position:relative}
.step-num{width:52px;height:52px;background:var(--navy);color:var(--gold);font-size:18pt;font-weight:bold;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.step h3{color:var(--navy);margin-bottom:8px;font-size:12pt}
.step p{font-size:9.5pt;line-height:1.6;color:#666}
.step-arrow{position:absolute;right:-14px;top:50%;transform:translateY(-50%);color:var(--gold);font-size:20pt;z-index:1}
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-top:40px}
.feat{display:flex;gap:14px;align-items:flex-start;padding:20px;border:1px solid var(--mgray);border-radius:8px;border-left:4px solid var(--gold);background:white}
.feat-icon{font-size:22pt;min-width:40px}
.feat-text h4{color:var(--navy);margin-bottom:6px;font-size:10pt}
.feat-text p{font-size:9pt;color:#666;line-height:1.5}

/* ── CALCULATOR ──────────────────────────────────────────────────────────── */
#tab-calc{background:var(--navy);padding:70px 32px}
#tab-calc .section-title{color:white}
#tab-calc .section-sub{color:rgba(255,255,255,.6)}
.rate-tiers{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;max-width:800px;margin:0 auto 28px;padding:0 4px}
.rate-tier{background:rgba(255,255,255,.07);border:1.5px solid rgba(217,142,59,.3);border-radius:10px;padding:16px 10px;text-align:center;cursor:pointer;transition:all .2s}
.rate-tier:hover,.rate-tier.active{background:rgba(217,142,59,.18);border-color:var(--gold);transform:translateY(-3px)}
.rt-weeks{color:rgba(255,255,255,.7);font-size:8.5pt;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.rt-rate{color:var(--gold);font-size:22pt;font-weight:bold;line-height:1}
.rt-label{color:rgba(255,255,255,.5);font-size:8pt;margin-top:4px}
.calc-wrap{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:40px;max-width:800px;margin-left:auto;margin-right:auto}
.calc-form{background:rgba(255,255,255,.07);border:1px solid rgba(217,142,59,.3);border-radius:10px;padding:28px}
.calc-form label{display:block;color:rgba(255,255,255,.7);font-size:8.5pt;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;margin-top:16px}
.calc-form label:first-child{margin-top:0}
.calc-form input,.calc-form select{width:100%;background:rgba(255,255,255,.1);border:1px solid rgba(217,142,59,.4);border-radius:5px;padding:10px 12px;color:white;font-family:Arial,sans-serif;font-size:10pt;outline:none}
.calc-form input::placeholder{color:rgba(255,255,255,.3)}
.calc-form input:focus{border-color:var(--gold)}
.calc-form select option{background:var(--navy);color:white}
.calc-btn{width:100%;margin-top:20px;background:var(--gold);color:var(--navy);font-weight:bold;padding:12px;border:none;border-radius:6px;font-size:10pt;cursor:pointer;font-family:Arial,sans-serif;transition:background .2s}
.calc-btn:hover{background:var(--gold-light)}
.calc-result{background:rgba(255,255,255,.08);border:1px solid rgba(217,142,59,.35);border-radius:14px;padding:28px;display:flex;flex-direction:column;justify-content:center;box-shadow:inset 0 1px 0 rgba(255,255,255,.1)}
.calc-result h3{color:var(--gold);font-size:11pt;margin-bottom:20px;text-transform:uppercase;letter-spacing:1px}
.result-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.08)}
.result-row:last-child{border-bottom:none}
.result-row .r-lbl{color:rgba(255,255,255,.6);font-size:9pt}
.result-row .r-val{color:white;font-weight:bold;font-size:11pt}
.result-row.highlight .r-val{color:var(--gold);font-size:14pt}
.calc-apply-btn{display:block;text-align:center;margin:28px auto 0;background:var(--gold);color:var(--navy);font-weight:bold;padding:14px 36px;border-radius:6px;border:none;cursor:pointer;font-family:Arial,sans-serif;font-size:11pt;transition:all .2s}
.calc-apply-btn:hover{background:var(--gold-light);transform:translateY(-2px)}

/* ── APPLY (FORM) ────────────────────────────────────────────────────────── */
#tab-apply{background:var(--lgray);padding:50px 32px}
.form-card{background:white;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,.1),0 3px 10px rgba(0,0,0,.06);max-width:900px;margin:0 auto;overflow:hidden}
.form-header{background:var(--navy);padding:20px 28px;display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid var(--gold);flex-wrap:wrap;gap:10px}
.form-header h2{color:white;font-size:14pt}
.form-header span{color:var(--gold);font-size:8.5pt}
.form-body{padding:28px}
.f-section{margin-bottom:28px}
.f-head{font-size:10.5pt;font-weight:bold;color:var(--navy);text-transform:uppercase;border-bottom:2px solid var(--gold);padding-bottom:5px;margin-bottom:14px;letter-spacing:.5px}
.f-sub{font-size:9.5pt;font-weight:bold;color:var(--gold);margin:12px 0 8px}
.f-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 20px}
.f-group{display:flex;flex-direction:column}
.f-group.full{grid-column:1/-1}
.f-group label{font-size:8pt;color:var(--navy);font-weight:bold;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.f-group input,.f-group select{border:1.5px solid #e0e0e0;border-radius:8px;padding:10px 13px;font-family:Arial,sans-serif;font-size:9.5pt;color:#222;outline:none;transition:border-color .2s,box-shadow .2s,background .15s;background:white}
.f-group input:focus,.f-group select:focus{border-color:var(--gold);box-shadow:0 0 0 4px rgba(217,142,59,.13);background:#fffdf8}
.f-group input[readonly]{background:var(--lgray);color:#888}
.f-group input::placeholder{color:#bbb;font-style:italic}
.auto-field input{background:rgba(217,142,59,.08);border-color:var(--gold)!important;font-weight:bold;color:var(--navy)}
.collateral-item{display:grid;grid-template-columns:auto 1fr auto 130px;gap:8px;align-items:center;margin-bottom:8px;font-size:9pt}
.collateral-item span{color:var(--navy);font-weight:bold;white-space:nowrap}
.collateral-item input{border:1px solid var(--mgray);border-radius:4px;padding:8px;font-family:Arial,sans-serif;font-size:9pt;outline:none}
.collateral-item input:focus{border-color:var(--gold)}
.ref-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.ref-block2{border:1px solid var(--mgray);border-radius:6px;overflow:hidden}
.ref-block2 .rb-title{background:var(--lgray);padding:7px 12px;font-weight:bold;color:var(--gold);font-size:9.5pt;border-bottom:1px solid var(--mgray)}
.ref-block2 .rb-body{padding:12px;display:flex;flex-direction:column;gap:8px}
.ref-block2 label{font-size:8pt;color:var(--navy);font-weight:bold;text-transform:uppercase;margin-bottom:2px;display:block}
.ref-block2 input{width:100%;border:1px solid var(--mgray);border-radius:4px;padding:7px 9px;font-family:Arial,sans-serif;font-size:9pt;outline:none}
.ref-block2 input:focus{border-color:var(--gold)}
.check-row{display:flex;gap:16px;flex-wrap:wrap;padding:4px 0}
.check-row label{display:flex;align-items:center;gap:6px;font-size:9pt;cursor:pointer}
.check-row input[type=checkbox]{accent-color:var(--navy);width:14px;height:14px}
.dur-rate-row{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:4px}
.dur-tile{border:2px solid var(--mgray);border-radius:8px;padding:12px 8px;text-align:center;cursor:pointer;transition:all .2s;background:white}
.dur-tile:hover{border-color:var(--gold);background:rgba(217,142,59,.06)}
.dur-tile.active{border-color:var(--navy);background:linear-gradient(135deg,var(--navy),#1a4d38);color:white;box-shadow:0 4px 16px rgba(22,62,51,.25);transform:translateY(-2px)}
.dt-weeks{font-size:8.5pt;color:var(--navy);font-weight:bold;text-transform:uppercase;letter-spacing:.5px}
.dt-rate{font-size:20pt;font-weight:bold;color:var(--gold);line-height:1.2}
.dt-tag{font-size:7.5pt;color:#888;margin-top:2px}
.method-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin-top:8px}
.method-card{display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 8px;border:1.5px solid var(--mgray);border-radius:8px;cursor:pointer;transition:all .2s;text-align:center;background:white}
.method-card input[type=radio]{display:none}
.method-card:hover{border-color:var(--gold);background:rgba(217,142,59,.06)}
.method-card:has(input:checked){border-color:var(--gold);background:rgba(217,142,59,.12);box-shadow:0 0 0 2px rgba(217,142,59,.25)}
.mc-icon{font-size:18pt}
.mc-name{font-size:9pt;font-weight:bold;color:var(--navy)}
.mc-desc{font-size:7.5pt;color:#888;line-height:1.3}
.method-card.mc-light{background:var(--lgray)}
.method-card.mc-light:has(input:checked){background:rgba(217,142,59,.12)}
.method-detail{margin-top:10px;padding:12px 16px;border-radius:6px;border:1.5px solid var(--gold);background:rgba(217,142,59,.06);font-size:9pt;color:var(--navy);line-height:1.7}
.method-detail strong{color:var(--gold)}
/* ── DOCUMENT UPLOADS ────────────────────────────────────────────────────── */
.upload-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:8px}
.upload-item{display:flex;flex-direction:column;align-items:center;gap:8px;border:2px dashed var(--mgray);border-radius:8px;padding:18px 12px;text-align:center;transition:all .2s;position:relative;background:white;cursor:pointer}
.upload-item:hover{border-color:var(--gold);background:rgba(217,142,59,.05);transform:translateY(-2px);box-shadow:0 4px 16px rgba(217,142,59,.12)}
.upload-item.has-file{border-color:#27ae60;border-style:solid;background:rgba(39,174,96,.05);box-shadow:0 2px 10px rgba(39,174,96,.12)}
.upload-item input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-icon{font-size:24pt;line-height:1}
.upload-label{font-size:8.5pt;font-weight:bold;color:var(--navy);text-transform:uppercase;letter-spacing:.5px}
.upload-hint{font-size:7.5pt;color:#888;line-height:1.4}
.upload-filename{font-size:7.5pt;color:var(--gold);font-weight:bold;margin-top:2px;word-break:break-all;display:none}
.upload-item.has-file .upload-filename{display:block}
.upload-item.has-file .upload-hint{display:none}
.upload-required::after{content:' *';color:#c0392b}
.f-group[data-req="1"] > label::after{content:' *';color:#c0392b;font-weight:bold}
.opt-tag{color:#999;font-weight:normal;font-size:7pt;text-transform:none;letter-spacing:0}
.doc-note{font-size:8pt;color:#888;font-style:italic;margin-top:10px}
.sig-row2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.sig-box2{border-radius:6px;padding:16px}
.sig-box2.borrower{border:1.5px solid var(--navy);background:var(--lgray)}
.sig-box2.lender{border:1.5px solid var(--gold);background:var(--navy)}
.sig-box2 .sig-title{font-weight:bold;font-size:9.5pt;text-align:center;margin-bottom:14px}
.sig-box2.borrower .sig-title{color:var(--navy)}
.sig-box2.lender .sig-title{color:white}
.sig-field2{margin-top:12px}
.sig-field2 input{width:100%;border:none;border-bottom:1.5px solid var(--mgray);background:transparent;outline:none;font-family:Arial,sans-serif;font-size:9pt;padding:4px 0}
.sig-box2.lender .sig-field2 input{border-bottom-color:var(--gold);color:white}
.sig-box2.lender .sig-field2 input::placeholder{color:rgba(217,142,59,.5)}
.sig-lbl{text-align:center;font-size:7.5pt;color:#aaa;margin-top:3px}
.sig-box2.lender .sig-lbl{color:var(--gold)}
.office-use{border:2px solid var(--gold);border-radius:6px;overflow:hidden;margin-top:16px}
.office-use .ou-title{background:var(--lgray);padding:7px 14px;font-weight:bold;color:var(--navy);font-size:9pt;text-align:center;border-bottom:1px solid var(--mgray)}
.office-use .ou-body{padding:14px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.print-bar2{background:var(--lgray);border-top:2px solid var(--gold);padding:14px 28px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.btn-gold{background:var(--gold);color:white;border:none;padding:10px 22px;border-radius:5px;font-family:Arial,sans-serif;font-size:9.5pt;font-weight:bold;cursor:pointer}
.btn-gold:hover{background:var(--gold-light)}
.btn-ghost{background:transparent;color:#666;border:1px solid var(--mgray);padding:10px 22px;border-radius:5px;font-family:Arial,sans-serif;font-size:9.5pt;cursor:pointer}
.btn-ghost:hover{border-color:#999;color:#333}
.print-note2{margin-left:auto;font-size:8.5pt;color:#888}

/* ── CONTACT ─────────────────────────────────────────────────────────────── */
#tab-contact{background:var(--white);padding:70px 32px}
.contact-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:40px;max-width:800px;margin-left:auto;margin-right:auto}
.contact-card{text-align:center;padding:28px 20px;border:1px solid var(--mgray);border-radius:8px;border-top:3px solid var(--gold)}
.contact-icon{font-size:24pt;margin-bottom:12px}
.contact-card h4{color:var(--navy);margin-bottom:8px}
.contact-card p{font-size:9.5pt;color:#555;line-height:1.7}
.contact-card a{color:var(--gold);text-decoration:none}
.map-hint{max-width:800px;margin:30px auto 0;background:var(--lgray);border-radius:8px;padding:20px 24px;border-left:4px solid var(--gold);font-size:9.5pt;color:#555}
.map-hint strong{color:var(--navy)}

/* ── FOOTER ──────────────────────────────────────────────────────────────── */
footer{background:var(--navy);border-top:1px solid rgba(217,142,59,.35);box-shadow:0 -1px 0 rgba(217,142,59,.15);padding:36px 32px 28px;display:flex;flex-direction:column;gap:22px}
.footer-top{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:24px}
.footer-brand{color:white}
.fb-name{font-size:14pt;font-weight:bold;letter-spacing:.5px}
.fb-tag{font-size:8.5pt;color:rgba(255,255,255,.45);margin-top:5px}
.footer-links{display:flex;gap:4px;flex-wrap:wrap}
.footer-links button{color:rgba(255,255,255,.55);text-decoration:none;font-size:8.5pt;background:none;border:none;cursor:pointer;font-family:Arial,sans-serif;padding:8px 12px;border-radius:5px;transition:color .2s ease,background-color .2s ease}
.footer-links button:hover{color:var(--gold);background:rgba(217,142,59,.08)}
.footer-bottom{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding-top:18px;border-top:1px solid rgba(255,255,255,.08)}
.footer-copy{color:rgba(255,255,255,.38);font-size:7.8pt;letter-spacing:.2px}
.footer-made{color:rgba(255,255,255,.3);font-size:7.5pt}

/* ── WHATSAPP + SCROLL ───────────────────────────────────────────────────── */
.wa-float{position:fixed;bottom:24px;right:24px;width:56px;height:56px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,211,102,.4);z-index:998;text-decoration:none;transition:transform .2s}
.wa-float:hover{transform:scale(1.1)}
.wa-float svg{width:28px;height:28px;fill:white}
.wa-tooltip{position:absolute;right:64px;background:var(--navy);color:white;padding:6px 12px;border-radius:6px;font-size:8.5pt;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.wa-float:hover .wa-tooltip{opacity:1}
.scroll-top{position:fixed;bottom:24px;left:24px;width:42px;height:42px;background:var(--navy);border:2px solid var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:998;opacity:0;transition:opacity .3s;color:var(--gold);font-size:16pt;font-weight:bold;text-decoration:none;line-height:1}
.scroll-top.visible{opacity:1}

/* ── ABOUT US ────────────────────────────────────────────────────────────── */
#tab-about{background:var(--lgray);padding:70px 32px}
.about-hero{background:var(--navy);border-radius:14px;padding:48px 40px;margin-bottom:40px;display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}
.about-hero-text h2{color:white;font-size:24pt;margin-bottom:12px}
.about-hero-text h2 span{color:var(--gold)}
.about-hero-text p{color:rgba(255,255,255,.7);font-size:11pt;line-height:1.7}
.about-hero-stats{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.about-stat{background:rgba(255,255,255,.07);border:1px solid rgba(217,142,59,.3);border-radius:10px;padding:20px;text-align:center}
.about-stat .as-num{font-size:22pt;font-weight:bold;color:var(--gold)}
.about-stat .as-lbl{font-size:8.5pt;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;margin-top:4px}
.about-values{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:40px}
.about-value{background:white;border-radius:10px;padding:28px 22px;border-bottom:4px solid var(--gold);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.about-value .av-icon{font-size:26pt;margin-bottom:12px}
.about-value h3{color:var(--navy);font-size:12pt;margin-bottom:8px}
.about-value p{font-size:9pt;color:#666;line-height:1.6}
.about-team{background:white;border-radius:10px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:40px}
.about-team h3{color:var(--navy);font-size:14pt;border-bottom:2px solid var(--gold);padding-bottom:8px;margin-bottom:20px}
.about-team p{font-size:10pt;color:#555;line-height:1.8}
.about-cta{text-align:center;padding:36px;background:var(--navy);border-radius:12px}
.about-cta h3{color:white;font-size:16pt;margin-bottom:10px}
.about-cta p{color:rgba(255,255,255,.65);font-size:10pt;margin-bottom:22px}

/* ── GOOGLE MAP ──────────────────────────────────────────────────────────── */
.map-embed-wrap{border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12);margin-top:24px;border:3px solid var(--gold)}
.map-embed-wrap iframe{display:block;width:100%;height:380px;border:none}
.map-links{display:flex;gap:12px;margin-top:12px;flex-wrap:wrap}
.map-link-btn{display:inline-flex;align-items:center;gap:6px;background:var(--navy);color:white;padding:9px 18px;border-radius:6px;font-size:9pt;font-weight:bold;text-decoration:none;transition:background .2s}
.map-link-btn:hover{background:var(--gold);color:var(--navy)}

/* ── WIZARD ──────────────────────────────────────────────────────────────── */
.wizard-progress{display:flex;align-items:center;justify-content:center;gap:0;padding:18px 20px;background:var(--lgray);border-bottom:1px solid var(--mgray);overflow-x:auto;position:relative}
.wiz-fill-bar{position:absolute;bottom:0;left:0;height:3px;background:linear-gradient(90deg,var(--gold),#f0b45a);transition:width .4s cubic-bezier(.22,.61,.36,1);border-radius:0 2px 0 0}
.wp-step{display:flex;flex-direction:column;align-items:center;gap:6px;min-width:64px;position:relative}
.wp-circle{width:32px;height:32px;border-radius:50%;background:white;border:2px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:9pt;font-weight:bold;color:#999;transition:all .3s cubic-bezier(.22,.61,.36,1);flex-shrink:0}
.wp-label{font-size:7pt;color:#999;text-transform:uppercase;letter-spacing:.5px;text-align:center;white-space:nowrap}
.wp-line{height:2px;background:var(--mgray);flex:1;min-width:20px;margin:0 -2px;margin-bottom:18px;transition:background .2s}
.wp-step.done .wp-circle{background:var(--gold);border-color:var(--gold);color:var(--navy);box-shadow:0 0 0 3px rgba(217,142,59,.2)}
.wp-step.done .wp-label{color:var(--gold)}
.wp-step.active .wp-circle{background:var(--navy);border-color:var(--navy);color:white;box-shadow:0 0 0 5px rgba(22,62,51,.18)}
.wp-step.active .wp-label{color:var(--navy);font-weight:bold}
.wp-step.done + .wp-line{background:var(--gold)} .wp-step.active + .wp-line{background:var(--mgray)}
.wiz-panel{display:none}
.wiz-panel.active{display:block;animation:fadeIn .3s ease}
.wiz-nav{background:var(--lgray);border-top:2px solid var(--gold);padding:14px 28px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.wiz-nav .left-grp,.wiz-nav .right-grp{display:flex;gap:10px;align-items:center}
.field-counter{font-size:8.5pt;color:#888}
.field-counter.ok{color:#198754;font-weight:bold}
.btn-nav{border:none;padding:11px 26px;border-radius:5px;font-family:Arial,sans-serif;font-size:9.5pt;font-weight:bold;cursor:pointer;transition:all .2s}
.btn-nav-next{background:var(--navy);color:white}
.btn-nav-next:hover{background:#1f5645}
.btn-nav-back{background:transparent;border:1px solid var(--mgray);color:#666}
.btn-nav-back:hover{border-color:#999;color:#333}
.btn-nav[disabled]{opacity:.45;cursor:not-allowed}

/* Inline validation */
/* ── VALIDATION ERRORS ─────────────────────────────────────── */
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-5px)}40%{transform:translateX(5px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}
.f-group.error{animation:shake .4s ease}
.f-group.error label{color:#c0392b!important}
.f-group.error > input,
.f-group.error > select,
.f-group input.invalid,
.f-group select.invalid{border-color:#c0392b!important;background:rgba(192,57,43,.04);box-shadow:0 0 0 3px rgba(192,57,43,.12)!important}
.f-err{color:#c0392b;font-size:7.5pt;margin-top:4px;display:none;font-weight:600;padding:2px 0 0 2px}
.f-group.error .f-err{display:block}
/* Upload box error */
.upload-item.error{border-color:#c0392b!important;border-style:solid!important;background:rgba(192,57,43,.04)!important;animation:shake .4s ease}
.upload-item.error .upload-label{color:#c0392b!important}
/* Duration tiles error */
.dur-rate-row.error .dur-tile:not(.active){border-color:rgba(192,57,43,.5)!important;background:rgba(192,57,43,.04)!important}
/* Repay method cards error */
.method-grid.error .method-card:not(:has(input:checked)){border-color:rgba(192,57,43,.5)!important;background:rgba(192,57,43,.04)!important}
/* Disbursement cards error */
.method-grid.error-disburse .method-card:not(:has(input:checked)){border-color:rgba(192,57,43,.5)!important;background:rgba(192,57,43,.04)!important}
/* Sig box error */
.sig-box2.error .sigpad-wrap{border-color:#c0392b!important;border-style:solid!important;box-shadow:0 0 0 3px rgba(192,57,43,.12)!important}
.sig-box2.error .sig-title{color:#c0392b!important}
/* Error banner at top of step */
.step-error-banner{display:none;background:#fff0f0;border:1.5px solid #c0392b;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:8.5pt;color:#c0392b;font-weight:600;line-height:1.6}
.step-error-banner.visible{display:flex;align-items:flex-start;gap:8px}
.step-error-banner .seb-icon{font-size:13pt;flex-shrink:0;margin-top:1px}

/* Dynamic add buttons */
.add-row-btn{display:inline-flex;align-items:center;gap:6px;background:transparent;border:1.5px dashed var(--gold);color:var(--navy);font-weight:bold;font-size:8.5pt;padding:8px 16px;border-radius:6px;cursor:pointer;font-family:Arial,sans-serif;margin-top:6px;transition:all .2s}
.add-row-btn:hover{background:rgba(217,142,59,.08)}
.remove-row-btn{background:transparent;border:none;color:#c0392b;font-size:8pt;cursor:pointer;font-family:Arial,sans-serif;text-decoration:underline;padding:2px 6px}

/* Resume banner */
.resume-banner{background:rgba(217,142,59,.1);border:1.5px solid var(--gold);border-radius:8px;padding:14px 18px;margin:0 0 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.resume-banner p{font-size:9pt;color:var(--navy);margin:0}
.resume-banner .rb-btns{display:flex;gap:8px}
.resume-banner button{border:none;padding:7px 16px;border-radius:5px;font-size:8.5pt;font-weight:bold;cursor:pointer;font-family:Arial,sans-serif}
.rb-resume{background:var(--navy);color:white}
.rb-discard{background:transparent;border:1px solid var(--mgray)!important;color:#888}

/* Signature pad */
.sigpad-wrap{border:1.5px dashed var(--mgray);border-radius:6px;background:white;position:relative;touch-action:none}
.sigpad-wrap.signed{border-color:var(--gold);border-style:solid}
.sigpad-wrap canvas{display:block;width:100%;height:120px;cursor:crosshair;touch-action:none}
.sigpad-placeholder{position:absolute;top:50%;left:0;right:0;transform:translateY(-50%);text-align:center;font-size:8pt;color:#bbb;pointer-events:none;font-style:italic}
.sigpad-wrap.signed .sigpad-placeholder{display:none}
.sigpad-clear{font-size:7.5pt;color:var(--gold);background:none;border:none;cursor:pointer;font-family:Arial,sans-serif;text-decoration:underline;margin-top:4px;padding:0}
.check-pills{display:flex;gap:8px;flex-wrap:wrap}
.check-pill{display:flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px solid var(--mgray);border-radius:20px;cursor:pointer;font-size:9pt;background:white;transition:all .18s ease;user-select:none}
.check-pill:hover{border-color:var(--gold);background:rgba(217,142,59,.06)}
.check-pill input[type=checkbox]{accent-color:var(--navy);width:15px;height:15px;cursor:pointer}
.check-pill:has(input:checked){border-color:var(--navy);background:var(--navy);color:white}
.sigpad-btnrow{display:flex;gap:14px;margin-top:6px;flex-wrap:wrap}
.sigpad-btnrow .sigpad-clear{margin-top:0}
.sigpad-btnrow .sigpad-clear:disabled{color:#ccc;cursor:not-allowed;text-decoration:none}
.sigpad-btnrow .sigpad-clear:not(:disabled):hover{color:var(--navy)}
.sig-box2.lender .sigpad-wrap{background:rgba(255,255,255,.05);border-color:rgba(217,142,59,.4)}
.sig-box2.lender canvas{pointer-events:none;cursor:default}
.sig-box2.error .sigpad-wrap{border-color:#c0392b!important;border-style:solid!important}
.sig-box2.error .sig-title{color:#c0392b!important}

/* Review screen */
.review-section{margin-bottom:22px}
.review-section h4{color:var(--navy);font-size:10pt;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--gold);padding-bottom:6px;margin-bottom:10px}
.review-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 20px}
.review-row{display:flex;flex-direction:column;font-size:9pt;padding:4px 0;border-bottom:1px solid var(--lgray)}
.review-row .rv-lbl{font-size:7.5pt;color:#999;text-transform:uppercase;letter-spacing:.5px}
.review-row .rv-val{color:var(--navy);font-weight:bold;word-break:break-word}
.review-row .rv-val.empty{color:#bbb;font-weight:normal;font-style:italic}
.review-edit{font-size:8pt;color:var(--gold);background:none;border:none;cursor:pointer;font-family:Arial,sans-serif;text-decoration:underline;float:right}
.review-doc-thumb{display:inline-flex;align-items:center;gap:6px;background:var(--lgray);border-radius:6px;padding:6px 12px;font-size:8.5pt;color:var(--navy);margin:4px 6px 4px 0}
.review-doc-thumb img{width:32px;height:32px;object-fit:cover;border-radius:4px}

@media(max-width:820px){
  .wizard-progress{padding:14px 10px}
  .wp-step{min-width:48px}
  .wp-label{font-size:6.5pt}
  .wp-line{min-width:10px}
  .review-grid{grid-template-columns:1fr}
}


/* === STAGE 6: lending clarity and commitment ============================== */
.loan-disclosure{max-width:780px;margin:22px auto 0;padding:14px 16px;border:1px solid rgba(22,62,51,.16);border-left:4px solid var(--gold);border-radius:10px;background:#fff;display:flex;gap:10px;align-items:flex-start;font-size:9pt;line-height:1.55;color:#4b5563}
.loan-disclosure strong{color:var(--navy);white-space:nowrap}.loan-disclosure span{flex:1}
.loan-start-card{margin:18px 28px 0;padding:18px;border:1px solid rgba(22,62,51,.14);border-radius:12px;background:linear-gradient(135deg,#fff,#f8faf9);box-shadow:0 8px 24px rgba(22,62,51,.06)}
.loan-start-card h3{margin:3px 0 5px;color:var(--navy);font-size:14pt}.loan-start-card p{margin:0;color:#667085;font-size:9pt}.lsc-kicker{font-size:7.5pt;text-transform:uppercase;letter-spacing:.12em;font-weight:800;color:#a86521}
.lsc-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:14px}.lsc-grid span{padding:9px 10px;border-radius:8px;background:#fff;border:1px solid #e7ece9;color:#344054;font-size:8.5pt;font-weight:600}
.commitment-card{margin:14px 0;padding:14px 16px;border-radius:10px;background:#163e33;color:#fff;display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.commitment-card .cc-label{font-size:7.5pt;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.65)}.commitment-card .cc-value{font-family:Georgia,serif;font-size:14pt;font-weight:700;color:#f2b763;margin-top:3px}
@media(max-width:760px){.lsc-grid{grid-template-columns:1fr 1fr}.loan-start-card{margin:14px 14px 0}.loan-disclosure{margin-left:0;margin-right:0;flex-direction:column}.commitment-card{grid-template-columns:1fr}.loan-disclosure strong{white-space:normal}}
@media(max-width:420px){.lsc-grid{grid-template-columns:1fr}}

/* ── GPS CONSENT (Apply form) ────────────────────────────────────────────── */
.gps-consent-box{background:rgba(217,142,59,.07);border:1.5px solid var(--gold);border-radius:8px;padding:16px 18px;margin-top:16px;display:flex;gap:14px;align-items:flex-start}
.gps-icon{font-size:20pt;min-width:32px;margin-top:2px}
.gps-text h4{color:var(--navy);font-size:9.5pt;font-weight:bold;margin-bottom:4px}
.gps-text p{font-size:8.5pt;color:#666;line-height:1.5;margin-bottom:10px}
.gps-btn{background:var(--navy);color:white;border:none;border-radius:5px;padding:8px 16px;font-size:8.5pt;font-weight:bold;cursor:pointer;font-family:Arial,sans-serif;transition:background .2s}
.gps-btn:hover{background:var(--gold);color:var(--navy)}
.gps-btn.captured{background:#198754}
.gps-coords{font-size:8pt;color:var(--navy);font-weight:bold;margin-top:6px;display:none}

/* ── PRINT ───────────────────────────────────────────────────────────────── */
@media print{
  nav,footer,.wa-float,.scroll-top,.nav-mobile,.print-bar2,.wiz-nav,.wizard-progress,.resume-banner,.home-cards,.rate-preview,.hero,.add-row-btn,.remove-row-btn,#submit-status{display:none!important}
  #tab-apply{display:block!important;padding:0;background:white}
  .tab-panel{display:none!important}
  #tab-apply{display:block!important}
  .wiz-panel{display:block!important}
  .form-card{box-shadow:none;border-radius:0}
  body{background:white}
}

/* ── RESPONSIVE ──────────────────────────────────────────────────────────── */
@media(max-width:1100px){
  .cards-grid{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:820px){
  nav{padding:0 16px}
  .nav-tabs{display:none}
  .nav-toggle{display:flex}
  .hero h1{font-size:26pt}
  .calc-wrap,.sig-row2,.ref-grid2,.f-grid{grid-template-columns:1fr}
  .about-hero{grid-template-columns:1fr;padding:28px 20px}
  .about-hero-stats{grid-template-columns:1fr 1fr}
  .contact-grid{grid-template-columns:1fr}
  .office-use .ou-body{grid-template-columns:1fr}
  .page-section,#tab-apply,#tab-calc,#tab-contact{padding:50px 16px}
  .form-body{padding:16px}
  footer{text-align:center}
  .footer-top,.footer-bottom{flex-direction:column;align-items:center;text-align:center}
  .footer-links{justify-content:center}
  .collateral-item{grid-template-columns:auto 1fr}
  .rate-tiers{grid-template-columns:repeat(2,1fr)}
  .dur-rate-row{grid-template-columns:repeat(2,1fr)}
  #dur-tile-custom{grid-column:span 2}
  .method-grid{grid-template-columns:repeat(2,1fr)}
  .cards-grid{grid-template-columns:1fr 1fr}
  .rate-pills{gap:10px}
  .rate-pill{min-width:100px;padding:14px 16px}
}
@media(max-width:480px){
  .hero{padding:40px 16px 32px}
  .hero-stats{gap:20px}
  .cards-grid{grid-template-columns:1fr}
}
</style>

<!-- ═══ CURRENCY CONVERTER STYLES ════════════════════════════════════════ -->
<style>
.fxc{max-width:880px;margin:0 auto;background:var(--white);border-radius:var(--radius);
  box-shadow:var(--shadow);padding:26px 28px 22px;border-top:3px solid var(--gold)}
.fxc-grid{display:grid;gap:14px;grid-template-columns:1fr;align-items:end}
@media(min-width:720px){.fxc-grid{grid-template-columns:1.3fr 1fr auto 1fr}}
.fxc-field label{display:block;font-size:8.5pt;font-weight:700;letter-spacing:.6px;
  text-transform:uppercase;color:var(--navy);opacity:.65;margin-bottom:6px}
.fxc-field input,.fxc-field select{width:100%;padding:11px 12px;border:1.5px solid var(--mgray);
  border-radius:7px;font-size:11pt;color:var(--navy);background:var(--white);font-family:inherit}
.fxc-field input{font-weight:700;font-size:13pt}
.fxc-field input:focus,.fxc-field select:focus{outline:none;border-color:var(--gold);
  box-shadow:0 0 0 3px rgba(217,142,59,.15)}
.fxc-swap{width:40px;height:40px;border-radius:50%;border:1.5px solid var(--mgray);
  background:var(--lgray);color:var(--navy);font-size:15pt;line-height:1;cursor:pointer;
  transition:.2s;flex:0 0 40px;margin:0 auto}
.fxc-swap:hover{border-color:var(--gold);color:var(--gold);background:rgba(217,142,59,.08)}
@media(max-width:719px){.fxc-swap{transform:rotate(90deg)}}
.fxc-chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}
.fxc-chips button{font-size:9pt;font-weight:600;color:var(--navy);background:var(--lgray);
  border:1px solid var(--mgray);border-radius:999px;padding:6px 13px;cursor:pointer;font-family:inherit}
.fxc-chips button:hover{border-color:var(--gold);color:var(--gold)}
.fxc-result{margin-top:20px;background:var(--navy);border-radius:8px;padding:18px 20px;color:var(--white)}
.fxc-res-main{display:flex;align-items:baseline;justify-content:space-between;gap:14px;flex-wrap:wrap}
.fxc-res-lbl{font-size:8.5pt;font-weight:700;letter-spacing:.8px;text-transform:uppercase;opacity:.6}
.fxc-res-val{font-size:22pt;font-weight:800;color:var(--gold-light);letter-spacing:-.5px;
  overflow-wrap:anywhere}
.fxc-res-pair{margin-top:8px;padding-top:10px;border-top:1px solid rgba(255,255,255,.13);
  font-size:9.5pt;opacity:.75}
.fxc-margin{margin-top:22px}
.fxc-margin>label{display:block;font-size:8.5pt;font-weight:700;letter-spacing:.6px;
  text-transform:uppercase;color:var(--navy);opacity:.65;margin-bottom:10px}
.fxc-margin>label b{color:var(--gold);opacity:1}
.fxc-margin input[type=range]{-webkit-appearance:none;appearance:none;width:100%;height:3px;
  background:var(--mgray);border-radius:3px;margin:0 0 14px;cursor:pointer}
.fxc-margin input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:20px;height:20px;
  border-radius:50%;background:var(--gold);border:3px solid var(--white);
  box-shadow:0 1px 4px rgba(0,0,0,.25);cursor:pointer}
.fxc-margin input[type=range]::-moz-range-thumb{width:20px;height:20px;border-radius:50%;
  background:var(--gold);border:3px solid var(--white);box-shadow:0 1px 4px rgba(0,0,0,.25);cursor:pointer}
.fxc-track{position:relative;height:30px;border-radius:6px;overflow:hidden;
  background:var(--lgray);border:1px solid var(--mgray)}
.fxc-fill{position:absolute;top:0;bottom:0;left:0;
  background:linear-gradient(90deg,var(--gold),var(--gold-light));transition:width .3s ease}
.fxc-gap{position:absolute;top:0;bottom:0;right:0;border-left:2px solid #C0392B;
  background:repeating-linear-gradient(45deg,rgba(192,57,43,.28) 0 5px,transparent 5px 10px);
  transition:width .3s ease}
.fxc-legend{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:9px;
  font-size:9pt;color:var(--dgray)}
.fxc-legend b{color:#C0392B}
.fxc-foot{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:20px;
  padding-top:16px;border-top:1px solid var(--mgray)}
.fxc-refresh{font-size:8.5pt;font-weight:700;letter-spacing:.6px;text-transform:uppercase;
  color:var(--navy);background:var(--lgray);border:1.5px solid var(--mgray);border-radius:6px;
  padding:9px 15px;cursor:pointer;font-family:inherit}
.fxc-refresh:hover:not(:disabled){border-color:var(--gold);color:var(--gold)}
.fxc-refresh:disabled{opacity:.5;cursor:default}
.fxc-stamp{font-size:8.5pt;color:var(--dgray);flex:1;min-width:180px}
.fxc-stamp.good{color:#1E7A4C}.fxc-stamp.bad{color:#C0392B}
.fxc-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--mgray);
  margin-right:7px;vertical-align:middle}
.fxc-stamp.good .fxc-dot{background:#1E7A4C}
.fxc-stamp.bad .fxc-dot{background:#C0392B}
.fxc-dot.busy,.fxc-dot.warn{background:var(--gold)}
@keyframes fxcPulse{0%,100%{opacity:.3}50%{opacity:1}}
.fxc-dot.busy{animation:fxcPulse 1s infinite}
.fxc-disclaimer{margin-top:12px;font-size:8.5pt;color:var(--dgray);opacity:.8;line-height:1.5}
@media(max-width:600px){.fxc{padding:20px 18px 18px}.fxc-res-val{font-size:18pt}}
@media(prefers-reduced-motion:reduce){.fxc *{transition:none!important;animation:none!important}}
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

<!-- ═══ AVESTA — PUBLIC SITE REFINEMENT ════════════════════════════════ -->
<style id="av-site-polish">
/* ── Type: no webfonts. Avesta's customers are on mobile data across Zambia;
      a render-blocking font is a worse experience than a well-set stack. ── */
body{
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;
  text-rendering:optimizeLegibility;
}
h1,h2,h3,.section-title{letter-spacing:-.022em}

/* ── Navigation ─────────────────────────────────────────────────────── */
.nav-tab{
  position:relative;border-radius:var(--av-r-sm);
  transition:color var(--av-fast),background var(--av-fast);
}
.nav-tab::after{
  content:"";position:absolute;left:50%;right:50%;bottom:-1px;height:2.5px;
  background:var(--av-gold);border-radius:2px;opacity:0;
  transition:left var(--av-mid) var(--av-ease),right var(--av-mid) var(--av-ease),
             opacity var(--av-fast);
}
.nav-tab.active::after{left:12%;right:12%;opacity:1}
.nav-tab:hover:not(.active){background:rgba(217,142,59,.09)}
.nav-tab-apply{box-shadow:var(--av-e1)}
.nav-tab-apply:hover{box-shadow:var(--av-e2);filter:brightness(1.06)}
.nav-mobile button{
  transition:background var(--av-fast),padding-left var(--av-fast) var(--av-ease);
  min-height:48px;
}
.nav-mobile button:hover,.nav-mobile button:active{
  background:rgba(217,142,59,.10);padding-left:30px;
}

/* ── Tab panels: settle in rather than snap ─────────────────────────── */
.tab-panel.active{animation:avRise var(--av-slow) var(--av-ease) both}

/* ── Section headers ────────────────────────────────────────────────── */
.section-tag{
  display:inline-flex;align-items:center;gap:8px;
  letter-spacing:.16em;text-transform:uppercase;
}
.section-tag::before{
  content:"";width:22px;height:1.5px;background:var(--av-gold);display:inline-block;
}
.section-sub{max-width:640px;margin-left:auto;margin-right:auto;line-height:1.7}

/* ── Cards: one light source, lift on hover ─────────────────────────── */
.feat,.method-card,.nc-card,.step-card,.calc-form,.calc-result,.form-card{
  box-shadow:var(--av-e1);
  transition:box-shadow var(--av-mid) var(--av-ease),transform var(--av-mid) var(--av-ease),
             border-color var(--av-fast);
}
.feat:hover,.method-card:hover,.nc-card:hover,.step-card:hover{
  box-shadow:var(--av-e3);transform:translateY(-3px);
}
.form-card{box-shadow:var(--av-e2)}

/* ── Rate tiles: the terms are the product, so make picking one feel
      like a decision, not a click. ──────────────────────────────────── */
.rate-tier{
  position:relative;overflow:hidden;cursor:pointer;
  box-shadow:var(--av-e1);
  transition:transform var(--av-mid) var(--av-ease),box-shadow var(--av-mid) var(--av-ease),
             border-color var(--av-fast),background var(--av-fast);
}
.rate-tier::after{
  content:"";position:absolute;left:0;right:0;top:0;height:3px;
  background:linear-gradient(90deg,var(--av-gold),var(--av-gold-deep));
  transform:scaleX(0);transform-origin:left;
  transition:transform var(--av-mid) var(--av-ease);
}
.rate-tier:hover{transform:translateY(-4px);box-shadow:var(--av-e3)}
.rate-tier:hover::after,.rate-tier.active::after{transform:scaleX(1)}
.rate-tier.active{box-shadow:var(--av-e3),0 0 0 2px var(--av-gold)}
.rt-rate{font-variant-numeric:tabular-nums;letter-spacing:-.03em}

/* ── Calculator ─────────────────────────────────────────────────────── */
.calc-form label{letter-spacing:.04em}
.calc-form input,.calc-form select{
  transition:border-color var(--av-fast),box-shadow var(--av-fast),background var(--av-fast);
}
.calc-form input:focus,.calc-form select:focus{
  border-color:var(--av-gold)!important;box-shadow:var(--av-ring);outline:none;
}
.calc-btn,.calc-apply-btn{box-shadow:var(--av-e1);letter-spacing:.03em}
.calc-btn:hover,.calc-apply-btn:hover{box-shadow:var(--av-e2);filter:brightness(1.08)}
.result-row{
  transition:background var(--av-fast);
  border-bottom:1px solid rgba(255,255,255,.09);
}
.result-row:hover{background:rgba(255,255,255,.05)}
.r-val{letter-spacing:-.01em}
/* The signature seal — total repayable is the commitment. */
.result-row.highlight{background:transparent!important;padding-top:18px;padding-bottom:18px}
.result-row.highlight .r-val{position:relative;display:inline-block}
.result-row.highlight .r-val::before{
  content:"";position:absolute;inset:-8px -13px;border-radius:var(--av-r-sm);
  border:1.5px solid rgba(217,142,59,.45);
  background:linear-gradient(180deg,rgba(217,142,59,.13),rgba(217,142,59,.04));
  pointer-events:none;
}
.result-row.highlight .r-val::after{
  content:"";position:absolute;inset:-4px -9px;border-radius:4px;
  border:1px dashed rgba(217,142,59,.36);pointer-events:none;
}

/* ── Currency converter ─────────────────────────────────────────────── */
.fxc{box-shadow:var(--av-e2)}
.fxc-field input,.fxc-field select{transition:border-color var(--av-fast),box-shadow var(--av-fast)}
.fxc-swap{transition:transform var(--av-mid) var(--av-spring),border-color var(--av-fast),
                     background var(--av-fast),color var(--av-fast)}
.fxc-swap:hover{transform:rotate(180deg)}
@media(max-width:719px){.fxc-swap:hover{transform:rotate(270deg)}}
.fxc-result{
  box-shadow:inset 0 1px 0 rgba(255,255,255,.07);
  background:linear-gradient(150deg,#1B4A3C,var(--av-navy) 62%);
}
.fxc-res-val{position:relative;display:inline-block;letter-spacing:-.02em}
.fxc-res-val::before{
  content:"";position:absolute;inset:-7px -12px;border-radius:var(--av-r-sm);
  border:1.5px solid rgba(232,174,110,.34);
  background:rgba(232,174,110,.07);pointer-events:none;
}
.fxc-chips button{transition:transform var(--av-fast) var(--av-ease),border-color var(--av-fast),
                             color var(--av-fast),background var(--av-fast)}
.fxc-chips button:hover{transform:translateY(-1px);background:var(--av-gold-wash)}
.fxc-fill{background:linear-gradient(90deg,var(--av-gold-deep),var(--av-gold))}
.fxc-refresh:hover{background:var(--av-gold-wash)}

/* ── Form fields ────────────────────────────────────────────────────── */
.f-group input,.f-group select,.f-group textarea{
  transition:border-color var(--av-fast),box-shadow var(--av-fast),background var(--av-fast);
}
.f-group input:focus,.f-group select:focus,.f-group textarea:focus{
  border-color:var(--av-gold)!important;box-shadow:var(--av-ring);outline:none;
}
.f-group input:not(:placeholder-shown):not(.invalid):not([readonly]){
  border-color:#CFD9D4;
}
.f-group.error input,.f-group input.invalid{
  border-color:var(--av-danger)!important;background:var(--av-danger-wash);
  animation:avShake 380ms var(--av-ease);
}
@keyframes avShake{
  10%,90%{transform:translateX(-1.5px)} 20%,80%{transform:translateX(3px)}
  30%,50%,70%{transform:translateX(-5px)} 40%,60%{transform:translateX(5px)}
}
.f-err{transition:opacity var(--av-fast),max-height var(--av-mid) var(--av-ease)}
.f-section{
  border-radius:var(--av-r)!important;
  transition:box-shadow var(--av-mid) var(--av-ease);
}
.f-section:focus-within{box-shadow:var(--av-e2)}
.f-head{letter-spacing:.05em}

/* ── Method cards (loan type choice) ────────────────────────────────── */
.method-card{
  position:relative;cursor:pointer;overflow:hidden;
}
.method-card::after{
  content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;
  box-shadow:inset 0 0 0 0 var(--av-gold);
  transition:box-shadow var(--av-mid) var(--av-ease);
}
.method-card.selected::after,.method-card.active::after{box-shadow:inset 0 0 0 2px var(--av-gold)}
.mc-icon{transition:transform var(--av-mid) var(--av-spring)}
.method-card:hover .mc-icon{transform:scale(1.12) rotate(-4deg)}

/* ── Upload tiles ───────────────────────────────────────────────────── */
.upload-item{
  position:relative;border-radius:var(--av-r)!important;
  transition:border-color var(--av-fast),background var(--av-fast),
             transform var(--av-fast) var(--av-ease),box-shadow var(--av-mid);
}
.upload-item:hover{transform:translateY(-2px);box-shadow:var(--av-e2);border-color:var(--av-gold)}
.upload-item.has-file,.upload-item.uploaded{
  border-color:var(--av-ok)!important;background:var(--av-ok-wash)!important;
}
.upload-item.has-file .upload-icon::after,.upload-item.uploaded .upload-icon::after{
  content:"\2713";position:absolute;margin-left:-6px;margin-top:-4px;
  background:var(--av-ok);color:#fff;width:16px;height:16px;border-radius:50%;
  font-size:10px;display:grid;place-items:center;font-weight:800;
}
.upload-icon{transition:transform var(--av-mid) var(--av-spring)}
.upload-item:hover .upload-icon{transform:translateY(-2px) scale(1.08)}
.upload-filename{word-break:break-all}

/* ── Wizard progress ────────────────────────────────────────────────── */
.wiz-fill-bar{
  transition:width var(--av-slow) var(--av-ease)!important;
  background:linear-gradient(90deg,var(--av-gold-deep),var(--av-gold))!important;
}
.wp-circle{
  transition:background var(--av-mid) var(--av-ease),color var(--av-mid),
             transform var(--av-mid) var(--av-spring),box-shadow var(--av-mid);
}
.wp-step.active .wp-circle{transform:scale(1.14);box-shadow:0 0 0 4px rgba(217,142,59,.2)}
.wp-step.done .wp-circle{background:var(--av-ok)!important;color:#fff!important}
.wiz-panel.active{animation:avRise var(--av-mid) var(--av-ease) both}

/* ── Review screen ──────────────────────────────────────────────────── */
.rv-grid{border-radius:var(--av-r)}
.rv-lbl{letter-spacing:.05em}
.rv-val{font-weight:600}

/* ── Signature pads ─────────────────────────────────────────────────── */
/* These targeted .sigwrap, but the element's class is .sigpad-wrap — so the
   green "signed" confirmation and the focus ring never appeared on any
   signature pad. The id is sigwrap_borrower; the class never was. */
.sig-field2,.sigpad-wrap{transition:border-color var(--av-fast),box-shadow var(--av-mid)}
.sigpad-wrap.signed{border-color:var(--av-ok)!important}
.sigpad-wrap:focus-within{box-shadow:var(--av-ring)}

/* ── Resume banner ──────────────────────────────────────────────────── */
.resume-banner{
  border-radius:var(--av-r)!important;box-shadow:var(--av-e1);
  animation:avRise var(--av-slow) var(--av-ease) both;
}

/* ── Mobile ─────────────────────────────────────────────────────────── */
@media(max-width:760px){
  .nav-mobile button,.nav-tab{min-height:46px}
  .f-group input,.f-group select,.calc-form input,.calc-form select{
    font-size:16px;   /* stops iOS zooming the page on focus */
    padding-top:12px;padding-bottom:12px;
  }
  .rate-tier{padding:14px 10px}
  .calc-apply-btn,.calc-btn{padding:15px;font-size:11pt}
  body{padding-bottom:env(safe-area-inset-bottom)}
  .section-title{line-height:1.15}
}
@media(max-width:420px){
  .fxc-res-val{font-size:17pt}
  .rt-rate{font-size:15pt}
}

/* Print: the agreement is a document first */
@media print{
  .nav-tabs,.nav-mobile,.nav-toggle,#av-toasts,.av-dlg-back,
  .calc-apply-btn,.fxc-refresh{display:none!important}
  .tab-panel{display:block!important}
  .form-card{box-shadow:none;border:1px solid #999}
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
/* === HOW IT WORKS ILLUSTRATION ==========================================
   The white background is cut out so the graphic sits on the section's own
   surface rather than carrying a white box across it. Served as webp: the
   same picture is 75 KB instead of 275 KB, which matters on mobile data.
   Decorative only, so it is hidden from screen readers.
========================================================================= */
.how-illo{max-width:560px;margin:4px auto 30px;padding:0 10px}
.how-illo img{width:100%;height:auto;display:block}
@media(max-width:600px){ .how-illo{max-width:400px;margin-bottom:22px} }
</style>
<style>
/* === IT SERVICES ======================================================== */
.svc-hero{background:linear-gradient(135deg,var(--navy) 50%,#1a4d38 100%);
  color:#fff;padding:44px 26px 40px;text-align:center;position:relative;overflow:hidden}
.svc-hero::after{content:'';position:absolute;bottom:-90px;left:-70px;width:340px;height:340px;
  background:radial-gradient(circle,rgba(91,155,255,.18) 0%,transparent 70%);border-radius:50%}
.svc-hero > *{position:relative;z-index:1}
.svc-kicker{font-size:8.5pt;font-weight:800;letter-spacing:2px;text-transform:uppercase;
  color:var(--accent);margin-bottom:10px}
.svc-hero h2{font-size:26pt;font-weight:800;letter-spacing:-.6px;margin:0 0 10px;line-height:1.08}
.svc-hero h2 em{font-style:normal;color:var(--accent)}
.svc-hero p{max-width:600px;margin:0 auto;font-size:11pt;color:rgba(255,255,255,.82);line-height:1.65}

.svc-grid{display:grid;gap:14px;grid-template-columns:1fr;max-width:1000px;
  margin:0 auto;padding:34px 20px 10px}
@media(min-width:620px){.svc-grid{grid-template-columns:1fr 1fr}}
@media(min-width:960px){.svc-grid{grid-template-columns:repeat(3,1fr)}}
.svc{background:var(--white);border:1px solid var(--mgray);border-radius:12px;
  padding:20px 18px;display:flex;gap:14px;align-items:flex-start;
  transition:border-color .18s ease, transform .18s ease, box-shadow .18s ease}
.svc:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 8px 22px rgba(22,62,51,.09)}
.svc-ico{flex:0 0 42px;width:42px;height:42px;border-radius:10px;
  background:linear-gradient(145deg,rgba(91,155,255,.18),rgba(91,155,255,.07));
  display:grid;place-items:center;color:var(--accent-deep)}
.svc-ico svg{width:21px;height:21px;stroke:currentColor;fill:none;
  stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.svc h3{font-size:11pt;font-weight:700;color:var(--navy);margin:0 0 4px;line-height:1.3}
.svc p{margin:0;font-size:9.5pt;color:var(--dgray);line-height:1.55}

.svc-cta{max-width:1000px;margin:26px auto 0;padding:0 20px 44px}
.svc-cta-inner{background:var(--navy);border-radius:14px;padding:28px 24px;text-align:center;
  border-top:3px solid var(--accent)}
.svc-cta h3{color:#fff;font-size:15pt;font-weight:800;margin:0 0 6px}
.svc-cta p{color:rgba(255,255,255,.72);font-size:10pt;margin:0 0 20px;line-height:1.6}
.svc-lines{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
.svc-lines a{display:inline-flex;align-items:center;gap:8px;text-decoration:none;
  font-size:10pt;font-weight:700;padding:12px 20px;border-radius:9px;
  background:var(--accent);color:var(--navy);transition:background .16s}
.svc-lines a:hover{background:var(--accent-light)}
.svc-lines a.ghost{background:transparent;color:var(--accent);border:1.5px solid rgba(91,155,255,.5)}
.svc-lines a.ghost:hover{background:rgba(91,155,255,.14)}
@media(max-width:600px){
  .svc-hero{padding:34px 20px 32px}
  .svc-hero h2{font-size:20pt}
  .svc-lines a{width:100%;justify-content:center}
}
@media(prefers-reduced-motion:reduce){.svc{transition:none}.svc:hover{transform:none}}
</style>
<style>
/* === THE TWO ARMS ON THE HOME PAGE =====================================
   Equal weight on purpose. Making lending the headline and IT a footnote
   is what sends an IT enquiry away; so is the reverse. Each card carries
   its own arm colour, which is the same colour that section uses, so the
   association is learned in one glance.
======================================================================== */
.arms{display:grid;gap:14px;grid-template-columns:1fr;max-width:820px;
  margin:30px auto 8px;text-align:left}
@media(min-width:720px){.arms{grid-template-columns:1fr 1fr}}
.arm{display:flex;flex-direction:column;gap:6px;padding:22px 20px;cursor:pointer;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.14);
  border-radius:14px;font-family:inherit;text-align:left;
  transition:background .2s ease, border-color .2s ease, transform .2s ease}
.arm:hover{background:rgba(255,255,255,.09);transform:translateY(-3px)}
.arm:focus-visible{outline:2px solid var(--white);outline-offset:3px}
.arm-lend{border-top:3px solid var(--gold)}
.arm-lend:hover{border-color:rgba(217,142,59,.55)}
.arm-tag{font-size:7.5pt;font-weight:800;letter-spacing:1.4px;text-transform:uppercase}
.arm-lend .arm-tag{color:var(--gold)}
.arm-title{font-size:14pt;font-weight:800;color:#fff;letter-spacing:-.3px;line-height:1.2}
.arm-sub{font-size:9.5pt;color:rgba(255,255,255,.72);line-height:1.55}
.arm-go{margin-top:6px;font-size:9.5pt;font-weight:700}
.arm-lend .arm-go{color:var(--gold)}
@media(max-width:600px){.arm{padding:18px 16px}.arm-title{font-size:12.5pt}}
@media(prefers-reduced-motion:reduce){.arm{transition:none}.arm:hover{transform:none}}
</style>
<style>
/* Brand line: the parent name leads, the two arms sit beneath it. Anyone
   arriving from an IT flyer sees straight away that they are in the right
   place, without the lending side being demoted. */
.logo-sub{display:block;font-size:7.5pt;font-weight:600;letter-spacing:.6px;
  text-transform:uppercase;color:var(--gold);opacity:.85;margin-top:1px;line-height:1.2}
@media(max-width:640px){.logo-sub{font-size:6.5pt;letter-spacing:.4px}}
@media(max-width:380px){.logo-sub{display:none}}
</style>
<style>
/* With the offices separated, the home hero pitches lending only. The other
   office gets one honest line rather than a competing card — the front step
   at index.php is where the real choice is made. */
.arms-single{max-width:460px}
.cross-sell{margin:16px auto 0;max-width:560px;font-size:9.5pt;
  color:rgba(255,255,255,.6);text-align:center}
.cross-sell a{color:var(--tech);font-weight:700;text-decoration:none;white-space:nowrap}
.cross-sell a.cross-back{color:rgba(255,255,255,.5);font-weight:600;margin-left:10px}
.cross-sell a:hover{text-decoration:underline}
</style>
<style>
/* === IT ENQUIRY ======================================================== */
.enq{max-width:1000px;margin:0 auto;padding:0 20px 10px}
.enq-inner{background:var(--white);border:1px solid var(--mgray);border-radius:14px;
  padding:26px 22px;border-top:3px solid var(--accent)}
.enq-inner h3{font-size:14pt;font-weight:800;color:var(--navy);margin:0 0 4px}
.enq-inner > p{margin:0 0 20px;font-size:10pt;color:var(--dgray);line-height:1.6}
.enq-grid{display:grid;gap:14px;grid-template-columns:1fr}
@media(min-width:640px){.enq-grid{grid-template-columns:1fr 1fr}.enq-wide{grid-column:1/-1}}
.enq-grid label{display:block;font-size:8pt;font-weight:700;letter-spacing:.7px;
  text-transform:uppercase;color:var(--dgray);margin-bottom:6px}
.enq-grid input,.enq-grid select,.enq-grid textarea{width:100%;padding:11px 12px;
  border:1.5px solid var(--mgray);border-radius:8px;font-size:10.5pt;font-family:inherit;
  color:var(--navy);background:var(--white)}
.enq-grid textarea{resize:vertical;line-height:1.55}
.enq-grid input:focus,.enq-grid select:focus,.enq-grid textarea:focus{
  outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(91,155,255,.16)}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.enq-foot{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:18px}
.enq-send{background:var(--navy);color:var(--accent-light);border:none;border-radius:9px;
  padding:13px 24px;font-size:10pt;font-weight:700;letter-spacing:.3px;cursor:pointer;
  font-family:inherit}
.enq-send:hover:not(:disabled){background:#1F5344}
.enq-send:disabled{opacity:.55;cursor:default}
.enq-msg{font-size:9.5pt;line-height:1.5;flex:1;min-width:180px;color:var(--dgray)}
.enq-msg.bad{color:#C0392B}
.enq-msg.good{color:#1E7A4C}
</style>
<style>
/* === SUB-NAVIGATION =====================================================
   Each service is one main tab; its sections sit on a second row beneath.
   Two shallow levels rather than one long strip of nine tabs: a visitor sees
   only the sections belonging to the service they came for.
   Scrolls sideways on a phone instead of wrapping into three cramped rows.
========================================================================= */
.subnav{background:var(--white);border-bottom:1px solid var(--mgray);
  position:sticky;top:60px;z-index:80;box-shadow:0 1px 6px rgba(22,62,51,.05)}
.subnav-inner{max-width:1100px;margin:0 auto;display:flex;gap:2px;
  overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding:0 12px}
.subnav-inner::-webkit-scrollbar{display:none}
.subnav button{flex:0 0 auto;background:none;border:none;font-family:inherit;
  font-size:9.5pt;font-weight:600;color:var(--dgray);cursor:pointer;
  padding:14px 15px 12px;border-bottom:3px solid transparent;white-space:nowrap;
  transition:color .15s,border-color .15s}
.subnav button:hover{color:var(--navy)}
.subnav button.active{color:var(--navy);font-weight:800;border-bottom-color:var(--accent)}
.subnav button:focus-visible{outline:2px solid var(--accent);outline-offset:-3px}
.subnav-label{flex:0 0 auto;display:flex;align-items:center;gap:7px;padding:0 14px 0 2px;
  font-size:7.5pt;font-weight:800;letter-spacing:1.1px;text-transform:uppercase;
  color:var(--accent-deep);border-right:1px solid var(--mgray);margin-right:8px}
@media(max-width:640px){
  .subnav{top:56px}
  .subnav-label{display:none}
  .subnav button{padding:12px 12px 10px;font-size:9pt}
}
@media(prefers-reduced-motion:reduce){.subnav button{transition:none}}
</style>
<style>
/* === BACK TO THE FRONT STEP =============================================
   Each office is a separate page, so the browser's own Back button works.
   But someone who arrived by a direct link has nothing to go back TO, and on
   a phone the browser chrome is often hidden. This is always there.
========================================================================= */
.office-back{display:inline-flex;align-items:center;gap:6px;flex:0 0 auto;
  background:rgba(217,142,59,.12);border:1px solid rgba(217,142,59,.32);
  color:var(--gold);border-radius:999px;padding:7px 13px 7px 10px;
  font-family:inherit;font-size:8.5pt;font-weight:700;letter-spacing:.3px;
  cursor:pointer;text-decoration:none;white-space:nowrap;margin-left:auto;
  transition:background .15s,border-color .15s}
.office-back:hover{background:rgba(217,142,59,.2);border-color:var(--gold)}
.office-back:focus-visible{outline:2px solid var(--gold);outline-offset:2px}
.office-back .ob-ico{font-size:10pt;line-height:1}
[data-arm="consulting"] .office-back,
html[data-site="it"] .office-back{
  background:rgba(91,155,255,.14);border-color:rgba(91,155,255,.35);color:var(--tech)}
html[data-site="it"] .office-back:hover{background:rgba(91,155,255,.22);border-color:var(--tech)}
@media(max-width:860px){.office-back .ob-text{display:none}
  .office-back{padding:7px 10px;border-radius:50%}}
@media(max-width:640px){.office-back{display:none}}  /* the mobile menu carries it */
.nav-mobile .mob-back{background:rgba(217,142,59,.12)!important;color:var(--gold)!important;
  font-weight:700!important;border-top:1px solid rgba(255,255,255,.12)!important}
</style>
<style>
/* Sign out sits beside "Switch service" but is deliberately quieter — one is
   a move between services, the other ends the session. They should not look
   like the same kind of action. */
.office-out{display:inline-flex;align-items:center;gap:6px;flex:0 0 auto;
  background:transparent;border:1px solid rgba(255,255,255,.18);
  color:rgba(255,255,255,.72);border-radius:999px;padding:7px 13px;
  font-family:inherit;font-size:8.5pt;font-weight:600;letter-spacing:.3px;
  text-decoration:none;white-space:nowrap;margin-left:8px;
  transition:background .15s,border-color .15s,color .15s}
.office-out:hover{background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.4)}
.office-out:focus-visible{outline:2px solid rgba(255,255,255,.7);outline-offset:2px}
@media(max-width:860px){.office-out .oo-text{display:none}
  .office-out{padding:7px 10px;border-radius:50%}}
@media(max-width:640px){.office-out{display:none}}
.nav-mobile .mob-out{color:rgba(255,255,255,.7)!important;font-weight:600!important;
  border-top:1px solid rgba(255,255,255,.12)!important}
</style>
<style>
/* Saying plainly what can be done remotely and what needs someone on site.
   A blanket "nationwide" would win an enquiry and lose the customer when it
   turns out nobody can reach them to fit a camera. */
.coverage{margin-top:14px;padding:14px 16px;background:var(--lgray);
  border-left:3px solid var(--accent);border-radius:8px;
  font-size:9.5pt;color:var(--dgray);line-height:1.65}
.coverage strong{color:var(--navy)}
</style>
<style>
/* === TROUBLESHOOTING ASSISTANT ==========================================
   Sits above the enquiry form on purpose: many people arrive with something
   they can fix in two minutes, and answering that earns more trust than
   taking their number. Whatever it cannot answer flows into the form below.
========================================================================= */
.ask{max-width:1000px;margin:0 auto;padding:0 20px 6px}
.ask-inner{background:var(--white);border:1px solid var(--mgray);border-radius:14px;
  padding:24px 22px;border-top:3px solid var(--accent)}
.ask-head{display:flex;gap:14px;align-items:flex-start;margin-bottom:16px}
.ask-badge{flex:0 0 38px;width:38px;height:38px;border-radius:10px;display:grid;place-items:center;
  background:linear-gradient(145deg,rgba(91,155,255,.18),rgba(91,155,255,.07));
  color:var(--accent-deep);font-size:16pt;font-weight:800}
.ask-head h3{font-size:13pt;font-weight:800;color:var(--navy);margin:0 0 3px}
.ask-head p{margin:0;font-size:9.5pt;color:var(--dgray);line-height:1.6}
.ask-log{display:none;flex-direction:column;gap:12px;margin-bottom:14px}
.ask-log.on{display:flex}
.ask-you{align-self:flex-end;max-width:85%;background:var(--navy);color:#fff;
  padding:10px 14px;border-radius:12px 12px 3px 12px;font-size:9.5pt;line-height:1.5}
.ask-me{align-self:flex-start;max-width:100%;background:var(--lgray);border-radius:12px 12px 12px 3px;
  padding:15px 16px;font-size:9.5pt;color:var(--dgray);line-height:1.6}
.ask-me h4{font-size:10.5pt;font-weight:800;color:var(--navy);margin:0 0 6px}
.ask-me ol{margin:10px 0 0;padding-left:20px}
.ask-me li{margin-bottom:7px;line-height:1.55}
.ask-stop{margin-top:12px;padding:10px 12px;background:var(--warn-wash,#FDF4E3);
  border-left:3px solid var(--warn,#946219);border-radius:7px;
  font-size:9pt;color:var(--warn,#946219);line-height:1.55}
.ask-stop strong{display:block;margin-bottom:2px}
.ask-hand{margin-top:12px;font-size:9pt}
.ask-hand button{background:none;border:none;color:var(--accent-deep);font-family:inherit;
  font-size:9pt;font-weight:700;cursor:pointer;padding:0;text-decoration:underline}
.ask-chips{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}
.ask-chips button{font-size:8.5pt;font-weight:600;color:var(--dgray);background:var(--lgray);
  border:1px solid var(--mgray);border-radius:999px;padding:7px 12px;cursor:pointer;font-family:inherit}
.ask-chips button:hover{border-color:var(--accent);color:var(--accent-deep)}
.ask-row{display:flex;gap:9px}
.ask-row input{flex:1;padding:12px 13px;border:1.5px solid var(--mgray);border-radius:9px;
  font-size:10.5pt;font-family:inherit;color:var(--navy);min-width:0}
.ask-row input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(91,155,255,.16)}
.ask-row button{background:var(--navy);color:var(--accent-light);border:none;border-radius:9px;
  padding:12px 20px;font-size:9.5pt;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}
.ask-row button:hover:not(:disabled){background:#1F5344}
.ask-row button:disabled{opacity:.55;cursor:default}
.ask-note{margin:12px 0 0;font-size:8.5pt;color:var(--dgray);opacity:.85;line-height:1.55}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;
  clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:600px){.ask-inner{padding:20px 16px}.ask-row{flex-direction:column}
  .ask-row button{width:100%}}
</style>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#163E33">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Avesta">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<link rel="icon" href="icons/icon-192.png" sizes="192x192">
<style>
/* Install button. Hidden until the browser says the app can be installed, so
   nobody is offered something their browser cannot do. */
.install-btn{display:inline-flex;align-items:center;gap:6px;flex:0 0 auto;
  background:rgba(91,155,255,.14);border:1px solid rgba(91,155,255,.35);
  color:var(--tech,#5B9BFF);border-radius:999px;padding:7px 13px;margin-left:8px;
  font-family:inherit;font-size:8.5pt;font-weight:700;cursor:pointer;white-space:nowrap;
  transition:background .15s,border-color .15s}
.install-btn:hover{background:rgba(91,155,255,.22);border-color:var(--tech,#5B9BFF)}
.install-btn:focus-visible{outline:2px solid var(--tech,#5B9BFF);outline-offset:2px}
@media(max-width:860px){.install-btn .ib-text{display:none}
  .install-btn{padding:7px 10px;border-radius:50%}}
@media(max-width:640px){.install-btn{display:none}}
</style>
<style>
/* The Money-Lenders Act expects a borrower to be able to read the terms
   before they are bound by them, so these pages sit outside the login. */
.legal-links{margin-top:14px;font-size:9pt;color:var(--dgray)}
.legal-links a{color:var(--accent-deep);font-weight:700;text-decoration:none}
.legal-links a:hover{text-decoration:underline}
</style>
<style>
/* What the assistant read from the question. Quiet, but there: if it has
   misread someone, this is where they notice before following the steps. */
.ask-read{margin:0 0 8px;font-size:8.5pt;color:var(--dgray);opacity:.85}
.ask-read strong{color:var(--accent-deep);font-weight:700}
.ask-fix{font-style:italic}
.ask-alts{display:flex;flex-wrap:wrap;gap:7px;align-items:center;margin-top:12px}
.ask-alts-lbl{font-size:8.5pt;font-weight:700;color:var(--dgray);margin-right:2px}
.ask-alts button{font-size:8.5pt;font-weight:600;color:var(--accent-deep);background:var(--white);
  border:1px solid var(--accent);border-radius:999px;padding:6px 11px;cursor:pointer;font-family:inherit}
.ask-alts button:hover{background:rgba(91,155,255,.1)}
.ask-lend{display:inline-block;margin-top:12px;font-weight:700;font-size:9.5pt;
  color:var(--gold-deep,#96591A);text-decoration:none}
.ask-lend:hover{text-decoration:underline}
</style>
<style>
/* A photo the customer can see is a photo they know worked. */
.upload-item .upload-thumb{display:block;width:100%;max-height:110px;object-fit:cover;
  border-radius:8px;margin-top:8px;border:1px solid rgba(0,0,0,.08)}
.upload-item.is-working{opacity:.75}
.upload-item.is-working .upload-filename{color:#946219;font-weight:600}
</style>
<link rel="stylesheet" href="/assets/avesta/responsive.css?v=20261003">
<script src="/assets/avesta/device-layout.js?v=20261003" defer></script>
</head>
<body>

<!-- NAV -->
<nav role="navigation" aria-label="Main navigation">
  <a class="nav-brand" onclick="showTab('home')" href="javascript:void(0)" aria-label="Home">
    <div class="logo-box">AE</div>
    <div class="logo-text">Avesta Enterprises
      <span class="logo-sub" id="logo-sub">Lending &amp; IT Consulting</span>
    </div>
  </a>
  <a class="office-back" href="index.php?pick=1"
     title="Back to Avesta Enterprises — choose Lending or IT Consulting">
    <span class="ob-ico" aria-hidden="true">&#8592;</span>
    <span class="ob-text">Switch service</span>
  </a>
  <button type="button" class="install-btn" id="install-app" hidden
          title="Install Avesta on this device">
    <span aria-hidden="true">&#8681;</span><span class="ib-text">Install app</span>
  </button>
  <a class="office-out" href="logout.php" title="Sign out of Avesta">
    <span aria-hidden="true">&#9211;</span>
    <span class="oo-text">Sign out</span>
  </a>
  <div class="nav-tabs">
    <button data-site="lending" class="nav-tab active" onclick="showTab('home')" id="ntab-home">Home</button>
    <button data-site="lending" class="nav-tab" onclick="showTab('how')" id="ntab-how">How It Works</button>
    <button data-site="both" class="nav-tab" onclick="showTab('about')" id="ntab-about">About Us</button>
    <button data-site="lending" class="nav-tab" onclick="showTab('calc')" id="ntab-calc">Calculator</button>
    <button data-site="it" class="nav-tab" onclick="showTab('services')" id="ntab-services">IT Services</button>
    <button data-site="lending" class="nav-tab" onclick="showTab('currency')" id="ntab-currency">Currency</button>
    <button data-site="both" class="nav-tab" onclick="showTab('contact')" id="ntab-contact">Contact</button>
    <button data-site="lending" class="nav-tab nav-tab-apply" onclick="showTab('apply')" id="ntab-apply">Apply Now</button>
    <button data-site="lending" class="nav-tab" onclick="showStaffLogin()" id="ntab-staff" style="font-size:8.5pt;letter-spacing:.5px">🔐 Staff</button>
  </div>
  <button class="nav-toggle" onclick="toggleMobileNav()" aria-label="Open menu" id="nav-toggle-btn">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- MOBILE NAV -->
<div class="nav-mobile" id="nav-mobile">
  <a class="mob-back" href="index.php?pick=1"
     style="display:block;padding:14px 20px;text-decoration:none">&#8592; Switch service</a>
  <button data-site="lending" onclick="showTab('home');closeMobileNav()">Home</button>
  <button data-site="lending" onclick="showTab('how');closeMobileNav()">How It Works</button>
  <button data-site="both" onclick="showTab('about');closeMobileNav()">About Us</button>
  <button data-site="lending" onclick="showTab('calc');closeMobileNav()">Calculator</button>
  <button data-site="it" onclick="showTab('services');closeMobileNav()">IT Services</button>
  <button data-site="lending" onclick="showTab('currency');closeMobileNav()">Currency Converter</button>
  <button data-site="lending" onclick="showTab('apply');closeMobileNav()">Apply Now</button>
  <button data-site="both" onclick="showTab('contact');closeMobileNav()">Contact</button>
  <button data-site="lending" onclick="showStaffLogin();closeMobileNav()">🔐 Staff Records</button>
  <a class="mob-out" href="logout.php"
     style="display:block;padding:14px 20px;text-decoration:none">&#9211; Sign out</a>
</div>

<div class="page-content">

  <!-- ═══ TAB: HOME ════════════════════════════════════════════════════════ -->
    <!-- Second level: the sections of whichever service you are in -->
  <nav class="subnav" id="subnav" aria-label="Sections" hidden>
    <div class="subnav-inner" id="subnav-inner"></div>
  </nav>

  <div data-site="lending" class="tab-panel active" id="tab-home">

    <!-- Hero -->
    <section class="hero" aria-label="Hero">
      <div class="hero-watermark" aria-hidden="true"></div>
      <div class="hero-content">
        <div class="hero-badge">Serving all of Zambia &middot; Head office, Ndola</div>
        <h1>Two ways Avesta<br><span>helps you get going</span></h1>
        <p>Short-term lending when cash is what you need, and IT support when it
           is the technology holding you up. One company, two things we do properly.</p>

        <!-- The two arms, given equal weight. A visitor arriving from a loan
             referral and one arriving from an IT flyer both find their door. -->
        <div class="arms arms-single">
          <button type="button" class="arm arm-lend" onclick="showTab('apply')">
            <span class="arm-tag">Avesta Lending</span>
            <span class="arm-title">Need cash this week?</span>
            <span class="arm-sub">Short-term loans, 1 to 4 weeks. Transparent terms, quick disbursement.</span>
            <span class="arm-go">Apply for a loan &rarr;</span>
          </button>
        </div>
        <p class="cross-sell">Also need a computer, network or camera sorted?
           <a href="it.php">Visit Avesta Consulting &rarr;</a>
           <a href="index.php?pick=1" class="cross-back">&#8592; Switch service</a></p>

        <div class="hero-stats">
          <div class="stat"><div class="stat-num">Fast</div><div class="stat-lbl">Loan approval</div></div>
          <div class="stat"><div class="stat-num">Clear</div><div class="stat-lbl">Terms up front</div></div>
          <div class="stat"><div class="stat-num">Local</div><div class="stat-lbl">All of Zambia</div></div>
          <div class="stat"><div class="stat-num">11</div><div class="stat-lbl">IT services</div></div>
        </div>
      </div>
</section>

    <!-- Rate preview -->
    <div class="rate-preview">
      <h2>Our Interest Rates</h2>
      <p>Simple, transparent rates — no hidden fees</p>
      <div class="rate-pills">
        <div class="rate-pill" onclick="showTab('calc')">
          <div class="rp-week">1 Week</div>
          <div class="rp-rate">15%</div>
          <div class="rp-label">Interest</div>
        </div>
        <div class="rate-pill" onclick="showTab('calc')">
          <div class="rp-week">2 Weeks</div>
          <div class="rp-rate">20%</div>
          <div class="rp-label">Interest</div>
        </div>
        <div class="rate-pill" onclick="showTab('calc')">
          <div class="rp-week">3 Weeks</div>
          <div class="rp-rate">25%</div>
          <div class="rp-label">Interest</div>
        </div>
        <div class="rate-pill" onclick="showTab('calc')">
          <div class="rp-week">4 Weeks</div>
          <div class="rp-rate">30%</div>
          <div class="rp-label">Interest</div>
        </div>
      </div>
    </div>

    <!-- Quick nav cards -->
    <div class="home-cards">
      <div class="home-cards-title">
        <h2>What Would You Like to Do?</h2>
        <p>Click a card below to get started</p>
      </div>
      <div class="cards-grid">
        <div class="nav-card" onclick="showTab('how')">
          <div class="nc-icon">📋</div>
          <h3>How It Works</h3>
          <p>Learn about our simple 3-step loan process — from application to receiving your funds.</p>
          <div class="nc-btn">Learn More</div>
        </div>
        <div class="nav-card" onclick="showTab('calc')">
          <div class="nc-icon">🧮</div>
          <h3>Loan Calculator</h3>
          <p>Estimate your repayments before you apply. Choose your duration and see exactly how much you'll repay.</p>
          <div class="nc-btn">Calculate Now</div>
        </div>
        <div class="nav-card" onclick="showTab('apply')">
          <div class="nc-icon">📝</div>
          <h3>Apply for a Loan</h3>
          <p>Fill in the full loan agreement online and print or save it as a PDF to submit.</p>
          <div class="nc-btn">Apply Now</div>
        </div>
        <div class="nav-card" onclick="showTab('about')">
          <div class="nc-icon">🏢</div>
          <h3>About Us</h3>
          <p>Learn who we are, our values, and why hundreds of Zambians trust Avesta Enterprises for their financial needs.</p>
          <div class="nc-btn">Our Story</div>
        </div>
        <div class="nav-card" onclick="showTab('contact')">
          <div class="nc-icon">📞</div>
          <h3>Contact Us</h3>
          <p>Have questions? Wherever you are in Zambia, reach us by phone, WhatsApp or email &mdash; or visit the office in Kansenshi, Ndola.</p>
          <div class="nc-btn">Get in Touch</div>
        </div>
      </div>
    </div>

  </div><!-- /tab-home -->


  <!-- ═══ TAB: HOW IT WORKS ════════════════════════════════════════════════ -->
  <div data-site="lending" class="tab-panel" id="tab-how">
    <div class="page-section" style="background:var(--lgray)">
      <div class="center">
        <div class="section-tag">Simple Process</div>
        <h2 class="section-title">How It Works</h2>
        <p class="section-sub">Get your loan in three easy steps — no complicated paperwork, no hidden fees.</p>
      </div>

      <div class="how-illo">
        <img src="images/how-it-works.webp" alt="" aria-hidden="true"
             width="1100" height="619" loading="lazy" decoding="async"
             onerror="this.parentNode.style.display='none'">
      </div>

      <div class="steps">
        <div class="step">
          <div class="step-num">1</div>
          <h3>Fill Application</h3>
          <p>Complete the loan agreement form with your personal details, loan amount, and preferred repayment schedule.</p>
          <div class="step-arrow">›</div>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <h3>Review &amp; Approval</h3>
          <p>Our team reviews your application and collateral details. We'll contact you to confirm terms and finalise the agreement.</p>
          <div class="step-arrow">›</div>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <h3>Receive Funds</h3>
          <p>Once signed, funds are disbursed via your chosen method — cash, bank transfer, or mobile money.</p>
        </div>
      </div>
    </div>
    <div class="page-section" style="background:var(--white)">
      <div class="center">
        <div class="section-tag">Why Choose Us</div>
        <h2 class="section-title">Loan Features</h2>
        <p class="section-sub">We offer straightforward, transparent lending with your needs in mind.</p>
      </div>
      <div class="features-grid">
        <div class="feat"><div class="feat-icon">⚡</div><div class="feat-text"><h4>Quick Disbursement</h4><p>Funds released promptly after agreement signing — cash, bank transfer, or mobile money.</p></div></div>
        <div class="feat"><div class="feat-icon">📋</div><div class="feat-text"><h4>Flexible Repayments</h4><p>Choose weekly, bi-weekly, monthly or lump-sum repayment plans that suit your cash flow.</p></div></div>
        <div class="feat"><div class="feat-icon">🔒</div><div class="feat-text"><h4>Secured Agreement</h4><p>All loans are backed by a formal legal agreement signed by both parties for your protection.</p></div></div>
        <div class="feat"><div class="feat-icon">💰</div><div class="feat-text"><h4>Transparent Rates</h4><p>Clear interest rates — 15% for 1 week up to 30% for 4 weeks. No hidden charges.</p></div></div>
        <div class="feat"><div class="feat-icon">🤝</div><div class="feat-text"><h4>Collateral Accepted</h4><p>Secure your loan with personal assets — giving you access to better terms and higher amounts.</p></div></div>
        <div class="feat"><div class="feat-icon">📱</div><div class="feat-text"><h4>Multiple Payment Methods</h4><p>Receive &amp; repay via Cash, Airtel Money, MTN MoMo, Zamtel, ZANACO, FNB, Stanbic and more.</p></div></div>
      </div>
      <div style="text-align:center;margin-top:40px">
        <button class="btn-primary" onclick="showTab('apply')">Apply for a Loan Now</button>
      </div>
    </div>
  </div><!-- /tab-how -->


  <!-- ═══ TAB: CALCULATOR ══════════════════════════════════════════════════ -->
  <div data-site="lending" class="tab-panel" id="tab-calc">
    <div class="center" style="padding-top:0">
      <div class="section-tag">Plan Your Loan</div>
      <h2 class="section-title">Loan Calculator</h2>
      <p class="section-sub">Estimate your repayments before you apply. Click a duration tile to auto-set the rate.</p>
    </div>

    <div class="rate-tiers">
      <div class="rate-tier" onclick="pickTier(this,1,15)">
        <div class="rt-weeks">1 Week</div><div class="rt-rate">15%</div><div class="rt-label">Interest</div>
      </div>
      <div class="rate-tier" onclick="pickTier(this,2,20)">
        <div class="rt-weeks">2 Weeks</div><div class="rt-rate">20%</div><div class="rt-label">Interest</div>
      </div>
      <div class="rate-tier" onclick="pickTier(this,3,25)">
        <div class="rt-weeks">3 Weeks</div><div class="rt-rate">25%</div><div class="rt-label">Interest</div>
      </div>
      <div class="rate-tier" onclick="pickTier(this,4,30)">
        <div class="rt-weeks">4 Weeks</div><div class="rt-rate">30%</div><div class="rt-label">Interest</div>
      </div>
    </div>

    <div class="calc-wrap">
      <div class="calc-form">
        <label for="c_amt">Loan Amount (ZMW)</label>
        <input type="number" id="c_amt" placeholder="e.g. 5000" oninput="calcLoan()">
        <label for="c_dur">Loan Duration</label>
        <select id="c_dur" onchange="applyDurRate()">
          <option value="">Select duration...</option>
          <option value="1">1 Week — 15% interest</option>
          <option value="2">2 Weeks — 20% interest</option>
          <option value="3">3 Weeks — 25% interest</option>
          <option value="4">4 Weeks — 30% interest</option>
        </select>
        <label for="c_rate">Interest Rate (%)</label>
        <input type="number" id="c_rate" placeholder="Auto-set by duration" step="0.1" readonly aria-readonly="true" style="background:rgba(217,142,59,.15);border-color:rgba(217,142,59,.6)">
        <label for="c_sched">Repayment Schedule</label>
        <select id="c_sched" onchange="calcLoan()">
          <option value="">Select...</option>
          <option value="Weekly">Weekly</option>
          <option value="Lump Sum at End of Term">Lump Sum at End of Term</option>
        </select>
        <button class="calc-btn" onclick="calcLoan()">Calculate Repayment</button>
      </div>
      <div class="calc-result">
        <h3>Repayment Summary</h3>
        <div class="result-row"><span class="r-lbl">Principal Amount</span><span class="r-val" id="r_principal">—</span></div>
        <div class="result-row"><span class="r-lbl">Interest Rate</span><span class="r-val" id="r_rate_disp">—</span></div>
        <div class="result-row"><span class="r-lbl">Total Interest</span><span class="r-val" id="r_interest">—</span></div>
        <div class="result-row highlight"><span class="r-lbl">Total Repayable</span><span class="r-val" id="r_total">—</span></div>
        <div class="result-row"><span class="r-lbl">Duration</span><span class="r-val" id="r_dur">—</span></div>
        <div class="result-row"><span class="r-lbl">Schedule</span><span class="r-val" id="r_sched">—</span></div>
      </div>
    </div>

    <div class="loan-disclosure" role="note" aria-label="Loan estimate notice"><strong>Before you apply</strong><span>This calculator is an estimate. Your application will show the loan amount, interest, total repayable and repayment dates again before you sign. Approval is not guaranteed by using the calculator.</span></div>
    <button class="calc-apply-btn" onclick="showTab('apply')">Ready? Apply for a Loan →</button>
    <p style="text-align:center;font-size:9pt;color:var(--dgray);margin-top:14px">
      Earning or holding value in another currency?
      <a href="#currency" onclick="showTab('currency');return false"
         style="color:var(--gold);font-weight:700;text-decoration:none">Use the currency converter →</a>
    </p>
  </div><!-- /tab-calc -->




  <!-- ═══ TAB: IT SERVICES ═════════════════════════════════════════════════ -->
  <div data-site="it" class="tab-panel" id="tab-services" data-arm="consulting">

    <section class="svc-hero">
      <div class="svc-kicker">Avesta Consulting</div>
      <h2>IT Services for <em>Business</em></h2>
      <p>Alongside lending, Avesta provides IT support to businesses and homes across Zambia.
         Websites, databases, data work, cybersecurity and training are delivered
         wherever you are; on-site work covers the Copperbelt and Lusaka, and the
         rest of the country by arrangement.</p>
    </section>

    <div class="svc-grid svc-accordion">
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 1 5.4-5.4l-2.5 2.5 2.1 2.1 2.5-2.5a4 4 0 0 1-5.1-5.1z"/></svg></span><span>Computer Repair &amp; Maintenance</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Diagnostics and repair for desktops and laptops, plus servicing to keep machines running.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Computer Repair & Maintenance')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/></svg></span><span>Windows &amp; Software Installation</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Operating system installs, reinstalls and licensed software set up properly the first time.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Windows & Software Installation')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5.5 5.5 0 0 1 7 0"/><circle cx="12" cy="19.5" r="1"/></svg></span><span>Networking &amp; Wi-Fi Setup</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Wired and wireless networks for homes and offices, including routers, switches and access points.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Networking & Wi-Fi Setup')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg></span><span>Printer &amp; Scanner Setup</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Installation, sharing across a network, and sorting out the drivers nobody else can.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Printer & Scanner Setup')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 18l-6-6 6-6M15 6l6 6-6 6"/></svg></span><span>Website Design &amp; Development</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Sites built to work on the phones your customers actually use, on the connections they actually have.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Website Design & Development')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v13c0 1.7 3.6 3 8 3s8-1.3 8-3v-13M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg></span><span>Database Management</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Design, setup and upkeep, so your records stay organised, searchable and safe.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Database Management')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></span><span>Data Entry &amp; Data Analysis</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Getting information into a usable shape, and turning it into something you can make decisions from.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Data Entry & Data Analysis')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span><span>Data Protection, Backup &amp; Recovery</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Backups that are tested, and recovery when something has already gone wrong.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Data Protection, Backup & Recovery')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 8h3l1.5-2h9L18 8h3v11H3z"/><circle cx="12" cy="13" r="3.5"/></svg></span><span>CCTV Installation &amp; Maintenance</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Camera systems installed, configured and serviced, with remote viewing where you need it.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('CCTV Installation & Maintenance')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l8 3v5.5c0 4.6-3.3 8.6-8 9.5-4.7-.9-8-4.9-8-9.5V6z"/><path d="M9 12l2 2 4-4"/></svg></span><span>Cybersecurity</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Firewalls, endpoint protection and hardening — practical security for small organisations.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('Cybersecurity')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
      <details class="svc svc-detail"><summary><span class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 7l9-4 9 4-9 4z"/><path d="M7 10v5c0 1.7 2.2 3 5 3s5-1.3 5-3v-5M21 7v6"/></svg></span><span>IT Training &amp; Consultancy</span><span class="svc-plus" aria-hidden="true">+</span></summary><div class="svc-reveal"><p>Training your staff can follow, and advice before you spend money rather than after.</p><button type="button" class="svc-support" onclick="showTab('contact');setTimeout(function(){var s=document.getElementById('e_service');if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].text.indexOf('IT Training & Consultancy')===0){s.selectedIndex=i;break;}}}},0)">Get support →</button></div></details>
    </div>



<!-- ═══ IT HELP ASSISTANT ═══════════════════════════════════════════════════
     A guided troubleshooter, not a person and not a language model. It knows
     the faults Avesta is actually called out for, gives the checks worth
     trying first, and hands over to a human the moment it is out of its
     depth. No API key, no per-message cost, and it works on a slow
     connection because everything is already on the page.

     To put a real model behind it later, add an endpoint to api.php and call
     it from askModel() below; the hand-off to the enquiry form stays as the
     fallback for when the model is unavailable.
═══════════════════════════════════════════════════════════════════════════ -->
<style>
.svc-accordion{align-items:start}.svc-detail{padding:0!important;overflow:hidden}.svc-detail summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:14px;padding:20px;min-height:78px;font-weight:800}.svc-detail summary::-webkit-details-marker{display:none}.svc-detail summary span:nth-child(2){flex:1}.svc-plus{font-size:24px;font-weight:400;transition:transform .2s ease}.svc-detail[open] .svc-plus{transform:rotate(45deg)}.svc-reveal{padding:0 20px 20px 64px}.svc-reveal p{margin:0 0 14px;color:var(--dgray)}.svc-support{border:0;border-radius:9px;padding:10px 14px;background:var(--accent);color:#fff;font:inherit;font-weight:800;cursor:pointer}@media(max-width:620px){.svc-reveal{padding-left:20px}.svc-detail summary{padding:16px}}
</style>
<style>
.bot{max-width:1000px;margin:0 auto;padding:0 20px 30px}
.bot-inner{background:var(--white);border:1px solid var(--mgray);border-radius:14px;
  border-top:3px solid var(--accent);overflow:hidden}
.bot-head{display:flex;align-items:flex-start;gap:12px;padding:20px 20px 16px}
.bot-avatar{flex:0 0 40px;width:40px;height:40px;border-radius:11px;display:grid;place-items:center;
  background:linear-gradient(145deg,rgba(91,155,255,.22),rgba(91,155,255,.08));
  color:var(--accent-deep);font-size:17px}
.bot-head h3{font-size:12.5pt;font-weight:800;color:var(--navy);margin:0 0 3px}
.bot-head p{margin:0;font-size:9pt;color:var(--dgray);line-height:1.55}
.bot-log{border-top:1px solid var(--mgray);padding:18px 20px;max-height:430px;overflow-y:auto;
  background:var(--lgray)}
.bot-msg{display:flex;gap:10px;margin-bottom:14px;align-items:flex-start}
.bot-msg .who{flex:0 0 28px;width:28px;height:28px;border-radius:8px;display:grid;place-items:center;
  font-size:12px;font-weight:700}
.bot-msg.bot .who{background:rgba(91,155,255,.18);color:var(--accent-deep)}
.bot-msg.me{flex-direction:row-reverse}
.bot-msg.me .who{background:var(--navy);color:var(--accent-light)}
.bot-bubble{max-width:82%;background:var(--white);border:1px solid var(--mgray);
  border-radius:12px;padding:12px 14px;font-size:9.5pt;line-height:1.6;color:var(--dgray)}
.bot-msg.me .bot-bubble{background:var(--navy);color:rgba(255,255,255,.92);border-color:var(--navy)}
.bot-bubble strong{color:var(--navy)}
.bot-msg.me .bot-bubble strong{color:var(--accent-light)}
.bot-bubble ol{margin:8px 0 0;padding-left:18px}
.bot-bubble li{margin-bottom:6px;line-height:1.55}
.bot-bubble .warn{display:block;margin-top:9px;padding:8px 10px;border-radius:7px;
  background:var(--av-warn-wash,#FDF4E3);color:var(--av-warn,#946219);font-size:9pt}
.bot-chips{display:flex;flex-wrap:wrap;gap:7px;padding:14px 20px;border-top:1px solid var(--mgray);
  background:var(--white)}
.bot-chips button{font-size:9pt;font-weight:600;color:var(--navy);background:var(--lgray);
  border:1px solid var(--mgray);border-radius:999px;padding:8px 13px;cursor:pointer;
  font-family:inherit;text-align:left}
.bot-chips button:hover{border-color:var(--accent);color:var(--accent-deep)}
.bot-ask{display:flex;gap:9px;padding:0 20px 18px;background:var(--white);align-items:flex-end}
.bot-ask input{flex:1;padding:12px 13px;border:1.5px solid var(--mgray);border-radius:9px;
  font-size:10.5pt;font-family:inherit;color:var(--navy)}
.bot-ask input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(91,155,255,.16)}
.bot-ask button{background:var(--navy);color:var(--accent-light);border:none;border-radius:9px;
  padding:12px 18px;font-size:9.5pt;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}
.bot-ask button:hover{background:#1F5344}
.bot-foot{padding:0 20px 16px;font-size:8.5pt;color:var(--dgray);opacity:.8;line-height:1.55;background:var(--white)}
@media(max-width:600px){.bot{padding:0 14px 24px}.bot-bubble{max-width:88%}
  .bot-chips button{font-size:8.5pt}}
@media(prefers-reduced-motion:reduce){.bot-msg{animation:none!important}}
</style>

<div class="bot">
  <div class="bot-inner">
    <div class="bot-head">
      <div class="bot-avatar" aria-hidden="true">&#128172;</div>
      <div>
        <h3>G.I.T — Avesta IT Assistant</h3>
        <p>Describe the problem and we will give you the checks worth trying first.
           If it needs a technician, we will say so rather than waste your time.</p>
      </div>
    </div>

    <div class="bot-log" id="bot-log" role="log" aria-live="polite" aria-label="Help desk conversation"></div>

    <div class="bot-chips" id="bot-chips"></div>

    <div class="bot-ask">
      <label for="bot-in" class="sr-only" style="position:absolute;left:-9999px">Describe your problem</label>
      <input type="text" id="bot-in" placeholder="e.g. my printer says offline" autocomplete="off">
      <button type="button" id="bot-send">Ask</button>
    </div>

    <p class="bot-foot">
      This is an automated guide, not a technician. It does not see your machine and
      cannot know everything. For anything urgent, call <strong>0769 974 200</strong>.
    </p>
  </div>
</div>

<script>
(function () {
  var log = document.getElementById('bot-log');
  if (!log) return;

  var PHONE = '0769 974 200';
  var WA = 'https://wa.me/260769974200';

  /* Faults Avesta is actually called out for. Each answer gives checks a
     non-technical person can safely do, and is honest about where to stop.
     `weight` words are the ones that most strongly indicate this fault. */
  var KB = [
    { id:'printer', title:'Printer not printing or shows offline',
      weight:['printer','print','offline','spooler','toner','cartridge','paper jam'],
      hint:['prints blank','wont print','won\u2019t print'],
      answer:'Printers usually go "offline" over the connection, not the hardware.',
      steps:[
        'Check the printer is on and shows no blinking error light.',
        'If it is on Wi-Fi, check it is on the same network as the computer \u2014 not a guest network.',
        'On Windows: Settings \u2192 Bluetooth &amp; devices \u2192 Printers, open the printer, and untick "Use Printer Offline".',
        'Open the print queue and cancel every stuck job, then switch the printer off for 30 seconds and back on.',
        'Still offline? Remove the printer and add it again by IP rather than by name.'
      ],
      call:'If it prints a test page from its own panel but not from the computer, the fault is the driver or the network, and we can usually fix that remotely.' },

    { id:'wifi', title:'Wi-Fi keeps dropping',
      weight:['wifi','wi-fi','wireless','drops','dropping','disconnect','keeps cutting','signal'],
      answer:'Repeated drops in one part of a building are almost always coverage or interference, not the router failing.',
      steps:[
        'Note whether it drops everywhere or only in certain rooms. Only in some rooms means coverage.',
        'Check how many devices are connected. Cheap routers struggle past 15 to 20.',
        'Move the router off the floor and away from metal cabinets, microwaves and cordless phones.',
        'In the router settings, set the 2.4GHz channel to 1, 6 or 11 rather than Auto.',
        'If it drops at the same time each day, something else is switching on \u2014 look at what.'
      ],
      call:'If it drops everywhere at once, that is usually the line or the router. We can test which, and add access points where coverage is the problem.' },

    { id:'nointernet', title:'No internet at all',
      weight:['no internet','internet down','no connection','cannot browse','offline completely','data not working'],
      answer:'Work outward from the computer to the line \u2014 it saves calling the provider over a loose cable.',
      steps:[
        'Can other devices get online? If none can, the problem is the router or the line.',
        'Check the lights on the router. A red or missing internet light points at the provider.',
        'Switch the router off at the wall for a full 60 seconds, then on. Give it three minutes.',
        'Check the cable from the wall to the router is seated at both ends.',
        'If only one computer is affected, restart it and forget/rejoin the network.'
      ],
      call:'If the router shows no internet light after a proper restart, call your provider first. If they say the line is fine, call us.' },

    { id:'slow', title:'Computer very slow',
      weight:['slow','sluggish','lagging','freezing','takes long','hanging'],
      answer:'On an older machine this is usually the disk or too much starting at boot, not the processor.',
      steps:[
        'Open Task Manager (Ctrl+Shift+Esc) and look at the Startup tab. Disable what you do not need at boot.',
        'Check the Performance tab: if Disk sits at 100% constantly, a mechanical drive is the bottleneck.',
        'Check free space on the C: drive. Under 10% free will slow Windows badly.',
        'Restart properly \u2014 not just close the lid. Machines left for weeks accumulate problems.'
      ],
      call:'If it is a mechanical hard drive, fitting an SSD is the single biggest improvement you can make to an older laptop. We do that.' },

    { id:'wontstart', title:'Computer will not turn on',
      weight:['wont turn on','won\u2019t turn on','not starting','no power','dead','black screen','not booting'],
      answer:'The aim here is to tell a power problem from a display problem.',
      steps:[
        'Any lights or fan noise at all? If none, it is power. If yes but nothing on screen, it is display or boot.',
        'On a laptop: unplug, hold the power button for 30 seconds, plug in the charger only, try again.',
        'Check the charger light. A charger that gives nothing is a common and cheap fault.',
        'On a desktop: check the wall socket with something else, and check the cable at the back.'
      ],
      call:'If there is power but no display, stop there. Beyond this point you risk making it worse \u2014 bring it in.',
      warn:'Do not open a laptop to "check the battery" unless you have done it before. Ribbon cables tear easily.' },

    { id:'virus', title:'Pop-ups, virus or ransomware',
      weight:['virus','malware','popup','pop-up','ransomware','encrypted','hacked','trojan','infected'],
      answer:'Disconnect first. Whatever it is, stopping it spreading matters more than removing it.',
      steps:[
        'Unplug the network cable or switch off Wi-Fi immediately.',
        'Do not pay anything. Do not enter passwords or card details into any message on screen.',
        'If files have been renamed or you see a ransom note, leave the machine alone and call us.',
        'For ordinary pop-ups: run a full Windows Defender scan and remove unknown browser extensions.'
      ],
      call:'Ransomware needs handling properly \u2014 call ' + PHONE + ' before doing anything else.',
      warn:'If this machine holds customer records, a breach may need reporting to the Data Protection Commissioner within 24 hours.' },

    { id:'files', title:'Deleted files or need data recovered',
      weight:['deleted','lost files','recover','recovery','recycle bin','gone','formatted','missing files'],
      answer:'What you do in the first hour decides whether the data comes back.',
      steps:[
        'Check the Recycle Bin first, and the app\u2019s own recent files.',
        'Stop using that drive. Every further write reduces what can be recovered.',
        'Do not install recovery software onto the same drive \u2014 that alone can overwrite what you are trying to save.',
        'If the files were on a network share or OneDrive, check version history before anything else.'
      ],
      call:'For anything that matters, stop and call us. Recovery attempts done wrong are usually what makes data unrecoverable.',
      warn:'If the drive is making clicking or grinding noises, switch it off now. Continuing will destroy the platters.' },

    { id:'cctv', title:'CCTV not recording or cannot view remotely',
      weight:['cctv','camera','dvr','nvr','recording','footage','surveillance','not viewing'],
      answer:'Recording faults and remote-viewing faults have different causes; check which one you have.',
      steps:[
        'Can you see live footage on the monitor at the recorder? If yes, cameras and recorder are fine.',
        'Check the hard drive status in the recorder menu. Surveillance drives wear out, typically after two to three years.',
        'For remote viewing: check the recorder still has an internet connection and the app is logged in.',
        'If the internet provider changed your router, port forwarding or the P2P registration may have been lost.'
      ],
      call:'We install and service CCTV. If the drive has failed or remote access needs reconfiguring, that is a call-out.' },

    { id:'email', title:'Email not sending or receiving',
      weight:['email','outlook','mail','not sending','not receiving','smtp','imap','mailbox full'],
      answer:'Sending and receiving fail for different reasons, so note which one is broken.',
      steps:[
        'Can you send but not receive, or the other way round? That alone narrows it a lot.',
        'Check the mailbox is not full \u2014 a full mailbox silently stops delivery.',
        'Check webmail in a browser. If webmail works, the fault is the app, not the account.',
        'Recently changed the password? Outlook often keeps the old one cached and locks the account out.'
      ],
      call:'We manage Microsoft 365 and mail setups. If webmail works but Outlook does not, we can usually sort it remotely.' },

    { id:'password', title:'Locked out of Windows',
      weight:['password','locked out','forgot password','cannot log in','login','signed out'],
      answer:'What is possible depends on the account type.',
      steps:[
        'If it is a Microsoft account, reset it at account.live.com from a phone \u2014 that is the quickest route.',
        'If it is a local account, there is no online reset; it has to be done on the machine.',
        'Check Caps Lock, and check the keyboard layout has not switched.',
        'Do not keep guessing on a work machine \u2014 repeated failures can lock the account entirely.'
      ],
      call:'Local accounts need doing in person and we will ask to see proof the machine is yours. That is deliberate.' },

    { id:'overheat', title:'Laptop hot, loud fan, or switching itself off',
      weight:['hot','overheat','fan','noisy','loud','shutting down','switches off','burning'],
      answer:'Almost always dust and blocked vents, especially in dusty conditions.',
      steps:[
        'Check the vents are not blocked. Using a laptop on a bed or sofa blocks them completely.',
        'Listen to the fan. Loud means it is working hard; silent while hot means it has failed.',
        'Shutting off suddenly under load is thermal protection \u2014 the machine is saving itself.',
        'Do not spray compressed air into a running fan; it spins the bearings past their rating.'
      ],
      call:'Internal cleaning and repasting takes about an hour and makes an old laptop usable again. We do it.' },

    { id:'backup', title:'Backups and protecting data',
      weight:['backup','back up','protect','safe','copy','restore','disaster'],
      answer:'A backup you have never restored from is not yet a backup.',
      steps:[
        'Keep three copies: the live one, one on separate hardware, one off site.',
        'Test a restore. Most failed backups are discovered at the worst moment.',
        'If it holds customer data, remember Zambian law requires it to stay on servers in Zambia.',
        'An external drive left plugged in permanently will be encrypted along with everything else by ransomware.'
      ],
      call:'We set up backups that are tested rather than assumed, and handle recovery when something has already gone wrong.' }
  ];

  var GREET = ['hi','hello','hey','good morning','good afternoon','good evening','muli bwanji','mwabuka'];
  var HUMAN = ['person','human','someone','talk to','speak to','call you','technician','agent'];
  var PRICE = ['price','cost','how much','charge','quote','fee','rate'];

  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  }

  function say(who, html) {
    var row = el('div', 'bot-msg ' + who);
    row.appendChild(el('span', 'who', who === 'me' ? 'You' : '\u2699'));
    row.appendChild(el('div', 'bot-bubble', html));
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
  }

  function answerFor(item) {
    var html = '<strong>' + item.title + '</strong><br>' + item.answer + '<ol>';
    item.steps.forEach(function (s) { html += '<li>' + s + '</li>'; });
    html += '</ol>';
    if (item.warn) html += '<span class="warn"><strong>Careful:</strong> ' + item.warn + '</span>';
    if (item.call) html += '<br>' + item.call;
    return html;
  }

  /* Score each entry against the question. Weighted words count for more than
     incidental ones, and a phrase match counts for more than a single word. */
  function match(q) {
    var t = ' ' + q.toLowerCase().replace(/[^a-z0-9\u2019' ]/g, ' ').replace(/\s+/g, ' ') + ' ';
    var best = null, bestScore = 0;
    KB.forEach(function (item) {
      var score = 0;
      (item.weight || []).forEach(function (w) {
        if (t.indexOf(' ' + w) > -1 || t.indexOf(w + ' ') > -1) score += w.indexOf(' ') > -1 ? 4 : 2;
      });
      (item.hint || []).forEach(function (w) { if (t.indexOf(w) > -1) score += 3; });
      if (score > bestScore) { bestScore = score; best = item; }
    });
    return bestScore >= 2 ? best : null;
  }

  function anyOf(q, list) {
    var t = q.toLowerCase();
    return list.some(function (w) { return t.indexOf(w) > -1; });
  }

  function handOff(reason) {
    return reason + '<br><br>Tell us what is happening in the enquiry form below and we will '
      + 'come back to you, or call <strong>' + PHONE + '</strong>. '
      + '<a href="' + WA + '" target="_blank" rel="noopener noreferrer">WhatsApp</a> works too.';
  }

  function respond(q) {
    if (anyOf(q, HUMAN)) {
      say('bot', handOff('Of course \u2014 a person is better for this.'));
      return;
    }
    if (anyOf(q, PRICE)) {
      // Never guess at prices. Getting this wrong costs a customer or a job.
      say('bot', handOff('Cost depends on what the job turns out to need, so we will not '
        + 'guess at it here. We quote before starting work, never after.'));
      return;
    }
    if (anyOf(q, GREET) && q.trim().split(/\s+/).length <= 3) {
      say('bot', 'Hi, I\'m G.I.T. What IT problem can I help you with?');
      return;
    }

    var hit = match(q);
    if (hit) {
      say('bot', answerFor(hit));
      // Offer the neighbouring problems people often turn out to have
      setTimeout(function () {
        say('bot', 'Did that help? If not, say a bit more about what you see on screen.');
      }, 450);
      return;
    }

    say('bot', handOff('That one is beyond what this guide covers, and guessing would '
      + 'waste your time.'));
  }

  function ask() {
    var input = document.getElementById('bot-in');
    var q = input.value.trim();
    if (!q) return;
    say('me', q.replace(/[<>&]/g, function (c) {
      return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c];
    }));
    input.value = '';
    setTimeout(function () { respond(q); }, 260);
  }

  document.getElementById('bot-send').addEventListener('click', ask);
  document.getElementById('bot-in').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') ask();
  });

  // The common openers, so nobody has to think of what to type
  var chips = document.getElementById('bot-chips');
  ['printer', 'wifi', 'nointernet', 'slow', 'wontstart', 'virus'].forEach(function (id) {
    var item = KB.filter(function (k) { return k.id === id; })[0];
    if (!item) return;
    var b = el('button', null, item.title);
    b.type = 'button';
    b.addEventListener('click', function () {
      say('me', item.title);
      setTimeout(function () { say('bot', answerFor(item)); }, 260);
    });
    chips.appendChild(b);
  });

  say('bot', 'Hi, I\'m G.I.T. What IT problem can I help you with? '
    + 'Pick one below, or type it in your own words.');
})();
</script>


    <!-- ═══ TROUBLESHOOTING ASSISTANT ════════════════════════════════════ -->
    <div class="ask">
      <div class="ask-inner">
        <div class="ask-head">
          <span class="ask-badge" aria-hidden="true">?</span>
          <div>
            <h3>G.I.T — Avesta IT Assistant</h3>
            <p>Describe the problem and I will tell you what it usually is, and what is
               safe to try before anyone comes out.</p>
          </div>
        </div>

        <div class="ask-log" id="ask-log" role="log" aria-live="polite" aria-label="Answers"></div>

        <div class="ask-chips" id="ask-chips"></div>

        <div class="ask-row">
          <label class="sr-only" for="ask-q">Describe the problem</label>
          <input type="text" id="ask-q" autocomplete="off"
                 placeholder="e.g. the Wi-Fi keeps dropping in the back room">
          <button type="button" id="ask-go">Ask</button>
        </div>
        <p class="ask-note">Straightforward answers, written by us. Nothing here will tell you to
           delete or format anything. If it is not something to fix yourself, it says so.</p>
      </div>
    </div>

    <!-- ═══ ENQUIRY ══════════════════════════════════════════════════════ -->
    <div class="enq">
      <div class="enq-inner">
        <h3>Tell us what you need</h3>
        <p>We will come back with what it takes and what it costs, before any work starts.</p>

        <div class="enq-grid">
          <div>
            <label for="e_name">Your name</label>
            <input type="text" id="e_name" autocomplete="name">
          </div>
          <div>
            <label for="e_phone">Phone or WhatsApp</label>
            <input type="tel" id="e_phone" inputmode="tel" autocomplete="tel">
          </div>
          <div class="enq-wide">
            <label for="e_service">What is it about?</label>
            <select id="e_service">
            <option>Not sure — please advise</option>
            <option>Computer Repair &amp; Maintenance</option>
            <option>Windows &amp; Software Installation</option>
            <option>Networking &amp; Wi-Fi Setup</option>
            <option>Printer &amp; Scanner Setup</option>
            <option>Website Design &amp; Development</option>
            <option>Database Management</option>
            <option>Data Entry &amp; Data Analysis</option>
            <option>Data Protection, Backup &amp; Recovery</option>
            <option>CCTV Installation &amp; Maintenance</option>
            <option>Cybersecurity</option>
            <option>IT Training &amp; Consultancy</option>
            <option>Something else</option>
            </select>
          </div>
          <div class="enq-wide">
            <label for="e_detail">What is happening?</label>
            <textarea id="e_detail" rows="4"
              placeholder="For example: office of 8 computers, the Wi-Fi keeps dropping in the back room."></textarea>
          </div>
        </div>

        <!-- Left empty by people, filled in by bots. Hidden from both sight and
             screen readers, and never shown as a validation error. -->
        <div class="hp" aria-hidden="true">
          <label for="e_website">Website</label>
          <input type="text" id="e_website" tabindex="-1" autocomplete="off">
        </div>

        <div class="enq-foot">
          <button type="button" class="enq-send" id="e_send">Send enquiry</button>
          <span class="enq-msg" id="enq-msg" role="status" aria-live="polite"></span>
        </div>
      </div>
    </div>

    <div class="svc-cta">
      <div class="svc-cta-inner">
        <h3>Need any of this sorted?</h3>
        <p>Tell us what is broken, or what you are trying to set up. We will tell you
           what it takes before any work starts.</p>
        <div class="svc-lines">
          <a class="ghost" href="index.php?pick=1">&#8592; Switch service</a>
          <a href="tel:+260769974200">Call +260 769 974 200</a>
          <a class="ghost" href="https://wa.me/260769974200" target="_blank" rel="noopener noreferrer">WhatsApp us</a>
          <a class="ghost" href="mailto:info@avesta.solutions">Email us</a>
        </div>
      </div>
    </div>

  </div><!-- /tab-services -->

  <!-- ═══ TAB: CURRENCY CONVERTER ══════════════════════════════════════════ -->
  <div data-site="lending" class="tab-panel" id="tab-currency">
    <div class="center" style="padding-top:0">
      <div class="section-tag">Exchange Rates</div>
      <h2 class="section-title">Currency Converter</h2>
      <p class="section-sub">Convert the Zambian kwacha against every major world currency. Useful if your income, collateral or invoices are valued in foreign currency.</p>
    </div>
    <!-- ═══ CURRENCY CONVERTER ═════════════════════════════════════════════ -->
    

    <div class="fxc">
      <div class="fxc-grid">
        <div class="fxc-field">
          <label for="fx_amt">Amount</label>
          <input type="text" id="fx_amt" inputmode="decimal" value="1000" autocomplete="off">
        </div>
        <div class="fxc-field">
          <label for="fx_from">From</label>
          <select id="fx_from"></select>
        </div>
        <button type="button" class="fxc-swap" id="fx_swap" aria-label="Swap currencies" title="Swap">&#8646;</button>
        <div class="fxc-field">
          <label for="fx_to">To</label>
          <select id="fx_to"></select>
        </div>
      </div>

      <div class="fxc-chips" id="fx_chips"></div>

      <div class="fxc-result">
        <div class="fxc-res-main">
          <span class="fxc-res-lbl">Converted amount</span>
          <span class="fxc-res-val" id="fx_out">&mdash;</span>
        </div>
        <div class="fxc-res-pair" id="fx_pair">&mdash;</div>
      </div>

      <div class="fxc-margin">
        <label for="fx_margin">Bank or bureau margin &mdash; <b id="fx_mpct">2%</b></label>
        <input type="range" id="fx_margin" min="0" max="8" step="0.25" value="2">
        <div class="fxc-track">
          <div class="fxc-fill" id="fx_fill"></div>
          <div class="fxc-gap" id="fx_gap"></div>
        </div>
        <div class="fxc-legend">
          <span id="fx_eff">&mdash;</span>
          <span>You lose <b id="fx_loss">&mdash;</b> to the spread</span>
        </div>
      </div>

      <div class="fxc-foot">
        <button type="button" class="fxc-refresh" id="fx_refresh">Refresh rates</button>
        <span class="fxc-stamp" id="fx_stamp"><i class="fxc-dot"></i>Loading rates&hellip;</span>
      </div>

      <p class="fxc-disclaimer" id="fx_note">Indicative rates only. Banks and bureaus set their own spread, and Avesta lends and collects in Zambian kwacha.</p>
    </div>

    <button class="calc-apply-btn" onclick="showTab('calc')">Work out your repayments →</button>
  </div><!-- /tab-currency -->

  <!-- ═══ TAB: APPLY ═══════════════════════════════════════════════════════ -->
  <div data-site="lending" class="tab-panel" id="tab-apply">
    <div class="form-card">
      <div class="form-header">
        <h2>AVESTA ENTERPRISES — LOAN AGREEMENT</h2>
        <span>Effective Date: <input type="date" id="eff_date" aria-label="Effective date of this agreement" style="background:transparent;border:none;border-bottom:1px solid var(--gold);color:var(--gold);outline:none;font-family:Arial;font-size:9pt;"></span>
      </div>

      <div class="loan-start-card" aria-label="Application checklist">
        <div><span class="lsc-kicker">Application checklist</span><h3>Know what you need before you start</h3><p>Your progress is saved on this device. You can review every figure before signing.</p></div>
        <div class="lsc-grid">
          <span>🪪 NRC front &amp; back</span><span>📱 Active phone number</span><span>💼 Income / employer details</span><span>📎 Security documents if applicable</span>
        </div>
      </div>

      <!-- RESUME BANNER -->
      <div class="resume-banner" id="resume-banner" style="display:none;margin:18px 28px 0">
        <p>📋 We found a saved application in progress. Would you like to continue where you left off?</p>
        <div class="rb-btns">
          <button class="rb-resume" onclick="resumeApplication()">Resume</button>
          <button class="rb-discard" onclick="discardSaved()">Start Fresh</button>
        </div>
      </div>

      <!-- PROGRESS BAR -->
      <div class="wizard-progress" id="wizard-progress">
        <div id="wiz-fill-bar" class="wiz-fill-bar" style="width:20%"></div>
        <div class="wp-step active" data-step="1"><div class="wp-circle">1</div><div class="wp-label">Your Details</div></div>
        <div class="wp-line"></div>
        <div class="wp-step" data-step="2"><div class="wp-circle">2</div><div class="wp-label">Choose Loan</div></div>
        <div class="wp-line"></div>
        <div class="wp-step" data-step="3"><div class="wp-circle">3</div><div class="wp-label">Payment</div></div>
        <div class="wp-line"></div>
        <div class="wp-step" data-step="4"><div class="wp-circle">4</div><div class="wp-label">Documents</div></div>
        <div class="wp-line"></div>
        <div class="wp-step" data-step="5"><div class="wp-circle">5</div><div class="wp-label">Review</div></div>
      </div>

      <div class="form-body">

        <!-- ═══ STEP 1: BORROWER & PARTIES ═══ -->
        <div class="wiz-panel active" id="wiz-1">
        <div class="f-section">
          <div class="f-head">1. Parties to the Agreement</div>
          <div class="f-sub">A. Borrower Information</div>
          <div class="f-grid">
            <div class="f-group" data-req="1"><label for="b_name">Full Name</label><input id="b_name" placeholder="Enter full legal name"><div class="f-err">Required</div></div>
            <div class="f-group" data-req="1"><label for="b_id">National ID / Passport No.</label><input id="b_id" placeholder="ID or Passport number"><div class="f-err">Required</div></div>
            <div class="f-group" data-req="1"><label for="b_dob">Date of Birth</label><input id="b_dob" type="date"></div>
            <div class="f-group" data-req="1"><label for="b_phone">Phone Number</label><input id="b_phone" type="tel" placeholder="0XXXXXXXXX"><div class="f-err">Required</div></div>
            <div class="f-group full" data-req="1"><label for="b_address">Physical Address</label><input id="b_address" placeholder="Street, Area, City"><div class="f-err">Required</div></div>
            <div class="f-group"><label for="b_email">Email Address</label><input id="b_email" type="email" placeholder="email@example.com"></div>
            <div class="f-group" data-req="1"><label for="b_occupation">Occupation / Employer</label><input id="b_occupation" placeholder="Job title and employer"><div class="f-err">Required</div></div>
            <div class="f-group" data-req="1"><label for="b_income">Monthly Income (ZMW)</label><input id="b_income" type="number" placeholder="0.00"><div class="f-err">Required</div></div>
          </div>

          <div class="f-sub" style="margin-top:18px">Security / Guarantee Type</div>
          <div class="method-grid">
            <label class="method-card mc-light"><input type="radio" name="security_type" value="collateral" onchange="toggleSecuritySection(this)"><span class="mc-icon">📦</span><span class="mc-name">Collateral</span><span class="mc-desc">Movable assets/items</span></label>
            <label class="method-card mc-light"><input type="radio" name="security_type" value="checkoff" onchange="toggleSecuritySection(this)"><span class="mc-icon">🏢</span><span class="mc-name">Salary Check-off</span><span class="mc-desc">Payroll deduction agreement</span></label>
            <label class="method-card mc-light"><input type="radio" name="security_type" value="employer_letter" onchange="toggleSecuritySection(this)"><span class="mc-icon">📋</span><span class="mc-name">Employer Letter</span><span class="mc-desc">Salary confirmation letter</span></label>
            <label class="method-card mc-light"><input type="radio" name="security_type" value="guarantor" onchange="toggleSecuritySection(this)"><span class="mc-icon">🤝</span><span class="mc-name">Guarantor</span><span class="mc-desc">Co-signer liable on default</span></label>
            <label class="method-card mc-light"><input type="radio" name="security_type" value="none" onchange="toggleSecuritySection(this)"><span class="mc-icon">✅</span><span class="mc-name">None</span><span class="mc-desc">Character-based / small loan</span></label>
          </div>

          <div class="f-sub" id="collateral-heading" style="margin-top:18px;display:none">Collateral / Security Items</div>
          <div id="collateral-list" style="display:none">
            <div class="collateral-item">
              <span>Item 1:</span><input placeholder="Description of item"><span style="color:var(--navy);font-weight:bold;white-space:nowrap;font-size:9pt;">Est. Value (ZMW):</span><input type="number" placeholder="0.00" style="border:1px solid var(--mgray);border-radius:4px;padding:8px;font-family:Arial;font-size:9pt;outline:none;">
            </div>
          </div>
          <button type="button" class="add-row-btn" id="add-collateral-btn" style="display:none" onclick="addCollateral()">➕ Add Another Item</button>

          <!-- SALARY CHECK-OFF DETAILS -->
          <div class="f-section" id="checkoff-section" style="display:none;margin-top:18px;border:1.5px solid var(--gold);border-radius:8px;padding:16px;background:rgba(217,142,59,.04)">
            <div class="f-sub" style="margin-top:0">Salary Check-off / Payroll Deduction Details</div>
            <p style="font-size:8.5pt;color:#666;margin-bottom:12px">The Borrower authorises their employer's payroll/HR department to deduct loan repayments directly from their salary and remit to Avesta Enterprises until the loan is fully repaid.</p>
            <div class="f-grid">
              <div class="f-group"><label for="co_employer">Employer / Company Name</label><input id="co_employer" placeholder="Full registered company name"></div>
              <div class="f-group"><label for="co_hr_name">HR / Payroll Contact Person</label><input id="co_hr_name" placeholder="Name of HR/Payroll officer"></div>
              <div class="f-group"><label for="co_hr_phone">HR / Payroll Contact Phone</label><input id="co_hr_phone" type="tel" placeholder="0XXXXXXXXX"></div>
              <div class="f-group"><label for="co_payroll_no">Employee Number / Payroll No.</label><input id="co_payroll_no" placeholder="Staff/payroll number"></div>
            </div>
          </div>

          <!-- EMPLOYER LETTER DETAILS -->
          <div class="f-section" id="employer-letter-section" style="display:none;margin-top:18px;border:1.5px solid var(--gold);border-radius:8px;padding:16px;background:rgba(217,142,59,.04)">
            <div class="f-sub" style="margin-top:0">Employer Confirmation Letter</div>
            <p style="font-size:8.5pt;color:#666;margin-bottom:12px">Please upload a signed letter from your employer confirming your employment status, position, and monthly salary, on company letterhead.</p>
            <div class="upload-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
              <div class="upload-item" id="employer-letter-box">
                <input type="file" id="doc_employer_letter" accept="image/*,.pdf" onchange="handleUpload(this,'employer-letter-box','employer-letter-name')" aria-label="Upload Employer Confirmation Letter">
                <div class="upload-icon">📋</div>
                <div class="upload-label">Employer Confirmation Letter</div>
                <div class="upload-hint">Signed letter on company<br>letterhead confirming salary</div>
                <div class="upload-filename" id="employer-letter-name"></div>
              </div>
            </div>
          </div>

          <!-- GUARANTOR DETAILS -->
          <div class="f-section" id="guarantor-section" style="display:none;margin-top:18px;border:1.5px solid var(--gold);border-radius:8px;padding:16px;background:rgba(217,142,59,.04)">
            <div class="f-sub" style="margin-top:0">Guarantor Details</div>
            <p style="font-size:8.5pt;color:#666;margin-bottom:12px">The Guarantor agrees to be jointly liable for repayment of this loan should the Borrower default. A Guarantor should be an employed individual able to support the loan amount.</p>
            <div class="f-grid">
              <div class="f-group"><label for="gtr_name">Guarantor Full Name</label><input id="gtr_name" placeholder="Guarantor full legal name"></div>
              <div class="f-group"><label for="gtr_id">Guarantor NRC / Passport No.</label><input id="gtr_id" placeholder="ID or Passport number"></div>
              <div class="f-group"><label for="gtr_phone">Guarantor Phone</label><input id="gtr_phone" type="tel" placeholder="0XXXXXXXXX"></div>
              <div class="f-group"><label for="gtr_relationship">Relationship to Borrower</label><input id="gtr_relationship" placeholder="e.g. Colleague, Relative"></div>
              <div class="f-group"><label for="gtr_occupation">Guarantor Occupation / Employer</label><input id="gtr_occupation" placeholder="Job title and employer"></div>
              <div class="f-group"><label for="gtr_income">Guarantor Monthly Income (ZMW)</label><input id="gtr_income" type="number" placeholder="0.00"></div>
            </div>
            <div class="upload-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:12px">
              <div class="upload-item" id="gtr-nrc-box">
                <input type="file" id="doc_gtr_nrc" accept="image/*,.pdf" onchange="handleUpload(this,'gtr-nrc-box','gtr-nrc-name')" aria-label="Upload Guarantor NRC Photo">
                <div class="upload-icon">🪪</div>
                <div class="upload-label">Guarantor NRC Photo</div>
                <div class="upload-hint">National Registration Card<br>(front &amp; back in one file)</div>
                <div class="upload-filename" id="gtr-nrc-name"></div>
              </div>
            </div>
          </div>

          <div class="f-sub" style="margin-top:18px">Borrower References <span style="color:#999;font-weight:normal;text-transform:none;letter-spacing:0">(optional)</span></div>
          <div class="ref-grid2" id="ref-list">
            <div class="ref-block2">
              <div class="rb-title">Reference 1</div>
              <div class="rb-body">
                <div><label>Full Name</label><input placeholder="Reference full name"></div>
                <div><label>Phone</label><input type="tel" placeholder="0XXXXXXXXX"></div>
                <div><label>Relationship</label><input placeholder="e.g. Colleague, Neighbour"></div>
              </div>
            </div>
          </div>
          <button type="button" class="add-row-btn" onclick="addReference()">➕ Add Another Reference</button>

          <div class="f-sub" style="margin-top:18px">B. Lender Information</div>
          <div class="f-grid">
            <div class="f-group"><label>Lender Name</label><input value="Avesta Enterprises" readonly></div>
            <div class="f-group"><label>Phone</label><input value="0971013108 / 0769974200" readonly></div>
            <div class="f-group full"><label>Address</label><input value="House No. 3, Thom Avenue, Kansenshi, Ndola" readonly></div>
            <div class="f-group"><label>Email</label><input value="info@avesta.solutions" readonly></div>
          </div>
        </div>
        </div><!-- /wiz-1 -->

        <!-- ═══ STEP 2: LOAN DETAILS ═══ -->
        <div class="wiz-panel" id="wiz-2">
        <div id="step2-err-banner" class="step-error-banner"><span class="seb-icon">⚠️</span><span id="step2-err-text"></span></div>
        <div class="f-section">
          <div class="f-head">2. Loan Details</div>
          <div class="f-grid">
            <div class="f-group" data-req="1"><label for="loan_amt">Loan Amount (ZMW)</label><input type="number" placeholder="0.00" id="loan_amt" oninput="updateTotals()"><div class="f-err">Required</div></div>
            <div class="f-group auto-field"><label for="loan_words">Amount in Words <span style="color:#999;font-weight:normal">(auto)</span></label>
              <input id="loan_words" placeholder="Auto-filled from amount above" style="background:#f9f9f9;font-size:8.5pt"
                onfocus="this.style.background='white'" onblur="this.style.background='#f9f9f9'"
                oninput="this.dataset.userEdited='true'">
            </div>
            <div class="f-group full" data-req="1"><label for="loan_purpose">Purpose of Loan</label><input id="loan_purpose" placeholder="Reason for borrowing"></div>
            <div class="f-group full" data-req="1"><label>How Borrower Will Receive Funds</label>
              <div class="method-grid">
                <label class="method-card"><input type="radio" name="disburse" value="cash" onchange="showDisburseDetail(this)"><span class="mc-icon">💵</span><span class="mc-name">Cash</span><span class="mc-desc">Collect at our office</span></label>
                <label class="method-card"><input type="radio" name="disburse" value="mobile_airtel" onchange="showDisburseDetail(this)"><span class="mc-icon">📱</span><span class="mc-name">Airtel Money</span><span class="mc-desc">Mobile money</span></label>
                <label class="method-card"><input type="radio" name="disburse" value="mobile_mtn" onchange="showDisburseDetail(this)"><span class="mc-icon">📲</span><span class="mc-name">MTN MoMo</span><span class="mc-desc">Mobile money</span></label>
                <label class="method-card"><input type="radio" name="disburse" value="zamtel" onchange="showDisburseDetail(this)"><span class="mc-icon">📡</span><span class="mc-name">Zamtel</span><span class="mc-desc">Mobile money</span></label>
                <label class="method-card"><input type="radio" name="disburse" value="bank_zanaco" onchange="showDisburseDetail(this)"><span class="mc-icon">🏦</span><span class="mc-name">ZANACO</span><span class="mc-desc">Bank transfer</span></label>
                <label class="method-card"><input type="radio" name="disburse" value="bank_fnb" onchange="showDisburseDetail(this)"><span class="mc-icon">🏦</span><span class="mc-name">FNB Zambia</span><span class="mc-desc">Bank transfer</span></label>
                <label class="method-card"><input type="radio" name="disburse" value="bank_stanbic" onchange="showDisburseDetail(this)"><span class="mc-icon">🏦</span><span class="mc-name">Stanbic</span><span class="mc-desc">Bank transfer</span></label>
                <label class="method-card"><input type="radio" name="disburse" value="bank_other" onchange="showDisburseDetail(this)"><span class="mc-icon">🏦</span><span class="mc-name">Other Bank</span><span class="mc-desc">Bank transfer</span></label>
              </div>
              <div id="disburse_detail" class="method-detail" style="display:none"></div>
              <div class="f-err" id="disburse-err">Please select a method</div>
            </div>
            <div class="f-group"><label for="disburse_date">Disbursement Date</label><input id="disburse_date" type="date" onchange="updateRepaymentDates()"></div>
          </div>
        </div>
        </div><!-- /wiz-2 -->

        <!-- ═══ STEP 3: REPAYMENT TERMS ═══ -->
        <div class="wiz-panel" id="wiz-3">
        <div id="step3-err-banner" class="step-error-banner"><span class="seb-icon">⚠️</span><span id="step3-err-text"></span></div>
        <div class="f-section">
          <div class="f-head">3. Repayment Terms</div>
          <div class="f-sub" data-req="1">Choose Loan Duration <span style="color:#c0392b">*</span></div>
          <div class="dur-rate-row">
            <div class="dur-tile" onclick="selectDur(this,1,15)"><div class="dt-weeks">1 Week</div><div class="dt-rate">15%</div><div class="dt-tag">Interest</div></div>
            <div class="dur-tile" onclick="selectDur(this,2,20)"><div class="dt-weeks">2 Weeks</div><div class="dt-rate">20%</div><div class="dt-tag">Interest</div></div>
            <div class="dur-tile" onclick="selectDur(this,3,25)"><div class="dt-weeks">3 Weeks</div><div class="dt-rate">25%</div><div class="dt-tag">Interest</div></div>
            <div class="dur-tile" onclick="selectDur(this,4,30)"><div class="dt-weeks">4 Weeks</div><div class="dt-rate">30%</div><div class="dt-tag">Interest</div></div>
            <div class="dur-tile" id="dur-tile-custom" onclick="selectLongTermDur(this)"><div class="dt-weeks">More than 1 Month</div><div class="dt-rate">+5%/wk</div><div class="dt-tag">Custom Term</div></div>
          </div>
          <div class="f-grid" id="custom-dur-detail" style="display:none;margin-top:10px;margin-bottom:4px">
            <div class="f-group"><label for="custom_weeks">Number of Weeks <span style="color:#999;font-weight:normal">(5 or more)</span></label><input id="custom_weeks" type="number" min="5" step="1" placeholder="e.g. 8" oninput="updateLongTermDur()"></div>
            <div class="f-group auto-field"><label for="custom_rate_display">Applicable Interest Rate</label><input id="custom_rate_display" placeholder="Auto-calculated" readonly></div>
          </div>
          <p style="font-size:8pt;color:#888;margin:-2px 0 8px">For loans longer than 4 weeks (1 month), interest increases by an additional 5% for every extra week — e.g. 5 weeks = 35%, 8 weeks = 50%.</p>
          <div class="f-err" id="dur-err" style="margin-bottom:8px">Please select a loan duration above</div>
          <input type="hidden" id="sel_dur"><input type="hidden" id="int_rate">
          <div class="f-grid" style="margin-top:14px">
            <div class="f-group auto-field"><label for="total_int">Total Interest Amount (ZMW)</label><input id="total_int" placeholder="Auto-calculated" readonly></div>
            <div class="f-group auto-field"><label for="total_repay">Total Repayment Amount (ZMW)</label><input id="total_repay" placeholder="Auto-calculated" readonly></div>
            <div class="f-group auto-field"><label for="num_inst">Number of Installments</label><input type="number" placeholder="Choose a repayment schedule" id="num_inst" readonly></div>
            <div class="f-group auto-field"><label for="inst_amt">Installment Amount (ZMW)</label><input id="inst_amt" placeholder="Auto-calculated" readonly></div>
            <div class="f-group auto-field"><label for="late_rate_display">Late Payment Interest Rate</label><input id="late_rate_display" placeholder="Auto: 7.5% per week overdue" readonly></div>
            <div class="f-group"><label for="weeks_overdue">Weeks Overdue <span style="color:#999;font-weight:normal">(for illustration)</span></label><input id="weeks_overdue" type="number" min="0" step="1" placeholder="0" oninput="updateLateFee()"></div>
            <div class="f-group auto-field"><label for="late_fee">Late Interest Amount (ZMW)</label><input id="late_fee" placeholder="Auto-calculated" readonly></div>
            <div class="f-group auto-field"><label for="first_payment">First Payment Due Date <span style="color:#999;font-weight:normal">(auto, editable)</span></label><input id="first_payment" type="date" oninput="userEditedDate('first_payment')"></div>
            <div class="f-group auto-field"><label for="last_payment">Last Payment Due Date <span style="color:#999;font-weight:normal">(auto, editable)</span></label><input id="last_payment" type="date" oninput="userEditedDate('last_payment')"></div>
            <div class="f-group full">
              <div class="commitment-card" aria-live="polite">
                <div><div class="cc-label">You receive</div><div class="cc-value" id="cc-principal">ZMW —</div></div>
                <div><div class="cc-label">Interest</div><div class="cc-value" id="cc-interest">ZMW —</div></div>
                <div><div class="cc-label">Total to repay</div><div class="cc-value" id="cc-total">ZMW —</div></div>
              </div>
            </div>
            <div class="f-group full"><label>Repayment Schedule</label>
              <div class="check-pills" style="margin-top:8px">
                <label class="check-pill"><input type="checkbox" value="Weekly" onchange="updateRepaymentSchedule(this)"> <span>📅 Weekly</span></label>
                <label class="check-pill"><input type="checkbox" value="Bi-Weekly" onchange="updateRepaymentSchedule(this)"> <span>📅 Bi-Weekly</span></label>
                <label class="check-pill"><input type="checkbox" value="Monthly" onchange="updateRepaymentSchedule(this)"> <span>📅 Monthly</span></label>
                <label class="check-pill"><input type="checkbox" value="Lump Sum" onchange="updateRepaymentSchedule(this)"> <span>💰 Lump Sum at End</span></label>
              </div>
              <p id="repayment-plan-summary" aria-live="polite" style="font-size:8.5pt;color:#555;margin-top:10px">Choose one repayment schedule to see the payment count and amounts. The monthly option uses four-week intervals, with the final payment due at the end of the term.</p>
            </div>
          </div>
          <p style="font-size:8.5pt;color:#666;margin-top:12px">Late-charge illustration: the fields above use 7.5% of the scheduled total repayment for each week entered. This example does not account for partial payments or the grace period. Your signed agreement confirms any actual late charges.</p>
          <div class="f-sub" style="margin-top:18px" data-req="1">How Borrower Will Make Repayments <span style="color:#c0392b">*</span></div>
          <div class="method-grid">
            <label class="method-card mc-light"><input type="radio" name="repay_method" value="cash" onchange="showRepayDetail(this)"><span class="mc-icon">💵</span><span class="mc-name">Cash</span><span class="mc-desc">Pay at our office</span></label>
            <label class="method-card mc-light"><input type="radio" name="repay_method" value="mobile_airtel" onchange="showRepayDetail(this)"><span class="mc-icon">📱</span><span class="mc-name">Airtel Money</span><span class="mc-desc">Send to our number</span></label>
            <label class="method-card mc-light"><input type="radio" name="repay_method" value="mobile_mtn" onchange="showRepayDetail(this)"><span class="mc-icon">📲</span><span class="mc-name">MTN MoMo</span><span class="mc-desc">Send to our number</span></label>
            <label class="method-card mc-light"><input type="radio" name="repay_method" value="zamtel" onchange="showRepayDetail(this)"><span class="mc-icon">📡</span><span class="mc-name">Zamtel</span><span class="mc-desc">Send to our number</span></label>
            <label class="method-card mc-light"><input type="radio" name="repay_method" value="bank_zanaco" onchange="showRepayDetail(this)"><span class="mc-icon">🏦</span><span class="mc-name">ZANACO</span><span class="mc-desc">Bank transfer</span></label>
            <label class="method-card mc-light"><input type="radio" name="repay_method" value="bank_fnb" onchange="showRepayDetail(this)"><span class="mc-icon">🏦</span><span class="mc-name">FNB Zambia</span><span class="mc-desc">Bank transfer</span></label>
            <label class="method-card mc-light"><input type="radio" name="repay_method" value="bank_stanbic" onchange="showRepayDetail(this)"><span class="mc-icon">🏦</span><span class="mc-name">Stanbic</span><span class="mc-desc">Bank transfer</span></label>
            <label class="method-card mc-light"><input type="radio" name="repay_method" value="bank_other" onchange="showRepayDetail(this)"><span class="mc-icon">🏦</span><span class="mc-name">Other Bank</span><span class="mc-desc">Bank transfer</span></label>
          </div>
          <div id="repay_detail" class="method-detail" style="display:none"></div>
          <div class="f-err" id="repay-err">Please select a repayment method above</div>
        </div>
        </div><!-- /wiz-3 -->

        <!-- ═══ STEP 4: DOCUMENTS ═══ -->
        <div class="wiz-panel" id="wiz-4">
        <div id="step4-err-banner" class="step-error-banner"><span class="seb-icon">⚠️</span><span id="step4-err-text"></span></div>
        <div class="f-section">
          <div class="f-head">4. Supporting Documents</div>
          <p style="font-size:8.5pt;color:#666;margin-bottom:6px">Upload clear photos or scans. Accepted: JPG, PNG, WebP, PDF (max 8 MB each).</p>

          <!-- Doc counter -->
          <div id="doc-counter-bar" style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding:10px 14px;background:#fff5f5;border-radius:8px;border:1.5px solid #f5c6cb">
            <span style="font-size:12pt">📎</span>
            <span style="font-size:9pt;color:#333;font-weight:600"><span id="doc-count-num">0</span> document<span id="doc-count-plural">s</span> attached</span>
            <span style="font-size:8pt;margin-left:auto;font-weight:600;color:#c0392b" id="doc-counter-hint">⚠️ NRC (both sides) required</span>
          </div>

          <!-- ── REQUIRED: NRC, both sides. The passport photo is optional. ──
     It used to be "NRC or passport photo", which let a photograph of a face
     stand in for an identity document. A photo is not ID. -->
          <div style="font-size:8pt;font-weight:700;color:#c0392b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px">
            ✱ Required — Upload both sides of your NRC
          </div>
          <div class="upload-grid" style="margin-bottom:18px">

            <div class="upload-item required-doc" id="nrc-front-box">
              <input type="file" id="doc_nrc_front" accept="image/*,.pdf" onchange="handleUpload(this,'nrc-front-box','nrc-front-name')" aria-label="Upload NRC — Front Side">
              <div class="upload-icon">🪪</div>
              <div class="upload-label">NRC — Front Side <span style="color:#c0392b;font-size:7.5pt;font-weight:700">REQUIRED</span></div>
              <div class="upload-hint">Photo of the front of your<br>National Registration Card</div>
              <div class="upload-filename" id="nrc-front-name"></div>
            </div>

            <div class="upload-item required-doc" id="nrc-back-box">
              <input type="file" id="doc_nrc_back" accept="image/*,.pdf" onchange="handleUpload(this,'nrc-back-box','nrc-back-name')" aria-label="Upload NRC — Back Side">
              <div class="upload-icon">🪪</div>
              <div class="upload-label">NRC — Back Side <span style="color:#c0392b;font-size:7.5pt;font-weight:700">REQUIRED</span></div>
              <div class="upload-hint">Photo of the back of your<br>National Registration Card</div>
              <div class="upload-filename" id="nrc-back-name"></div>
            </div>



          </div>

          <!-- ── COLLATERAL PHOTOS (shown when security=collateral) ───── -->
          <div id="collateral-photo-section" style="display:none;margin-bottom:18px">
            <div style="font-size:8pt;font-weight:700;color:#c0392b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px">
              ✱ Required — Photos of Collateral Items
            </div>
            <p style="font-size:8pt;color:#666;margin-bottom:10px">Since you selected Collateral as security, please upload clear photos of the items you listed. At least 1 photo required.</p>
            <div class="upload-grid">
              <div class="upload-item required-doc" id="collateral1-box">
                <input type="file" id="doc_collateral1" accept="image/*,.pdf" onchange="handleUpload(this,'collateral1-box','collateral1-name')" aria-label="Upload Collateral Photo 1">
                <div class="upload-icon">📦</div>
                <div class="upload-label">Collateral Photo 1 <span style="color:#c0392b;font-size:7.5pt;font-weight:700">REQUIRED</span></div>
                <div class="upload-hint">Clear photo of the item<br>offered as security</div>
                <div class="upload-filename" id="collateral1-name"></div>
              </div>
              <div class="upload-item" id="collateral2-box">
                <input type="file" id="doc_collateral2" accept="image/*,.pdf" onchange="handleUpload(this,'collateral2-box','collateral2-name')" aria-label="Upload Collateral Photo 2 (optional)">
                <div class="upload-icon">📦</div>
                <div class="upload-label">Collateral Photo 2 <span class="opt-tag">(optional)</span></div>
                <div class="upload-hint">Additional photo or<br>different angle</div>
                <div class="upload-filename" id="collateral2-name"></div>
              </div>
              <div class="upload-item" id="collateral3-box">
                <input type="file" id="doc_collateral3" accept="image/*,.pdf" onchange="handleUpload(this,'collateral3-box','collateral3-name')" aria-label="Upload Collateral Photo 3 (optional)">
                <div class="upload-icon">📦</div>
                <div class="upload-label">Collateral Photo 3 <span class="opt-tag">(optional)</span></div>
                <div class="upload-hint">Additional item or<br>supporting evidence</div>
                <div class="upload-filename" id="collateral3-name"></div>
              </div>
            </div>
            <p id="collateral-photo-err" class="f-err" style="display:none;margin-top:8px;font-size:8.5pt"></p>
          </div>

          <!-- Optional evidence stays available without crowding the required uploads. -->
          <details id="optional-supporting-documents" style="margin:16px 0">
          <summary style="font-weight:700;cursor:pointer;padding:12px 0">Optional supporting documents</summary>
          <p style="font-size:8.5pt;color:#666;margin:8px 0 14px">Upload only documents relevant to your application. A recent payslip or bank statement can support your income details; you do not need to upload the same document twice. Security documents are requested when you choose that security arrangement.</p>
          <div class="upload-grid">

            <div class="upload-item" id="passport-box">
              <input type="file" id="doc_passport" accept="image/*,.pdf" onchange="handleUpload(this,'passport-box','passport-name')" aria-label="Upload Passport Photo">
              <div class="upload-icon">🖼️</div>
              <div class="upload-label">Passport Photo <span style="color:#6c757d;font-size:7.5pt;font-weight:700">OPTIONAL</span></div>
              <div class="upload-hint">Recent passport-size<br>photograph (colour)</div>
              <div class="upload-filename" id="passport-name"></div>
            </div>

            <div class="upload-item" id="salary1-box">
              <input type="file" id="doc_salary1" accept="image/*,.pdf" onchange="handleUpload(this,'salary1-box','salary1-name')" aria-label="Upload Salary Proof – Month 1">
              <div class="upload-icon">📄</div>
              <div class="upload-label">Salary Proof – Month 1</div>
              <div class="upload-hint">Payslip or bank statement<br>(most recent month)</div>
              <div class="upload-filename" id="salary1-name"></div>
            </div>

            <div class="upload-item" id="salary2-box">
              <input type="file" id="doc_salary2" accept="image/*,.pdf" onchange="handleUpload(this,'salary2-box','salary2-name')" aria-label="Upload Salary Proof – Month 2">
              <div class="upload-icon">📄</div>
              <div class="upload-label">Salary Proof – Month 2</div>
              <div class="upload-hint">Payslip or bank statement<br>(previous month)</div>
              <div class="upload-filename" id="salary2-name"></div>
            </div>

            <div class="upload-item" id="payslip-box">
              <input type="file" id="doc_payslip" accept="image/*,.pdf" onchange="handleUpload(this,'payslip-box','payslip-name')" aria-label="Upload Payslip / Proof of Income">
              <div class="upload-icon">💼</div>
              <div class="upload-label">Payslip / Proof of Income</div>
              <div class="upload-hint">Most recent payslip or<br>any proof of income</div>
              <div class="upload-filename" id="payslip-name"></div>
            </div>

            <div class="upload-item" id="bankstmt-box">
              <input type="file" id="doc_bank_statement" accept="image/*,.pdf" onchange="handleUpload(this,'bankstmt-box','bankstmt-name')" aria-label="Upload Bank Statement">
              <div class="upload-icon">🏦</div>
              <div class="upload-label">Bank Statement</div>
              <div class="upload-hint">Last 3 months bank<br>statement (PDF or photo)</div>
              <div class="upload-filename" id="bankstmt-name"></div>
            </div>

            <div class="upload-item" id="utility-box">
              <input type="file" id="doc_utility_bill" accept="image/*,.pdf" onchange="handleUpload(this,'utility-box','utility-name')" aria-label="Upload Utility Bill">
              <div class="upload-icon">🏠</div>
              <div class="upload-label">Utility Bill</div>
              <div class="upload-hint">ZESCO, water or any bill<br>showing your address</div>
              <div class="upload-filename" id="utility-name"></div>
            </div>

            <div class="upload-item" id="employer-letter-ref" style="position:relative">
              <div class="upload-icon">📋</div>
              <div class="upload-label">Employer Letter</div>
              <div class="upload-hint">Uploaded in security section<br>(Step 1 if applicable)</div>
              <div class="upload-filename" id="employer-letter-ref-status" style="font-size:8pt;color:#888;margin-top:4px">Check Step 1 → Security</div>
            </div>

            <div class="upload-item" id="gtrnrc-ref" style="position:relative">
              <div class="upload-icon">🪪</div>
              <div class="upload-label">Guarantor NRC</div>
              <div class="upload-hint">Uploaded in security section<br>(Step 1 if applicable)</div>
              <div class="upload-filename" id="gtrnrc-ref-status" style="font-size:8pt;color:#888;margin-top:4px">Check Step 1 → Security</div>
            </div>

          </div>

          </details>

          <p id="doc-upload-err" class="f-err" style="display:none;margin-top:12px;font-size:8.5pt"></p>
          <p class="doc-note" style="margin-top:12px">✱ Both sides of your NRC are mandatory. The passport photo and other documents are optional but help speed up your approval. All files are kept securely.</p>
        </div>
        </div><!-- /wiz-4 -->

        <!-- ═══ STEP 5: REVIEW, TERMS, GPS & SIGN ═══ -->
        <div class="wiz-panel" id="wiz-5">

          <div class="f-section">
            <div class="f-head">5. Review Your Application</div>
            <p style="font-size:8.5pt;color:#888;margin-bottom:12px">Please check everything below carefully. Click "Edit" next to any section to make changes before signing.</p>
            <div id="review-content"></div>
          </div>

          <div class="f-section">
            <div class="f-head">6. Terms and Conditions</div>
            <div style="font-size:8.5pt;line-height:1.7;color:#555">
              <p style="margin-bottom:8px"><strong style="color:var(--navy)">6.1 Repayment Obligation —</strong> The Borrower agrees to repay the full loan amount plus interest according to the repayment schedule outlined in Section 3. Failure to make timely payments will result in the late fee specified above.</p>
              <p style="margin-bottom:8px"><strong style="color:var(--navy)">6.2 Default —</strong> If the Borrower fails to make any payment within the grace period, the entire outstanding balance shall become immediately due and payable. The Lender reserves the right to recover the outstanding balance through all available legal means.</p>
              <p style="margin-bottom:8px"><strong style="color:var(--navy)">6.3 Collateral —</strong> The collateral items listed in Section 1 are pledged as security for this loan. In the event of default, the Lender shall have the right to take possession of the collateral items to recover the outstanding balance.</p>
              <p style="margin-bottom:8px"><strong style="color:var(--navy)">6.4 Early Repayment —</strong> The Borrower may repay the loan in full at any time before the last payment date without penalty. Any early repayment shall be applied first to outstanding fees, then to interest, and finally to principal.</p>
              <p style="margin-bottom:8px"><strong style="color:var(--navy)">6.5 Governing Law —</strong> This Agreement shall be governed by the laws of the Republic of Zambia. Any disputes arising from this Agreement shall be resolved amicably or through the appropriate courts of Zambia.</p>
              <p style="margin-bottom:8px"><strong style="color:var(--navy)">6.6 Amendments —</strong> Any modification to this Agreement must be made in writing and signed by both Parties to be valid and enforceable.</p>
              <p><strong style="color:var(--navy)">6.7 Entire Agreement —</strong> This Agreement constitutes the entire agreement between the Parties and supersedes all prior discussions, representations, or agreements relating to the subject matter herein.</p>
            </div>
            <label style="display:flex;align-items:flex-start;gap:8px;margin-top:14px;font-size:9pt;cursor:pointer">
              <input type="checkbox" id="agree_terms" style="margin-top:2px;accent-color:var(--navy)" onchange="const e=document.getElementById('terms-err');if(e&&this.checked)e.style.display='none';updateFieldCounter()">
              <span>I, the Borrower, have read and agree to the Terms and Conditions above, and confirm that all information provided in this application is true and accurate.</span>
            </label>
          <p id="terms-err" class="f-err" style="display:none;margin-top:4px"></p>
          </div>

          <!-- GPS LOCATION CONSENT -->
          <div class="f-section">
            <div class="f-head">📍 Location Verification</div>
            <div class="gps-consent-box">
              <div class="gps-icon">📡</div>
              <div class="gps-text">
                <h4>Allow Location Access</h4>
                <p>To help verify your application and for security purposes, Avesta Enterprises requests your current GPS location at the time of submission. By signing below, you consent to share your location alongside your application. This helps us confirm you are within our service area and protects against fraudulent applications.</p>
                <button class="gps-btn" id="gps-btn" onclick="captureGPS()">📍 Capture My Location</button>
                <div class="gps-coords" id="gps-coords"></div>
              </div>
            </div>
            <input type="hidden" id="gps_lat">
            <input type="hidden" id="gps_lng">
            <input type="hidden" id="gps_accuracy">
          </div>

          <!-- SIGNATURES -->
          <div class="f-section">
            <div class="f-head">7. Signatures</div>
            <p style="font-style:italic;font-size:8.5pt;color:#888;margin-bottom:16px">Please sign using your finger or mouse in the box below. By signing, both Parties agree to be bound by the terms and conditions of this Loan Agreement.</p>
            <div class="sig-row2">
              <div class="sig-box2 borrower" data-req="1">
                <div class="sig-title">BORROWER</div>
                <div class="sig-field2">
                  <div class="sigpad-wrap" id="sigwrap_borrower">
                    <canvas id="sigpad_borrower"></canvas>
                    <div class="sigpad-placeholder">Sign here with finger or mouse</div>
                  </div>
                  <div class="sigpad-btnrow">
                    <button type="button" class="sigpad-clear" onclick="clearSig('borrower')">🗑 Clear</button>
                    <button type="button" class="sigpad-clear sigpad-undo" id="sig-undo-borrower" onclick="undoSig('borrower')" disabled>↩ Undo</button>
                    <button type="button" class="sigpad-clear sigpad-redo" id="sig-redo-borrower" onclick="redoSig('borrower')" disabled>↪ Redo</button>
                  </div>
                  <p id="borrower-sig-err" class="f-err" style="display:none;margin-top:4px"></p>
                  <div class="sig-lbl">Signature</div>
                  <div class="f-err">Borrower signature is required</div>
                </div>
                <div class="sig-field2"><input id="sig_borrower_name" placeholder="Print full name"><div class="sig-lbl">Printed Name</div></div>
                <div class="sig-field2"><input id="sig_borrower_date" aria-label="Date signed by borrower" type="date"><div class="sig-lbl">Date</div></div>
              </div>
              <div class="sig-box2 lender">
                <div class="sig-title">LENDER — AVESTA ENTERPRISES</div>
                <div class="sig-field2">
                  <div class="sigpad-wrap" id="sigwrap_lender">
                    <canvas id="sigpad_lender"></canvas>
                    <div class="sigpad-placeholder" style="color:rgba(255,255,255,.3)">Official signature</div>
                  </div>
                  <!-- Lender signature is fixed — pre-loaded from LENDER_SIG_DATA -->
                  <div style="font-size:7pt;color:rgba(217,142,59,.6);margin-top:3px;font-style:italic">🔒 Official signature — not editable</div>
                  <div class="sig-lbl">Authorised Signature</div>
                </div>
                <div class="sig-field2"><input id="sig_lender_name" placeholder="Print full name" value="Avesta Enterprises"><div class="sig-lbl">Printed Name</div></div>
                <div class="sig-field2"><input id="sig_lender_date" aria-label="Date signed by lender" type="date"><div class="sig-lbl">Date</div></div>
              </div>
            </div>
            <div class="f-sub">Witness (Optional)</div>
            <div class="f-grid">
              <div class="f-group"><label for="wit_name">Witness Full Name</label><input id="wit_name" placeholder="Witness full name"></div>
              <div class="f-group"><label for="wit_phone">Witness Phone</label><input id="wit_phone" type="tel" placeholder="0XXXXXXXXX"></div>
              <div class="f-group"><label for="wit_sig">Witness Signature</label><input id="wit_sig" placeholder="Sign here"></div>
              <div class="f-group"><label for="wit_date">Date</label><input id="wit_date" type="date"></div>
            </div>
            <div class="office-use">
              <div class="ou-title">OFFICE USE ONLY</div>
              <div class="ou-body">
                <div class="f-group"><label for="ou_ref">Loan Reference #</label><input id="ou_ref" placeholder="REF-XXXXXX"></div>
                <div class="f-group"><label for="ou_staff">Processed By</label><input id="ou_staff" placeholder="Staff name"></div>
                <div class="f-group"><label for="ou_date">Date Processed</label><input id="ou_date" type="date"></div>
              </div>
            </div>
          </div>

        </div><!-- /wiz-5 -->

      </div><!-- /form-body -->

      <!-- SUBMIT STATUS -->
      <div id="submit-status" style="display:none;border-top:2px solid var(--gold)"></div>

      <!-- WIZARD NAV -->
      <div class="wiz-nav">
        <div class="left-grp">
          <button type="button" class="btn-nav btn-nav-back" id="wiz-back" onclick="wizNav(-1)" disabled>← Back</button>
          <button class="btn-ghost" onclick="clearForm()">✕ Clear All</button>
        </div>
        <div class="right-grp">
          <span class="field-counter" id="field-counter"></span>
          <button type="button" class="btn-nav btn-nav-next" id="wiz-next" onclick="wizNav(1)">Next →</button>
          <button class="btn-gold" id="submit-btn" onclick="submitToSheet()" style="display:none">✅ Submit Application</button>
          <button class="btn-gold" onclick="window.print()" style="background:var(--navy);display:none" id="print-btn">🖨 Print / PDF</button>
        </div>
      </div>
    </div>
  </div><!-- /tab-apply -->


  <!-- ═══ TAB: ABOUT US ════════════════════════════════════════════════════ -->
  <div data-site="both" class="tab-panel" id="tab-about">
    <!-- Hero block -->
    <div class="about-hero">
      <div class="about-hero-text">
        <div class="section-tag" style="margin-bottom:14px">Who We Are</div>
        <h2>Avesta <span>Enterprises</span></h2>
        <p><span data-copy data-lending="Avesta Lending is a micro-lending business headquartered in Ndola, providing fast, fair, short-term finance to individuals and small businesses anywhere in Zambia. Terms are stated up front, repayment schedules are yours to choose, and there is nothing hidden in the agreement." data-it="Avesta Consulting is an IT practice headquartered in Ndola, keeping the technology that businesses and homes across Zambia depend on running — repairs, networks, CCTV, cybersecurity, websites and training. We tell you what a job takes and what it costs before any work starts.">Avesta Lending is a micro-lending business in Ndola, providing fast, fair, short-term finance to individuals and small businesses across the Copperbelt.</span> <span data-copy data-lending="Since our founding we have helped hundreds of clients access the funds they need, when they need them most — starting on the Copperbelt and now right across Zambia." data-it="We work with offices, shops and households across Zambia, from a single laptop to a whole network and camera system. Remote work reaches anywhere; on-site visits cover the Copperbelt and Lusaka, and further by arrangement.">Since our founding we have helped hundreds of clients in Ndola and across the Copperbelt access the funds they need, when they need them most.</span></p>
        <p style="margin-top:12px"><span data-copy data-lending="Our mission is simple: make lending easy, transparent and accessible to every Zambian." data-it="Our mission is simple: make good IT support something a small Zambian business can actually get hold of.">Our mission is simple: make lending easy, transparent and accessible to every Zambian.</span></p>

      </div>
      <div class="about-hero-stats">
        <div class="about-stat"><div class="as-num">Fast</div><div class="as-lbl">Approval</div></div>
        <div class="about-stat"><div class="as-num">4</div><div class="as-lbl">Loan Plans</div></div>
        <div class="about-stat"><div class="as-num">100%</div><div class="as-lbl">Transparent</div></div>
        <div class="about-stat"><div class="as-num">ZMW</div><div class="as-lbl">Local Currency</div></div>
      </div>
    </div>

    <!-- Values -->
    <div class="section-tag" style="display:block;text-align:center;margin-bottom:20px">Our Values</div>
    <div class="about-values">
      <div class="about-value">
        <div class="av-icon">🤝</div>
        <h3>Trust &amp; Integrity</h3>
        <p><span data-copy data-lending="Every loan agreement is clear and honest. No hidden charges, no surprise fees. We believe in building long-term relationships based on trust." data-it="You are told what a job needs and what it costs before work starts. No surprise call-out fees, and no selling you hardware you do not need.">Every loan agreement is clear and honest. No hidden charges, no surprise fees. We believe in building long-term relationships based on trust.</span></p>
      </div>
      <div class="about-value">
        <div class="av-icon">⚡</div>
        <h3><span data-copy data-lending="Speed & Convenience" data-it="Back Working Fast">Speed &amp; Convenience</span></h3>
        <p><span data-copy data-lending="We understand that financial needs are often urgent. Our streamlined process ensures you get a decision quickly and funds disbursed the same day." data-it="A dead machine or a network down is a business not trading. We aim to get you working again the same day wherever the fault allows it.">We understand that financial needs are often urgent. Our streamlined process ensures you get a decision quickly and funds disbursed the same day.</span></p>
      </div>
      <div class="about-value">
        <div class="av-icon">🛡️</div>
        <h3><span data-copy data-lending="Responsible Lending" data-it="Honest Diagnosis">Responsible Lending</span></h3>
        <p><span data-copy data-lending="We assess each application carefully to ensure repayment terms are manageable. We care about your financial wellbeing, not just the transaction." data-it="We fix the cause rather than the symptom, and we tell you plainly when equipment is past repairing instead of billing you to nurse it along.">We assess each application carefully to ensure repayment terms are manageable. We care about your financial wellbeing, not just the transaction.</span></p>
      </div>
      <div class="about-value">
        <div class="av-icon">🌍</div>
        <h3>Community Focus</h3>
        <p>Rooted in Ndola and reaching across Zambia, we exist to serve the communities we work in. <span data-copy data-lending="Every loan we provide helps a neighbour meet a goal, solve a problem," data-it="Every system we keep running helps a neighbour trade, study or stay safe,">Every loan we provide helps a neighbour meet a goal, solve a problem,</span> or grow a business.</p>
      </div>
    </div>

    <!-- Our Story -->
    <div class="about-team">
      <h3>Our Story</h3>
      <p>Avesta Enterprises was founded with a clear vision: to bridge the gap between everyday Zambians and accessible finance. We noticed that many hard-working people were being turned away by large banks due to lengthy processes and rigid requirements. We set out to create a different kind of lending company — one that treats every client with dignity, moves fast, and keeps things simple.</p>
      <p style="margin-top:12px">Operating from our office on Thom Avenue in Kansenshi, Ndola, we serve clients from across the Copperbelt. Whether you need funds for a school fee, medical emergency, business stock, or home improvement — Avesta is here for you.</p>
      <p style="margin-top:12px"><strong style="color:var(--navy)">Office Hours:</strong> Monday – Saturday, 08:00 – 17:00 &nbsp;|&nbsp; <strong style="color:var(--navy)">Location:</strong> House No. 3, Thom Avenue, Kansenshi, Ndola &nbsp;|&nbsp; <strong style="color:var(--navy)">Phones:</strong> 0971 013 108 / 0769 974 200</p>
    </div>

    <!-- CTA -->
    <div class="about-cta">
      <h3>Ready to Get Started?</h3>
      <p>Apply for a loan today — it only takes a few minutes and you could receive funds the same day.</p>
      <button class="btn-primary" onclick="showTab('apply')">Apply for a Loan →</button>
    </div>
  </div><!-- /tab-about -->


  <!-- ═══ TAB: CONTACT ═════════════════════════════════════════════════════ -->
  <div data-site="both" class="tab-panel" id="tab-contact">
    <div class="center" style="padding-bottom:0">
      <div class="section-tag">Get in Touch</div>
      <h2 class="section-title">Contact Us</h2>
      <p class="section-sub"><span data-copy data-lending="Have questions about a loan? Reach out — we're happy to help." data-it="Need something fixed, set up or secured? Tell us what you are dealing with.">Have questions about a loan? Reach out — we're happy to help.</span></p>
          <p class="coverage"><strong>Where we work.</strong>
            Lending is available to anyone in Zambia &mdash; apply online, and
            disbursement and repayment run through mobile money or bank transfer,
            so distance is no obstacle.
            IT work that can be done remotely &mdash; websites, databases, data work,
            cybersecurity, training and advice &mdash; reaches anywhere in the country.
            On-site work such as repairs, networks and CCTV covers the Copperbelt and
            Lusaka as standard, and anywhere else in Zambia by arrangement.</p>
          <p class="legal-links"><a href="legal.php?p=privacy">Privacy Policy</a> &middot; <a href="legal.php?p=terms">Terms &amp; Conditions</a> &middot; <a href="legal.php?p=complaints">Complaints Procedure</a></p>
    </div>
    <div class="contact-grid">
      <div class="contact-card">
        <div class="contact-icon">📞</div>
        <h4>Phone</h4>
        <p><a href="tel:0971013108">0971 013 108</a><br><a href="tel:0769974200">0769 974 200</a></p>
      </div>
      <div class="contact-card">
        <div class="contact-icon">✉️</div>
        <h4>Email</h4>
        <p><a href="mailto:info@avesta.solutions">info@avesta.solutions</a></p>
      </div>
      <div class="contact-card">
        <div class="contact-icon">📍</div>
        <h4>Office</h4>
        <p>House No. 3, Thom Avenue<br>Kansenshi, Ndola, Zambia</p>
      </div>
    </div>
    <div class="map-hint">
      <strong>Office Hours:</strong> Monday – Saturday, 08:00 – 17:00 &nbsp;|&nbsp;
      <strong>WhatsApp:</strong> <a href="https://wa.me/260971013108" target="_blank" rel="noopener" style="color:var(--gold)">Chat with us</a> &nbsp;|&nbsp;
      <strong>Location:</strong> Kansenshi, Ndola — near Thom Avenue
    </div>

    <!-- Google Map Embed -->
    <div style="max-width:860px;margin:0 auto;padding:0 0 40px">
      <div class="section-tag" style="display:inline-block;margin-bottom:12px">📍 Find Us</div>
      <div class="map-embed-wrap">
        <iframe
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3844.7!2d28.6362!3d-12.9683!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x196d3e0000000001%3A0x0!2sThom+Avenue%2C+Kansenshi%2C+Ndola%2C+Zambia!5e0!3m2!1sen!2szm!4v1700000000000"
          allowfullscreen=""
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="Avesta Enterprises location — Thom Avenue, Kansenshi, Ndola">
        </iframe>
      </div>
      <div class="map-links">
        <a class="map-link-btn" href="https://maps.google.com/?q=Thom+Avenue,+Kansenshi,+Ndola,+Zambia" target="_blank" rel="noopener">🗺️ Open in Google Maps</a>
        <a class="map-link-btn" href="https://maps.google.com/?q=Thom+Avenue,+Kansenshi,+Ndola,+Zambia&dirflg=d" target="_blank" rel="noopener">🚗 Get Directions</a>
        <a class="map-link-btn" href="https://wa.me/260971013108?text=Hello%2C+I%27d+like+to+visit+your+office.+Please+send+me+your+exact+location." target="_blank" rel="noopener" style="background:#25D366">💬 WhatsApp for Directions</a>
      </div>
    </div>

    <div style="text-align:center;margin-top:8px;padding-bottom:50px">
      <button class="btn-primary" onclick="showTab('apply')">Apply for a Loan</button>
    </div>
  </div><!-- /tab-contact -->


<!-- ═══ STAFF RECORDS TAB ═══ -->
<div data-site="lending" class="tab-panel" id="tab-staff">
<div style="min-height:calc(100vh - 60px);background:#f5f5f5;padding:40px 24px">

  <!-- PIN GATE -->
  <div id="staff-pin-gate" style="max-width:360px;margin:60px auto;background:white;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.1);padding:40px 32px;text-align:center">
    <div style="font-size:32pt;margin-bottom:12px">🔐</div>
    <h2 style="color:#163E33;margin-bottom:6px;font-size:16pt">Staff Access</h2>
    <p style="color:#888;font-size:9pt;margin-bottom:24px">Enter your staff PIN to view borrower records</p>
    <input type="password" id="staff-pin-input" maxlength="6" placeholder="Enter PIN"
      style="width:100%;padding:14px;font-size:16pt;letter-spacing:6px;text-align:center;border:2px solid #ddd;border-radius:8px;outline:none;margin-bottom:12px"
      onkeydown="if(event.key==='Enter')checkStaffPin()">
    <button onclick="checkStaffPin()"
      style="width:100%;background:#163E33;color:white;padding:13px;border:none;border-radius:8px;font-size:11pt;font-weight:bold;cursor:pointer">Unlock</button>
    <p id="staff-pin-err" style="color:#c0392b;font-size:8.5pt;margin-top:10px;display:none">❌ Incorrect PIN. Try again.</p>
    <p style="color:#bbb;font-size:7.5pt;margin-top:20px">Authorised Avesta Enterprises staff only</p>
  </div>

  <!-- RECORDS DASHBOARD (hidden until PIN unlocked) -->
  <div id="staff-dashboard" style="display:none;max-width:1100px;margin:0 auto">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px">
      <div>
        <h2 style="color:#163E33;font-size:18pt;margin-bottom:4px">📁 Borrower Records</h2>
        <p style="color:#888;font-size:9pt">All submitted loan applications and their attached documents</p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input id="staff-search" type="text" placeholder="🔍 Search by name or ID..."
          style="padding:10px 14px;border:1.5px solid #ddd;border-radius:7px;font-size:9.5pt;width:180px"
          oninput="filterRecords()">
        <select id="staff-status-filter" aria-label="Filter records by status" onchange="filterRecords()" style="padding:10px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:8.5pt;outline:none;background:white">
          <option value="">All Statuses</option>
          <option>New Application</option>
          <option>Under Review</option>
          <option>Approved</option>
          <option>Disbursed</option>
          <option>Repaid</option>
          <option>Rejected</option>
        </select>
        <button onclick="staffFetchRemote(false)" style="background:#163E33;color:#D98E3B;border:1.5px solid #D98E3B;padding:10px 14px;border-radius:7px;font-size:8.5pt;cursor:pointer;font-weight:bold" title="Sync records from server">🔄 Sync</button>
        <button onclick="openStaffBackupModal()" style="background:#1F5645;color:white;border:none;padding:10px 14px;border-radius:7px;font-size:8.5pt;cursor:pointer;font-weight:bold" title="Backup &amp; Restore records">📦 Backup / Restore</button>
        <button onclick="confirmClearAll()" style="background:#8e44ad;color:white;border:none;padding:10px 14px;border-radius:7px;font-size:8.5pt;cursor:pointer;font-weight:bold" title="Delete all local records">🗑 Clear Local</button>
        <button onclick="lockStaff()" style="background:#c0392b;color:white;border:none;padding:10px 14px;border-radius:7px;font-size:8.5pt;cursor:pointer;font-weight:bold">🔒 Lock</button>
      </div>
    </div>

    <!-- Stats bar -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:28px" id="staff-stats">
      <div style="background:white;border-radius:10px;padding:18px 20px;border-left:4px solid #163E33;box-shadow:0 2px 8px rgba(0,0,0,.06)">
        <div style="font-size:20pt;font-weight:bold;color:#163E33" id="stat-total">0</div>
        <div style="font-size:8pt;color:#888;text-transform:uppercase;letter-spacing:.5px">Total Applications</div>
      </div>
      <div style="background:white;border-radius:10px;padding:18px 20px;border-left:4px solid #D98E3B;box-shadow:0 2px 8px rgba(0,0,0,.06)">
        <div style="font-size:20pt;font-weight:bold;color:#D98E3B" id="stat-docs">0</div>
        <div style="font-size:8pt;color:#888;text-transform:uppercase;letter-spacing:.5px">With Documents</div>
      </div>
      <div style="background:white;border-radius:10px;padding:18px 20px;border-left:4px solid #27ae60;box-shadow:0 2px 8px rgba(0,0,0,.06)">
        <div style="font-size:20pt;font-weight:bold;color:#27ae60" id="stat-amount">ZMW 0</div>
        <div style="font-size:8pt;color:#888;text-transform:uppercase;letter-spacing:.5px">Total Requested</div>
      </div>
      <div style="background:white;border-radius:10px;padding:18px 20px;border-left:4px solid #e67e22;box-shadow:0 2px 8px rgba(0,0,0,.06)">
        <div style="font-size:20pt;font-weight:bold;color:#e67e22" id="stat-pending">0</div>
        <div style="font-size:8pt;color:#888;text-transform:uppercase;letter-spacing:.5px">New Applications</div>
      </div>
    </div>

    <!-- Sync status bar -->
    <div id="staff-sync-status" style="display:none;align-items:center;justify-content:center;gap:8px;background:#163E33;color:#D98E3B;padding:8px 16px;border-radius:8px;font-size:8.5pt;font-weight:600;margin-bottom:16px"></div>

    <!-- Records list -->
    <div id="staff-records-list"></div>

    <p style="text-align:center;color:#bbb;font-size:8pt;margin-top:32px">Records sync from the server — visible on any device. Local cache used when offline.</p>
  </div>
</div>
</div><!-- /tab-staff -->


<!-- ═══ STAFF BACKUP / RESTORE MODAL ═══ -->
<div id="staff-backup-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:flex-start;justify-content:center;padding:24px;overflow-y:auto">
  <div style="background:white;border-radius:14px;width:100%;max-width:640px;overflow:hidden;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <!-- Header -->
    <div style="background:var(--navy);padding:16px 22px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="color:white;font-weight:bold;font-size:11pt">📦 Backup &amp; Restore Records</div>
        <div style="color:rgba(255,255,255,.55);font-size:8pt;margin-top:2px">Export to share · Import to merge records from any device</div>
      </div>
      <button onclick="closeStaffBackupModal()" style="background:rgba(255,255,255,.15);border:none;color:white;width:32px;height:32px;border-radius:50%;font-size:13pt;cursor:pointer;line-height:1">✕</button>
    </div>
    <!-- Tabs -->
    <div style="display:flex;border-bottom:2px solid #f0f0f0">
      <button onclick="switchStaffBackupTab('export')" id="sbtab-export" style="flex:1;padding:12px;border:none;background:white;font-size:9pt;font-weight:700;color:var(--navy);border-bottom:3px solid var(--navy);cursor:pointer;margin-bottom:-2px">📤 Export / Download</button>
      <button onclick="switchStaffBackupTab('import')" id="sbtab-import" style="flex:1;padding:12px;border:none;background:white;font-size:9pt;font-weight:600;color:#888;cursor:pointer">📥 Import / Upload</button>
    </div>
    <!-- EXPORT TAB -->
    <div id="sbtab-content-export" style="padding:24px">
      <p style="font-size:8.5pt;color:#555;margin-bottom:18px;line-height:1.7">Download all records as a file to back up or share with other staff or the admin portal.</p>
      <div style="border:1.5px solid #e0e0e0;border-radius:10px;padding:18px;margin-bottom:14px">
        <div style="display:flex;align-items:flex-start;gap:14px">
          <div style="font-size:22pt">📄</div>
          <div style="flex:1">
            <div style="font-weight:bold;color:var(--navy);font-size:10pt">Full Backup (JSON with Documents)</div>
            <div style="font-size:8pt;color:#888;margin-top:3px;line-height:1.6">Includes all application data and uploaded files (NRC, payslips, etc). Use to fully restore on another device.</div>
          </div>
          <button onclick="staffExportJSON(true)" style="background:var(--navy);color:white;border:none;padding:10px 18px;border-radius:7px;font-size:8.5pt;font-weight:bold;cursor:pointer;white-space:nowrap;flex-shrink:0">⬇ Download</button>
        </div>
      </div>
      <div style="border:1.5px solid #e0e0e0;border-radius:10px;padding:18px;margin-bottom:14px">
        <div style="display:flex;align-items:flex-start;gap:14px">
          <div style="font-size:22pt">📋</div>
          <div style="flex:1">
            <div style="font-weight:bold;color:var(--navy);font-size:10pt">Data Only Backup (JSON, no documents)</div>
            <div style="font-size:8pt;color:#888;margin-top:3px;line-height:1.6">Application data only — no file attachments. Small file, easy to share via WhatsApp or email.</div>
          </div>
          <button onclick="staffExportJSON(false)" style="background:#1F5645;color:white;border:none;padding:10px 18px;border-radius:7px;font-size:8.5pt;font-weight:bold;cursor:pointer;white-space:nowrap;flex-shrink:0">⬇ Download</button>
        </div>
      </div>
      <div style="border:1.5px solid #e0e0e0;border-radius:10px;padding:18px">
        <div style="display:flex;align-items:flex-start;gap:14px">
          <div style="font-size:22pt">📊</div>
          <div style="flex:1">
            <div style="font-weight:bold;color:var(--navy);font-size:10pt">Spreadsheet Export (CSV)</div>
            <div style="font-size:8pt;color:#888;margin-top:3px;line-height:1.6">Open in Excel or Google Sheets. Good for reports. No uploaded documents.</div>
          </div>
          <button onclick="staffExportCSV()" style="background:var(--gold);color:var(--navy);border:none;padding:10px 18px;border-radius:7px;font-size:8.5pt;font-weight:bold;cursor:pointer;white-space:nowrap;flex-shrink:0">⬇ Download</button>
        </div>
      </div>
      <div id="staff-export-status" style="margin-top:14px;font-size:8.5pt;color:#198754;font-weight:600;text-align:center"></div>
    </div>
    <!-- IMPORT TAB -->
    <div id="sbtab-content-import" style="display:none;padding:24px">
      <p style="font-size:8.5pt;color:#555;margin-bottom:16px;line-height:1.7">Upload a JSON backup file exported from this portal or the admin portal. Records are merged — duplicates skipped, newer status wins.</p>
      <div id="staff-drop-zone" ondragover="staffDragOver(event)" ondragleave="staffDragLeave(event)" ondrop="staffDrop(event)"
        style="border:2.5px dashed var(--gold);border-radius:10px;padding:32px 20px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:16px;background:rgba(217,142,59,.04)"
        onclick="document.getElementById('staff-import-file').click()">
        <div style="font-size:28pt;margin-bottom:8px">📂</div>
        <div style="font-weight:bold;color:var(--navy);font-size:10pt;margin-bottom:4px">Drop JSON file here or click to browse</div>
        <div style="font-size:8pt;color:#888">Accepts .json files exported from the staff tab or admin portal</div>
        <input type="file" id="staff-import-file" aria-label="Choose a backup file to import" accept=".json,application/json" style="display:none" onchange="staffImportFile(this)">
      </div>
      <div id="staff-import-preview" style="display:none;border:1.5px solid #e0e0e0;border-radius:8px;padding:14px;margin-bottom:14px;background:#f9f9f9">
        <div id="staff-import-summary" style="font-size:9pt;color:var(--navy);font-weight:600;margin-bottom:10px"></div>
        <div id="staff-import-list" style="max-height:180px;overflow-y:auto;font-size:8.5pt;color:#555"></div>
      </div>
      <div style="display:flex;gap:10px">
        <button id="staff-import-btn" onclick="staffConfirmImport()" style="display:none;flex:1;background:var(--navy);color:white;border:none;padding:12px;border-radius:7px;font-size:9.5pt;font-weight:bold;cursor:pointer">✅ Import Records</button>
        <button id="staff-import-clear" onclick="staffClearImport()" style="display:none;background:transparent;color:#888;border:1.5px solid #ddd;padding:12px 18px;border-radius:7px;font-size:9pt;cursor:pointer">Clear</button>
      </div>
      <div id="staff-import-status" style="margin-top:12px;font-size:8.5pt;font-weight:600;text-align:center"></div>
    </div>
  </div>
</div>

<!-- Edit Record Modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:flex-start;justify-content:center;padding:24px;overflow-y:auto">
  <div style="background:white;border-radius:14px;width:100%;max-width:700px;overflow:hidden;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <div style="background:#163E33;color:white;padding:16px 22px;display:flex;align-items:center;justify-content:space-between">
      <span style="font-weight:bold;font-size:11pt">✏️ Edit Application Record</span>
      <button onclick="closeEditModal()" style="background:rgba(255,255,255,.15);border:none;color:white;padding:6px 12px;border-radius:5px;cursor:pointer;font-size:12pt">✕</button>
    </div>
    <div id="edit-modal-body" style="padding:22px;max-height:70vh;overflow-y:auto"></div>
    <div style="padding:14px 22px;background:#f8f8f8;border-top:1px solid #eee;display:flex;gap:10px;justify-content:flex-end">
      <button onclick="closeEditModal()" style="background:white;border:1.5px solid #ddd;color:#666;padding:10px 20px;border-radius:7px;font-size:9pt;cursor:pointer;font-weight:bold">Cancel</button>
      <button onclick="saveEdit()" style="background:#D98E3B;color:#163E33;border:none;padding:10px 24px;border-radius:7px;font-size:9.5pt;cursor:pointer;font-weight:bold">💾 Save Changes</button>
    </div>
  </div>
</div>

<!-- Edit field styles -->
<style>
.edit-field{display:flex;flex-direction:column;gap:4px}
.edit-field label{font-size:7pt;color:#888;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
.edit-field input:focus,.edit-field select:focus{border-color:#D98E3B!important;box-shadow:0 0 0 3px rgba(217,142,59,.15)}
@media(max-width:600px){#edit-modal-body .grid-2col{grid-template-columns:1fr!important}}
</style>

<!-- Document Viewer Modal -->
<div id="doc-viewer-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;max-width:90vw;max-height:90vh;overflow:auto;padding:0;position:relative;min-width:320px">
    <div style="background:#163E33;color:white;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-radius:12px 12px 0 0">
      <span id="doc-viewer-title" style="font-weight:bold;font-size:10pt"></span>
      <div style="display:flex;gap:8px">
        <a id="doc-viewer-download" download style="background:#D98E3B;color:#163E33;padding:6px 14px;border-radius:5px;font-size:8.5pt;font-weight:bold;text-decoration:none">⬇ Download</a>
        <button onclick="closeDocViewer()" style="background:rgba(255,255,255,.2);border:none;color:white;padding:6px 12px;border-radius:5px;cursor:pointer;font-size:10pt">✕</button>
      </div>
    </div>
    <div id="doc-viewer-body" style="padding:16px;text-align:center;min-height:200px"></div>
  </div>
</div>

<style>
.record-card{background:white;border-radius:10px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;transition:box-shadow .2s}
.record-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.12)}
.record-header{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;gap:12px;flex-wrap:wrap}
.record-name{font-weight:bold;color:#163E33;font-size:11pt}
.record-meta{font-size:8pt;color:#888;margin-top:2px}
.record-badge{background:#163E33;color:white;padding:4px 10px;border-radius:20px;font-size:7.5pt;white-space:nowrap}
.record-badge.gold{background:#D98E3B;color:#163E33}
.record-body{display:none;border-top:1px solid #f0f0f0;padding:20px}
.record-body.open{display:block}
.doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-top:12px}
.doc-thumb{border:2px dashed #ddd;border-radius:8px;padding:16px 10px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa}
.doc-thumb:hover{border-color:#D98E3B;background:#fffbf5}
.doc-thumb.has-doc{border-style:solid;border-color:#27ae60;background:#f0fff4}
.doc-thumb-icon{font-size:22pt;margin-bottom:6px}
.doc-thumb-label{font-size:7.5pt;color:#555;font-weight:bold;text-transform:uppercase;letter-spacing:.3px}
.doc-thumb-status{font-size:7pt;margin-top:4px}
@keyframes staffSpin { to { transform: rotate(360deg); } }

/* ── FULL RECORD VIEW ─────────────────────────────────────── */
.rv-action-bar{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f8f8f8;border-bottom:1px solid #eee;flex-wrap:wrap;gap:10px}
.rv-status-sel{border:1.5px solid;border-radius:5px;padding:5px 10px;font-size:8.5pt;font-weight:700;outline:none;background:white;cursor:pointer}
.rv-btn{border:none;padding:7px 14px;border-radius:5px;font-size:8.5pt;font-weight:700;cursor:pointer;font-family:Arial,sans-serif;transition:all .15s}
.rv-btn-edit{background:#163E33;color:white}.rv-btn-edit:hover{background:#1f5645}
.rv-btn-delete{background:#fff0f0;color:#c0392b;border:1px solid #f5c6cb}.rv-btn-delete:hover{background:#fde8e8}
.rv-section{padding:16px 18px;border-bottom:1px solid #f2f2f2}
.rv-section:last-child{border-bottom:none}
.rv-sec-title{font-size:9pt;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px;display:flex;align-items:center;gap:6px;padding-bottom:8px;border-bottom:2px solid var(--gold)}
.rv-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px}
.rv-half{display:flex;flex-direction:column;padding:6px 0}
.rv-lbl{font-size:7pt;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;margin-bottom:2px}
.rv-val{font-size:9.5pt;color:#1a1a1a;font-weight:600;word-break:break-word;line-height:1.4}
.rv-divider{grid-column:span 2;font-size:8pt;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:.8px;padding:8px 0 4px;border-top:1px dashed #e0e0e0;margin-top:6px}
.rv-docs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
.rv-doc-tile{border:1.5px dashed #ddd;border-radius:8px;padding:14px 8px;text-align:center;background:#fafafa;transition:all .2s}
.rv-doc-tile:hover{border-color:var(--gold);background:rgba(217,142,59,.06)}
.rv-doc-has{border-style:solid;border-color:#27ae60;background:#f0fff4}
.rv-doc-has:hover{border-color:#1a8a4a;background:#e6fff0}
.rv-doc-icon{font-size:20pt;margin-bottom:4px}
.rv-doc-label{font-size:7.5pt;color:#555;font-weight:700;text-transform:uppercase;letter-spacing:.3px;margin-bottom:3px}
.rv-doc-status{font-size:7.5pt;color:#27ae60;font-weight:600}
.rv-doc-tile:not(.rv-doc-has) .rv-doc-status{color:#bbb}
.rv-sig-box{background:#f9f9f9;border-radius:8px;padding:14px;border:1px solid #eee}
.rv-sig-label{font-size:7.5pt;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.rv-sig-pad{background:white;border:1px solid #ddd;border-radius:5px;padding:8px;min-height:50px;display:flex;align-items:center;justify-content:center}
.rv-sig-empty{color:#ccc;font-size:8.5pt;font-style:italic}
.rv-sig-meta{margin-top:8px;display:flex;flex-direction:column;gap:3px;font-size:8.5pt;color:#555;line-height:1.5}
@media(max-width:600px){
  .rv-grid{grid-template-columns:1fr}
  .rv-half[style*="span 2"]{grid-column:span 1!important}
  .rv-divider{grid-column:span 1}
  .rv-docs-grid{grid-template-columns:repeat(auto-fill,minmax(110px,1fr))}
}
</style>

<script>
/* ── SITE SEPARATION ──────────────────────────────────────────────────────
   Lending and IT Consulting are run as two offices onto one codebase. A
   borrower never wades through printer repairs; an IT client never lands on
   loan terms.

   Anything belonging to the other office is REMOVED from the document, not
   hidden — so a stray deep link cannot reach it and a screen reader never
   reads it out. This runs here, after the markup and before the application
   scripts, so those scripts find their elements already absent and bow out
   cleanly rather than booting and having the DOM pulled from under them.
─────────────────────────────────────────────────────────────────────────── */
(function () {
  var site = (window.AV_SITE || 'lending').toLowerCase();
  if (site !== 'lending' && site !== 'it') site = 'lending';
  window.AV_SITE = site;

  document.documentElement.setAttribute('data-site', site);
  if (site === 'it') document.documentElement.setAttribute('data-arm', 'consulting');

  var gone = [];
  Array.prototype.slice.call(document.querySelectorAll('[data-site]')).forEach(function (el) {
    var want = el.getAttribute('data-site');
    if (want === 'both' || want === site) return;
    if (el.id && el.id.indexOf('tab-') === 0) gone.push(el.id.slice(4));
    if (el.parentNode) el.parentNode.removeChild(el);
  });

  window.AV_VALID_TABS = ['home','how','calc','services','currency','apply','contact','about','staff']
    .filter(function (t) { return gone.indexOf(t) === -1; });

  window.AV_LANDING = site === 'it' ? 'services' : 'home';

  var sub = document.getElementById('logo-sub');
  if (sub) sub.textContent = site === 'it' ? 'IT Consulting' : 'Lending';

  // Shared tabs (About, Contact) carry a line for each office. Swapping the
  // text is better than hiding the tab: an IT client still gets an About page,
  // it just describes the consultancy rather than the lending business.
  Array.prototype.slice.call(document.querySelectorAll('[data-copy]')).forEach(function (el) {
    var t = el.getAttribute(site === 'it' ? 'data-it' : 'data-lending');
    if (t) el.textContent = t;
  });
})();
</script>
<script>
// ── STAFF CONFIG ───────────────────────────────────────────────────────────
// Staff access uses the authenticated server session.
const STAFF_STORAGE_KEY = 'avesta_submission_';
let staffUnlocked = false;
let staffAllRecords = [];
let staffIsFetching = false;

// Reuse SHEET_URL from the submission config at the bottom of the page
function getStaffScriptUrl() {
  return localStorage.getItem('avesta_script_url') ||
    (typeof SHEET_URL !== 'undefined' ? SHEET_URL : '') ||
    'api.php';
}

// ── PIN & AUTH ─────────────────────────────────────────────────────────────
function showStaffLogin() {
  if (!window.AV_USER || !['admin', 'staff'].includes(window.AV_USER.role)) {
    window.location.href = 'login.php';
    return;
  }
  showTab('staff');
  if (!staffUnlocked) checkStaffPin();
}

function checkStaffPin() {
  const val = document.getElementById('staff-pin-input').value;
  const err = document.getElementById('staff-pin-err');
  if (window.AV_USER && ['admin', 'staff'].includes(window.AV_USER.role)) {
    staffUnlocked = true;
    document.getElementById('staff-pin-gate').style.display = 'none';
    document.getElementById('staff-dashboard').style.display = 'block';
    staffLoadLocal();
    staffFetchRemote(false);
    // Poll remote every 30s while staff tab is open
    if (!window._staffPollInterval) {
      window._staffPollInterval = setInterval(() => {
        if (staffUnlocked) staffFetchRemote(true);
      }, 30000);
    }
  } else {
    err.style.display = 'block';
    document.getElementById('staff-pin-input').value = '';
    document.getElementById('staff-pin-input').focus();
    setTimeout(() => err.style.display = 'none', 3000);
  }
}

function lockStaff() {
  staffUnlocked = false;
  clearInterval(window._staffPollInterval);
  window._staffPollInterval = null;
  document.getElementById('staff-pin-input').value = '';
  document.getElementById('staff-pin-err').style.display = 'none';
  document.getElementById('staff-pin-gate').style.display = 'block';
  document.getElementById('staff-dashboard').style.display = 'none';
  showTab('home');
}

// ── NORMALISE ──────────────────────────────────────────────────────────────
function staffNormalise(r) {
  r.full_name      = r.full_name      || r.b_name           || '—';
  r.national_id    = r.national_id    || r.b_id             || '—';
  r.phone          = r.phone          || r.b_phone          || '—';
  r.address        = r.address        || r.b_address        || '—';
  r.email          = r.email          || r.b_email          || '—';
  r.occupation     = r.occupation     || r.b_occupation     || '—';
  r.monthly_income = r.monthly_income || r.b_income         || '—';
  r.date_of_birth  = r.date_of_birth  || r.b_dob            || '—';
  r.loan_amount    = r.loan_amount    || r.loan_amt          || '0';
  r.loan_purpose   = r.loan_purpose                         || '—';
  r.loan_duration  = r.loan_duration  || r.sel_dur           || '—';
  r.interest_rate  = r.interest_rate  || r.int_rate          || '—';
  r.total_repayment= r.total_repayment|| r.total_repay       || '—';
  r.repay_method   = r.repay_method   || r.radio_repay_method|| '—';
  r.receive_method = r.receive_method || r.radio_disburse    || '—';
  r.status         = r.status                               || 'New Application';
  return r;
}

// ── LOAD FROM LOCALSTORAGE (instant, cached) ───────────────────────────────
function staffLoadLocal() {
  staffAllRecords = [];
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    if (k && k.startsWith(STAFF_STORAGE_KEY)) {
      try {
        const r = JSON.parse(localStorage.getItem(k));
        r._key = k;
        staffAllRecords.push(staffNormalise(r));
      } catch(e) {}
    }
  }
  staffAllRecords.sort((a,b) => new Date(b.submitted_at||0) - new Date(a.submitted_at||0));
  updateStats();
  renderRecords(staffAllRecords);
}

// ── FETCH FROM GOOGLE SHEETS ───────────────────────────────────────────────

async function staffFetchRemote(silent) {
  const url = getStaffScriptUrl();
  const syncEl = document.getElementById('staff-sync-status');

  // Always render local records first — never block on network
  staffLoadLocal();

  if (!url) {
    if (syncEl) {
      syncEl.style.display = 'flex';
      syncEl.style.background = '#fff3cd'; syncEl.style.color = '#856404';
      syncEl.innerHTML = '⚠️ api.php not reachable — showing local records only.';
    }
    return;
  }
  if (staffIsFetching) return;
  staffIsFetching = true;

  if (!silent && syncEl) {
    syncEl.style.display = 'flex';
    syncEl.innerHTML = '<span style="animation:staffSpin 1s linear infinite;display:inline-block;margin-right:6px">⟳</span> Syncing with server…';
    syncEl.style.background = '#163E33'; syncEl.style.color = '#D98E3B';
  }
  try {
    // avFetchJson wires the abort signal to the request, so the timeout
    // actually cancels a hung call, and reports HTML error pages plainly.
    const json = (await window.avFetchJson(
      url + '?action=getRecords&t=' + Date.now(), {}, 10000)).data;
    if (!json.records || !Array.isArray(json.records)) throw new Error('Server returned: ' + JSON.stringify(json).slice(0,100));
    let newCount = 0;
    json.records.forEach(r => {
      if (!r._key) r._key = STAFF_STORAGE_KEY + (r.submitted_at||Date.now()).toString().replace(/[^a-z0-9]/gi,'_');
      staffNormalise(r);
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
    staffLoadLocal();
    if (syncEl) {
      syncEl.style.background = '#d4edda'; syncEl.style.color = '#155724';
      syncEl.innerHTML = '✅ Synced — ' + json.records.length + ' record' + (json.records.length!==1?'s':'') + (newCount>0?' ('+newCount+' new)':'');
      setTimeout(() => { syncEl.style.display = 'none'; }, 4000);
    }
  } catch(e) {
    const msg = e.name === 'AbortError' ? 'Request timed out' : e.message;
    if (syncEl) {
      syncEl.style.display = 'flex';
      syncEl.style.background = '#fff3cd'; syncEl.style.color = '#856404';
      syncEl.innerHTML = '⚠️ Sheets sync failed (' + msg + ') — showing ' + staffAllRecords.length + ' cached record' + (staffAllRecords.length!==1?'s':'');
      setTimeout(() => { syncEl.style.display = 'none'; }, 8000);
    }
    console.warn('staffFetchRemote error:', e);
  } finally {
    staffIsFetching = false;
  }
}

// Push status change to server
async function staffPushStatus(r) {
  const url = getStaffScriptUrl();
  if (!url) return;
  try {
    await fetch(url + '?action=updateStatus&key=' + encodeURIComponent(r._key) +
      '&status=' + encodeURIComponent(r.status) +
      '&submitted_at=' + encodeURIComponent(r.submitted_at||'') + '&t=' + Date.now());
  } catch(e) {}
}

// Push delete to server
async function staffPushDelete(key, submitted_at) {
  const url = getStaffScriptUrl();
  if (!url) return;
  try {
    await fetch(url + '?action=deleteRecord&key=' + encodeURIComponent(key) +
      '&submitted_at=' + encodeURIComponent(submitted_at||'') + '&t=' + Date.now());
  } catch(e) {}
}

// ── DOC LABELS ─────────────────────────────────────────────────────────────
const DOC_LABELS = {
  doc_nrc_front:      { icon:'🪪', label:'NRC Front' },
  doc_nrc_back:       { icon:'🪪', label:'NRC Back' },
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

// ── STATS ──────────────────────────────────────────────────────────────────
function updateStats() {
  // The staff panel is removed from the page for anyone who is not an admin,
  // so these boxes may simply not be there. Write into them only if they are.
  const put = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };
  if (!document.getElementById('stat-total')) return;
  put('stat-total', staffAllRecords.length);
  const withDocs = staffAllRecords.filter(r => Object.keys(DOC_LABELS).some(k => r[k+'_base64'] || r[k+'_url'])).length;
  put('stat-docs', withDocs);
  const total = staffAllRecords.reduce((s,r) => s + (parseFloat(r.loan_amount)||0), 0);
  put('stat-amount', 'ZMW ' + total.toLocaleString('en-ZM',{minimumFractionDigits:2,maximumFractionDigits:2}));
  const pending = staffAllRecords.filter(r => (r.status||'').toLowerCase().includes('new')).length;
  const el = document.getElementById('stat-pending');
  if (el) el.textContent = pending;
}

// ── SEARCH / FILTER ────────────────────────────────────────────────────────
function filterRecords() {
  const q = document.getElementById('staff-search').value.toLowerCase();
  const statusF = document.getElementById('staff-status-filter') ? document.getElementById('staff-status-filter').value : '';
  let filtered = staffAllRecords.filter(r => {
    const matchQ = !q || (r.full_name||'').toLowerCase().includes(q) ||
      (r.national_id||'').toLowerCase().includes(q) || (r.phone||'').includes(q);
    const matchS = !statusF || r.status === statusF;
    return matchQ && matchS;
  });
  renderRecords(filtered);
}

// ── RENDER RECORDS ─────────────────────────────────────────────────────────
function renderRecords(records) {
  const container = document.getElementById('staff-records-list');
  // Not an admin: the whole staff panel was removed from the page, so there is
  // nowhere to render and nothing to do.
  if (!container) return;
  if (!records.length) {
    container.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#bbb"><div style="font-size:36pt">📭</div><p style="margin-top:12px;font-size:12pt;font-weight:600">No records found</p><p style="font-size:8.5pt;margin-top:6px">Submitted applications will appear here</p></div>';
    return;
  }

  const STATUS_COLORS = {
    'New Application':'#2980b9','Under Review':'#e67e22',
    'Approved':'#27ae60','Disbursed':'#0e6b3c',
    'Repaid':'#0a5c4a','Rejected':'#c0392b'
  };
  const fmtZMW = v => 'ZMW ' + parseFloat(v||0).toLocaleString('en-ZM',{minimumFractionDigits:2,maximumFractionDigits:2});
  const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const row = (label,val) => `<div class="rv-row"><span class="rv-lbl">${label}</span><span class="rv-val">${esc(val||'—')}</span></div>`;
  const sec = (icon,title,body) => `<div class="rv-section"><div class="rv-sec-title">${icon} ${title}</div>${body}</div>`;
  const half = (label,val) => `<div class="rv-half"><span class="rv-lbl">${label}</span><span class="rv-val">${esc(val||'—')}</span></div>`;

  container.innerHTML = records.map((r, idx) => {
    const sc = STATUS_COLORS[r.status] || '#888';
    const docCount = Object.keys(DOC_LABELS).filter(k => r[k+'_base64'] || r[k+'_url']).length;
    const hasSig = r.borrower_signature && r.borrower_signature !== '—';
    const hasGps = r.gps_latitude && r.gps_latitude !== '—';
    const secType = r.security_type || r.radio_security_type || '';

    // ── SECTION: BORROWER ──────────────────────────────────────────────────
    const borrowerSec = sec('👤','Borrower Information',`
      <div class="rv-grid">
        ${half('Full Name', r.full_name)}
        ${half('NRC / National ID', r.national_id)}
        ${half('Date of Birth', r.date_of_birth)}
        ${half('Phone', r.phone)}
        ${half('Email', r.email)}
        ${half('Occupation / Employer', r.occupation)}
        ${half('Monthly Income', r.monthly_income ? fmtZMW(r.monthly_income) : '—')}
        <div class="rv-half" style="grid-column:span 2"><span class="rv-lbl">Physical Address</span><span class="rv-val">${esc(r.address||'—')}</span></div>
      </div>`);

    // ── SECTION: LOAN DETAILS ──────────────────────────────────────────────
    const loanSec = sec('💰','Loan Details',`
      <div class="rv-grid">
        ${half('Loan Amount', r.loan_amount ? fmtZMW(r.loan_amount) : '—')}
        ${half('Amount in Words', r.loan_words || r.loan_amt_words || '—')}
        <div class="rv-half" style="grid-column:span 2"><span class="rv-lbl">Purpose of Loan</span><span class="rv-val">${esc(r.loan_purpose||'—')}</span></div>
        ${half('Disbursement Method', r.receive_method || r.radio_disburse || '—')}
        ${half('Disbursement Date', r.disburse_date || '—')}
        ${half('Effective Date', r.effective_date || '—')}
        ${half('Submitted At', r.submitted_at || '—')}
      </div>`);

    // ── SECTION: REPAYMENT ─────────────────────────────────────────────────
    const repaySec = sec('📅','Repayment Terms',`
      <div class="rv-grid">
        ${half('Loan Duration', r.loan_duration || '—')}
        ${half('Interest Rate', r.interest_rate ? r.interest_rate + '%' : '—')}
        ${half('Total Interest', r.total_interest ? fmtZMW(r.total_interest) : r.total_int ? fmtZMW(r.total_int) : '—')}
        ${half('Total Repayment', r.total_repayment ? fmtZMW(r.total_repayment) : '—')}
        ${half('Repayment Schedule', r.repay_schedule || '—')}
        ${half('Repayment Method', r.repay_method || r.radio_repay_method || '—')}
        ${half('No. of Installments', r.installments || r.num_inst || '—')}
        ${half('Installment Amount', (r.installment_amt||r.inst_amt) ? fmtZMW(r.installment_amt||r.inst_amt) : '—')}
        ${half('First Payment Date', r.first_payment || '—')}
        ${half('Last Payment Date', r.last_payment || '—')}
        ${half('Late Payment Rate', r.late_payment_rate || r.late_rate_display || '—')}
        ${half('Weeks Overdue', r.weeks_overdue || '0')}
        ${half('Late Fee Amount', r.late_fee ? fmtZMW(r.late_fee) : '—')}
      </div>`);

    // ── SECTION: SECURITY ──────────────────────────────────────────────────
    let securityBody = `<div class="rv-grid">
      ${half('Security Type', secType || '—')}
      <div class="rv-half" style="grid-column:span 2"><span class="rv-lbl">Collateral Items</span><span class="rv-val">${esc(r.collateral_items||'—')}</span></div>`;
    if (r.checkoff_employer || r.co_employer) {
      securityBody += `
      <div class="rv-divider">Salary Check-off Details</div>
      ${half('Employer / Company', r.checkoff_employer || r.co_employer || '—')}
      ${half('HR / Payroll Contact', r.checkoff_hr_name || r.co_hr_name || '—')}
      ${half('HR Phone', r.checkoff_hr_phone || r.co_hr_phone || '—')}
      ${half('Payroll / Employee No.', r.checkoff_payroll_no || r.co_payroll_no || '—')}`;
    }
    if (r.guarantor_name || r.gtr_name) {
      securityBody += `
      <div class="rv-divider">Guarantor Details</div>
      ${half('Guarantor Full Name', r.guarantor_name || r.gtr_name || '—')}
      ${half('Guarantor NRC / Passport', r.guarantor_id || r.gtr_id || '—')}
      ${half('Guarantor Phone', r.guarantor_phone || r.gtr_phone || '—')}
      ${half('Guarantor Relationship', r.guarantor_relationship || r.gtr_relationship || '—')}
      ${half('Guarantor Occupation', r.guarantor_occupation || r.gtr_occupation || '—')}
      ${half('Guarantor Monthly Income', (r.guarantor_income||r.gtr_income) ? fmtZMW(r.guarantor_income||r.gtr_income) : '—')}`;
    }
    if (r.references && r.references !== '—') {
      securityBody += `<div class="rv-half" style="grid-column:span 2"><span class="rv-lbl">References</span><span class="rv-val">${esc(r.references)}</span></div>`;
    }
    securityBody += `</div>`;
    const securitySec = sec('🔒','Security / Guarantee', securityBody);

    // ── SECTION: DOCUMENTS ─────────────────────────────────────────────────
    const docsSec = `<div class="rv-section">
      <div class="rv-sec-title">📎 Supporting Documents <span style="background:${docCount>0?'#eafaf1':'#f5f5f5'};color:${docCount>0?'#198754':'#aaa'};font-size:7.5pt;padding:2px 8px;border-radius:20px;margin-left:6px">${docCount} uploaded</span></div>
      <div class="rv-docs-grid">
        ${Object.entries(DOC_LABELS).map(([k,meta]) => {
          const hasDoc = !!(r[k+'_base64'] || r[k+'_url']);
          const fname = r[k+'_filename'] || meta.label;
          const mime  = r[k+'_mimetype'] || '';
          return `<div class="rv-doc-tile ${hasDoc?'rv-doc-has':''}" ${hasDoc?`data-document-field="${k}" data-record-index="${staffAllRecords.indexOf(r)}" role="button" tabindex="0"`:''} style="${hasDoc?'cursor:pointer':'cursor:default'}">
            <div class="rv-doc-icon">${meta.icon}</div>
            <div class="rv-doc-label">${meta.label}</div>
            <div class="rv-doc-status">${hasDoc?'✅ View':'—'}</div>
          </div>`;
        }).join('')}
      </div>
    </div>`;

    // ── SECTION: SIGNATURES ────────────────────────────────────────────────
    const sigSec = `<div class="rv-section">
      <div class="rv-sec-title">✍️ Signatures & Witness</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;flex-wrap:wrap">
        <div class="rv-sig-box">
          <div class="rv-sig-label">Borrower Signature</div>
          ${hasSig
            ? `<div class="rv-sig-pad"><img src="${r.borrower_signature}" style="max-height:60px;max-width:100%;object-fit:contain"></div>`
            : `<div class="rv-sig-pad rv-sig-empty">No signature</div>`}
          <div class="rv-sig-meta">
            <span><b>Name:</b> ${esc(r.borrower_printed_name||r.sig_borrower_name||'—')}</span>
            <span><b>Date:</b> ${esc(r.borrower_sign_date||r.sig_borrower_date||'—')}</span>
          </div>
        </div>
        <div class="rv-sig-box">
          <div class="rv-sig-label">Witness</div>
          <div class="rv-sig-meta" style="margin-top:8px">
            <span><b>Name:</b> ${esc(r.witness_name||r.wit_name||'—')}</span>
            <span><b>Phone:</b> ${esc(r.witness_phone||r.wit_phone||'—')}</span>
            <span><b>Date:</b> ${esc(r.witness_date||r.wit_date||'—')}</span>
          </div>
        </div>
      </div>
    </div>`;

    // ── SECTION: GPS ──────────────────────────────────────────────────────
    const gpsSec = `<div class="rv-section">
      <div class="rv-sec-title">📍 GPS Location</div>
      <div class="rv-grid">
        ${half('Latitude', r.gps_latitude || '—')}
        ${half('Longitude', r.gps_longitude || '—')}
        ${half('Accuracy', r.gps_accuracy_m && r.gps_accuracy_m !== '—' ? r.gps_accuracy_m + ' m' : '—')}
        <div class="rv-half">${hasGps ? `<a href="https://maps.google.com/?q=${r.gps_latitude},${r.gps_longitude}" target="_blank" style="color:var(--gold);font-weight:bold;font-size:9pt">🗺 Open in Google Maps ↗</a>` : '<span style="color:#ccc;font-size:9pt">—</span>'}</div>
      </div>
    </div>`;

    // ── SECTION: OFFICE USE ────────────────────────────────────────────────
    const officeSec = (r.ou_ref||r.ou_staff||r.ou_date) ? sec('🗂','Office Use Only',`
      <div class="rv-grid">
        ${half('Loan Reference #', r.ou_ref || '—')}
        ${half('Processed By', r.ou_staff || '—')}
        ${half('Date Processed', r.ou_date || '—')}
      </div>`) : '';

    return `
    <div class="record-card" id="rcard-${idx}">
      <!-- ── CARD HEADER (always visible) ── -->
      <div class="record-header" onclick="toggleRecord(${idx})">
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="record-name">${esc(r.full_name||'Unknown Applicant')}</div>
            <span style="background:${sc}18;color:${sc};font-size:7.5pt;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap;border:1px solid ${sc}44">${esc(r.status||'New Application')}</span>
          </div>
          <div class="record-meta" style="margin-top:4px">
            🪪 ${esc(r.national_id||'—')} &nbsp;|&nbsp; 📞 ${esc(r.phone||'—')} &nbsp;|&nbsp;
            💰 ${r.loan_amount ? fmtZMW(r.loan_amount) : '—'} &nbsp;|&nbsp;
            ⏱ ${esc(r.loan_duration||'—')} &nbsp;|&nbsp;
            🗓 ${esc((r.submitted_at||'—').slice(0,16))}
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:12px">
          ${docCount > 0 ? `<span class="record-badge gold">📎 ${docCount}</span>` : ''}
          <span style="color:#aaa;font-size:12pt;transition:transform .2s" id="rcard-chevron-${idx}">▼</span>
        </div>
      </div>

      <!-- ── FULL RECORD BODY (expands on click) ── -->
      <div class="record-body" id="rbody-${idx}">

        <!-- STATUS + ACTIONS BAR -->
        <div class="rv-action-bar">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-size:8pt;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Status:</span>
            <select onchange="staffUpdateStatus('${r._key}',this.value)" class="rv-status-sel" style="border-color:${sc};color:${sc}">
              ${['New Application','Under Review','Approved','Disbursed','Repaid','Rejected'].map(s=>`<option value="${s}" ${(r.status||'New Application')===s?'selected':''}>${s}</option>`).join('')}
            </select>
          </div>
          <div style="display:flex;gap:8px">
            <button onclick="openEditModal('${r._key}')" class="rv-btn rv-btn-edit">✏️ Edit</button>
            <button onclick="staffDeleteRecord('${r._key}')" class="rv-btn rv-btn-delete">🗑 Delete</button>
          </div>
        </div>

        <!-- ALL SECTIONS -->
        ${borrowerSec}
        ${loanSec}
        ${repaySec}
        ${securitySec}
        ${docsSec}
        ${sigSec}
        ${gpsSec}
        ${officeSec}

      </div>
    </div>`;
  }).join('');
}

function toggleRecord(idx) {
  const body = document.getElementById('rbody-' + idx);
  const chev = document.getElementById('rcard-chevron-' + idx);
  if (!body) return;
  const open = body.classList.toggle('open');
  chev.textContent = open ? '▲' : '▼';
}

// ── STATUS UPDATE ──────────────────────────────────────────────────────────
function staffUpdateStatus(key, newStatus) {
  const r = staffAllRecords.find(x => x._key === key);
  if (!r) return;
  const prevStatus = r.status;
  r.status = newStatus;
  try { localStorage.setItem(key, JSON.stringify(r)); } catch(e) {}
  staffPushStatus(r);
  updateStats();
  filterRecords();
  // Notify borrower if preference is enabled
  if (newStatus !== prevStatus) staffNotifyBorrower(r, newStatus);
}

// ── BORROWER NOTIFICATION FROM STAFF TAB ─────────────────────────────────────
function staffNotifyBorrower(r, newStatus) {
  const notifyStatuses = ['Approved','Disbursed','Rejected','Under Review'];
  if (!notifyStatuses.includes(newStatus)) return;

  // Check preference (stored by admin portal, shared via localStorage)
  const enabled = JSON.parse(localStorage.getItem('avesta_borrower_notif_enabled') || 'false');
  if (!enabled) return;

  const borrowerPhone = (r.phone || r.b_phone || '').replace(/[^0-9]/g,'');
  if (!borrowerPhone) return;

  const saved = JSON.parse(localStorage.getItem('avesta_wa_settings')||'{}');
  if (!saved.key) return;

  const name = (r.full_name || 'Valued Customer').split(' ')[0];
  const amt  = 'ZMW ' + parseFloat(r.loan_amount||0).toLocaleString();
  const ref  = (r._key||'').replace('avesta_submission_','AE-').slice(0,14).toUpperCase();

  const messages = {
    'Under Review': 'Dear ' + name + ', your loan application (' + ref + ') has been received and is under review by Avesta Enterprises. We will update you shortly. Thank you.',
    'Approved':     'Congratulations ' + name + '! Your loan of ' + amt + ' (Ref: ' + ref + ') has been APPROVED by Avesta Enterprises. Disbursement will be processed shortly. Thank you.',
    'Disbursed':    'Dear ' + name + ', your loan of ' + amt + ' (Ref: ' + ref + ') has been DISBURSED. Please confirm receipt and remember your repayment schedule. Thank you - Avesta Enterprises.',
    'Rejected':     'Dear ' + name + ', your loan application (Ref: ' + ref + ') was not approved at this time. Please contact Avesta Enterprises for more information. Thank you.'
  };

  const text = messages[newStatus] || ('Your loan status is now: ' + newStatus + '. Ref: ' + ref + ' - Avesta Enterprises.');
  const url = 'https://api.textmebot.com/send.php?recipient=' + borrowerPhone +
    '&apikey=' + saved.key + '&text=' + encodeURIComponent(text);
  const img = new Image();
  img.src = url;
  img.onerror = img.onload = () => {};
}

// ── DELETE ─────────────────────────────────────────────────────────────────
async function staffDeleteRecord(key) {
  const r = staffAllRecords.find(x => x._key === key);
  const name = r ? (r.full_name || 'this record') : 'this record';
  const go = await avConfirm('Delete ' + name + '?',
    'The application and its documents are removed from this device. This cannot be undone.',
    { okText: 'Delete record', cancelText: 'Keep it' });
  if (!go) return;
  const submitted_at = r ? r.submitted_at : '';
  try { localStorage.removeItem(key); } catch(e) {}
  staffAllRecords = staffAllRecords.filter(x => x._key !== key);
  staffPushDelete(key, submitted_at);
  updateStats();
  renderRecords(staffAllRecords);
}

// ── CLEAR ALL ──────────────────────────────────────────────────────────────
async function confirmClearAll() {
  if (!staffAllRecords.length) { avToast('Nothing to delete. There are no records on this device.', 'info'); return; }
  const count = staffAllRecords.length;
  const go = await avConfirm(
    'Delete all ' + count + ' record' + (count > 1 ? 's' : '') + '?',
    'Every application on this device is removed. Back up first if you have not already — this cannot be undone.',
    { okText: 'Continue', cancelText: 'Cancel' });
  if (!go) return;
  const second = await avPrompt('Type DELETE to confirm',
    'This is the last step before ' + count + ' record' + (count > 1 ? 's are' : ' is') + ' erased.',
    'DELETE', { okText: 'Delete everything' });
  if ((second || '').trim().toUpperCase() !== 'DELETE') {
    avToast('Cancelled. Nothing was deleted.', 'info'); return;
  }
  const keys = [];
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    if (k && k.startsWith(STAFF_STORAGE_KEY)) keys.push(k);
  }
  keys.forEach(k => { try { localStorage.removeItem(k); } catch(e) {} });
  staffAllRecords = [];
  updateStats();
  renderRecords([]);
  avToast('All records cleared. This device now has no saved applications.', 'ok');
}

// ── BACKUP / RESTORE MODAL ────────────────────────────────────────────────
let staffImportData = null;

function openStaffBackupModal() {
  const m = document.getElementById('staff-backup-modal');
  m.style.display = 'flex';
  document.getElementById('staff-export-status').textContent =
    staffAllRecords.length + ' record' + (staffAllRecords.length !== 1 ? 's' : '') + ' available to export';
  document.getElementById('staff-import-status').textContent = '';
  switchStaffBackupTab('export');
}

function closeStaffBackupModal() {
  document.getElementById('staff-backup-modal').style.display = 'none';
  staffClearImport();
}

function switchStaffBackupTab(tab) {
  document.getElementById('sbtab-content-export').style.display = tab === 'export' ? 'block' : 'none';
  document.getElementById('sbtab-content-import').style.display = tab === 'import' ? 'block' : 'none';
  const exp = document.getElementById('sbtab-export');
  const imp = document.getElementById('sbtab-import');
  exp.style.color      = tab === 'export' ? 'var(--navy)' : '#888';
  exp.style.fontWeight = tab === 'export' ? 'bold' : '600';
  exp.style.borderBottom = tab === 'export' ? '3px solid var(--navy)' : 'none';
  imp.style.color      = tab === 'import' ? 'var(--navy)' : '#888';
  imp.style.fontWeight = tab === 'import' ? 'bold' : '600';
  imp.style.borderBottom = tab === 'import' ? '3px solid var(--navy)' : 'none';
}

document.getElementById('staff-backup-modal').addEventListener('click', function(e) {
  if (e.target === this) closeStaffBackupModal();
});

function staffExportJSON(includeDocs) {
  if (!staffAllRecords.length) {
    document.getElementById('staff-export-status').textContent = 'No records to export.';
    return;
  }
  const records = staffAllRecords.map(r => {
    if (includeDocs) return Object.assign({}, r);
    const clean = {};
    Object.keys(r).forEach(k => { if (!k.endsWith('_base64')) clean[k] = r[k]; });
    return clean;
  });
  const payload = {
    _avesta_backup: true, version: '2',
    exported_at: new Date().toISOString(),
    exported_by: 'Avesta Staff Portal',
    device: navigator.userAgent.slice(0, 60),
    record_count: records.length,
    include_docs: includeDocs, records
  };
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'Avesta_Backup_' + (includeDocs?'full':'data') + '_' + new Date().toISOString().slice(0,10) + '.json';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(a.href), 3000);
  document.getElementById('staff-export-status').textContent =
    '✅ Downloaded ' + records.length + ' record' + (records.length!==1?'s':'') + (includeDocs?' with documents.':' (data only).');
}

function staffExportCSV() {
  if (!staffAllRecords.length) {
    document.getElementById('staff-export-status').textContent = 'No records to export.';
    return;
  }
  const cols = ['submitted_at','full_name','national_id','date_of_birth','phone','email',
    'address','occupation','monthly_income','loan_amount','loan_purpose','loan_duration',
    'interest_rate','total_repayment','repay_method','receive_method','disburse_date',
    'status','gps_latitude','gps_longitude'];
  const esc = v => '"' + String(v||'').replace(/"/g,'""') + '"';
  const headers = cols.map(c => esc(c.replace(/_/g,' '))).join(',');
  const rows = staffAllRecords.map(r => cols.map(c => esc(r[c])).join(','));
  const blob = new Blob([[headers,...rows].join('\n')], {type:'text/csv'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'Avesta_Applications_' + new Date().toISOString().slice(0,10) + '.csv';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(a.href), 3000);
  document.getElementById('staff-export-status').textContent =
    '✅ CSV downloaded — ' + staffAllRecords.length + ' records.';
}

function staffDragOver(e) {
  e.preventDefault();
  document.getElementById('staff-drop-zone').style.background='rgba(217,142,59,.12)';
  document.getElementById('staff-drop-zone').style.borderColor='var(--navy)';
}
function staffDragLeave(e) {
  document.getElementById('staff-drop-zone').style.background='rgba(217,142,59,.04)';
  document.getElementById('staff-drop-zone').style.borderColor='var(--gold)';
}
function staffDrop(e) {
  e.preventDefault(); staffDragLeave(e);
  if (e.dataTransfer.files[0]) staffParseFile(e.dataTransfer.files[0]);
}
function staffImportFile(input) {
  if (input.files && input.files[0]) staffParseFile(input.files[0]);
}

function staffParseFile(file) {
  if (!file.name.endsWith('.json')) {
    document.getElementById('staff-import-status').style.color='#c0392b';
    document.getElementById('staff-import-status').textContent='❌ Please upload a .json backup file.';
    return;
  }
  const reader = new FileReader();
  reader.onload = function(ev) {
    try {
      const parsed = JSON.parse(ev.target.result);
      if (!parsed.records || !Array.isArray(parsed.records)) throw new Error('Invalid format');
      staffImportData = parsed;
      staffShowImportPreview(parsed);
    } catch(err) {
      document.getElementById('staff-import-status').style.color='#c0392b';
      document.getElementById('staff-import-status').textContent='❌ Invalid file: '+err.message;
    }
  };
  reader.readAsText(file);
}

function staffShowImportPreview(parsed) {
  const existing = new Set(staffAllRecords.map(r => r._key));
  let newC=0, updC=0;
  parsed.records.forEach(r => {
    if (!r._key) r._key = STAFF_STORAGE_KEY+(r.submitted_at||Date.now()).toString().replace(/[^a-z0-9]/gi,'_');
    if (!existing.has(r._key)) newC++; else updC++;
  });
  const sum = document.getElementById('staff-import-summary');
  sum.textContent = parsed.records.length + ' records in file · ' + newC + ' new · ' + updC + ' existing';
  sum.style.color = 'var(--navy)';
  document.getElementById('staff-import-list').innerHTML = parsed.records.slice(0,12).map(r=>
    '<div style="padding:4px 0;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;gap:8px">' +
    '<span style="font-weight:bold">'+(r.full_name||r.b_name||'—').replace(/</g,'&lt;')+'</span>' +
    '<span style="color:#888">ZMW '+parseFloat(r.loan_amount||r.loan_amt||0).toLocaleString()+' · '+(r.status||'New').replace(/</g,'&lt;')+' · '+(r.submitted_at||'').slice(0,16)+'</span>' +
    '</div>'
  ).join('') + (parsed.records.length>12?'<div style="color:#aaa;font-size:8pt;padding:4px 0">…and '+(parsed.records.length-12)+' more</div>':'');
  document.getElementById('staff-import-preview').style.display='block';
  document.getElementById('staff-import-btn').style.display='block';
  document.getElementById('staff-import-clear').style.display='inline-block';
  document.getElementById('staff-import-status').textContent='';
}

function staffConfirmImport() {
  if (!staffImportData) return;
  const btn = document.getElementById('staff-import-btn');
  btn.disabled=true; btn.textContent='⏳ Importing…';
  let added=0, updated=0, skipped=0;
  staffImportData.records.forEach(r => {
    if (!r._key) r._key = STAFF_STORAGE_KEY+(r.submitted_at||Date.now()).toString().replace(/[^a-z0-9]/gi,'_');
    r.full_name   = r.full_name   || r.b_name  || '—';
    r.national_id = r.national_id || r.b_id    || '—';
    r.phone       = r.phone       || r.b_phone || '—';
    r.loan_amount = r.loan_amount || r.loan_amt|| '0';
    r.status      = r.status                   || 'New Application';
    const existing = localStorage.getItem(r._key);
    if (!existing) { localStorage.setItem(r._key, JSON.stringify(r)); added++; }
    else {
      try {
        const local = JSON.parse(existing);
        const merged = Object.assign({}, r, {
          ...Object.fromEntries(Object.entries(local).filter(([k])=>k.endsWith('_base64'))),
          status: r.status||local.status||'New Application'
        });
        if (JSON.stringify(merged)!==JSON.stringify(local)) {
          localStorage.setItem(r._key,JSON.stringify(merged)); updated++;
        } else { skipped++; }
      } catch(e) { skipped++; }
    }
  });
  staffLoadLocal();
  btn.disabled=false; btn.textContent='✅ Import Records';
  const st = document.getElementById('staff-import-status');
  st.style.color='#198754';
  st.textContent='✅ Done — '+added+' added, '+updated+' updated, '+skipped+' unchanged.';
  staffImportData=null;
}

function staffClearImport() {
  staffImportData=null;
  document.getElementById('staff-import-preview').style.display='none';
  document.getElementById('staff-import-btn').style.display='none';
  document.getElementById('staff-import-clear').style.display='none';
  document.getElementById('staff-import-status').textContent='';
  document.getElementById('staff-import-file').value='';
  document.getElementById('staff-drop-zone').style.background='rgba(217,142,59,.04)';
  document.getElementById('staff-drop-zone').style.borderColor='var(--gold)';
}

function backupRecords() { openStaffBackupModal(); }

// ── DOCUMENT VIEWER ────────────────────────────────────────────────────────
function activateStaffDocumentTile(event) {
  if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
  const tile = event.target.closest('[data-document-field][data-record-index]');
  if (!tile) return;
  event.preventDefault();
  const idx = Number(tile.dataset.recordIndex);
  const r = staffAllRecords[idx];
  const field = tile.dataset.documentField;
  if (!r || !DOC_LABELS[field]) return;
  viewDoc(idx, field, r[field + '_filename'] || DOC_LABELS[field].label, r[field + '_mimetype'] || '');
}
document.addEventListener('click', activateStaffDocumentTile);
document.addEventListener('keydown', activateStaffDocumentTile);

function viewDoc(recIdx, docKey, filename, mimetype) {
  const r = staffAllRecords[recIdx];
  if (!r) return;
  const b64 = r[docKey + '_base64'];
  const docUrl = r[docKey + '_url']; // server-hosted file (works across devices)

  let src;
  if (b64) {
    src = b64.startsWith('data:') ? b64 : `data:${mimetype};base64,${b64}`;
  } else if (docUrl) {
    src = getStaffScriptUrl() + '?action=doc&key=' + encodeURIComponent(r._key)
        + '&field=' + encodeURIComponent(docKey);
  } else {
    return; // no document available
  }

  document.getElementById('doc-viewer-title').textContent = filename;
  document.getElementById('doc-viewer-download').href = docUrl && !b64 ? src + '&download=1' : src;
  document.getElementById('doc-viewer-download').download = filename;
  const body = document.getElementById('doc-viewer-body');
  if (mimetype.startsWith('image/') || /\.(jpe?g|png|gif|webp)$/i.test(filename)) {
    body.innerHTML = `<img src="${src}" style="max-width:100%;max-height:75vh;border-radius:6px">`;
  } else if (mimetype === 'application/pdf' || /\.pdf$/i.test(filename)) {
    body.innerHTML = `<embed src="${src}" type="application/pdf" width="700" height="500" style="max-width:100%;border-radius:6px">`;
  } else {
    body.innerHTML = `<p style="padding:40px;color:#888">Preview not available. Use the Download button above.</p>`;
  }
  document.getElementById('doc-viewer-modal').style.display = 'flex';
}

function closeDocViewer() {
  document.getElementById('doc-viewer-modal').style.display = 'none';
  document.getElementById('doc-viewer-body').innerHTML = '';
}
document.getElementById('doc-viewer-modal').addEventListener('click', function(e) {
  if (e.target === this) closeDocViewer();
});

// ── EDIT RECORD ────────────────────────────────────────────────────────────
let editingKey = null;

function openEditModal(key) {
  const r = staffAllRecords.find(x => x._key === key);
  if (!r) return;
  editingKey = key;
  const fields = [
    {key:'full_name',label:'Full Name',type:'text'},
    {key:'national_id',label:'NRC / National ID',type:'text'},
    {key:'date_of_birth',label:'Date of Birth',type:'date'},
    {key:'phone',label:'Phone',type:'tel'},
    {key:'email',label:'Email',type:'email'},
    {key:'address',label:'Address',type:'text'},
    {key:'occupation',label:'Occupation',type:'text'},
    {key:'monthly_income',label:'Monthly Income (ZMW)',type:'number'},
    {key:'loan_amount',label:'Loan Amount (ZMW)',type:'number'},
    {key:'loan_purpose',label:'Loan Purpose',type:'text'},
    {key:'loan_duration',label:'Loan Duration',type:'text'},
    {key:'interest_rate',label:'Interest Rate (%)',type:'text'},
    {key:'total_repayment',label:'Total Repayment (ZMW)',type:'number'},
    {key:'first_payment',label:'First Payment Date',type:'date'},
    {key:'submitted_at',label:'Submitted Date',type:'text'},
    {key:'status',label:'Status',type:'select',options:['New Application','Under Review','Approved','Disbursed','Repaid','Rejected']},
  ];
  const rows = fields.map(f => {
    const val = (r[f.key]||'');
    if (f.type === 'select') {
      const opts = f.options.map(o => `<option value="${o}" ${val===o?'selected':''}>${o}</option>`).join('');
      return `<div class="edit-field"><label for="ef_${f.key}">${f.label}</label><select id="ef_${f.key}" style="width:100%;padding:9px 10px;border:1.5px solid #ddd;border-radius:6px;font-size:9pt;outline:none">${opts}</select></div>`;
    }
    return `<div class="edit-field"><label for="ef_${f.key}">${f.label}</label><input type="${f.type}" id="ef_${f.key}" value="${String(val).replace(/"/g,'&quot;')}" style="width:100%;padding:9px 10px;border:1.5px solid #ddd;border-radius:6px;font-size:9pt;outline:none"></div>`;
  }).join('');
  document.getElementById('edit-modal-body').innerHTML = `<div class="grid-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:10px 16px">${rows}</div>`;
  document.getElementById('edit-modal').style.display = 'flex';
}

function saveEdit() {
  if (!editingKey) return;
  const r = staffAllRecords.find(x => x._key === editingKey);
  if (!r) return;
  ['full_name','national_id','date_of_birth','phone','email','address','occupation',
   'monthly_income','loan_amount','loan_purpose','loan_duration','interest_rate',
   'total_repayment','first_payment','submitted_at','status'].forEach(k => {
    const el = document.getElementById('ef_' + k);
    if (el) r[k] = el.value;
  });
  try { localStorage.setItem(editingKey, JSON.stringify(r)); } catch(e) { alert('Save failed: ' + e.message); return; }
  staffPushStatus(r); // push status change at minimum
  closeEditModal();
  staffLoadLocal();
  avToast('Record updated. Your changes are saved on this device.', 'ok');
}

function closeEditModal() {
  document.getElementById('edit-modal').style.display = 'none';
  editingKey = null;
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});

</script>

</div><!-- /page-content -->

<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <div class="fb-name">AVESTA ENTERPRISES</div>
      <div class="fb-tag">Easy finance when you need it</div>
    </div>
    <div class="footer-links">
      <button onclick="showTab('home')">Home</button>
      <button onclick="showTab('how')">How It Works</button>
      <button onclick="showTab('about')">About Us</button>
      <button onclick="showTab('calc')">Calculator</button>
      <button onclick="showTab('apply')">Apply</button>
      <button onclick="showTab('contact')">Contact</button>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">© <span id="yr"></span> Avesta Enterprises. Ndola, Zambia. All rights reserved.</div>
    <div class="footer-made">Kansenshi, Ndola · Zambia</div>
  </div>
</footer>

<!-- WHATSAPP -->
<a class="wa-float" href="https://wa.me/260971013108?text=Hello%20Avesta%20Enterprises%2C%20I%27m%20interested%20in%20a%20loan." target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
  <span class="wa-tooltip">Chat with us</span>
  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>

<!-- SCROLL TOP -->
<a class="scroll-top" id="scroll-top" href="javascript:void(0)" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Back to top">↑</a>

<script>
/* ══ TAB SYSTEM ══════════════════════════════════════════════════════════ */
function showTab(id) {
  // The other office's panels are not in this document. Asking for one is not
  // an error — fall back to this office's own landing tab rather than throwing
  // and taking the rest of the page down with it.
  let panel = document.getElementById('tab-' + id);
  if (!panel) {
    id = window.AV_LANDING || 'home';
    panel = document.getElementById('tab-' + id);
    if (!panel) return;
  }
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
  panel.classList.add('active');
  const btn = document.getElementById('ntab-' + id);
  if (btn) btn.classList.add('active');
  window.scrollTo({top: 0, behavior: 'smooth'});
  // Guarded: sandboxed iframes (about:srcdoc), file:// pages and some in-app
  // browsers block history writes and throw SecurityError. Without this the
  // throw aborts the rest of showTab(), so the signature redraw below never
  // runs. The tab itself still switches — only the URL hash is skipped.
  try { history.replaceState(null, '', '#' + id); } catch (e) { /* no-op */ }
  // Redraw lender signature when apply tab becomes visible
  if (id === 'apply') {
    setTimeout(() => {
      if (sigCanvases.lender && !sigSigned.lender) drawLenderDefaultSig();
      else if (sigCanvases.lender && sigSigned.lender) {
        // trigger resize to re-scale on DPR
        window.dispatchEvent(new Event('resize'));
      }
    }, 80);
  }
}

// Load correct tab from URL hash on page load
(function() {
  const hash = location.hash.replace('#','');
  // Shared with the site-separation script, which trims whatever belongs to
  // the other office so its deep links stop resolving here.
  window.AV_VALID_TABS = window.AV_VALID_TABS ||
    ['home','how','calc','services','currency','apply','contact','about','staff'];
  const valid = window.AV_VALID_TABS;
  if (hash && valid.includes(hash)) showTab(hash);
  else if (window.AV_LANDING && window.AV_LANDING !== 'home') showTab(window.AV_LANDING);
})();

/* ══ MOBILE NAV ══════════════════════════════════════════════════════════ */
function toggleMobileNav() {
  const nav = document.getElementById('nav-mobile');
  const btn = document.getElementById('nav-toggle-btn');
  const open = nav.classList.toggle('open');
  btn.setAttribute('aria-expanded', open);
}
function closeMobileNav() {
  document.getElementById('nav-mobile').classList.remove('open');
  document.getElementById('nav-toggle-btn').setAttribute('aria-expanded','false');
}

/* ══ SCROLL TO TOP ═══════════════════════════════════════════════════════ */
window.addEventListener('scroll', () => {
  const st = document.getElementById('scroll-top');
  if (st) st.classList.toggle('visible', window.scrollY > 300);
});

/* ══ YEAR ════════════════════════════════════════════════════════════════ */
document.getElementById('yr').textContent = new Date().getFullYear();

/* ══ DATA ════════════════════════════════════════════════════════════════ */
const DURATION_RATES = {1:15, 2:20, 3:25, 4:30};
const MOBILE_DETAILS = {
  mobile_airtel: '<strong>Airtel Money:</strong> Send to <strong>0971013108</strong> — Name: Avesta Enterprises. Use your full name as reference.',
  mobile_mtn:    '<strong>MTN MoMo:</strong> Send to <strong>0769974200</strong> — Name: Avesta Enterprises. Use your full name as reference.',
  zamtel:        '<strong>Zamtel Kwacha:</strong> Send to <strong>0971013108</strong> — Name: Avesta Enterprises. Use your full name as reference.',
};
const BANK_DETAILS = {
  bank_zanaco:  '<strong>ZANACO:</strong> Account Name: Avesta Enterprises &nbsp;|&nbsp; Account No: <em>provided on request</em> &nbsp;|&nbsp; Branch: Ndola.',
  bank_fnb:     '<strong>FNB Zambia:</strong> Account Name: Avesta Enterprises &nbsp;|&nbsp; Account No: <em>provided on request</em> &nbsp;|&nbsp; Branch: Ndola.',
  bank_stanbic: '<strong>Stanbic Bank:</strong> Account Name: Avesta Enterprises &nbsp;|&nbsp; Account No: <em>provided on request</em> &nbsp;|&nbsp; Branch: Ndola.',
  bank_other:   '<strong>Other Bank:</strong> Please contact us at <strong>0971013108</strong> for our banking details.',
};
const CASH_DETAIL = '<strong>Cash:</strong> Visit us at <strong>House No. 3, Thom Avenue, Kansenshi, Ndola</strong>. Office hours: Mon–Sat, 08:00–17:00.';

function getMethodDetail(val) {
  if (val === 'cash') return CASH_DETAIL;
  return MOBILE_DETAILS[val] || BANK_DETAILS[val] || '';
}

/* ══ CALCULATOR ══════════════════════════════════════════════════════════ */
function pickTier(tile, weeks, rate) {
  document.querySelectorAll('.rate-tier').forEach(t => t.classList.remove('active'));
  tile.classList.add('active');
  document.getElementById('c_rate').value = rate;
  document.getElementById('c_dur').value = weeks;
  calcLoan();
}
function applyDurRate() {
  const dur = parseInt(document.getElementById('c_dur').value);
  if (DURATION_RATES[dur]) {
    document.getElementById('c_rate').value = DURATION_RATES[dur];
    document.querySelectorAll('.rate-tier').forEach((t,i) => t.classList.toggle('active', i+1===dur));
    calcLoan();
  }
}
function calcLoan() {
  const amt  = parseFloat(document.getElementById('c_amt').value) || 0;
  const rate = parseFloat(document.getElementById('c_rate').value) || 0;
  const dur  = document.getElementById('c_dur').value;
  const sched= document.getElementById('c_sched').value;
  const fmt  = v => 'ZMW ' + v.toLocaleString('en-ZM',{minimumFractionDigits:2,maximumFractionDigits:2});
  const durL = {1:'1 Week',2:'2 Weeks',3:'3 Weeks',4:'4 Weeks'};
  if (amt > 0) {
    const interest = amt * rate / 100, total = amt + interest;
    document.getElementById('r_principal').textContent = fmt(amt);
    document.getElementById('r_rate_disp').textContent = rate > 0 ? rate + '%' : '—';
    document.getElementById('r_interest').textContent  = rate > 0 ? fmt(interest) : '—';
    document.getElementById('r_total').textContent     = fmt(total);
    document.getElementById('r_dur').textContent       = durL[dur] || '—';
    document.getElementById('r_sched').textContent     = sched || '—';
  } else {
    ['r_principal','r_rate_disp','r_interest','r_total','r_dur','r_sched'].forEach(id => {
      document.getElementById(id).textContent = '—';
    });
  }
}

/* ══ FORM ════════════════════════════════════════════════════════════════ */
function showDisburseDetail(radio) {
  // Disbursement = Avesta sends to borrower, no 'send to' instructions shown here
  document.getElementById('disburse_detail').style.display = 'none';
}
function showRepayDetail(radio) {
  const box = document.getElementById('repay_detail');
  const detail = getMethodDetail(radio.value);
  box.innerHTML = detail; box.style.display = detail ? 'block' : 'none';
}
function selectDur(tile, weeks, rate) {
  document.querySelectorAll('.dur-tile').forEach(t => t.classList.remove('active'));
  tile.classList.add('active');
  const labels = {1:'1 Week (15% interest)',2:'2 Weeks (20% interest)',3:'3 Weeks (25% interest)',4:'4 Weeks (30% interest)'};
  document.getElementById('sel_dur').value = labels[weeks];
  document.getElementById('int_rate').value = rate;
  selectedDurationWeeks = weeks;
  const durErr = document.getElementById('dur-err');
  if (durErr) durErr.style.display = 'none';
  document.getElementById('custom-dur-detail').style.display = 'none';
  updateTotals();
  saveProgress();
}

const LONG_TERM_BASE_WEEKS = 4;
const LONG_TERM_BASE_RATE = 30;
const LONG_TERM_RATE_PER_EXTRA_WEEK = 5;

function selectLongTermDur(tile) {
  document.querySelectorAll('.dur-tile').forEach(t => t.classList.remove('active'));
  tile.classList.add('active');
  const durErr = document.getElementById('dur-err');
  if (durErr) durErr.style.display = 'none';
  document.getElementById('custom-dur-detail').style.display = '';
  updateLongTermDur();
  saveProgress();
}

function updateLongTermDur() {
  const weeksInput = document.getElementById('custom_weeks');
  const display = document.getElementById('custom_rate_display');
  const weeks = parseInt(weeksInput.value) || 0;
  if (weeks >= LONG_TERM_BASE_WEEKS + 1) {
    const extraWeeks = weeks - LONG_TERM_BASE_WEEKS;
    const rate = LONG_TERM_BASE_RATE + (LONG_TERM_RATE_PER_EXTRA_WEEK * extraWeeks);
    display.value = rate + '% (' + weeks + ' weeks)';
    document.getElementById('sel_dur').value = weeks + ' Weeks (' + rate + '% interest)';
    document.getElementById('int_rate').value = rate;
    selectedDurationWeeks = weeks;
    updateTotals();
  } else {
    display.value = '';
    document.getElementById('sel_dur').value = '';
    document.getElementById('int_rate').value = '';
    selectedDurationWeeks = 0;
    updateTotals();
  }
  saveProgress();
}

// ── NUMBER TO WORDS (Zambian Kwacha) ─────────────────────────────────────────
function numberToWords(n) {
  if (!n || isNaN(n) || n <= 0) return '';
  const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
    'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen',
    'Eighteen','Nineteen'];
  const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  function say(num) {
    if (num === 0) return '';
    if (num < 20) return ones[num] + ' ';
    if (num < 100) return tens[Math.floor(num/10)] + ' ' + (num%10 ? ones[num%10] + ' ' : '');
    if (num < 1000) return ones[Math.floor(num/100)] + ' Hundred ' + say(num%100);
    if (num < 1000000) return say(Math.floor(num/1000)) + 'Thousand ' + say(num%1000);
    if (num < 1000000000) return say(Math.floor(num/1000000)) + 'Million ' + say(num%1000000);
    return say(Math.floor(num/1000000000)) + 'Billion ' + say(num%1000000000);
  }
  const int = Math.floor(n);
  const dec = Math.round((n - int) * 100);
  let result = say(int).trim() + ' Kwacha';
  if (dec > 0) result += ' and ' + say(dec).trim() + ' Ngwee';
  // Capitalise first letter
  return result.charAt(0).toUpperCase() + result.slice(1);
}

function updateLoanWords() {
  const el = document.getElementById('loan_words');
  if (!el || el.dataset.userEdited === 'true') return;
  const amt = parseFloat(document.getElementById('loan_amt').value) || 0;
  el.value = amt > 0 ? numberToWords(amt) : '';
}

function updateTotals() {
  const amt  = parseFloat(document.getElementById('loan_amt').value) || 0;
  const rate = parseFloat(document.getElementById('int_rate').value) || 0;
  const interest = amt * rate / 100, total = amt + interest;
  document.getElementById('total_int').value   = interest > 0 ? interest.toFixed(2) : '';
  document.getElementById('total_repay').value = total > 0 ? total.toFixed(2) : '';
  const fmtCommit = v => v > 0 ? 'ZMW ' + v.toLocaleString('en-ZM',{minimumFractionDigits:2,maximumFractionDigits:2}) : 'ZMW —';
  const ccPrincipal = document.getElementById('cc-principal');
  const ccInterest = document.getElementById('cc-interest');
  const ccTotal = document.getElementById('cc-total');
  if (ccPrincipal) ccPrincipal.textContent = fmtCommit(amt);
  if (ccInterest) ccInterest.textContent = fmtCommit(interest);
  if (ccTotal) ccTotal.textContent = fmtCommit(total);
  updateInstallment();
  updateLateFee();
  updateRepaymentDates();
  updateLoanWords();
}

// ── AUTO REPAYMENT DATES ─────────────────────────────────────────────────────
// Tracks the currently selected loan duration in weeks (set by selectDur /
// selectLongTermDur). Used to auto-fill First/Last Payment Due Date.
let selectedDurationWeeks = 0;

// Tracks whether the user has manually edited a date field — once edited,
// auto-calculation stops overwriting that specific field.
const userEditedDates = { first_payment: false, last_payment: false };

function userEditedDate(fieldId) {
  userEditedDates[fieldId] = true;
}

function addWeeksToDate(dateStr, weeks) {
  if (!dateStr || !weeks) return '';
  const d = new Date(dateStr + 'T00:00:00');
  if (isNaN(d.getTime())) return '';
  d.setDate(d.getDate() + (weeks * 7));
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function repaymentPlan() {
  const weeks = selectedDurationWeeks;
  const choice = document.querySelector('#wiz-3 .check-pills input:checked');
  if (!choice || !weeks) return null;
  const interval = choice.value === 'Weekly' ? 1 : choice.value === 'Bi-Weekly' ? 2 : choice.value === 'Monthly' ? 4 : weeks;
  return { count: Math.ceil(weeks / interval), firstWeeks: Math.min(interval, weeks) };
}

function updateRepaymentSchedule(changed) {
  document.querySelectorAll('#wiz-3 .check-pills input').forEach(input => {
    if (input !== changed) input.checked = false;
  });
  updateInstallment();
  updateRepaymentDates();
  updateFieldCounter();
  saveProgress();
}

function updateRepaymentDates() {
  const disburseDate = document.getElementById('disburse_date').value;
  const plan = repaymentPlan();
  const first = document.getElementById('first_payment');
  const last = document.getElementById('last_payment');
  if (!userEditedDates.first_payment) first.value = disburseDate && plan ? addWeeksToDate(disburseDate, plan.firstWeeks) : '';
  if (!userEditedDates.last_payment) last.value = disburseDate && plan ? addWeeksToDate(disburseDate, selectedDurationWeeks) : '';
}
const LATE_RATE_PER_WEEK = 7.5; // % increment per week overdue
function updateLateFee() {
  const total = parseFloat(document.getElementById('total_repay').value) || 0;
  const weeks = parseInt(document.getElementById('weeks_overdue').value) || 0;
  const display = document.getElementById('late_rate_display');
  const lateFee = document.getElementById('late_fee');
  if (weeks > 0) {
    const ratePct = LATE_RATE_PER_WEEK * weeks;
    display.value = ratePct + '% (' + weeks + ' wk' + (weeks>1?'s':'') + ' \u00d7 ' + LATE_RATE_PER_WEEK + '%/wk)';
    lateFee.value = total > 0 ? (total * ratePct / 100).toFixed(2) : '';
  } else {
    display.value = LATE_RATE_PER_WEEK + '% per week overdue';
    lateFee.value = '';
  }
}
function updateInstallment() {
  // Normalize older drafts that allowed several schedules to be checked.
  const choices = [...document.querySelectorAll('#wiz-3 .check-pills input:checked')];
  choices.slice(1).forEach(input => { input.checked = false; });
  const plan = repaymentPlan();
  const total = Math.round((parseFloat(document.getElementById('total_repay').value) || 0) * 100);
  const summary = document.getElementById('repayment-plan-summary');
  document.getElementById('num_inst').value = plan ? plan.count : '';
  if (!plan || total <= 0) {
    document.getElementById('inst_amt').value = '';
    if (summary) summary.textContent = 'Choose one repayment schedule to see the payment count and amounts. The monthly option uses four-week intervals, with the final payment due at the end of the term.';
    return;
  }
  const regular = Math.floor(total / plan.count);
  const last = total - regular * (plan.count - 1);
  document.getElementById('inst_amt').value = (regular / 100).toFixed(2);
  const money = cents => 'K' + (cents / 100).toFixed(2);
  if (summary) summary.textContent = last === regular
    ? plan.count + ' payment' + (plan.count === 1 ? '' : 's') + ' of ' + money(regular) + '. Total: ' + money(total) + '.'
    : (plan.count - 1) + ' payments of ' + money(regular) + ' and a final payment of ' + money(last) + '. Total: ' + money(total) + '. The final payment includes the rounding adjustment.';
}
async function clearForm() {
  const go = await avConfirm('Clear the form?',
    'Everything you have typed so far is removed, including uploads. This cannot be undone.',
    { okText: 'Clear form', cancelText: 'Keep my answers' });
  if (!go) return;
  document.querySelectorAll('#tab-apply input:not([readonly])').forEach(i => {
    if (i.type === 'radio' || i.type === 'checkbox') i.checked = false;
    else i.value = '';
  });
  selectedDurationWeeks = 0;
  updateTotals();
  userEditedDates.first_payment = false;
  userEditedDates.last_payment = false;
  const lw = document.getElementById('loan_words');
  if (lw) lw.dataset.userEdited = 'false';
  updateLateFee();
  // Clear file uploads
  document.querySelectorAll('#tab-apply .upload-item').forEach(box => box.classList.remove('has-file'));
  document.querySelectorAll('#tab-apply .upload-filename').forEach(el => el.textContent = '');
  document.querySelectorAll('.dur-tile').forEach(t => t.classList.remove('active'));
  document.getElementById('custom-dur-detail').style.display = 'none';
  document.getElementById('custom_rate_display').value = '';
  ['disburse_detail','repay_detail'].forEach(id => {
    const el = document.getElementById(id);
    if (el) { el.style.display='none'; el.innerHTML=''; }
  });
  const s = document.getElementById('submit-status');
  if (s) { s.style.display='none'; s.textContent=''; }
  // Reset dynamic collateral/reference rows to one each
  const collList = document.getElementById('collateral-list');
  if (collList) {
    collList.innerHTML = `<div class="collateral-item"><span>Item 1:</span><input placeholder="Description of item"><span style="color:var(--navy);font-weight:bold;white-space:nowrap;font-size:9pt;">Est. Value (ZMW):</span><input type="number" placeholder="0.00" style="border:1px solid var(--mgray);border-radius:4px;padding:8px;font-family:Arial;font-size:9pt;outline:none;"></div>`;
  }
  const refList = document.getElementById('ref-list');
  if (refList) {
    refList.innerHTML = `<div class="ref-block2"><div class="rb-title">Reference 1</div><div class="rb-body"><div><label>Full Name</label><input placeholder="Reference full name"></div><div><label>Phone</label><input type="tel" placeholder="0XXXXXXXXX"></div><div><label>Relationship</label><input placeholder="e.g. Colleague, Neighbour"></div></div></div>`;
  }
  // Reset security/guarantee type sections
  const collPhotoSec = document.getElementById('collateral-photo-section');
  if (collPhotoSec) collPhotoSec.style.display = 'none';
  ['collateral-heading','collateral-list','checkoff-section','employer-letter-section','guarantor-section'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
  const addCollBtn = document.getElementById('add-collateral-btn');
  if (addCollBtn) addCollBtn.style.display = 'none';
  ['employer-letter-box','gtr-nrc-box'].forEach(id => {
    const box = document.getElementById(id);
    if (box) box.classList.remove('has-file');
  });
  ['employer-letter-name','gtr-nrc-name'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = '';
  });
  // Clear signature pads (and reset undo/redo history for a fresh form)
  clearSig('borrower'); // lender sig is fixed
  sigHistory.borrower = { undoStack: [], redoStack: [] };
  updateSigButtons('borrower');
  if (sigCanvases.lender) { const ctx = sigCanvases.lender.getContext('2d'); const r = window.devicePixelRatio||1; ctx.clearRect(0,0,sigCanvases.lender.width/r,sigCanvases.lender.height/r); sigSigned.lender=false; document.getElementById('sigwrap_lender').classList.remove('signed'); }
  drawLenderDefaultSig();
  document.getElementById('sig_lender_name').value = 'Avesta Enterprises';
  drawLenderDefaultSig();
  document.getElementById('agree_terms').checked = false;
  const gc = document.getElementById('gps-coords');
  if (gc) { gc.style.display='none'; gc.innerHTML=''; }
  const gb = document.getElementById('gps-btn');
  if (gb) { gb.textContent='📍 Capture My Location'; gb.classList.remove('captured'); gb.disabled=false; }
  document.querySelectorAll('#tab-apply .f-group.error').forEach(g => g.classList.remove('error'));
  document.querySelectorAll('#tab-apply .invalid').forEach(el => el.classList.remove('invalid'));
  localStorage.removeItem('avesta_loan_draft');
  document.getElementById('resume-banner').style.display = 'none';
  goToStep(1);
  // Reset submit button state
  const sb = document.getElementById('submit-btn');
  if (sb) { sb.disabled = false; sb.textContent = '✅ Submit Application'; sb.style.background = ''; }
  const ss = document.getElementById('submit-status');
  if (ss) { ss.style.display = 'none'; ss.textContent = ''; }
}

/* ══ AUTO-SAVE / RESUME ══════════════════════════════════════════════════ */
const DRAFT_KEY = 'avesta_loan_draft';

function saveProgress() {
  try {
    const data = {};
    document.querySelectorAll('#tab-apply input:not([type=file]):not([readonly])').forEach(el => {
      if (!el.id && !el.name) return;
      if (el.type === 'radio') {
        if (el.checked) data['radio_' + el.name] = el.value;
      } else if (el.type === 'checkbox') {
        const key = el.id || (el.closest('label') ? 'chk_' + el.closest('label').textContent.trim() : null);
        if (key) data[key] = el.checked;
      } else if (el.id) {
        data[el.id] = el.value;
      }
    });
    // dynamic collateral & reference values (no ids, capture by position)
    data._collateral = Array.from(document.querySelectorAll('#collateral-list .collateral-item')).map(row => {
      const inputs = row.querySelectorAll('input');
      return [inputs[0].value, inputs[1].value];
    });
    data._references = Array.from(document.querySelectorAll('#ref-list .ref-block2')).map(block => {
      const inputs = block.querySelectorAll('input');
      return [inputs[0].value, inputs[1].value, inputs[2].value];
    });
    data._activeDur = document.querySelector('.dur-tile.active') ? Array.from(document.querySelectorAll('.dur-tile')).indexOf(document.querySelector('.dur-tile.active')) : -1;
    data._sigBorrower = sigCanvases.borrower && sigSigned.borrower ? sigCanvases.borrower.toDataURL() : null;
    data._step = currentStep;
    localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
  } catch(e) { /* ignore quota errors */ }
}

function checkForSavedDraft() {
  const raw = localStorage.getItem(DRAFT_KEY);
  if (!raw) return;
  try {
    const data = JSON.parse(raw);
    const hasContent = Object.keys(data).some(k => !k.startsWith('_') && data[k]);
    if (hasContent) document.getElementById('resume-banner').style.display = 'flex';
  } catch(e) {}
}

function resumeApplication() {
  const raw = localStorage.getItem(DRAFT_KEY);
  if (!raw) return;
  try {
    const data = JSON.parse(raw);
    Object.keys(data).forEach(key => {
      if (key.startsWith('_')) return;
      if (key.startsWith('radio_')) {
        const name = key.slice(6);
        const r = document.querySelector(`input[name="${name}"][value="${CSS.escape(data[key])}"]`);
        if (r) { r.checked = true; r.dispatchEvent(new Event('change')); }
      } else if (key.startsWith('chk_')) {
        const label = key.slice(4);
        document.querySelectorAll('#tab-apply input[type=checkbox]').forEach(c => {
          if (c.closest('label') && c.closest('label').textContent.trim() === label) c.checked = data[key];
        });
      } else {
        const el = document.getElementById(key);
        if (el) el.value = data[key];
      }
    });
    // collateral
    if (data._collateral && data._collateral.length) {
      const list = document.getElementById('collateral-list');
      list.innerHTML = '';
      data._collateral.forEach((vals, i) => {
        addCollateral();
        const row = list.children[i];
        const inputs = row.querySelectorAll('input');
        inputs[0].value = vals[0] || ''; inputs[1].value = vals[1] || '';
      });
    }
    // references
    if (data._references && data._references.length) {
      const list = document.getElementById('ref-list');
      list.innerHTML = '';
      data._references.forEach((vals, i) => {
        addReference();
        const block = list.children[i];
        const inputs = block.querySelectorAll('input');
        inputs[0].value = vals[0] || ''; inputs[1].value = vals[1] || ''; inputs[2].value = vals[2] || '';
      });
    }
    // duration tile — also restore selectedDurationWeeks so auto-dates keep working
    if (typeof data._activeDur === 'number' && data._activeDur >= 0) {
      const tiles = document.querySelectorAll('.dur-tile');
      if (tiles[data._activeDur]) {
        tiles[data._activeDur].classList.add('active');
        if (tiles[data._activeDur].id === 'dur-tile-custom') {
          document.getElementById('custom-dur-detail').style.display = '';
          updateLongTermDur(); // this sets selectedDurationWeeks internally
        } else {
          // Standard 1-4 week tiles: derive weeks from the saved sel_dur text
          const durText = data.sel_dur || '';
          const m = durText.match(/^(\d+)\s*Week/);
          if (m) selectedDurationWeeks = parseInt(m[1]);
        }
      }
    }
    // Restore manual-edit flags so auto-calc doesn't clobber dates the user already set
    if (data.first_payment) userEditedDates.first_payment = true;
    if (data.last_payment) userEditedDates.last_payment = true;
    updateTotals();
    // signature
    if (data._sigBorrower) {
      const img = new Image();
      img.onload = function() {
        const ctx = sigCanvases.borrower.getContext('2d');
        ctx.drawImage(img, 0, 0, sigCanvases.borrower.width, sigCanvases.borrower.height);
        sigSigned.borrower = true;
        document.getElementById('sigwrap_borrower').classList.add('signed');
      };
      img.src = data._sigBorrower;
    }
    if (typeof data._step === 'number') goToStep(data._step);
  } catch(e) {}
  document.getElementById('resume-banner').style.display = 'none';
}

function discardSaved() {
  localStorage.removeItem(DRAFT_KEY);
  document.getElementById('resume-banner').style.display = 'none';
}

/* ══ DYNAMIC ROWS: COLLATERAL & REFERENCES ══════════════════════════════ */
/* ══ SECURITY / GUARANTEE TYPE TOGGLE ════════════════════════════════════ */
function toggleSecuritySection(radio) {
  const val = radio.value;
  document.getElementById('collateral-heading').style.display = (val === 'collateral') ? 'block' : 'none';
  document.getElementById('collateral-list').style.display = (val === 'collateral') ? 'block' : 'none';
  document.getElementById('add-collateral-btn').style.display = (val === 'collateral') ? 'inline-flex' : 'none';
  document.getElementById('checkoff-section').style.display = (val === 'checkoff') ? 'block' : 'none';
  document.getElementById('employer-letter-section').style.display = (val === 'employer_letter') ? 'block' : 'none';
  document.getElementById('guarantor-section').style.display = (val === 'guarantor') ? 'block' : 'none';
  // Show collateral photo section in Step 4 when collateral is selected
  const collPhotoSec = document.getElementById('collateral-photo-section');
  if (collPhotoSec) collPhotoSec.style.display = (val === 'collateral') ? 'block' : 'none';
  saveProgress();
}

let collateralCount = 1;
function addCollateral() {
  collateralCount++;
  const list = document.getElementById('collateral-list');
  const div = document.createElement('div');
  div.className = 'collateral-item';
  div.innerHTML = `<span>Item ${collateralCount}:</span><input placeholder="Description of item"><span style="color:var(--navy);font-weight:bold;white-space:nowrap;font-size:9pt;">Est. Value (ZMW):</span><input type="number" placeholder="0.00" style="border:1px solid var(--mgray);border-radius:4px;padding:8px;font-family:Arial;font-size:9pt;outline:none;"> <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">Remove</button>`;
  list.appendChild(div);
}
let referenceCount = 1;
function addReference() {
  referenceCount++;
  const list = document.getElementById('ref-list');
  const div = document.createElement('div');
  div.className = 'ref-block2';
  div.innerHTML = `<div class="rb-title">Reference ${referenceCount} <button type="button" class="remove-row-btn" style="float:right" onclick="this.closest('.ref-block2').remove()">Remove</button></div><div class="rb-body"><div><label>Full Name</label><input placeholder="Reference full name"></div><div><label>Phone</label><input type="tel" placeholder="0XXXXXXXXX"></div><div><label>Relationship</label><input placeholder="e.g. Colleague, Neighbour"></div></div>`;
  list.appendChild(div);
}

/* ══ SIGNATURE PAD ═══════════════════════════════════════════════════════ */
const sigCanvases = {};
const sigHistory = {}; // { borrower: { undoStack: [dataURL,...], redoStack: [...] } }
const sigSigned = { borrower: false, lender: false };
const LENDER_SIG_DATA = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAbEAAABtCAYAAAAvW19RAAAJPklEQVR4nO3d/XHcNhDGYSSTXlyEunID0jWgrlyEu8kfMR2aIsAFsAvsAr9nxmNbI90dKRAv8cmUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA/Ppr9gcAavz8fPuQfu+37z/E3wsgpr9nfwBAqibAWr4fQDy0xOCaRhDRIgPWRYjBpcbwer/8/3X8gyAD1vTP7A8AnHW0vK4BdnztdfN1AIsgxOAC41cAWhBimIrwAtCDEMMUPeF1Ht86vc4r3XcpAlgYIYbhMgGWnZRxuJuc8e37jw9ac8C+WCeGoYQB9kVpdiEzD4F9EWLw6I9WWEVIMRMR2AwhBg9elz/VCkHHOBkwmWWXP2NicItuQgBPCDEsYefJHaVj50YAHliWQ0IM7nQU+K26Di/hdT52xgaxDcbEgNje09fw3irMsTdaYnCjs8thq4r7Vytsq2MG7hBiGOq8OFmrn/xhPGyprrXTsRJgQCLEMMHoyQarTG6g9QV8xZgYsJ7Xt+8/PlYJ7ztHi7T2b9j4+fk2bfs3HoqJ0AStk1f0yryyCzH88SKen59v026a6E7EykJX6A13tkuN/yGOmdcZIYZwCuujwrsJLpXjO79u5lE2IpFvCqDvrvyMLiN0Jzp3LSQ9BWSVnR2E3WuvlMYel9KYQG9oabfGWESN347rSVLWR117hJhTxoOk7ylTIXkPs5oAS6mv1aFkdkvx/Hue/Vl2t91NwIj6hBBzRljRWlRGrY8/GaqlFTYovAgIjNIThpot/dxriR5oq4UQG6CzEj1aTaMqyeHdcCUdY0S5C52wAca57RXRRIgZCjoBobvQ5SYRlL5PIMr5A/An0xtjZicaCBpeh+x4WU4pjARBFe387G5mVxbwBS2xTplKOvrFWltRRT/eyLabLHBCufPPfHiCEKtUaFmseEHdVZArHmcNzdCoPZdN7+1lfLNHRdfzeQw5asCvcI2Zj4UdCLEKVgtRL6QX3goFfYauis1g533RVlLa77+SXMBJzpV0/Lb180hfM9DNsfj6YZ2YA4ZdhYwr6BLfeXsJgYod6V3NFoW+QTfHLcRrSWfunUiIXSjeERULQOO0ey+FW0NPV2WINW0lwhAjwJw6Ku3aytvZGHrxpi9KuSPEThQKWFXlGqgLoZXFBJFVNvUlwAQ0t10bIcBs3FdL+HpGiKXuMKlane7sTqzGcZzvl/8/Ep4PUaX+9HqeVT7UMnRY78jhtT1054xZtl8n1hhgxQr84W7Ma2BJQ0ltz8WaAFvx4iuIOqtuO47Gs7JlRuPa8dwi3j7EMpoDrOJ1RquuGK0KauV44G4VOt2IE0zcs7SWaKxdu/wM3oe0ytbdiY13UaVK1UMhP3PXBVd5zpep0JnIMUfueVcOu/7ubNEd2GvbEAu+NdQd9wW+4py7C99WNccc/Vg9CTDB4sr99evVdiHmqA+7VZi1UCm1tbxS8nUMrQgwPdLZdAFaWKGu3wh2HhPzVLCvspW5h8eBS7WMfXk9llpMWtEVbMav+iSLlabEa1s+xIK0vMTry6IU5F3Hvi48lrXQHK2tHLJQePZECottubQtE2LOp7WLZtiNKCQjCqWgG223GYc5nIdKN5MyQoVXqZ66+9nea/S8s0jLz5/fX/IaM4LO7ZhYw0mfHVRnrgdpLbsmWgPM0/nR8DAbcamxv1EqN022MuzGI7dH4ej3rHnvGeXZpCVWszAu0PqMEtOFhtosPtOCsz17cQ5szD6vVu//pQ6xCKyea79zQwOzulC9JdZx4mcXTglmFl1UhteXsb8VB6xphdmo3LYL/1FvOfY8XsaizKuGWJBJFBJbdHn1atn3MKW1z6OgomU2YiNCbDiVySvWrTG17sTg3UnbVLIaWlpfnFMgnOK1/fPz7fbr12vd+tq3GBOLEGCuJ15Ya+3CaxhY36rVIehK3+p8YHm3E7dKLS+LvR1VQmz2WoaT4wm/T9+zVWidtfyuGlrZO08dj3ATF9KvMdTrl7WXcBxPCOf32OZ83l65+kZzLDzaOrHHWYCnQp4thL2zbHpfa6aOQdnqpy5HOzfw72bdUnWvysON3OvytyrDafMeQ/dxcpMGlYkdihM6JCH1cff1m8+TPYG1letTIVutsm4c39w+vJjUgZkCLVdSrStUWmI3AdOUtJIDmvFcm9pV6xF1TMwRFUir5xw5VOyKWnFJAfS19PwIy5Vk9w7roDu6bFVUh1hp0G7kxZnbSkUSMj1TPs/vu0Jl1DmrtCrAoHvxYj8aN0G53qtT3SYpoyZB13J80cbEfjsqxsxBi09wy0mLHl4KyyG27zp88NgaS4lzp6U0xFC7V6EXpbEzi+GN42cqfnb2Hpa/NY2JeekSuasMJLslePjss3TuP0d4PRCeX86joqeelZpt8LyT9mqMPsaW5TcplVuF0vduaol5KQSFz5G9E/by2WfoCLDtw1/q0iXjYRB9eU/lcqVyO/tYPPYihO1ObLDVzDDFBwUSYJUIMqzO8kGktXWN20exlJTuBgrdiVuEmGI/NV1enVZZWwhcaQxL7LrYmZluBQrPWyK4FHEOsQrNnh3t6yJciPU8pXRFSg8KJLyADdSMaRXqWfXNLHqEC7EeXmZVatBsdaVEeAH4n8H0ebOx9ZBjYiXaW055oFygaHUB+MJo3Zf5xLCtWmIpxWqNKT9klFmGAP5gvGB5SJ2zW4ipbPtjGYS0ugBYMwyv4cMUu4VYSsn+cdk1DNZb0OIC8IXF8puckfXPqiFmusi09xdktFCQ8ALwZLnlN6uGmNjArsEcpsYD8ChE/bJyiOVaY6aPw3gILrWpqgfPhQtASCHC67DcFPuUxH2/t0H29BiH3C9VeSbhGaEFoJvkCR8pxatjlgyxlKoHMaUPgXv6PpPgSilewQLgy02IiYPL02S4q2W7Eyu3p5KGj+WO5AQXAGtd9YzHOmnZltjhoQntAd2FAIaKtOnDk11CLCVfQUarCwAULB9iKZlOupA4ZknS4gIAZVuE2Nmo7VZyCC8A0LNdiB06wky8xozAAgBb24ZYSsXtn5oWQxNaADDW1iF2dg00AgkAAAAAAAAAAACoU7EFFgBAaNm9E7UdIVQz4YPgAgBbzE4U8LyDMwAAj2hVAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACA2P4FDLUpNKNLd3sAAAAASUVORK5CYII=";

function drawLenderDefaultSig(retries) {
  retries = retries || 0;
  const canvas = sigCanvases.lender;
  if (!canvas) return;
  const wrap = canvas.parentElement;

  // If the panel isn't visible yet, the canvas has zero width — retry shortly
  // instead of silently failing to draw (caps at 10 retries / ~1s).
  const rectW = wrap.getBoundingClientRect().width;
  if (!rectW && retries < 10) {
    setTimeout(() => drawLenderDefaultSig(retries + 1), 100);
    return;
  }

  const ctx = canvas.getContext('2d');
  const padW = rectW || wrap.offsetWidth || 400;
  const padH = 120;
  ctx.clearRect(0, 0, padW, padH);
  const img = new Image();
  img.onload = () => {
    const maxW = padW * 0.80, maxH = padH * 0.80;
    let w = img.width, h = img.height;
    const scale = Math.min(maxW / w, maxH / h);
    w = Math.round(w * scale);
    h = Math.round(h * scale);
    const x = Math.round((padW - w) / 2);
    const y = Math.round((padH - h) / 2);
    ctx.drawImage(img, x, y, w, h);
    sigSigned.lender = true;
    wrap.classList.add('signed');
  };
  img.onerror = () => {
    console.warn('Lender signature image failed to load');
  };
  img.src = LENDER_SIG_DATA;
}

function initSigPad(who) {
  const canvas = document.getElementById('sigpad_' + who);
  if (!canvas) return;
  sigCanvases[who] = canvas;
  const wrap = canvas.parentElement;
  function resize() {
    const ratio = window.devicePixelRatio || 1;
    const rect = wrap.getBoundingClientRect();
    const prevImg = sigSigned[who] ? canvas.toDataURL() : null;
    canvas.width = rect.width * ratio;
    canvas.height = 120 * ratio;
    const ctx = canvas.getContext('2d');
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = who === 'lender' ? '#D98E3B' : '#163E33';
    if (prevImg) {
      const img = new Image();
      img.onload = () => ctx.drawImage(img, 0, 0, rect.width, 120);
      img.src = prevImg;
    } else if (who === 'lender') {
      // Defer draw to ensure the canvas has rendered dimensions
      setTimeout(drawLenderDefaultSig, 50);
    }
  }
  resize();
  window.addEventListener('resize', resize);


  if (!sigHistory[who]) sigHistory[who] = { undoStack: [], redoStack: [] };

  function snapshotBefore() {
    // Save canvas state BEFORE the next stroke begins, for undo
    try {
      sigHistory[who].undoStack.push(canvas.toDataURL());
      if (sigHistory[who].undoStack.length > 20) sigHistory[who].undoStack.shift(); // cap history
      sigHistory[who].redoStack = []; // new stroke invalidates redo history
      updateSigButtons(who);
    } catch(e) {}
  }

  let drawing = false;
  function pos(e) {
    const rect = canvas.getBoundingClientRect();
    const t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - rect.left, y: t.clientY - rect.top };
  }
  function start(e) {
    e.preventDefault();
    drawing = true;
    snapshotBefore();
    const ctx = canvas.getContext('2d');
    const p = pos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
  }
  function move(e) {
    if (!drawing) return;
    e.preventDefault();
    const ctx = canvas.getContext('2d');
    const p = pos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    if (!sigSigned[who]) {
      sigSigned[who] = true;
      wrap.classList.add('signed');
    }
  }
  function end(e) {
    if (!drawing) return;
    drawing = false;
    if (who === 'borrower') saveProgress();
  }
  // Lender pad is fixed — no drawing allowed
  if (who === 'lender') return;
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, {passive:false});
  canvas.addEventListener('touchmove', move, {passive:false});
  canvas.addEventListener('touchend', end);
}

function clearSig(who) {
  if (who === 'lender') return; // Lender signature is fixed
  const canvas = sigCanvases[who];
  if (!canvas) return;
  // Save current state for undo before clearing
  if (sigHistory[who]) {
    try {
      sigHistory[who].undoStack.push(canvas.toDataURL());
      if (sigHistory[who].undoStack.length > 20) sigHistory[who].undoStack.shift();
      sigHistory[who].redoStack = [];
    } catch(e) {}
  }
  const ctx = canvas.getContext('2d');
  const ratio = window.devicePixelRatio || 1;
  ctx.clearRect(0, 0, canvas.width / ratio, canvas.height / ratio);
  sigSigned[who] = false;
  document.getElementById('sigwrap_' + who).classList.remove('signed');
  const errBox = document.getElementById('sigwrap_' + who).closest('.sig-field2').querySelector('.f-err');
  if (errBox) errBox.parentElement.classList.remove('error');
  updateSigButtons(who);
}

function undoSig(who) {
  if (who === 'lender') return; // Lender signature is fixed, no undo
  const canvas = sigCanvases[who];
  const hist = sigHistory[who];
  if (!canvas || !hist || hist.undoStack.length === 0) return;

  // Save current state to redo stack before reverting
  try { hist.redoStack.push(canvas.toDataURL()); } catch(e) {}

  const prevState = hist.undoStack.pop();
  const ctx = canvas.getContext('2d');
  const rect = canvas.parentElement.getBoundingClientRect();
  ctx.clearRect(0, 0, rect.width, 120);

  // Was the previous state a blank canvas? Compare against a known-empty signature
  const img = new Image();
  img.onload = () => {
    ctx.clearRect(0, 0, rect.width, 120);
    ctx.drawImage(img, 0, 0, rect.width, 120);
    // Detect if canvas is now effectively empty
    checkIfSigEmpty(who);
  };
  img.src = prevState;
  updateSigButtons(who);
}

function redoSig(who) {
  if (who === 'lender') return;
  const canvas = sigCanvases[who];
  const hist = sigHistory[who];
  if (!canvas || !hist || hist.redoStack.length === 0) return;

  try { hist.undoStack.push(canvas.toDataURL()); } catch(e) {}

  const nextState = hist.redoStack.pop();
  const ctx = canvas.getContext('2d');
  const rect = canvas.parentElement.getBoundingClientRect();
  const img = new Image();
  img.onload = () => {
    ctx.clearRect(0, 0, rect.width, 120);
    ctx.drawImage(img, 0, 0, rect.width, 120);
    sigSigned[who] = true;
    document.getElementById('sigwrap_' + who).classList.add('signed');
  };
  img.src = nextState;
  updateSigButtons(who);
}

// Detects whether the canvas is visually blank after an undo, and updates state
function checkIfSigEmpty(who) {
  const canvas = sigCanvases[who];
  if (!canvas) return;
  try {
    const ctx = canvas.getContext('2d');
    const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    let isEmpty = true;
    for (let i = 3; i < data.length; i += 4) { // check alpha channel
      if (data[i] !== 0) { isEmpty = false; break; }
    }
    sigSigned[who] = !isEmpty;
    const wrap = document.getElementById('sigwrap_' + who);
    if (isEmpty) wrap.classList.remove('signed');
    else wrap.classList.add('signed');
  } catch(e) {}
}

// Enables/disables the Undo and Redo buttons based on available history
function updateSigButtons(who) {
  const hist = sigHistory[who] || { undoStack: [], redoStack: [] };
  const undoBtn = document.getElementById('sig-undo-' + who);
  const redoBtn = document.getElementById('sig-redo-' + who);
  if (undoBtn) undoBtn.disabled = hist.undoStack.length === 0;
  if (redoBtn) redoBtn.disabled = hist.redoStack.length === 0;
}


/* ══ WIZARD NAVIGATION ═══════════════════════════════════════════════════ */
const TOTAL_STEPS = 5;
let currentStep = 1;

function goToStep(n) {
  if (n < 1) n = 1;
  if (n > TOTAL_STEPS) n = TOTAL_STEPS;
  currentStep = n;
  document.querySelectorAll('.wiz-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('wiz-' + n).classList.add('active');
  document.querySelectorAll('.wp-step').forEach(s => {
    const step = parseInt(s.dataset.step);
    s.classList.remove('active','done');
    if (step < n) s.classList.add('done');
    else if (step === n) s.classList.add('active');
  });
  document.getElementById('wiz-back').disabled = (n === 1);
  const nextBtn = document.getElementById('wiz-next');
  const submitBtn = document.getElementById('submit-btn');
  const printBtn = document.getElementById('print-btn');
  if (n === TOTAL_STEPS) {
    nextBtn.style.display = 'none';
    submitBtn.style.display = 'inline-block';
    printBtn.style.display = 'inline-block';
    renderReview();
  } else {
    nextBtn.style.display = 'inline-block';
    submitBtn.style.display = 'none';
    printBtn.style.display = 'none';
  }
  updateFieldCounter();
  // Update progress fill bar
  const fillBar = document.getElementById('wiz-fill-bar');
  if (fillBar) fillBar.style.width = (n / TOTAL_STEPS * 100) + '%';
  // Ensure lender signature is always visible the moment its step becomes active —
  // the canvas has zero size while its panel is display:none, so we must redraw
  // once the panel is actually visible and has real dimensions.
  if (sigCanvases.lender) {
    requestAnimationFrame(() => {
      setTimeout(() => {
        if (!sigSigned.lender) {
          drawLenderDefaultSig();
        } else {
          // Re-scale existing signature to the now-visible canvas size
          window.dispatchEvent(new Event('resize'));
        }
      }, 60);
    });
  }
  document.querySelector('.form-card').scrollIntoView({behavior:'smooth', block:'start'});
}

function wizNav(dir) {
  if (dir > 0) {
    if (!validateStep(currentStep)) { updateFieldCounter(); return; }
  } else {
    hideStepBanner(currentStep);
  }
  goToStep(currentStep + dir);
  saveProgress();
}

// ── VALIDATION ─────────────────────────────────────────────────────────────
function showStepBanner(step, messages) {
  const banner = document.getElementById('step' + step + '-err-banner');
  const text   = document.getElementById('step' + step + '-err-text');
  if (!banner || !text) return;
  if (messages.length === 0) {
    banner.classList.remove('visible');
    return;
  }
  text.innerHTML = '<strong>Please complete the following before continuing:</strong><ul style="margin:4px 0 0 16px;list-style:disc">' +
    messages.map(m => `<li>${m}</li>`).join('') + '</ul>';
  banner.classList.add('visible');
}
function hideStepBanner(step) {
  const b = document.getElementById('step' + step + '-err-banner');
  if (b) b.classList.remove('visible');
}

function clearStepErrors(step) {
  const panel = document.getElementById('wiz-' + step);
  panel.querySelectorAll('.f-group.error').forEach(g => g.classList.remove('error'));
  panel.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
  panel.querySelectorAll('.upload-item.error').forEach(el => el.classList.remove('error'));
  panel.querySelectorAll('.method-grid.error,.method-grid.error-disburse,.dur-rate-row.error').forEach(el => {
    el.classList.remove('error','error-disburse');
  });
  panel.querySelectorAll('.f-err').forEach(el => { el.style.display = 'none'; el.textContent = ''; });
  panel.querySelectorAll('.sig-box2.error').forEach(el => el.classList.remove('error'));
  hideStepBanner(step);
}

function validateStep(step) {
  clearStepErrors(step);
  let ok = true;
  const panel = document.getElementById('wiz-' + step);
  const errorMessages = [];

  // ── Required text / number inputs ────────────────────────────────────────
  panel.querySelectorAll('.f-group[data-req="1"]').forEach(group => {
    const input   = group.querySelector('input');
    const select  = group.querySelector('select');
    const el      = input || select;
    const empty   = el && !el.value.trim();
    if (empty) {
      group.classList.add('error');
      if (el) el.classList.add('invalid');
      const label = group.querySelector('label');
      const fErr  = group.querySelector('.f-err');
      const name  = label ? label.textContent.replace('*','').trim() : 'This field';
      if (fErr && !fErr.textContent) fErr.textContent = name + ' is required';
      errorMessages.push(name + ' is required');
      ok = false;
    }
  });

  // ── Step 2: disbursement method ───────────────────────────────────────────
  if (step === 2) {
    const checked  = panel.querySelector('input[name="disburse"]:checked');
    const grid     = panel.querySelector('.method-grid');
    const errEl    = document.getElementById('disburse-err');
    if (!checked) {
      if (grid) grid.classList.add('error-disburse');
      if (errEl) { errEl.style.display = 'block'; errEl.textContent = 'Please select how you will receive your loan funds'; }
      errorMessages.push('Loan disbursement method is required');
      ok = false;
    }
  }

  // ── Step 3: duration + repayment method ───────────────────────────────────
  if (step === 3) {
    // Duration tile
    const durErr    = document.getElementById('dur-err');
    const activeTile = document.querySelector('.dur-tile.active');
    const durRow    = document.querySelector('.dur-rate-row');
    if (!activeTile) {
      durErr.style.display = 'block';
      durErr.textContent = 'Please select a loan duration';
      if (durRow) durRow.classList.add('error');
      errorMessages.push('Loan duration is required');
      ok = false;
    } else if (activeTile.id === 'dur-tile-custom') {
      const weeks = parseInt(document.getElementById('custom_weeks').value) || 0;
      if (weeks < LONG_TERM_BASE_WEEKS + 1) {
        durErr.style.display = 'block';
        durErr.textContent = 'Enter number of weeks (minimum 5) for a long-term loan';
        if (durRow) durRow.classList.add('error');
        errorMessages.push('Custom loan duration weeks required (minimum 5)');
        ok = false;
      } else { durErr.style.display = 'none'; }
    } else { durErr.style.display = 'none'; }

    if (!repaymentPlan()) {
      errorMessages.push('Choose one repayment schedule');
      ok = false;
    }

    // Repayment method
    const repayErr  = document.getElementById('repay-err');
    const repayGrid = panel.querySelector('.method-grid');
    if (!document.querySelector('input[name="repay_method"]:checked')) {
      repayErr.style.display = 'block';
      repayErr.textContent = 'Please select a repayment method';
      if (repayGrid) repayGrid.classList.add('error');
      errorMessages.push('Repayment method is required');
      ok = false;
    } else {
      if (repayGrid) repayGrid.classList.remove('error');
    }
  }

  // ── Step 4: NRC, both sides, required. Passport photo optional. ──────────
  if (step === 4) {
    const nrcFrontEl = document.getElementById('doc_nrc_front');
    const nrcBackEl  = document.getElementById('doc_nrc_back');
    const hasNrcFront = nrcFrontEl && nrcFrontEl.files && nrcFrontEl.files.length > 0;
    const hasNrcBack  = nrcBackEl  && nrcBackEl.files  && nrcBackEl.files.length  > 0;

    // The NRC is the identity document. A passport photo is optional and
    // never counts in its place.
    const hasValidId = hasNrcFront && hasNrcBack;

    if (!hasValidId) {
      // Mark only the side that is actually missing
      if (!hasNrcFront) document.getElementById('nrc-front-box').classList.add('error');
      if (!hasNrcBack)  document.getElementById('nrc-back-box').classList.add('error');
      const docErr = document.getElementById('doc-upload-err');
      if (docErr) {
        docErr.style.display = 'block';
        docErr.textContent = hasNrcFront
          ? '⚠ Please upload the back of your NRC as well.'
          : hasNrcBack
            ? '⚠ Please upload the front of your NRC as well.'
            : '⚠ Please upload both sides of your NRC (front and back).';
      }
      document.getElementById(hasNrcFront ? 'nrc-back-box' : 'nrc-front-box')
        .scrollIntoView({ behavior:'smooth', block:'center' });
      errorMessages.push('NRC (both sides) is required');
      ok = false;
    } else {
      ['nrc-front-box','nrc-back-box','passport-box'].forEach(id => {
        document.getElementById(id).classList.remove('error');
      });
      const docErr = document.getElementById('doc-upload-err');
      if (docErr) docErr.style.display = 'none';
    }

    // ── Collateral photo required if security type = collateral ────────────
    const securityRadio = document.querySelector('input[name="security_type"]:checked');
    const secType = securityRadio ? securityRadio.value : '';
    if (secType === 'collateral') {
      const coll1 = document.getElementById('doc_collateral1');
      const hasCollPhoto = coll1 && coll1.files && coll1.files.length > 0;
      if (!hasCollPhoto) {
        document.getElementById('collateral1-box').classList.add('error');
        const collErr = document.getElementById('collateral-photo-err');
        if (collErr) {
          collErr.style.display = 'block';
          collErr.textContent = '⚠ Please upload at least one photo of your collateral item.';
        }
        document.getElementById('collateral1-box').scrollIntoView({ behavior:'smooth', block:'center' });
        errorMessages.push('At least one collateral photo is required');
        ok = false;
      } else {
        document.getElementById('collateral1-box').classList.remove('error');
        const collErr = document.getElementById('collateral-photo-err');
        if (collErr) collErr.style.display = 'none';
      }
    }
  }

  // ── Step 5: terms + signature ─────────────────────────────────────────────
  if (step === 5) {
    const terms    = document.getElementById('agree_terms');
    const termsErr = document.getElementById('terms-err');
    if (!terms.checked) {
      if (termsErr) { termsErr.style.display = 'block'; termsErr.textContent = 'You must accept the Terms and Conditions to submit'; }
      errorMessages.push('Accept the Terms and Conditions');
      ok = false;
    }
    const sigGroup = document.getElementById('sigwrap_borrower') ? document.getElementById('sigwrap_borrower').closest('.sig-box2') : null;
    const sigErr   = document.getElementById('borrower-sig-err');
    if (!sigSigned.borrower) {
      if (sigGroup) sigGroup.classList.add('error');
      if (sigErr)   { sigErr.style.display = 'block'; sigErr.textContent = 'Please draw your signature in the box above'; }
      errorMessages.push('Borrower signature is required');
      ok = false;
    } else {
      if (sigGroup) sigGroup.classList.remove('error');
      if (sigErr)   sigErr.style.display = 'none';
    }
  }

  // ── Show banner + scroll to first error ──────────────────────────────────
  if (!ok) {
    showStepBanner(step, errorMessages);
    setTimeout(() => {
      const banner = document.getElementById('step' + step + '-err-banner');
      const firstErr = banner && banner.classList.contains('visible')
        ? banner
        : panel.querySelector('.error, .invalid, .upload-item.error');
      if (firstErr) firstErr.scrollIntoView({behavior:'smooth', block:'center'});
    }, 60);
  } else {
    hideStepBanner(step);
  }
  return ok;
}

function updateFieldCounter() {
  const counter = document.getElementById('field-counter');
  const panel = document.getElementById('wiz-' + currentStep);
  let remaining = 0;

  panel.querySelectorAll('.f-group[data-req="1"]').forEach(group => {
    const input = group.querySelector('input');
    if (input && !input.value.trim()) remaining++;
  });
  if (currentStep === 2 && !panel.querySelector('input[name="disburse"]:checked')) remaining++;
  if (currentStep === 3) {
    const activeTile = document.querySelector('.dur-tile.active');
    if (!activeTile) remaining++;
    else if (activeTile.id === 'dur-tile-custom') {
      const weeks = parseInt(document.getElementById('custom_weeks').value) || 0;
      if (weeks < LONG_TERM_BASE_WEEKS + 1) remaining++;
    }
    if (!document.querySelector('input[name="repay_method"]:checked')) remaining++;
  }
  if (currentStep === 4) {
    const nrcFront = document.getElementById('doc_nrc_front');
    const nrcBack  = document.getElementById('doc_nrc_back');
    const hasFront = nrcFront && nrcFront.files && nrcFront.files.length > 0;
    const hasBack  = nrcBack  && nrcBack.files  && nrcBack.files.length  > 0;
    if (!(hasFront && hasBack)) remaining++;
  }
  if (currentStep === 5) {
    if (!document.getElementById('agree_terms').checked) remaining++;
    if (!sigSigned.borrower) remaining++;
  }

  if (remaining === 0) {
    counter.textContent = currentStep === TOTAL_STEPS ? '✓ Ready to submit' : '✓ All set for this step';
    counter.classList.add('ok');
    hideStepBanner(currentStep);
  } else {
    counter.textContent = remaining + ' item' + (remaining > 1 ? 's' : '') + ' remaining';
    counter.classList.remove('ok');
  }
}

/* ══ REVIEW SCREEN ═══════════════════════════════════════════════════════ */
function rv(label, value, step) {
  const display = (value && value.trim()) ? value : '—';
  const emptyClass = (value && value.trim()) ? '' : ' empty';
  return `<div class="review-row"><span class="rv-lbl">${label}</span><span class="rv-val${emptyClass}">${display}</span></div>`;
}

function renderReview() {
  const c = document.getElementById('review-content');
  const collateral = Array.from(document.querySelectorAll('#collateral-list .collateral-item')).map(row => {
    const inputs = row.querySelectorAll('input');
    return (inputs[0].value || inputs[1].value) ? `${inputs[0].value || '—'} (ZMW ${inputs[1].value || '0.00'})` : null;
  }).filter(Boolean).join(', ');
  const refs = Array.from(document.querySelectorAll('#ref-list .ref-block2')).map(block => {
    const inputs = block.querySelectorAll('input');
    return inputs[0].value ? `${inputs[0].value} (${inputs[2].value || '—'}, ${inputs[1].value || '—'})` : null;
  }).filter(Boolean).join('; ');

  let html = '';
  html += `<div class="review-section"><h4>Borrower Information <button type="button" class="review-edit" onclick="goToStep(1)">Edit</button></h4><div class="review-grid">`;
  html += rv('Full Name', gv('b_name'));
  html += rv('Phone', gv('b_phone'));
  html += rv('National ID', gv('b_id'));
  html += rv('Date of Birth', gv('b_dob'));
  html += rv('Address', gv('b_address'));
  html += rv('Email', gv('b_email'));
  html += rv('Occupation', gv('b_occupation'));
  html += rv('Monthly Income', gv('b_income') ? 'ZMW ' + gv('b_income') : '');
  html += `</div>`;
  html += `</div>`;

  const secLabels = {collateral:'Collateral', checkoff:'Salary Check-off (Payroll Deduction)', employer_letter:'Employer Confirmation Letter', guarantor:'Guarantor', none:'None / Character-based'};
  const secType = getChecked('security_type');
  let secDetail = '—';
  if (secType === 'collateral') secDetail = collateral;
  else if (secType === 'checkoff') secDetail = `Employer: ${gv('co_employer') || '—'}; HR Contact: ${gv('co_hr_name') || '—'} (${gv('co_hr_phone') || '—'}); Payroll No: ${gv('co_payroll_no') || '—'}`;
  else if (secType === 'employer_letter') secDetail = document.getElementById('doc_employer_letter').files.length ? '✅ ' + document.getElementById('doc_employer_letter').files[0].name : '⚠️ Not uploaded';
  else if (secType === 'guarantor') secDetail = `${gv('gtr_name') || '—'} (${gv('gtr_relationship') || '—'}), ${gv('gtr_phone') || '—'}, NRC: ${gv('gtr_id') || '—'}${document.getElementById('doc_gtr_nrc').files.length ? ' ✅ NRC uploaded' : ''}`;
  html += `<div class="review-grid" style="margin-top:6px"><div class="review-row" style="grid-column:1/-1">${rv('Security / Guarantee Type', secType !== '—' ? secLabels[secType] : '')}</div><div class="review-row" style="grid-column:1/-1">${rv('Security Details', secDetail === '—' ? '' : secDetail)}</div><div class="review-row" style="grid-column:1/-1">${rv('References', refs)}</div></div>`;
  html += `</div>`;

  html += `<div class="review-section"><h4>Loan Details <button type="button" class="review-edit" onclick="goToStep(2)">Edit</button></h4><div class="review-grid">`;
  html += rv('Loan Amount', gv('loan_amt') ? 'ZMW ' + gv('loan_amt') : '');
  html += rv('Amount in Words', gv('loan_words'));
  html += rv('Purpose', gv('loan_purpose'));
  html += rv('Disbursement Method', getChecked('disburse'));
  html += rv('Disbursement Date', gv('disburse_date'));
  html += `</div></div>`;

  html += `<div class="review-section"><h4>Repayment Terms <button type="button" class="review-edit" onclick="goToStep(3)">Edit</button></h4><div class="review-grid">`;
  html += rv('Duration', gv('sel_dur'));
  html += rv('Interest Rate', gv('int_rate') ? gv('int_rate') + '%' : '');
  html += rv('Total Interest', gv('total_int') ? 'ZMW ' + gv('total_int') : '');
  html += rv('Total Repayment', gv('total_repay') ? 'ZMW ' + gv('total_repay') : '');
  html += rv('Installments', gv('num_inst'));
  html += rv('Installment Amount', gv('inst_amt') ? 'ZMW ' + gv('inst_amt') : '');
  html += rv('Late Payment Interest Rate', gv('late_rate_display'));
  html += rv('Late Interest Amount', gv('late_fee') ? 'ZMW ' + gv('late_fee') : '');
  html += rv('First Payment', gv('first_payment'));
  html += rv('Last Payment', gv('last_payment'));
  html += rv('Repayment Schedule', getCheckboxes('#wiz-3 .check-pills'));
  html += rv('Repayment Method', getChecked('repay_method'));
  html += `</div></div>`;

  html += `<div class="review-section"><h4>Supporting Documents <button type="button" class="review-edit" onclick="goToStep(4)">Edit</button></h4><div>`;
  const _nrcF = document.getElementById('doc_nrc_front');
  const _nrcB = document.getElementById('doc_nrc_back');
  const hasNrc = (_nrcF && _nrcF.files.length > 0) && (_nrcB && _nrcB.files.length > 0);
  const docMap = [['doc_nrc_front','NRC — Front Side','nrc'],['doc_nrc_back','NRC — Back Side','nrc'],['doc_passport','Passport Photo','optional'],['doc_collateral1','Collateral Photo 1','collateral'],['doc_collateral2','Collateral Photo 2',false],['doc_collateral3','Collateral Photo 3',false],['doc_salary1','Salary Proof – Month 1',false],['doc_salary2','Salary Proof – Month 2',false],['doc_payslip','Payslip / Proof of Income',false],['doc_bank_statement','Bank Statement',false],['doc_utility_bill','Utility Bill',false],['doc_employer_letter','Employer Letter',false],['doc_gtr_nrc','Guarantor NRC',false]];
  docMap.forEach(([id,label,required]) => {
    const el = document.getElementById(id);
    const ok = el.files && el.files.length > 0;
    let icon, text;
    if (ok) { icon = '✅'; text = el.files[0].name; }
    else if (required === 'nrc') { icon = '⚠️'; text = 'Not uploaded — required'; }
    else { icon = '➖'; text = 'Not provided (optional)'; }
    html += `<span class="review-doc-thumb">${icon} ${label}: ${text}</span>`;
  });
  html += `</div></div>`;

  c.innerHTML = html;
}

/* ══ INIT ════════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {
  // Signature pads are wrapped because they are the FIRST thing to run here.
  // Some privacy browsers and in-app WebViews block the canvas 2d context, and
  // an unguarded throw would take out everything below it — draft resume,
  // auto-save and live field validation would all silently stop working.
  try {
    initSigPad('borrower');
    initSigPad('lender');
  } catch (e) {
    console.warn('Signature pad unavailable in this browser:', e);
  }
  // Everything below belongs to the loan application. On the IT office that
  // tab is not in the document at all, so there is nothing to wire up.
  const applyTab = document.getElementById('tab-apply');
  if (!applyTab) return;

  updateSigButtons('borrower');
  updateLateFee();
  checkForSavedDraft();
  // Auto-save on any input/change within the apply tab
  applyTab.addEventListener('input', debounce(saveProgress, 800));
  applyTab.addEventListener('change', () => { saveProgress(); updateFieldCounter(); });

  // Live feedback: clear error state as soon as user fills a field
  document.getElementById('tab-apply').addEventListener('input', function(e) {
    const target = e.target;
    if (target.value && target.value.trim()) {
      target.classList.remove('invalid');
      const group = target.closest('.f-group');
      if (group && group.classList.contains('error')) {
        const label = group.querySelector('label');
        if (label) label.style.color = '';
        group.classList.remove('error');
      }
    }
    updateFieldCounter();
  });
  document.getElementById('tab-apply').addEventListener('change', function(e) {
    const target = e.target;
    // Clear upload box error when file selected
    const uploadBox = target.closest('.upload-item');
    if (uploadBox && uploadBox.classList.contains('error') && target.files && target.files.length > 0) {
      uploadBox.classList.remove('error');
    }
    // Clear method-grid error when radio selected
    if (target.type === 'radio') {
      const grid = target.closest('.method-grid');
      if (grid) { grid.classList.remove('error','error-disburse'); }
      const durRow = target.closest('.dur-rate-row');
      if (durRow) durRow.classList.remove('error');
      // clear associated err element
      const disburseErr = document.getElementById('disburse-err');
      if (disburseErr && target.name === 'disburse') disburseErr.style.display = 'none';
      const repayErr = document.getElementById('repay-err');
      if (repayErr && target.name === 'repay_method') repayErr.style.display = 'none';
    }
    updateFieldCounter();
  });
  updateFieldCounter();
});

function debounce(fn, ms) {
  let t;
  return function(...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); };
}


/* ══ GPS LOCATION CAPTURE ════════════════════════════════════════════════ */
function captureGPS() {
  const btn = document.getElementById('gps-btn');
  const coords = document.getElementById('gps-coords');
  if (!navigator.geolocation) {
    coords.style.display = 'block';
    coords.style.color = '#c0392b';
    coords.textContent = '⚠ GPS not supported by your browser.';
    return;
  }
  btn.textContent = '⏳ Getting location...';
  btn.disabled = true;
  navigator.geolocation.getCurrentPosition(
    function(pos) {
      const lat = pos.coords.latitude.toFixed(6);
      const lng = pos.coords.longitude.toFixed(6);
      const acc = Math.round(pos.coords.accuracy);
      document.getElementById('gps_lat').value = lat;
      document.getElementById('gps_lng').value = lng;
      document.getElementById('gps_accuracy').value = acc;
      btn.textContent = '✅ Location Captured';
      btn.classList.add('captured');
      btn.disabled = false;
      coords.style.display = 'block';
      coords.style.color = '#198754';
      coords.innerHTML = `✅ Lat: ${lat}, Lng: ${lng} &nbsp;(±${acc}m) &nbsp;<a href="https://maps.google.com/?q=${lat},${lng}" target="_blank" rel="noopener" style="color:var(--gold);font-size:8pt">View on Map ↗</a>`;
    },
    function(err) {
      btn.textContent = '📍 Capture My Location';
      btn.disabled = false;
      coords.style.display = 'block';
      coords.style.color = '#c0392b';
      const msgs = {1:'Location access denied. Please allow location in browser settings.',2:'Location unavailable. Try again.',3:'Request timed out. Try again.'};
      coords.textContent = '⚠ ' + (msgs[err.code] || 'Could not get location.');
    },
    {enableHighAccuracy: true, timeout: 12000, maximumAge: 0}
  );
}

/* ══ DOCUMENT UPLOAD HANDLER ════════════════════════════════════════════ */
function updateDocCounter() {
  const count = DOC_FIELD_IDS.filter(id => {
    const el = document.getElementById(id);
    return el && el.files && el.files.length > 0;
  }).length;
  const numEl   = document.getElementById('doc-count-num');
  const pluralEl = document.getElementById('doc-count-plural');
  const hintEl  = document.getElementById('doc-counter-hint');
  const bar     = document.getElementById('doc-counter-bar');
  if (numEl) numEl.textContent = count;
  if (pluralEl) pluralEl.textContent = count === 1 ? '' : 's';

  // The NRC, both sides, is the requirement
  const hasNrcFront = (document.getElementById('doc_nrc_front') || {files:[]}).files.length > 0;
  const hasNrcBack  = (document.getElementById('doc_nrc_back')  || {files:[]}).files.length > 0;
  const hasValidId  = hasNrcFront && hasNrcBack;

  if (bar) {
    bar.style.background  = hasValidId ? '#f0faf4' : (count > 0 ? '#fff8e1' : '#fff5f5');
    bar.style.borderColor = hasValidId ? '#c3e6cb' : (count > 0 ? '#ffe082' : '#f5c6cb');
  }
  if (hintEl) {
    if (hasValidId) {
      hintEl.textContent = '✅ NRC attached';
      hintEl.style.color = '#198754';
    } else if (hasNrcFront && !hasNrcBack) {
      hintEl.textContent = '⚠️ NRC back side still needed';
      hintEl.style.color = '#e67e22';
    } else if (hasNrcBack && !hasNrcFront) {
      hintEl.textContent = '⚠️ NRC front side still needed';
      hintEl.style.color = '#e67e22';
    } else {
      hintEl.textContent = '⚠️ NRC (both sides) required';
      hintEl.style.color = '#c0392b';
    }
  }

  // Also clear collateral error if photo 1 just got attached
  const coll1 = document.getElementById('doc_collateral1');
  if (coll1 && coll1.files && coll1.files.length > 0) {
    const collErr = document.getElementById('collateral-photo-err');
    if (collErr) collErr.style.display = 'none';
    const cb = document.getElementById('collateral1-box');
    if (cb) cb.classList.remove('error');
  }
}

function handleUpload(input, boxId, nameId) {
  const box = document.getElementById(boxId);
  const nameEl = document.getElementById(nameId);
  delete window.__docPayloads[input.id];
  const oldThumb = box.querySelector('.upload-thumb');
  if (oldThumb) oldThumb.remove();

  function reset(message) {
    input.value = '';
    delete window.__docPayloads[input.id];
    box.classList.remove('has-file', 'is-working');
    if (nameEl) nameEl.textContent = '';
    if (message) alert(message);
    updateDocCounter();
    updateFieldCounter();
  }

  if (!(input.files && input.files[0])) { reset(); return; }
  const file = input.files[0];
  const photo = isImageFile(file);

  // Photos are shrunk before anything is sent, so their original size does not
  // matter — a 9 MB phone photo becomes about 200 KB. The old 5 MB check ran
  // BEFORE compression, and turned away perfectly usable photos. PDFs are sent
  // as they are, so they keep a limit.
  const maxMB = photo ? 40 : 5;
  if (file.size > maxMB * 1024 * 1024) {
    reset(photo
      ? 'That photo is unusually large (' + (file.size / 1048576).toFixed(0) + ' MB). Please take it again with the normal camera setting.'
      : 'The file "' + file.name + '" is over 5 MB. Please send a smaller copy, or a photo of the pages instead.');
    return;
  }

  box.classList.add('has-file');
  box.classList.remove('error');
  box.style.borderColor = '';

  const markDone = function () {
    // Clear the NRC error once both sides are in
    if (['nrc-front-box','nrc-back-box','passport-box'].includes(boxId)) {
      const front = (document.getElementById('doc_nrc_front')||{files:[]}).files.length > 0;
      const back  = (document.getElementById('doc_nrc_back') ||{files:[]}).files.length > 0;
      if (front && back) {
        ['nrc-front-box','nrc-back-box','passport-box'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.classList.remove('error');
        });
        const de = document.getElementById('doc-upload-err');
        if (de) de.style.display = 'none';
      }
    }
    // Clear the collateral error once a photo is in
    if (boxId === 'collateral1-box') {
      const ce = document.getElementById('collateral-photo-err');
      if (ce) ce.style.display = 'none';
    }
    updateDocCounter();
    updateFieldCounter();
  };

  if (!photo) {
    if (nameEl) nameEl.textContent = '✅ ' + file.name;
    markDone();
    return;
  }

  // Photos: compress now, show what we got
  box.classList.add('is-working');
  if (nameEl) nameEl.textContent = '⏳ Preparing photo…';
  fileToPayload(file).then(function (payload) {
    if (input.files[0] !== file) return;          // replaced while working
    window.__docPayloads[input.id] = payload;
    box.classList.remove('is-working');
    if (payload.preview) {
      const t = document.createElement('img');
      t.className = 'upload-thumb';
      t.alt = 'Preview of the photo you chose';
      t.src = payload.preview;
      box.appendChild(t);
    }
    const kb = Math.round(payload.base64.length * 0.75 / 1024);
    if (nameEl) nameEl.textContent = '✅ ' + file.name + ' · ready (' + kb + ' KB)';
    markDone();
  }).catch(function () {
    const heic = /\.(heic|heif)$/i.test(file.name) || /heic|heif/i.test(file.type);
    reset(heic
      ? 'This phone photo format (HEIC) cannot be read here. Please take the photo again from this form using the camera option, or choose a JPEG copy.'
      : 'This photo could not be read. Please take it again, or choose a different picture.');
  });
  // No markDone() here: it would mark the photo ready while it still says
  // "Preparing photo…". The .then() above does it once the photo exists.
  updateDocCounter();
}

/* ══ GOOGLE SHEETS SUBMIT ════════════════════════════════════════════════ */
// ▶ PASTE YOUR GOOGLE APPS SCRIPT WEB APP URL BELOW
const SHEET_URL = "api.php"; // Relative path — works on any device that loads this site

function gv(id) {
  const el = document.getElementById(id);
  return el ? el.value.trim() : '';
}
function getChecked(name) {
  return Array.from(document.querySelectorAll(`input[name="${name}"]:checked`))
    .map(r => r.value).join(', ') || '—';
}
function getCheckboxes(groupSelector) {
  return Array.from(document.querySelectorAll(`${groupSelector} input[type=checkbox]:checked`))
    .map(c => c.closest('label').textContent.trim()).join(', ') || '—';
}

/* ══ FILE → BASE64 (for document upload to server) ═══════════════════════ */
function readFileAsDataURL(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

// Compresses images to keep uploads fast on mobile networks; PDFs pass through unchanged.
//
// A photo the browser cannot decode (typically an iPhone HEIC chosen on a PC)
// is REJECTED rather than sent raw: the server has no type for it and would
// save a .bin file nobody in the office can open.
function isImageFile(file) {
  return (file.type || '').indexOf('image/') === 0 || /\.(jpe?g|png|webp|gif|heic|heif)$/i.test(file.name || '');
}

function decodeImage(file) {
  // createImageBitmap decodes large photos more economically than <img> and
  // honours the camera's rotation flag, so portrait photos stay upright.
  if (typeof createImageBitmap === 'function') {
    return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () {
      return decodeWithImg(file);
    });
  }
  return decodeWithImg(file);
}

function decodeWithImg(file) {
  return readFileAsDataURL(file).then(function (dataUrl) {
    return new Promise(function (resolve, reject) {
      const img = new Image();
      img.onload = function () { resolve(img); };
      img.onerror = function () { reject(new Error('unreadable')); };
      img.src = dataUrl;
    });
  });
}

function fileToPayload(file, maxDim, quality) {
  maxDim = maxDim || 1280;
  quality = quality || 0.72;
  if (!isImageFile(file)) {
    return readFileAsDataURL(file).then(function (dataUrl) {
      return { filename: file.name, mimetype: file.type || 'application/octet-stream',
               base64: dataUrl.split(',')[1] };
    });
  }
  return decodeImage(file).then(function (img) {
    let w = img.width, h = img.height;
    if (!w || !h) throw new Error('unreadable');
    if (w > maxDim || h > maxDim) {
      const scale = maxDim / Math.max(w, h);
      w = Math.round(w * scale); h = Math.round(h * scale);
    }
    const canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('no-canvas');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(img, 0, 0, w, h);
    if (img.close) img.close();
    const outUrl = canvas.toDataURL('image/jpeg', quality);
    const base64 = outUrl.split(',')[1];
    if (!base64) throw new Error('unreadable');
    const baseName = file.name.replace(/\.[^.]+$/, '') || 'document';
    return { filename: baseName + '.jpg', mimetype: 'image/jpeg', base64: base64, preview: outUrl };
  });
}

// Compressed results, made when a photo is picked, reused at submit
window.__docPayloads = window.__docPayloads || {};

const DOC_FIELD_IDS = ['doc_nrc_front','doc_nrc_back','doc_passport','doc_collateral1','doc_collateral2','doc_collateral3','doc_salary1','doc_salary2','doc_employer_letter','doc_gtr_nrc','doc_payslip','doc_bank_statement','doc_utility_bill'];

async function submitToSheet() {
  const btn = document.getElementById('submit-btn');
  const status = document.getElementById('submit-status');

  // Validate all steps; jump to first invalid one
  for (let s = 1; s <= TOTAL_STEPS; s++) {
    if (!validateStep(s)) {
      goToStep(s);
      status.style.display = 'block';
      status.style.background = '#fff3cd';
      status.style.color = '#856404';
      status.textContent = '⚠ Please complete the highlighted fields before submitting.';
      avToast('Step ' + s + ' needs a few more details. The missing fields are highlighted in red.', 'warn');
      return;
    }
  }

  btn.disabled = true;
  btn.textContent = '⏳ Submitting...';
  if (typeof avBusy === 'function') avBusy(btn, true);
  status.style.display = 'block';
  status.style.background = 'rgba(217,142,59,.1)';
  status.style.color = 'var(--navy)';
  status.textContent = 'Preparing documents...';

  // Photos were compressed when picked; anything not yet prepared is done now
  const docPayloads = {};
  for (const id of DOC_FIELD_IDS) {
    const el = document.getElementById(id);
    if (el && el.files && el.files[0]) {
      try {
        docPayloads[id] = window.__docPayloads[id] || await fileToPayload(el.files[0]);
      } catch (e) {
        docPayloads[id] = null;
      }
    }
  }

  // The server takes up to 25 MB per application. Photos are small once
  // compressed; PDFs are sent as they are and can add up. Say which, rather
  // than letting the whole application fail with no explanation.
  const BUDGET = 20 * 1024 * 1024;
  const sizes = Object.keys(docPayloads).filter(k => docPayloads[k])
    .map(k => [k, Math.round(docPayloads[k].base64.length * 0.75)]);
  const total = sizes.reduce((a, x) => a + x[1], 0) * 1.37;   // base64 overhead
  if (total > BUDGET) {
    sizes.sort((a, b) => b[1] - a[1]);
    const biggest = sizes.slice(0, 2).map(x => (document.getElementById(x[0]).files[0] || {}).name + ' (' +
      (x[1] / 1048576).toFixed(1) + ' MB)').join(' and ');
    btn.disabled = false;
    btn.textContent = '✅ Submit Application';
    if (typeof avBusy === 'function') avBusy(btn, false);
    status.style.background = '#fff3cd';
    status.style.color = '#856404';
    status.textContent = '⚠ Your documents are too large to send together. The largest are ' + biggest +
      '. Please use smaller copies, or photos of the pages instead of PDFs.';
    goToStep(4);
    return;
  }

  status.textContent = 'Sending to Avesta Enterprises...';

  const collateral = Array.from(document.querySelectorAll('#collateral-list .collateral-item')).map(row => {
    const inputs = row.querySelectorAll('input');
    return (inputs[0].value || inputs[1].value) ? `${inputs[0].value || '—'} (ZMW ${inputs[1].value || '0.00'})` : null;
  }).filter(Boolean).join('; ') || '—';

  const refs = Array.from(document.querySelectorAll('#ref-list .ref-block2')).map(block => {
    const inputs = block.querySelectorAll('input');
    return inputs[0].value ? `${inputs[0].value} (${inputs[2].value || '—'}, ${inputs[1].value || '—'})` : null;
  }).filter(Boolean).join('; ') || '—';

  const data = {
    submitted_at:     new Date().toLocaleString('en-ZM', {timeZone:'Africa/Lusaka'}),
    effective_date:   gv('eff_date'),
    // Borrower
    full_name:        gv('b_name'),
    national_id:      gv('b_id'),
    date_of_birth:    gv('b_dob'),
    phone:            gv('b_phone'),
    address:          gv('b_address'),
    email:            gv('b_email'),
    occupation:       gv('b_occupation'),
    monthly_income:   gv('b_income'),
    collateral_items: collateral,
    security_type:    getChecked('security_type'),
    checkoff_employer: gv('co_employer'),
    checkoff_hr_name: gv('co_hr_name'),
    checkoff_hr_phone: gv('co_hr_phone'),
    checkoff_payroll_no: gv('co_payroll_no'),
    guarantor_name:   gv('gtr_name'),
    guarantor_id:     gv('gtr_id'),
    guarantor_phone:  gv('gtr_phone'),
    guarantor_relationship: gv('gtr_relationship'),
    guarantor_occupation: gv('gtr_occupation'),
    guarantor_income: gv('gtr_income'),
    references:       refs,
    // Loan
    loan_amount:      gv('loan_amt'),
    loan_words:       gv('loan_words'),
    loan_purpose:     gv('loan_purpose'),
    receive_method:   getChecked('disburse'),
    disburse_date:    gv('disburse_date'),
    // Repayment
    loan_duration:    gv('sel_dur'),
    interest_rate:    gv('int_rate'),
    total_interest:   gv('total_int'),
    total_repayment:  gv('total_repay'),
    installments:     gv('num_inst'),
    installment_amt:  gv('inst_amt'),
    late_payment_rate: gv('late_rate_display'),
    weeks_overdue:    gv('weeks_overdue'),
    late_fee:         gv('late_fee'),
    first_payment:    gv('first_payment'),
    last_payment:     gv('last_payment'),
    repay_schedule:   getCheckboxes('#wiz-3 .check-pills'),
    repay_method:     getChecked('repay_method'),
    // Signatures
    borrower_signature: sigSigned.borrower ? sigCanvases.borrower.toDataURL() : '—',
    borrower_printed_name: gv('sig_borrower_name'),
    borrower_sign_date: gv('sig_borrower_date'),
    witness_name:     gv('wit_name'),
    witness_phone:    gv('wit_phone'),
    witness_date:     gv('wit_date'),
    // Location
    gps_latitude:     gv('gps_lat') || '—',
    gps_longitude:    gv('gps_lng') || '—',
    gps_accuracy_m:   gv('gps_accuracy') || '—',
    status:           'New Application',
  };

  // Attach document files (base64) — server saves these as real files on disk
  DOC_FIELD_IDS.forEach(id => {
    const p = docPayloads[id];
    data[id + '_filename'] = p ? p.filename : '';
    data[id + '_mimetype']  = p ? p.mimetype  : '';
    data[id + '_base64']    = p ? p.base64    : '';
  });
  data.employer_letter_uploaded = docPayloads.doc_employer_letter ? 'Yes' : 'No';
  data.guarantor_nrc_uploaded   = docPayloads.doc_gtr_nrc ? 'Yes' : 'No';

  try {
    // PHP API — same domain, no CORS issues. Send the FULL record including
    // base64 documents so admin can view them from any device.
    const apiRes = await fetch(SHEET_URL + '?action=submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    // If PHP isn't running, the host answers with an HTML page. Swallowing
    // that into {} gives the applicant a vague failure; name it instead.
    let apiJson;
    try { apiJson = await window.avReadJson(apiRes); }
    catch (parseErr) { apiJson = { ok: false, error: parseErr.message }; }

    // Server explicitly reported failure (e.g. upload too large, disk write
    // failed) — don't show a fake success message.
    if (!apiRes.ok || apiJson.ok === false) {
      if (typeof avBusy === 'function') avBusy(btn, false);
      btn.disabled = false;
      btn.textContent = '✅ Submit Application';
      avToast(apiJson.error || 'Submission failed. Your answers are still saved — try again in a moment.', 'danger');
      status.style.background = '#f8d7da';
      status.style.color = '#842029';
      status.style.display = 'block';
      status.style.padding = '16px 18px';
      status.style.borderRadius = '8px';
      status.textContent = '❌ ' + (apiJson.error || 'Submission failed (server error). Please try again or contact Avesta Enterprises directly.');
      status.scrollIntoView({behavior:'smooth', block:'start'});
      return;
    }

    if (apiJson && apiJson._key) data._key = apiJson._key;
    if (typeof avBusy === 'function') avBusy(btn, false);
    btn.textContent = '✅ Submitted!';
    btn.style.background = '#198754';
    btn.disabled = false;
    // Save submission record to localStorage for staff dashboard
    data._notify_pending = true;
    const recKey = 'avesta_submission_' + Date.now();
    localStorage.setItem(recKey, JSON.stringify(data));
    localStorage.removeItem(DRAFT_KEY);
    // Render rich success card
    const applicantName = (document.getElementById('b_name') || {}).value || 'Applicant';
    const loanAmt = (document.getElementById('loan_amt') || {}).value || '0';
    const loanDur = (document.querySelector('.dur-tile.active') || {}).dataset?.label || '';
    status.style.background = '';
    status.style.color = '';
    status.innerHTML = `
      <div style="background:linear-gradient(135deg,#0F2E24 0%,#1A5040 100%);padding:32px 28px;text-align:center">
        <!-- Animated checkmark -->
        <div style="width:68px;height:68px;background:#D98E3B;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:30pt;box-shadow:0 6px 24px rgba(217,142,59,.4)">✓</div>
        <h2 style="color:white;font-family:Georgia,serif;font-size:18pt;margin-bottom:6px">Application Received!</h2>
        <p style="color:rgba(255,255,255,.7);font-size:10pt;margin-bottom:0">Thank you, <strong style="color:#D98E3B">${applicantName}</strong>. Your loan application has been successfully submitted to Avesta Enterprises.</p>
      </div>
      <!-- Info cards -->
      <div style="background:#F7F3EC;padding:22px 28px;display:flex;gap:14px;flex-wrap:wrap;justify-content:center;border-bottom:1px solid #e8e0d0">
        <div style="background:white;border-radius:10px;padding:14px 20px;text-align:center;min-width:130px;border-top:3px solid #D98E3B;box-shadow:0 2px 8px rgba(0,0,0,.06)">
          <div style="font-size:9pt;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Amount Applied</div>
          <div style="font-size:14pt;font-weight:700;color:#0F2E24;font-family:Georgia,serif">ZMW ${parseFloat(loanAmt||0).toLocaleString()}</div>
        </div>
        <div style="background:white;border-radius:10px;padding:14px 20px;text-align:center;min-width:130px;border-top:3px solid #1A5040;box-shadow:0 2px 8px rgba(0,0,0,.06)">
          <div style="font-size:9pt;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Reference No.</div>
          <div style="font-size:11pt;font-weight:700;color:#0F2E24;font-family:Georgia,serif">${recKey.replace('avesta_submission_','AE-').slice(0,14)}</div>
        </div>
        <div style="background:white;border-radius:10px;padding:14px 20px;text-align:center;min-width:130px;border-top:3px solid #27ae60;box-shadow:0 2px 8px rgba(0,0,0,.06)">
          <div style="font-size:9pt;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Status</div>
          <div style="font-size:11pt;font-weight:700;color:#27ae60">Under Review</div>
        </div>
      </div>
      <!-- What happens next -->
      <div style="padding:24px 28px;background:white">
        <h3 style="color:#0F2E24;font-size:11pt;margin-bottom:16px;display:flex;align-items:center;gap:8px">
          <span style="background:#D98E3B;color:#0F2E24;width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:9pt;font-weight:bold;flex-shrink:0">?</span>
          What Happens Next?
        </h3>
        <div style="display:flex;flex-direction:column;gap:14px">
          <div style="display:flex;align-items:flex-start;gap:14px">
            <div style="background:#0F2E24;color:#D98E3B;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:10pt;flex-shrink:0;margin-top:1px">1</div>
            <div>
              <div style="font-weight:700;color:#0F2E24;font-size:9.5pt;margin-bottom:2px">Application Review</div>
              <div style="color:#666;font-size:9pt;line-height:1.6">Our team will carefully review your application and the documents you submitted. This typically takes a few hours to one business day.</div>
            </div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <div style="background:#0F2E24;color:#D98E3B;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:10pt;flex-shrink:0;margin-top:1px">2</div>
            <div>
              <div style="font-weight:700;color:#0F2E24;font-size:9.5pt;margin-bottom:2px">Approval Decision</div>
              <div style="color:#666;font-size:9pt;line-height:1.6">Once your application is approved, you will be contacted directly via phone or email to confirm the loan terms and disbursement details.</div>
            </div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <div style="background:#D98E3B;color:#0F2E24;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:10pt;flex-shrink:0;margin-top:1px">3</div>
            <div>
              <div style="font-weight:700;color:#D98E3B;font-size:9.5pt;margin-bottom:2px">💰 Funds Disbursed</div>
              <div style="color:#555;font-size:9pt;line-height:1.6"><strong>Your approved loan amount will be sent to you promptly once approval is confirmed</strong> — via your chosen method (cash, Airtel Money, MTN MoMo, or bank transfer). Same-day disbursement on approved applications.</div>
            </div>
          </div>
        </div>
        <!-- Contact reminder -->
        <div style="margin-top:20px;background:#F7F3EC;border-left:4px solid #D98E3B;border-radius:0 8px 8px 0;padding:14px 16px">
          <div style="font-size:9pt;color:#0F2E24;font-weight:700;margin-bottom:4px">📞 Need to follow up?</div>
          <div style="font-size:9pt;color:#555;line-height:1.7">Call us on <strong>+260 971 013 108</strong> or <strong>+260 769 974 200</strong>, or email <strong>info@avesta.solutions</strong> and quote your reference number <strong style="color:#D98E3B">${recKey.replace('avesta_submission_','AE-').slice(0,14)}</strong>.</div>
        </div>
        <!-- Print button -->
        <div style="margin-top:18px;text-align:center">
          <button onclick="window.print()" style="background:#0F2E24;color:white;border:none;padding:12px 28px;border-radius:8px;font-size:10pt;font-weight:700;cursor:pointer;margin-right:10px">🖨 Print Application</button>
          <button onclick="clearForm()" style="background:white;color:#0F2E24;border:2px solid #0F2E24;padding:12px 28px;border-radius:8px;font-size:10pt;font-weight:700;cursor:pointer">+ New Application</button>
        </div>
      </div>`;
    status.style.display = 'block';
    status.scrollIntoView({behavior:'smooth', block:'start'});
  } catch (e) {
    if (typeof avBusy === 'function') avBusy(btn, false);
    btn.disabled = false;
    btn.textContent = '✅ Submit Application';
    status.style.background = '#f8d7da';
    status.style.color = '#842029';
    status.textContent = '❌ Submission failed. Please check your internet connection and try again.';
    avToast('Could not reach the server. Your answers are saved on this device — try again when you have signal.', 'danger');
  }
}
</script>



<!-- ═══ CURRENCY CONVERTER SCRIPT ════════════════════════════════════════ -->
<script>
(function(){
  var ENDPOINT = 'api.php?action=rates';
  var KEY      = 'avesta_fx_v1';
  var FAVS     = ['ZMW','USD','ZAR','GBP','EUR','CNY','AED','INR','TZS','MWK','BWP','KES'];
  var SEED     = {ZMW:19.60, USD:1, EUR:0.92, GBP:0.79, ZAR:18.1, CNY:7.2}; // BoZ mid, Sep 2026

  var S = {from:'ZMW', to:'USD', amt:1000, margin:2,
           table:null, at:null, label:null, official:false};
  var busy = false;
  var $ = function(id){ return document.getElementById(id); };
  if(!$('fx_amt')) return;   // converter block not on this page

  var namer = null;
  try{ namer = new Intl.DisplayNames(['en'], {type:'currency'}); }catch(e){}
  function nameOf(c){
    try{ var n = namer && namer.of(c); return (n && n !== c) ? n : c; }catch(e){ return c; }
  }
  function money(n, code){
    if(!isFinite(n)) return '\u2014';
    var o = {style:'currency', currency:code, currencyDisplay:'narrowSymbol'};
    try{ return new Intl.NumberFormat('en-US', o).format(n); }
    catch(e){
      try{ o.currencyDisplay = 'symbol'; return new Intl.NumberFormat('en-US', o).format(n); }
      catch(e2){ return code + ' ' + n.toLocaleString('en-US',{maximumFractionDigits:2}); }
    }
  }
  function num(v){ var n = parseFloat(String(v).replace(/[^0-9.]/g,'')); return isFinite(n) ? n : 0; }
  function ago(ts){
    var m = Math.round((Date.now()-ts)/60000);
    if(m < 1) return 'just now';
    if(m < 60) return m + ' min ago';
    var h = Math.round(m/60);
    return h < 24 ? h + 'h ago' : Math.round(h/24) + ' days ago';
  }
  function stamp(state, text){
    var el = $('fx_stamp');
    el.className = 'fxc-stamp' + (state === 'bad' ? ' bad' : state === 'good' ? ' good' : '');
    el.innerHTML = '<i class="fxc-dot ' + state + '"></i>' + text;
  }

  function save(){
    try{ localStorage.setItem(KEY, JSON.stringify({
      from:S.from, to:S.to, margin:S.margin, table:S.table,
      at:S.at, label:S.label, official:S.official })); }catch(e){}
  }
  function load(){
    try{
      var d = JSON.parse(localStorage.getItem(KEY) || 'null');
      if(!d) return;
      if(d.from) S.from = d.from;
      if(d.to) S.to = d.to;
      if(typeof d.margin === 'number') S.margin = d.margin;
      if(d.table && d.table.USD) S.table = d.table;
      S.at = d.at || null; S.label = d.label || null; S.official = !!d.official;
    }catch(e){}
  }

  function table(){ return S.table || SEED; }
  function rate(){
    var t = table();
    if(!t[S.from] || !t[S.to]) return NaN;
    return t[S.to] / t[S.from];       // every pair cross-rates through USD
  }

  function fillSelects(){
    var t = table();
    var codes = Object.keys(t).sort();
    var favs = FAVS.filter(function(c){ return codes.indexOf(c) > -1; });
    var rest = codes.filter(function(c){ return favs.indexOf(c) === -1; });

    [['fx_from', S.from], ['fx_to', S.to]].forEach(function(pair){
      var sel = $(pair[0]);
      sel.innerHTML = '';
      function group(label, list){
        if(!list.length) return;
        var g = document.createElement('optgroup');
        g.label = label;
        list.forEach(function(c){
          var o = document.createElement('option');
          o.value = c;
          o.textContent = c + ' \u2014 ' + nameOf(c);
          g.appendChild(o);
        });
        sel.appendChild(g);
      }
      group('Common', favs);
      group('All currencies', rest);
      sel.value = pair[1];
    });
  }

  function niceRound(v){
    if(!isFinite(v) || v <= 0) return 1;
    var mag = Math.pow(10, Math.floor(Math.log10(v))), lead = v / mag;
    return (lead < 1.5 ? 1 : lead < 3.5 ? 2 : lead < 7.5 ? 5 : 10) * mag;
  }
  function chips(){
    var t = table(), box = $('fx_chips'), per = t[S.from] || 1;
    box.innerHTML = '';
    [10, 50, 100, 500, 1000].map(function(u){ return niceRound(u * per); })
      .filter(function(v,i,a){ return a.indexOf(v) === i; })
      .forEach(function(v){
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = money(v, S.from).replace(/\.00$/,'');
        b.onclick = function(){ S.amt = v; $('fx_amt').value = v; render(); };
        box.appendChild(b);
      });
  }

  function render(){
    var r = rate();
    var mid = S.amt * r;
    var eff = mid * (1 - S.margin/100);

    $('fx_out').textContent = money(mid, S.to);
    $('fx_pair').textContent = isFinite(r)
      ? '1 ' + S.from + ' = ' + (r < 0.01 ? r.toPrecision(4) : r.toFixed(4)) + ' ' + S.to +
        '   \u2022   1 ' + S.to + ' = ' + (1/r < 0.01 ? (1/r).toPrecision(4) : (1/r).toFixed(4)) + ' ' + S.from
      : 'No rate available for this pair \u2014 try refreshing.';

    $('fx_mpct').textContent = String(+S.margin.toFixed(2)) + '%';
    $('fx_fill').style.width = (100 - S.margin) + '%';
    $('fx_gap').style.width  = S.margin + '%';
    $('fx_eff').textContent  = isFinite(mid) ? 'At that margin you receive ' + money(eff, S.to) : '\u2014';
    $('fx_loss').textContent = isFinite(mid) ? money(mid - eff, S.to) : '\u2014';

    $('fx_note').textContent = (S.official && (S.from === 'ZMW' || S.to === 'ZMW'))
      ? 'Kwacha rate is the official Bank of Zambia figure; other currencies use market mid rates. Avesta lends and collects in Zambian kwacha.'
      : 'Indicative rates only. Banks and bureaus set their own spread, and Avesta lends and collects in Zambian kwacha.';

    $('fx_refresh').disabled = busy;
    save();
  }

  function sync(){
    if(busy) return;
    busy = true; render();
    stamp('busy', 'Fetching latest rates\u2026');

    window.avFetchJson(ENDPOINT, {cache:'no-store', credentials:'same-origin'}, 12000)
      .then(function(g){ return {ok:g.res.ok, d:g.data}; })
      .then(function(res){
        var d = res.d;
        if(!res.ok || d.ok === false) throw new Error(d.error || 'Rate service unavailable');
        if(!d.table || !d.table.USD) throw new Error('Rate table incomplete');

        S.table = d.table;
        S.label = 'Market rates';
        S.official = false;

        if(d.boz && d.boz.official && d.boz.zmw_per_usd > 0){
          S.table.ZMW = d.boz.zmw_per_usd;     // one override covers every kwacha pair
          S.label = 'Bank of Zambia (kwacha) + market';
          S.official = true;
        }
        S.at = Date.now();
        var n = Object.keys(S.table).length;
        stamp(d.stale ? 'warn' : 'good',
              S.label + ' \u2022 ' + n + ' currencies \u2022 ' +
              (d.stale ? 'server cache' : 'updated ' + ago(S.at)));
        fillSelects(); chips();
      })
      .catch(function(e){
        var msg = (e && e.message) ? e.message : 'Could not load rates';
        if(!navigator.onLine) msg = 'You are offline \u2014 showing saved rates';
        else if(/failed to fetch|networkerror|load failed|aborted/i.test(msg))
          msg = 'Could not reach the rate service \u2014 showing saved rates';
        stamp('bad', msg + (S.at ? ' \u2022 rates from ' + ago(S.at) : ''));
      })
      .then(function(){ busy = false; render(); });
  }

  // ── events ──
  $('fx_amt').addEventListener('input', function(){ S.amt = num(this.value); render(); });
  $('fx_from').addEventListener('change', function(){
    if(this.value === S.to){ S.to = S.from; $('fx_to').value = S.to; }
    S.from = this.value; chips(); render();
  });
  $('fx_to').addEventListener('change', function(){
    if(this.value === S.from){ S.from = S.to; $('fx_from').value = S.from; }
    S.to = this.value; chips(); render();
  });
  $('fx_swap').addEventListener('click', function(){
    var f = S.from; S.from = S.to; S.to = f;
    $('fx_from').value = S.from; $('fx_to').value = S.to;
    chips(); render();
  });
  $('fx_margin').addEventListener('input', function(){ S.margin = parseFloat(this.value); render(); });
  $('fx_refresh').addEventListener('click', sync);

  // ── boot ──
  load();
  $('fx_amt').value = S.amt;
  $('fx_margin').value = S.margin;
  fillSelects(); chips(); render();
  if(S.at) stamp('', (S.label || 'Saved rates') + ' \u2022 updated ' + ago(S.at));
  if(!S.at || (Date.now() - S.at) > 3600000) sync();
})();
</script>


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



<!-- ═══ IT ENQUIRY ═══════════════════════════════════════════════════════ -->
<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  if (!$('e_send')) return;          // lending office — no enquiry form here

  var msg = $('enq-msg');
  function say(text, tone) {
    msg.textContent = text;
    msg.className = 'enq-msg' + (tone ? ' ' + tone : '');
  }

  $('e_send').addEventListener('click', async function () {
    var btn = this;
    var name   = $('e_name').value.trim();
    var phone  = $('e_phone').value.trim();
    var detail = $('e_detail').value.trim();
    var svc    = $('e_service').value;

    // Validate in the order someone fills the form, and say what to do rather
    // than what is wrong.
    if (name.length < 2) {
      say('We need a name to reply to.', 'bad'); $('e_name').focus(); return;
    }
    if (phone.replace(/[^0-9]/g, '').length < 9) {
      say('Add a phone or WhatsApp number so we can reach you.', 'bad');
      $('e_phone').focus(); return;
    }
    if (detail.length < 15) {
      say('Tell us a little more — what is happening, and where?', 'bad');
      $('e_detail').focus(); return;
    }

    // A bot fills every field it finds. A person never sees this one, so
    // anything in it means the submission is not a person. Accept it quietly
    // rather than saying so, which would teach the bot to leave it alone.
    if ($('e_website').value !== '') {
      say('Thank you — we will be in touch.', 'good');
      btn.disabled = true;
      return;
    }

    btn.disabled = true;
    say('Sending\u2026');

    try {
      var got = await window.avFetchJson('api.php?action=submitEnquiry', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name, phone: phone, service: svc, detail: detail })
      }, 15000);

      if (!got.res.ok || got.data.ok === false) {
        throw new Error(got.data.error || 'Could not send it');
      }

      say('Thank you, ' + name.split(' ')[0] + '. We have your enquiry and will '
          + 'come back to you on ' + phone + '.', 'good');
      ['e_name', 'e_phone', 'e_detail'].forEach(function (id) { $(id).value = ''; });
      $('e_service').selectedIndex = 0;
      if (window.avToast) avToast('Enquiry sent', 'ok');
    } catch (e) {
      // Never lose what they typed. Re-enable so they can try again, and give
      // them the phone number as a way through if the form keeps failing.
      btn.disabled = false;
      say((e.message || 'Could not send it') +
          ' \u2014 you can also call us on 0769 974 200.', 'bad');
    }
  });
})();
</script>

<!-- ═══ SUB-NAVIGATION ═══════════════════════════════════════════════════ -->
<script>
(function () {
  // Which sections belong to which service. Tabs an office has pruned away are
  // skipped, so this works on the combined site and on either office.
  var SECTIONS = {
    lending: { label: 'Lending', tabs: [
      ['home','Overview'], ['how','How it works'], ['calc','Calculator'],
      ['currency','Currency'], ['apply','Apply'], ['about','About'], ['contact','Contact']
    ]},
    it: { label: 'IT Consulting', tabs: [
      ['services','Services'], ['about','About'], ['contact','Contact']
    ]}
  };

  var bar = document.getElementById('subnav'), inner = document.getElementById('subnav-inner');
  if (!bar || !inner || typeof window.showTab !== 'function') return;

  var site = (window.AV_SITE || 'lending').toLowerCase();
  var set  = SECTIONS[site] || SECTIONS.lending;
  var tabs = set.tabs.filter(function (t) { return !!document.getElementById('tab-' + t[0]); });
  if (tabs.length < 2) return;

  var lbl = document.createElement('span');
  lbl.className = 'subnav-label';
  lbl.textContent = set.label;
  inner.appendChild(lbl);

  var buttons = {};
  tabs.forEach(function (t) {
    var b = document.createElement('button');
    b.type = 'button';
    b.textContent = t[1];
    b.addEventListener('click', function () { showTab(t[0]); });
    inner.appendChild(b);
    buttons[t[0]] = b;
  });
  bar.hidden = false;

  function mark(id) {
    Object.keys(buttons).forEach(function (k) {
      buttons[k].classList.toggle('active', k === id);
      buttons[k].setAttribute('aria-current', k === id ? 'page' : 'false');
    });
    var on = buttons[id];
    if (on && on.scrollIntoView) {
      try { on.scrollIntoView({ inline: 'nearest', block: 'nearest' }); } catch (e) {}
    }
  }

  // Follow showTab rather than tracking the open section separately, so the
  // two can never disagree about where the visitor is.
  var original = window.showTab;
  window.showTab = function () {
    var r = original.apply(this, arguments);
    var panel = document.querySelector('.tab-panel.active');
    if (panel) mark(panel.id.slice(4));
    return r;
  };

  var open = document.querySelector('.tab-panel.active');
  if (open) mark(open.id.slice(4));
})();
</script>

<!-- ═══ TROUBLESHOOTING ASSISTANT ════════════════════════════════════════ -->
<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  if (!$('ask-go')) return;                 // lending office — not present

  var log = $('ask-log'), box = $('ask-q'), btn = $('ask-go'), chips = $('ask-chips');
  var lastService = '';

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;   // textContent, never innerHTML
    return e;
  }

  function scroll() { log.scrollTop = log.scrollHeight; }

  function said(text) {
    log.classList.add('on');
    log.appendChild(el('div', 'ask-you', text));
    scroll();
  }

  // Buttons that ask a specific topic by name, used for "also relevant" and
  // for the choices offered when a question is too vague to call.
  function topicButtons(label, titles) {
    var box = el('div', 'ask-alts');
    box.appendChild(el('span', 'ask-alts-lbl', label));
    titles.forEach(function (title) {
      var b = el('button', null, title);
      b.type = 'button';
      b.addEventListener('click', function () { ask(title, title); });
      box.appendChild(b);
    });
    return box;
  }

  function answer(d) {
    var wrap = el('div', 'ask-me');

    // Show what the answer rests on, so a wrong reading is obvious at a glance
    if (d.keywords && d.keywords.length) {
      var got = el('p', 'ask-read');
      got.appendChild(el('span', null, 'Picked up: '));
      d.keywords.forEach(function (k, i) {
        if (i) got.appendChild(document.createTextNode(' · '));
        got.appendChild(el('strong', null, k));
      });
      wrap.appendChild(got);
    }
    if (d.corrected && d.corrected.length) {
      wrap.appendChild(el('p', 'ask-read ask-fix', d.corrected.map(function (c) {
        return 'Read \u201c' + c.typed + '\u201d as \u201c' + c.read_as + '\u201d';
      }).join(' · ') + '.'));
    }

    if (d.title) wrap.appendChild(el('h4', null, d.title));
    wrap.appendChild(el('p', null, d.answer));

    if (d.choices && d.choices.length) {
      wrap.appendChild(topicButtons('Closest matches:', d.choices));
    }
    if (d.lending) {
      var go = el('a', 'ask-lend', 'Go to Avesta Lending \u2192');
      go.href = 'loans.php';
      wrap.appendChild(go);
    }

    if (d.steps && d.steps.length) {
      var ol = el('ol');
      d.steps.forEach(function (s) { ol.appendChild(el('li', null, s)); });
      wrap.appendChild(ol);
    }

    if (d.stop) {
      var stop = el('div', 'ask-stop');
      stop.appendChild(el('strong', null, 'When to stop and call us'));
      stop.appendChild(document.createTextNode(d.stop));
      wrap.appendChild(stop);
    }

    if (d.diagnostic) {
      var meta = el('p', 'ask-read');
      var bits = [];
      if (d.diagnostic.category) bits.push('Area: ' + d.diagnostic.category);
      if (d.diagnostic.error_codes && d.diagnostic.error_codes.length) bits.push('Error: ' + d.diagnostic.error_codes.join(', '));
      if (d.diagnostic.risk) bits.push('Risk: ' + d.diagnostic.risk);
      if (d.confidence) bits.push('Confidence: ' + d.confidence);
      meta.textContent = bits.join(' · ');
      wrap.appendChild(meta);
    }

    if (d.sources && d.sources.length) {
      var src = el('div', 'ask-hand');
      src.appendChild(el('strong', null, 'Sources '));
      d.sources.forEach(function (x, i) {
        var a = el('a', null, x.title || ('Source ' + (i + 1)));
        a.href = x.url; a.target = '_blank'; a.rel = 'noopener noreferrer';
        src.appendChild(a);
        if (i < d.sources.length - 1) src.appendChild(document.createTextNode(' · '));
      });
      wrap.appendChild(src);
    }

    if (d.also && d.also.length) {
      wrap.appendChild(topicButtons('You also mentioned:', d.also));
    }

    // Every answer offers the way through to a person. Someone who has just
    // been told to stop touching the machine needs that most.
    var hand = el('div', 'ask-hand');
    hand.appendChild(document.createTextNode('Still stuck? '));
    var b = el('button', null, d.matched ? 'Send this to us' : 'Ask a person');
    b.type = 'button';
    b.addEventListener('click', function () { handOver(d); });
    hand.appendChild(b);
    wrap.appendChild(hand);

    log.classList.add('on');
    log.appendChild(wrap);
    scroll();
  }

  // Carry the conversation into the enquiry form rather than making them
  // type it again.
  function handOver(d) {
    var detail = $('e_detail'), svc = $('e_service');
    if (detail) {
      var said = [];
      Array.prototype.forEach.call(log.querySelectorAll('.ask-you'), function (n) {
        said.push(n.textContent);
      });
      if (said.length && !detail.value.trim()) detail.value = said.join('. ');
      detail.focus();
    }
    if (svc && d.service) {
      for (var i = 0; i < svc.options.length; i++) {
        if (svc.options[i].text === d.service) { svc.selectedIndex = i; break; }
      }
    }
    var form = document.querySelector('.enq');
    if (form && form.scrollIntoView) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (window.avToast) avToast('Carried over — add anything else and send it.', 'info');
  }

  async function ask(q, topic) {
    if (!q || q.trim().length < 3) {
      if (window.avToast) avToast('Tell me a little more about what it is doing.', 'warn');
      box.focus(); return;
    }
    said(q);
    box.value = '';
    btn.disabled = true;

    try {
      var got = await window.avFetchJson('api.php?action=itHelp', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(topic ? { question: q, topic: topic } : { question: q })
      }, 12000);
      if (!got.res.ok || got.data.ok === false) throw new Error(got.data.error || 'No answer');
      answer(got.data);
      if (got.data.service) lastService = got.data.service;
    } catch (e) {
      answer({ answer: 'I could not reach our system just then. You can still send the '
                     + 'problem below, or call 0769 974 200.' });
    }
    btn.disabled = false;
  }

  btn.addEventListener('click', function () { ask(box.value); });
  box.addEventListener('keydown', function (e) { if (e.key === 'Enter') ask(box.value); });

  answer({ answer: "Hi, I'm G.I.T. What IT problem can I help you with?" });

  // Starting points, so nobody faces an empty box wondering what to type
  [['Computer will not start', 'my computer will not start'],
   ['Very slow', 'the computer is very slow'],
   ['Wi-Fi keeps dropping', 'the wifi keeps dropping'],
   ['Printer offline', 'printer says offline'],
   ['Lost files', 'i deleted files by mistake'],
   ['Possible virus', 'i think we have a virus'],
   // Consultation, not just faults — otherwise nobody discovers they can ask
   ['Power cuts', 'load shedding keeps affecting my computer'],
   ['Upgrade or replace?', 'is my old computer worth upgrading'],
   ['Secure my Wi-Fi', 'how do i secure my wifi'],
   ['Email not working', 'outlook is not syncing'],
   ['Someone called me', 'microsoft called and said my computer has a virus'],
   ['Test my connection', 'basic networking commands']
  ].forEach(function (pair) {
    var c = el('button', null, pair[0]);
    c.type = 'button';
    c.addEventListener('click', function () { ask(pair[1]); });
    chips.appendChild(c);
  });
})();
</script>
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

<!-- ═══ WHO SEES THE STAFF OPTION ════════════════════════════════════════════
     Staff Records is the door to the portal, where applications, NRCs and
     repayments live. Borrowers should not see it at all: an option that only
     ever refuses you is an invitation to try. Removed from the page rather
     than hidden, so it cannot be found by reading the source either. The
     portal checks the role again on the server — this is the sign on the
     door, not the lock.
═══════════════════════════════════════════════════════════════════════════ -->
<script>
(function () {
  var role = (window.AV_USER && window.AV_USER.role) || 'borrower';
  if (role !== 'admin') {
    ['ntab-staff'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.parentNode) el.parentNode.removeChild(el);
    });
    Array.prototype.slice.call(document.querySelectorAll('[onclick*="showStaffLogin"]'))
      .forEach(function (el) { if (el.parentNode) el.parentNode.removeChild(el); });
    var panel = document.getElementById('tab-staff');
    if (panel && panel.parentNode) panel.parentNode.removeChild(panel);
    if (Array.isArray(window.AV_VALID_TABS)) {
      window.AV_VALID_TABS = window.AV_VALID_TABS.filter(function (t) { return t !== 'staff'; });
    }
  }

  // Everyone can change their own password
  var menu = document.querySelector('.nav-mobile') || document.getElementById('nav-mobile');
  if (menu) {
    var a = document.createElement('a');
    a.href = 'login.php?change=1';
    a.className = 'mob-pw';
    a.style.cssText = 'display:block;padding:14px 20px;text-decoration:none';
    a.textContent = 'Change my password';
    var out = menu.querySelector('.mob-out');
    if (out) menu.insertBefore(a, out); else menu.appendChild(a);
    var recovery = a.cloneNode(false);
    recovery.href = 'recovery.php?setup=1';
    recovery.textContent = 'Recovery email';
    if (out) menu.insertBefore(recovery, out); else menu.appendChild(recovery);
  }
  if (window.AV_USER) {
    var desktopOut = document.querySelector('.office-out');
    if (desktopOut) {
      var link = document.createElement('a');
      link.href = 'recovery.php?setup=1';
      link.className = 'office-out';
      link.textContent = 'Recovery email';
      desktopOut.parentNode.insertBefore(link, desktopOut);
    }
  }
})();
</script>

<!-- ═══ PUBLIC VISITORS ══════════════════════════════════════════════════
     A visitor who is not signed in can read everything that sells the
     business, and nothing that belongs to a person. The application form is
     removed from the page — not hidden — and every Apply button becomes a
     sign-in link. The server refuses an unsigned submission regardless; this
     is so the page is honest about it rather than failing at the last step.
═══════════════════════════════════════════════════════════════════════════ -->
<script>
(function () {
  if (!window.AV_PUBLIC) return;

  var panel = document.getElementById('tab-apply');
  if (panel && panel.parentNode) panel.parentNode.removeChild(panel);
  if (Array.isArray(window.AV_VALID_TABS)) {
    window.AV_VALID_TABS = window.AV_VALID_TABS.filter(function (t) { return t !== 'apply'; });
  }

  // Anything that opened the application now offers to sign in first
  var to = 'login.php?next=' + encodeURIComponent(location.pathname.replace(/^\//, '') + '#apply');
  Array.prototype.slice.call(document.querySelectorAll('[onclick*="showTab(\'apply\')"]'))
    .forEach(function (el) {
      el.removeAttribute('onclick');
      el.addEventListener('click', function (e) { e.preventDefault(); location.href = to; });
      if (/apply/i.test(el.textContent) && !/sign in/i.test(el.textContent)) {
        el.textContent = el.textContent.replace(/apply( now| for a loan)?/i, 'Sign in to apply');
      }
    });

  // showTab('apply') from a stale link or the sub-navigation
  var orig = window.showTab;
  window.showTab = function (id) {
    if (id === 'apply') { location.href = to; return; }
    return orig.apply(this, arguments);
  };

  var out = document.querySelector('.office-out');       // "Sign out" makes no sense yet
  if (out) {
    out.href = 'login.php';
    var label = out.querySelector('.oo-text');
    if (label) label.textContent = 'Sign in';
  }
})();
</script>
</body>
</html>
