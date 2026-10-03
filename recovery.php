<?php
require_once __DIR__ . '/recovery-lib.php';
av_session_start();
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$setup = isset($_GET['setup']);
$me = $setup ? av_require_login([], 'login.php') : av_user();
$account = $me ? av_find_user($me['username']) : null;
if (empty($_SESSION['recovery_csrf'])) $_SESSION['recovery_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['recovery_csrf'];
$error = ''; $notice = ''; $done = false;
$slot = $setup ? 'verify_email_challenge' : 'reset_email_challenge';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $error = 'The form expired. Refresh this page and try again.';
    } elseif (($_POST['action'] ?? '') === 'request') {
        if ($setup) {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid email address.';
            } elseif (!$account || !password_verify((string) ($_POST['current'] ?? ''), $account['pass_hash'])) {
                $error = 'Your current password is not correct.';
            } else {
                $r = av_recovery_issue($me['username'], 'verify', $account, $email);
            }
        } else {
            $identifier = trim((string) ($_POST['identifier'] ?? ''));
            if ($identifier === '' || strlen($identifier) > 254) $error = 'Enter your registered email or username.';
            else $r = av_recovery_issue($identifier);
        }
        if ($error === '' && isset($r)) {
            if (!$r['ok']) $error = $r['error'];
            else {
                $_SESSION[$slot] = $r['challenge'];
                $notice = $setup ? 'Your verification email was accepted for sending. Check your inbox and spam folder.'
                    : 'If this account has a verified email, a reset code has been requested. Check your inbox and spam folder. Allow a minute before requesting another code.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'complete') {
        $p1 = (string) ($_POST['password'] ?? '');
        if (!$setup && $p1 !== (string) ($_POST['password2'] ?? '')) $error = 'The new passwords do not match.';
        else {
            $r = av_recovery_complete((string) ($_SESSION[$slot] ?? ''), trim((string) ($_POST['code'] ?? '')),
                $setup ? 'verify' : 'reset', $p1, $setup ? $me['id'] : null);
            if (!$r['ok']) $error = $r['error'];
            else {
                unset($_SESSION[$slot]);
                $done = true;
                $notice = $setup ? 'Email verified. You can now use it to recover your account.'
                    : 'Password changed. Sign in with your new password.';
                if (!$setup) av_logout('password_reset');
                else $account = av_find_user($me['username']);
            }
        }
    }
}
$challenge = !$done && !empty($_SESSION[$slot]);
$esc = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Account recovery · Avesta Enterprises</title>
<link rel="icon" href="/icons/icon-192.png">
<style>
*{box-sizing:border-box}body{margin:0;background:#163E33;color:#12241E;font:15px/1.6 system-ui,sans-serif;min-height:100vh;display:grid;place-items:center;padding:24px 16px}
main{width:100%;max-width:440px}header{color:#fff;text-align:center;margin-bottom:20px}header a{color:#E9B06A}h1{font-size:23px;margin:0}h2{font-size:19px;margin:0 0 8px;color:#163E33}
.card{background:#fff;border-radius:16px;padding:26px;border-top:4px solid #D98E3B;box-shadow:0 18px 44px #0004}p{margin:8px 0 18px}label{display:block;font-size:13px;font-weight:650;margin-bottom:6px}
input{font:inherit;width:100%;padding:12px;border:1.5px solid #E3DED4;border-radius:9px;margin-bottom:16px}input:focus{outline:3px solid #D98E3B55;border-color:#D98E3B}
button{font:inherit;font-weight:700;background:#163E33;color:#E9B06A;padding:12px;border:0;border-radius:9px;width:100%;cursor:pointer}a{color:#B26F26}small{display:block;color:#66766F;margin-bottom:16px}
.msg{padding:12px;border-radius:9px;background:#E8F4EE;margin-bottom:16px}.error{background:#FBEAE8;color:#9d2a20}.help{border-top:1px solid #E3DED4;margin-top:22px;padding-top:16px;font-size:13px}
</style><link rel="stylesheet" href="/assets/avesta/responsive.css?v=20261003"></head><body><main><header><h1>Avesta Enterprises</h1><a href="/">Back to the website</a></header><section class="card">
<h2><?= $setup ? 'Set up recovery email' : 'Reset your password' ?></h2>
<?php if ($notice): ?><div class="msg" role="status"><?= $esc($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg error" role="alert"><?= $esc($error) ?></div><?php endif; ?>
<?php if (!$done): ?>
<?php if ($setup): ?>
<p>Verify an email you control. This works for administrator, staff and loan accounts.</p>
<?php if (!empty($account['email_verified_at'])): ?><small>Current verified email: <?= $esc($account['email']) ?>. It stays active until a replacement is verified.</small><?php endif; ?>
<?php else: ?><p>Use the email verified on your account, or your username. Codes expire after 10 minutes and can be used once.</p><?php endif; ?>
<?php if ($challenge): ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= $esc($csrf) ?>"><input type="hidden" name="action" value="complete">
<label for="code">Eight-digit email code</label><input id="code" name="code" required inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="one-time-code" autofocus>
<?php if (!$setup): ?>
<label for="password">New password</label><input id="password" type="password" name="password" required minlength="10" autocomplete="new-password">
<label for="password2">Repeat new password</label><input id="password2" type="password" name="password2" required autocomplete="new-password">
<?php endif; ?>
<button type="submit"><?= $setup ? 'Verify email' : 'Save new password' ?></button>
</form><small>Five attempts per code. If it expires, request a new one below.</small>
<?php endif; ?>
<form method="post" style="margin-top:20px">
<input type="hidden" name="csrf" value="<?= $esc($csrf) ?>"><input type="hidden" name="action" value="request">
<?php if ($setup): ?>
<label for="email">Recovery email</label><input id="email" name="email" type="email" required autocomplete="email" value="<?= $esc($_POST['email'] ?? $account['email'] ?? '') ?>">
<label for="current">Current password</label><input id="current" name="current" type="password" required autocomplete="current-password">
<?php else: ?>
<label for="identifier">Registered email or username</label><input id="identifier" name="identifier" required autocomplete="username" maxlength="254" value="<?= $esc($_POST['identifier'] ?? '') ?>">
<?php endif; ?>
<button type="submit"><?= $challenge ? 'Request a new code' : 'Send email code' ?></button>
</form>
<?php endif; ?>
<div class="help">
<?php if ($setup): ?><a href="<?= $me && in_array($me['role'], ['admin','staff'], true) ? 'portal.php' : 'apply.php' ?>">Back to your account</a>
<?php else: ?><a href="login.php">Back to sign in</a><?php endif; ?>
<p>No email code, or lost access to your email? <a href="login.php?forgot=1">Request administrator help</a> or <a href="https://wa.me/260769974200">contact Avesta on WhatsApp</a>. The office verifies your identity before issuing a temporary password.</p>
<small>Never send your password or recovery code in WhatsApp or chat.</small>
</div></section></main></body></html>
