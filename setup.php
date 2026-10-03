<?php
/**
 * One-time bootstrap: creates the first administrator.
 * Refuses to do anything once any user exists, so it cannot be used twice.
 */
require_once __DIR__ . '/auth.php';
av_session_start();

$users = av_load_users();
$done  = count($users) > 0;
$error = '';

if (!$done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string) ($_POST['password'] ?? '');
    $p2 = (string) ($_POST['password2'] ?? '');
    if ($p1 !== $p2) {
        $error = 'The two passwords do not match.';
    } else {
        $r = av_create_user((string) ($_POST['username'] ?? ''), $p1,
                            (string) ($_POST['name'] ?? ''), 'admin', (string) ($_POST['email'] ?? ''));
        if (!$r['ok']) {
            $error = $r['error'];
        } else {
            av_audit('setup.first_admin_created', $r['user']['username']);
            header('Location: login.php');
            exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Set up Avesta</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#163E33;
 min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 18px;color:#12241E}
.card{background:#fff;max-width:410px;width:100%;border-radius:16px;padding:26px 24px;
 box-shadow:0 18px 44px rgba(0,0,0,.3);border-top:3px solid #D98E3B}
h1{font-size:17px;color:#163E33;margin-bottom:4px}
p.sub{font-size:12.5px;color:#66766F;margin-bottom:20px;line-height:1.55}
label{display:block;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
 color:#66766F;margin-bottom:6px}
input{width:100%;padding:12px 13px;border:1.5px solid #E3DED4;border-radius:9px;font-size:15px;
 margin-bottom:15px;font-family:inherit}
input:focus{outline:none;border-color:#D98E3B;box-shadow:0 0 0 3px rgba(217,142,59,.16)}
button{width:100%;padding:13px;border:none;border-radius:9px;background:#163E33;color:#E9B06A;
 font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
.msg{padding:11px 13px;border-radius:9px;font-size:12.5px;margin-bottom:16px;line-height:1.5}
.bad{background:#FBEAE8;color:#C0392B;border-left:3px solid #C0392B}
.good{background:#E8F4EE;color:#1E7A4C;border-left:3px solid #1E7A4C}
.hint{font-size:11px;color:#66766F;margin:-9px 0 15px}
a{color:#B26F26;font-weight:700}
</style></head><body>
<div class="card">
<?php if ($done): ?>
  <h1>Setup already complete</h1>
  <div class="msg good" style="margin-top:14px">
    An administrator account exists, so this page is now disabled.
  </div>
  <p class="sub">Delete <code>setup.php</code> from the server for good measure,
     then <a href="login.php">sign in</a>.</p>
<?php else: ?>
  <h1>Create the first administrator</h1>
  <p class="sub">This runs once. Afterwards the page disables itself and you can
     delete it from the server.</p>
  <?php if ($error): ?><div class="msg bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autocapitalize="none"
           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    <label for="email">Recovery email (optional)</label>
    <input type="email" id="email" name="email" autocomplete="email">
    <p class="hint">Verify this email after signing in to enable password recovery.</p>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required minlength="10">
    <p class="hint">At least 10 characters. A short phrase you will remember works well.</p>
    <label for="password2">Repeat password</label>
    <input type="password" id="password2" name="password2" required>
    <button type="submit">Create administrator</button>
  </form>
<?php endif; ?>
</div></body></html>
