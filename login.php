<?php
require_once __DIR__ . '/auth.php';
av_session_start();

// Already signed in? Go where you were headed.
if (av_user() !== null && !isset($_GET['change']) && ($_POST['do'] ?? '') !== 'chpw') {
    $next = $_GET['next'] ?? 'index.php';
    if (!preg_match('#^/?[A-Za-z0-9._/-]*$#', $next)) $next = 'index.php';
    header('Location: ' . $next);
    exit;
}

$error = '';
$notice = '';
$mode = ($_GET['mode'] ?? 'in') === 'up' && ALLOW_SELF_SIGNUP ? 'up' : 'in';
if (isset($_GET['forgot'])) $mode = 'forgot';
if (isset($_GET['change']) || ($_POST['do'] ?? '') === 'chpw') $mode = 'chpw';
$next = $_GET['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? 'in';

    // ── I have forgotten my password ────────────────────────────────────────
    if ($do === 'forgot') {
        $mode = 'forgot';
        $u  = (string) ($_POST['username'] ?? '');
        $ph = (string) ($_POST['phone'] ?? '');
        if (trim($u) === '' || strlen(preg_replace('/[^0-9]/', '', $ph)) < 9) {
            $error = 'Enter your username and the phone number on your account.';
        } else {
            av_request_reset($u, $ph);
            // The same answer either way: "no such user" would tell a stranger
            // which usernames are real.
            $notice = 'Thank you. If that account exists, the office has been told. Call '
                    . '0769 974 200 and we will confirm who you are and give you a temporary password.';
            $mode = 'in';
        }
    }

    // ── Setting a new password ──────────────────────────────────────────────
    elseif ($do === 'chpw') {
        $mode = 'chpw';
        $me = av_user();
        if ($me === null) {
            $error = 'Please sign in first.';
            $mode  = 'in';
        } else {
            $p1  = (string) ($_POST['password'] ?? '');
            $p2  = (string) ($_POST['password2'] ?? '');
            $row = av_find_user($me['username']);
            if (!$row || !password_verify((string) ($_POST['current'] ?? ''), $row['pass_hash'])) {
                $error = 'Your current password is not correct.';
            } elseif ($p1 !== $p2) {
                $error = 'The two new passwords do not match.';
            } else {
                $r = av_set_password($me['id'], $p1);
                if (!$r['ok']) {
                    $error = $r['error'];
                } else {
                    av_audit('user.password_changed', $me['id'], ['by' => 'self']);
                    av_clear_must_change($me['id']);
                    header('Location: index.php');
                    exit;
                }
            }
        }
    }

    elseif ($do === 'up' && ALLOW_SELF_SIGNUP) {
        $mode = 'up';
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');
        if ($p1 !== $p2) {
            $error = 'The two passwords do not match.';
        } else {
            $r = av_create_user((string) ($_POST['username'] ?? ''), $p1,
                                (string) ($_POST['name'] ?? ''), 'borrower', (string) ($_POST['email'] ?? ''));
            if (!$r['ok']) {
                $error = $r['error'];
            } else {
                av_audit('user.self_registered', $r['user']['username']);
                $li = av_login((string) $_POST['username'], $p1);
                if ($li['ok']) { header('Location: ' . (trim((string) ($_POST['email'] ?? '')) !== '' ? 'recovery.php?setup=1' : 'index.php')); exit; }
                $notice = 'Account created. Please sign in.';
                $mode = 'in';
            }
        }
    } else {
        $r = av_login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($r['ok']) {
            $to = (string) ($_POST['next'] ?? '');
            if ($to === '' || !preg_match('#^/?[A-Za-z0-9._/-]*$#', $to)) {
                $to = in_array($r['user']['role'], ['admin', 'staff'], true) ? 'portal.php' : 'index.php';
            }
            header('Location: ' . $to);
            exit;
        }
        $error = $r['error'];
    }
}

$hasUsers = count(av_load_users()) > 0;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Sign in — Avesta Enterprises</title>
<style>
:root{
  --navy:#163E33; --navy-2:#1F5344; --ink:#12241E;
  --gold:#D98E3B; --gold-deep:#B26F26; --gold-light:#E9B06A;
  --cream:#F7F3EC; --paper:#FFFFFF; --line:#E3DED4;
  --muted:#66766F; --danger:#C0392B; --danger-wash:#FBEAE8;
  --ok:#1E7A4C; --ok-wash:#E8F4EE;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  background:var(--navy);
  background-image:
    radial-gradient(90% 55% at 50% 0%, var(--navy-2) 0%, var(--navy) 62%);
  color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;
  padding:28px 18px calc(28px + env(safe-area-inset-bottom));line-height:1.5;
}
.wrap{width:100%;max-width:410px}
.brand{text-align:center;margin-bottom:22px}
.mark{
  width:58px;height:58px;border-radius:15px;margin:0 auto 14px;
  background:linear-gradient(145deg,var(--gold-light),var(--gold-deep));
  display:grid;place-items:center;font-weight:800;font-size:23px;color:var(--navy);
  box-shadow:0 8px 22px rgba(0,0,0,.28);letter-spacing:-.5px;
}
.brand h1{font-size:19px;font-weight:700;color:#fff;letter-spacing:-.2px}
.brand p{font-size:12px;color:rgba(255,255,255,.62);margin-top:4px}
.card{
  background:var(--paper);border-radius:16px;padding:26px 24px 22px;
  box-shadow:0 18px 44px rgba(0,0,0,.3);border-top:3px solid var(--gold);
}
h2{font-size:16px;font-weight:700;color:var(--navy);margin-bottom:3px}
.sub{font-size:12.5px;color:var(--muted);margin-bottom:20px}
label{display:block;font-size:10px;font-weight:700;letter-spacing:.8px;
  text-transform:uppercase;color:var(--muted);margin-bottom:6px}
input{
  width:100%;padding:12px 13px;border:1.5px solid var(--line);border-radius:9px;
  font-size:15px;color:var(--ink);background:var(--paper);font-family:inherit;
  margin-bottom:15px;
}
input:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(217,142,59,.16)}
button.go{
  width:100%;padding:13px;border:none;border-radius:9px;cursor:pointer;
  background:var(--navy);color:var(--gold-light);font-size:14px;font-weight:700;
  letter-spacing:.3px;font-family:inherit;transition:background .15s;
}
button.go:hover{background:var(--navy-2)}
.msg{
  padding:11px 13px;border-radius:9px;font-size:12.5px;margin-bottom:16px;line-height:1.5;
}
.msg.bad{background:var(--danger-wash);color:var(--danger);border-left:3px solid var(--danger)}
.msg.good{background:var(--ok-wash);color:var(--ok);border-left:3px solid var(--ok)}
.msg.info{background:var(--cream);color:var(--navy);border-left:3px solid var(--gold)}
.alt{text-align:center;font-size:12.5px;color:var(--muted);margin-top:18px;
  padding-top:16px;border-top:1px solid var(--line)}
.alt a{color:var(--gold-deep);font-weight:700;text-decoration:none}
.alt a:hover{text-decoration:underline}
.hint{font-size:11px;color:var(--muted);margin:-9px 0 15px}
.foot{text-align:center;font-size:11px;color:rgba(255,255,255,.45);margin-top:20px;line-height:1.6}
.foot a{color:rgba(255,255,255,.72)}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#163E33">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Avesta">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<link rel="icon" href="icons/icon-192.png" sizes="192x192">
<link rel="stylesheet" href="/assets/avesta/responsive.css?v=20261003">
</head>
<body>
<div class="wrap">

  <div class="brand">
    <div class="mark">AE</div>
    <h1>Avesta Enterprises</h1>
    <p>Lending &amp; IT Consulting &middot; Across Zambia</p>
  </div>

  <div class="card">
    <?php if (!$hasUsers): ?>
      <div class="msg info">
        <strong>No accounts yet.</strong><br>
        Open <code>setup.php</code> once to create the first administrator.
      </div>
    <?php endif; ?>

    <?php if ($error): ?><div class="msg bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="msg good"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <?php if ($mode === 'up'): ?>
      <h2>Create an account</h2>
      <p class="sub">You&rsquo;ll use this to apply and to follow your application.</p>
      <form method="post" autocomplete="on">
        <input type="hidden" name="do" value="up">
        <label for="up_name">Full name</label>
        <input type="text" id="up_name" name="name" required autocomplete="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        <label for="up_username">Username</label>
        <input type="text" id="up_username" name="username" required autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        <p class="hint">3&ndash;32 characters. Letters, numbers, dot, dash or underscore.</p>
        <label for="up_email">Recovery email (optional)</label>
        <input type="email" id="up_email" name="email" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <p class="hint">Verify this email after creating your account to enable reset codes.</p>
        <label for="up_password">Password</label>
        <input type="password" id="up_password" name="password" required
               autocomplete="new-password" minlength="10">
        <p class="hint">At least 10 characters. A short phrase works well.</p>
        <label for="up_password2">Repeat password</label>
        <input type="password" id="up_password2" name="password2" required autocomplete="new-password">
        <button class="go" type="submit">Create account</button>
      </form>
      <div class="alt">Already have one? <a href="login.php">Sign in</a></div>

    <?php elseif ($mode === 'forgot'): ?>
      <h2>Forgotten password</h2>
      <p class="sub">Use a verified recovery email, or ask the office for help.</p>
      <p class="msg info"><a href="recovery.php">Reset with an email code</a><br>For accounts with a verified email address.</p>
      <h2>Ask an administrator for help</h2>
      <form method="post" autocomplete="on">
        <input type="hidden" name="do" value="forgot">
        <label for="fg_username">Username</label>
        <input type="text" id="fg_username" name="username" required autocapitalize="none"
               autocomplete="username" autofocus>
        <label for="fg_phone">Phone number on your account</label>
        <input type="tel" id="fg_phone" name="phone" required inputmode="tel" autocomplete="tel">
        <button class="go" type="submit">Send request</button>
      </form>
      <div class="alt">
        If email recovery fails or you have no verified email, the office can confirm your identity
        and give you a temporary password to change at your next sign-in.
      </div>
      <div class="alt"><a href="login.php">Back to sign in</a></div>

    <?php elseif ($mode === 'chpw'): ?>
      <h2>Choose a new password</h2>
      <p class="sub">
        <?= !empty($_SESSION['must_change'])
            ? 'You signed in with a temporary password. Please set your own now.'
            : 'Change the password on your account.' ?>
      </p>
      <form method="post" autocomplete="on">
        <input type="hidden" name="do" value="chpw">
        <label for="cp_current">Current password</label>
        <input type="password" id="cp_current" name="current" required
               autocomplete="current-password" autofocus>
        <label for="cp_password">New password</label>
        <input type="password" id="cp_password" name="password" required autocomplete="new-password">
        <label for="cp_password2">New password again</label>
        <input type="password" id="cp_password2" name="password2" required autocomplete="new-password">
        <button class="go" type="submit">Save new password</button>
      </form>
      <?php if (empty($_SESSION['must_change'])): ?>
        <div class="alt"><a href="index.php">Back to the site</a></div>
      <?php else: ?>
        <div class="alt"><a href="logout.php">Sign out instead</a></div>
      <?php endif; ?>

    <?php else: ?>
      <h2>Sign in</h2>
      <p class="sub">This site is private. Please sign in to continue.</p>
      <form method="post" autocomplete="on">
        <input type="hidden" name="do" value="in">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
        <label for="in_username">Username</label>
        <input type="text" id="in_username" name="username" required autocomplete="username"
               autocapitalize="none" autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        <label for="in_password">Password</label>
        <input type="password" id="in_password" name="password" required autocomplete="current-password">
        <button class="go" type="submit">Sign in</button>
      </form>
      <div class="alt"><a href="login.php?forgot=1">Forgotten your password?</a></div>
      <?php if (ALLOW_SELF_SIGNUP): ?>
        <div class="alt">New here? <a href="login.php?mode=up">Create an account</a></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <p class="foot">
    Here for <a href="it.php">IT services</a>? That office needs no account.<br>
    Your details are held under Zambia&rsquo;s Data Protection Act.<br><a href="legal.php?p=privacy">Privacy</a> &middot; <a href="legal.php?p=terms">Terms</a> &middot; <a href="legal.php?p=complaints">Complaints</a><br>
    Trouble signing in? <a href="https://wa.me/260769974200">Message us on WhatsApp</a>
  </p>
</div>
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
</body>
</html>
