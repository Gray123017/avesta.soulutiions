<?php
/**
 * AVESTA — auth.php
 * Authentication, roles, rate limiting and the audit log.
 *
 * No database. Users live in users.json, the audit trail in audit_log.jsonl.
 * Passwords are stored as bcrypt hashes — never in plain text, never recoverable.
 *
 * Roles
 *   admin     full access, manages users
 *   staff     views and edits applications, cannot manage users
 *   borrower  sees only their own application
 *
 * FIRST RUN
 * There are no users until you create the first admin. Visit setup.php once,
 * in a browser, and it will let you create one — then it disables itself.
 */

// ── config ───────────────────────────────────────────────────────────────────
const USERS_FILE   = __DIR__ . '/users.json';
const AUDIT_FILE   = __DIR__ . '/audit_log.jsonl';
const THROTTLE_FILE= __DIR__ . '/login_attempts.json';

const SESSION_NAME     = 'AVESTASESS';
const SESSION_IDLE     = 3600;    // sign out after 1 hour of inactivity
const SESSION_MAX      = 43200;   // and after 12 hours regardless
const MAX_FAILS        = 5;       // failed logins before lockout
const LOCKOUT_SECONDS  = 900;     // 15 minutes
const ALLOW_SELF_SIGNUP = true;   // borrowers can create their own account
const AUDIT_MAX_LINES  = 20000;   // trimmed from the top beyond this


// ── DATA FILES THAT CANNOT BE FETCHED OVER THE WEB ──────────────────────────
/**
 * .htaccess keeps data files private on Apache and LiteSpeed. nginx ignores
 * it entirely, and so does PHP's built-in server — on such a host anyone
 * could simply request /records_data.json and read every application.
 *
 * So the store files are named .php and begin with an exit guard. Requested
 * directly they execute, print nothing and stop. Read from disk, the guard is
 * stripped off the front. This holds on any server that runs PHP at all,
 * which is every server this will ever sit on.
 *
 * Existing .json files are migrated on first read, so an upgrade needs no
 * manual step and loses nothing.
 */
const AV_GUARD = "<?php http_response_code(404); exit; ?>\n";

function avStorePath(string $legacyJsonPath): string {
    return preg_replace('/\.jsonl?$/', '', $legacyJsonPath) . '.php';
}

function avStoreRead(string $legacyJsonPath) {
    $guarded = avStorePath($legacyJsonPath);

    if (is_file($guarded)) {
        $raw = (string) @file_get_contents($guarded);
        $cut = strpos($raw, '?>');
        return json_decode($cut === false ? $raw : substr($raw, $cut + 2), true);
    }

    // Migrate an older unguarded file, once. The backup is written guarded
    // too — simply renaming the original would leave every record still
    // downloadable, which is the whole problem being fixed. The original is
    // only removed after the guarded copy has been read back successfully.
    if (is_file($legacyJsonPath)) {
        $data = json_decode((string) @file_get_contents($legacyJsonPath), true);
        if ($data === null) return null;

        if (!avStoreWrite($legacyJsonPath, $data)) return $data;   // keep the original

        $check = @file_get_contents(avStorePath($legacyJsonPath));
        $cut   = $check === false ? false : strpos($check, '?>');
        $back  = $cut === false ? null : json_decode(substr($check, $cut + 2), true);
        if ($back !== $data) return $data;                          // not verified: keep it

        avStoreWrite($legacyJsonPath . '.backup', $data);           // guarded backup
        @unlink($legacyJsonPath);
        return $data;
    }
    return null;
}

function avStoreWrite(string $legacyJsonPath, $data): bool {
    $guarded = avStorePath($legacyJsonPath);
    $body = AV_GUARD . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $tmp  = $guarded . '.tmp';
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;
    return @rename($tmp, $guarded);      // atomic: a crash cannot truncate it
}

// ── session ──────────────────────────────────────────────────────────────────
function av_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,      // JavaScript cannot read the cookie
        'secure'   => $https,    // only sent over HTTPS once you have a cert
        'samesite' => 'Lax',     // blocks most cross-site request forgery
    ]);
    session_start();

    // Expire idle and over-long sessions server-side, not just by cookie age
    $now = time();
    if (!empty($_SESSION['uid'])) {
        $idle = $now - (int) ($_SESSION['seen'] ?? $now);
        $age  = $now - (int) ($_SESSION['started'] ?? $now);
        if ($idle > SESSION_IDLE || $age > SESSION_MAX) {
            av_logout('expired');
        } else {
            $_SESSION['seen'] = $now;
        }
    }
}

function av_user(): ?array {
    av_session_start();
    if (empty($_SESSION['uid'])) return null;
    return [
        'id'       => $_SESSION['uid'],
        'username' => $_SESSION['username'] ?? '',
        'name'     => $_SESSION['name'] ?? '',
        'role'     => $_SESSION['role'] ?? 'borrower',
    ];
}

function av_is(string ...$roles): bool {
    $u = av_user();
    return $u !== null && in_array($u['role'], $roles, true);
}

function av_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ── users ────────────────────────────────────────────────────────────────────
function av_load_users(): array {
    $d = avStoreRead(USERS_FILE);
    return is_array($d) ? $d : [];
}

function av_save_users(array $users): bool {
    return avStoreWrite(USERS_FILE, $users);
}

function av_find_user(string $username): ?array {
    $u = strtolower(trim($username));
    foreach (av_load_users() as $row) {
        if (strtolower($row['username'] ?? '') === $u) return $row;
    }
    return null;
}

function av_username_ok(string $u): bool {
    return (bool) preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $u);
}

/** Rejects the passwords that actually get broken, not arbitrary symbol rules. */
function av_password_problem(string $p, string $username = ''): ?string {
    if (strlen($p) < 10) return 'Password must be at least 10 characters.';
    if (strlen($p) > 200) return 'Password is too long.';
    $low = strtolower($p);

    // Reject the username itself, and the username with trailing digits
    // stripped — "gray2" must not accept "graygraygray".
    if ($username !== '') {
        $stem = strtolower(rtrim($username, '0123456789'));
        foreach (array_unique([strtolower($username), $stem]) as $needle) {
            if (strlen($needle) >= 3 && strpos($low, $needle) !== false) {
                return 'Password must not contain your username.';
            }
        }
    }

    // A short word repeated is not a passphrase, however long the result
    for ($len = 2; $len <= 8; $len++) {
        if (strlen($p) >= $len * 2 && strlen($p) % $len === 0) {
            if (str_repeat(substr($p, 0, $len), (int) (strlen($p) / $len)) === $p) {
                return 'That password is just a short word repeated. Try a phrase instead.';
            }
        }
    }
    $common = ['password', 'passw0rd', '1234567890', 'qwertyuiop', 'letmein123',
               'avesta123', 'admin12345', 'zambia123', 'iloveyou1'];
    foreach ($common as $c) {
        if ($low === $c || strpos($low, $c) !== false) return 'That password is too easy to guess.';
    }
    if (preg_match('/^(.)\1+$/', $p)) return 'That password is too easy to guess.';
    return null;
}

function av_create_user(string $username, string $password, string $name,
                        string $role = 'borrower'): array {
    $username = trim($username);
    if (!av_username_ok($username)) {
        return ['ok' => false, 'error' => 'Username must be 3–32 letters, numbers, dot, dash or underscore.'];
    }
    if (av_find_user($username)) {
        return ['ok' => false, 'error' => 'That username is already taken.'];
    }
    if (($why = av_password_problem($password, $username)) !== null) {
        return ['ok' => false, 'error' => $why];
    }
    if (!in_array($role, ['admin', 'staff', 'borrower'], true)) $role = 'borrower';

    $users = av_load_users();
    $row = [
        'id'         => 'u_' . bin2hex(random_bytes(8)),
        'username'   => $username,
        'name'       => trim($name) !== '' ? trim($name) : $username,
        'role'       => $role,
        'pass_hash'  => password_hash($password, PASSWORD_DEFAULT),
        'active'     => true,
        'created_at' => date('c'),
        'last_login' => null,
    ];
    $users[] = $row;
    if (!av_save_users($users)) {
        return ['ok' => false, 'error' => 'Could not save the user — check folder permissions.'];
    }
    unset($row['pass_hash']);
    return ['ok' => true, 'user' => $row];
}

function av_set_password(string $userId, string $newPassword): array {
    $users = av_load_users();
    foreach ($users as &$u) {
        if (($u['id'] ?? '') === $userId) {
            if (($why = av_password_problem($newPassword, $u['username'] ?? '')) !== null) {
                return ['ok' => false, 'error' => $why];
            }
            $u['pass_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            unset($u);
            return av_save_users($users)
                ? ['ok' => true]
                : ['ok' => false, 'error' => 'Could not save the change.'];
        }
    }
    return ['ok' => false, 'error' => 'User not found.'];
}

// ── login throttling ─────────────────────────────────────────────────────────
/* ── PASSWORD RESETS ─────────────────────────────────────────────────────────
 * There is no email server, and a borrower who has forgotten their password
 * usually phones the office anyway. So a reset is a request the admin sees,
 * verifies against the person's NRC and phone number, and then acts on by
 * issuing a temporary password. The temporary password must be changed at the
 * next sign-in, so it cannot quietly become someone's permanent password.
 */
define('AV_RESET_FILE', __DIR__ . '/password_resets.json');

// Stored through the same guarded store as users and records, so the file is
// never readable over the web even where .htaccess is ignored.
function av_resets_load(): array {
    $d = avStoreRead(AV_RESET_FILE);
    return is_array($d) ? $d : [];
}

function av_resets_save(array $rows): bool {
    return avStoreWrite(AV_RESET_FILE, $rows);
}

/**
 * Records a request. Deliberately says the same thing whether or not the
 * account exists: telling a stranger "no such user" hands them a way to find
 * out which usernames are real.
 */
function av_request_reset(string $username, string $phone): array {
    $username = strtolower(trim($username));
    $phone    = trim($phone);
    $user     = av_find_user($username);

    $rows = av_resets_load();
    $open = 0;
    foreach ($rows as $r) {
        if (($r['username'] ?? '') === $username && ($r['status'] ?? '') === 'open') $open++;
    }
    // A request already waiting is enough; more would just bury the real one
    if ($user && $open < 3) {
        $rows[] = [
            'id'           => 'r_' . bin2hex(random_bytes(6)),
            'username'     => $username,
            'name'         => $user['name'] ?? $username,
            'phone'        => substr($phone, 0, 40),
            'requested_at' => date('c'),
            'ip'           => av_client_ip(),
            'status'       => 'open',
            'handled_by'   => null,
            'handled_at'   => null,
        ];
        if (count($rows) > 2000) $rows = array_slice($rows, -2000);
        av_resets_save($rows);
        av_audit('user.reset_requested', $username, ['phone' => substr($phone, 0, 40)]);
    }
    return ['ok' => true];
}

/** Words that are easy to read out over the phone and type once. */
function av_temp_password(): string {
    $words = ['Kwacha', 'Copper', 'Ndola', 'Kansenshi', 'Luangwa', 'Zambezi', 'Chipata',
              'Mufulira', 'Kabwe', 'Chembe', 'Kariba', 'Lusaka'];
    for ($i = 0; $i < 40; $i++) {
        $p = $words[random_int(0, count($words) - 1)] . '-'
           . $words[random_int(0, count($words) - 1)] . '-' . random_int(10, 99);
        if (av_password_problem($p) === null) return $p;
    }
    return 'Avesta-' . bin2hex(random_bytes(5));   // never reached in practice
}

/**
 * The admin resets someone's password. Returns the temporary password ONCE —
 * it is never stored in readable form, so if it is lost the reset is simply
 * done again.
 */
function av_admin_reset_password(string $userId, string $byUsername): array {
    $users = av_load_users();
    $target = null;
    foreach ($users as $u) if (($u['id'] ?? '') === $userId) { $target = $u; break; }
    if ($target === null) return ['ok' => false, 'error' => 'That account was not found.'];

    $temp = av_temp_password();
    $r = av_set_password($userId, $temp);
    if (!$r['ok']) return $r;

    // Force a change at the next sign-in
    $users = av_load_users();
    foreach ($users as &$u) {
        if (($u['id'] ?? '') === $userId) { $u['must_change'] = true; break; }
    }
    unset($u);
    av_save_users($users);

    // Close any open requests for this person
    $rows = av_resets_load();
    $changed = false;
    foreach ($rows as &$row) {
        if (($row['username'] ?? '') === ($target['username'] ?? '') && ($row['status'] ?? '') === 'open') {
            $row['status'] = 'done';
            $row['handled_by'] = $byUsername;
            $row['handled_at'] = date('c');
            $changed = true;
        }
    }
    unset($row);
    if ($changed) av_resets_save($rows);

    av_audit('user.password_reset', $target['username'] ?? $userId, ['by' => $byUsername]);
    return ['ok' => true, 'temporary_password' => $temp,
            'username' => $target['username'] ?? '', 'name' => $target['name'] ?? ''];
}

/** Clears the forced change once the person has set their own password. */
function av_clear_must_change(string $userId): void {
    $users = av_load_users();
    $hit = false;
    foreach ($users as &$u) {
        if (($u['id'] ?? '') === $userId && !empty($u['must_change'])) { unset($u['must_change']); $hit = true; }
    }
    unset($u);
    if ($hit) { av_save_users($users); $_SESSION['must_change'] = false; }
}

function av_throttle_read(): array {
    if (!is_file(THROTTLE_FILE)) return [];
    $d = json_decode((string) @file_get_contents(THROTTLE_FILE), true);
    return is_array($d) ? $d : [];
}

function av_throttle_key(string $username): string {
    return strtolower($username) . '|' . av_client_ip();
}

/** Seconds remaining on a lockout, or 0 if the caller may try again. */
function av_locked_for(string $username): int {
    $all = av_throttle_read();
    $e = $all[av_throttle_key($username)] ?? null;
    if (!$e) return 0;
    if (($e['fails'] ?? 0) < MAX_FAILS) return 0;
    $left = LOCKOUT_SECONDS - (time() - (int) ($e['last'] ?? 0));
    return $left > 0 ? $left : 0;
}

function av_throttle_note(string $username, bool $success): void {
    $all = av_throttle_read();
    $key = av_throttle_key($username);
    $now = time();

    // Drop entries older than the lockout window so the file cannot grow forever
    foreach ($all as $k => $v) {
        if ($now - (int) ($v['last'] ?? 0) > LOCKOUT_SECONDS * 4) unset($all[$k]);
    }
    if ($success) {
        unset($all[$key]);
    } else {
        $prev = $all[$key]['fails'] ?? 0;
        if ($now - (int) ($all[$key]['last'] ?? 0) > LOCKOUT_SECONDS) $prev = 0;
        $all[$key] = ['fails' => $prev + 1, 'last' => $now];
    }
    @file_put_contents(THROTTLE_FILE, json_encode($all), LOCK_EX);
}

// ── audit log ────────────────────────────────────────────────────────────────
/**
 * Append-only record of who did what. The Data Protection Act expects a
 * controller to be able to show this; it is also the only way to answer
 * "who approved that loan" after the fact.
 */
function av_audit(string $action, string $target = '', array $detail = []): void {
    $u = av_user();
    $line = json_encode([
        'ts'      => date('c'),
        'user'    => $u['username'] ?? 'anonymous',
        'user_id' => $u['id'] ?? null,
        'role'    => $u['role'] ?? null,
        'action'  => $action,
        'target'  => $target,
        'detail'  => $detail,
        'ip'      => av_client_ip(),
    ], JSON_UNESCAPED_UNICODE);

    @file_put_contents(AUDIT_FILE, $line . "\n", FILE_APPEND | LOCK_EX);

    // Keep the file bounded — trim oldest entries occasionally
    if (@filesize(AUDIT_FILE) > 4 * 1024 * 1024) {
        $lines = @file(AUDIT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (count($lines) > AUDIT_MAX_LINES) {
            $keep = array_slice($lines, -AUDIT_MAX_LINES);
            @file_put_contents(AUDIT_FILE, implode("\n", $keep) . "\n", LOCK_EX);
        }
    }
}

function av_audit_read(int $limit = 200, string $filterUser = '', string $filterAction = ''): array {
    if (!is_file(AUDIT_FILE)) return [];
    $lines = @file(AUDIT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $out = [];
    for ($i = count($lines) - 1; $i >= 0 && count($out) < $limit; $i--) {
        $row = json_decode($lines[$i], true);
        if (!is_array($row)) continue;
        if ($filterUser !== '' && strcasecmp($row['user'] ?? '', $filterUser) !== 0) continue;
        if ($filterAction !== '' && ($row['action'] ?? '') !== $filterAction) continue;
        $out[] = $row;
    }
    return $out;
}

// ── login / logout ───────────────────────────────────────────────────────────
function av_login(string $username, string $password): array {
    av_session_start();

    $wait = av_locked_for($username);
    if ($wait > 0) {
        av_audit('login.locked_out', $username, ['seconds_left' => $wait]);
        return ['ok' => false, 'locked' => true,
                'error' => 'Too many failed attempts. Try again in ' . ceil($wait / 60) . ' minute(s).'];
    }

    $user = av_find_user($username);

    // Always run a hash comparison, even when the user does not exist, so the
    // response time cannot be used to discover which usernames are real.
    $hash = $user['pass_hash'] ?? '$2y$10$usesomesillystringfoobarbazquxquuxcorgegraultgarply00000';
    $ok = password_verify($password, $hash) && $user !== null;

    if ($ok && empty($user['active'])) {
        av_audit('login.disabled_account', $username);
        return ['ok' => false, 'error' => 'This account has been disabled. Contact the administrator.'];
    }

    if (!$ok) {
        av_throttle_note($username, false);
        $left = MAX_FAILS - (av_throttle_read()[av_throttle_key($username)]['fails'] ?? 0);
        av_audit('login.failed', $username);
        return ['ok' => false,
                'error' => 'Incorrect username or password.'
                         . ($left > 0 && $left <= 2 ? ' ' . $left . ' attempt(s) left before lockout.' : '')];
    }

    av_throttle_note($username, true);

    // New session id on login, so a session fixed before login is worthless
    session_regenerate_id(true);
    $_SESSION['uid']      = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name']     = $user['name'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['must_change'] = !empty($user['must_change']);
    $_SESSION['started']  = time();
    $_SESSION['seen']     = time();

    // Re-hash if PHP's default cost has moved on since the password was set
    if (password_needs_rehash($user['pass_hash'], PASSWORD_DEFAULT)) {
        av_set_password($user['id'], $password);
    }

    $users = av_load_users();
    foreach ($users as &$u) {
        if ($u['id'] === $user['id']) { $u['last_login'] = date('c'); break; }
    }
    unset($u);
    av_save_users($users);

    av_audit('login.success', $user['username'], ['role' => $user['role']]);

    return ['ok' => true, 'user' => [
        'id' => $user['id'], 'username' => $user['username'],
        'name' => $user['name'], 'role' => $user['role'],
    ]];
}

function av_logout(string $why = 'user'): void {
    av_session_start();
    if (!empty($_SESSION['uid'])) {
        av_audit('logout', $_SESSION['username'] ?? '', ['reason' => $why]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── page guard ───────────────────────────────────────────────────────────────
/**
 * Call at the top of any page that requires a session. Redirects to the login
 * page rather than rendering anything, so the HTML never reaches the browser.
 */
function av_require_login(array $roles = [], string $loginPage = 'login.php'): array {
    $u = av_user();
    // Someone signed in with a temporary password goes nowhere else until they
    // have set their own. Otherwise a password read out over the phone quietly
    // becomes the account's permanent password.
    if ($u !== null && !empty($_SESSION['must_change'])
        && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'logout.php') {
        header('Location: ' . $loginPage . '?change=1');
        exit;
    }
    if ($u === null) {
        $to = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . $loginPage . ($to ? '?next=' . urlencode($to) : ''));
        exit;
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        av_audit('access.denied', $_SERVER['REQUEST_URI'] ?? '', ['role' => $u['role']]);
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Not allowed</title>'
           . '<div style="font-family:system-ui;max-width:32rem;margin:15vh auto;padding:0 1.5rem;'
           . 'color:#163E33;line-height:1.6">'
           . '<h1 style="font-size:1.3rem">This area is for staff</h1>'
           . '<p>Your account does not have access to this page.</p>'
           . '<p><a href="index.php" style="color:#B26F26">Back to the site</a> &middot; '
           . '<a href="logout.php" style="color:#B26F26">Sign in as someone else</a></p></div>';
        exit;
    }
    return $u;
}
