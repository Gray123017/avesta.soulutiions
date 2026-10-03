<?php
require_once __DIR__ . '/auth.php';

const AV_EMAIL_RECOVERY_FILE = __DIR__ . '/email_recovery.json';
const AV_RECOVERY_TTL = 600;
const AV_RECOVERY_GUESSES = 5;

/** All challenge changes, attempts and consumption are serialized. */
function av_recovery_locked(callable $fn): array {
    $fp = @fopen(__DIR__ . '/email_recovery.lock', 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        if ($fp) fclose($fp);
        return ['ok' => false, 'error' => 'Recovery is temporarily unavailable. Please contact the office.'];
    }
    try {
        $state = avStoreRead(AV_EMAIL_RECOVERY_FILE) ?: ['challenges' => [], 'rates' => []];
        $state['challenges'] = array_filter($state['challenges'], function ($row) {
            return ($row['expires'] ?? 0) > time();
        });
        $state['rates'] = array_filter($state['rates'], function ($row) {
            return ($row['start'] ?? 0) > time() - 3600;
        });
        return $fn($state);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function av_recovery_save(array $state): bool {
    return avStoreWrite(AV_EMAIL_RECOVERY_FILE, $state);
}

/** Transport is server-side only. A true result means accepted for sending, not delivered. */
if (!function_exists('av_recovery_send')) {
    function av_recovery_send(string $email, string $code, string $purpose): bool {
        $from = getenv('AVESTA_RECOVERY_FROM') ?: 'info@avesta.solutions';
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) return false;
        $subject = $purpose === 'verify' ? 'Verify your Avesta recovery email' : 'Your Avesta password reset code';
        $body = "Your Avesta code is: " . $code . "\n\n"
            . "It expires in 10 minutes and can be used once. Do not share this code.\n"
            . "Enter it only at https://avesta.solutions/recovery.php\n\n"
            . "If you did not request this, ignore this email. Your password has not changed.\n"
            . "For help, contact Avesta on 0769 974 200.\n";
        return @mail($email, $subject, $body, "From: Avesta Enterprises <" . $from . ">\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n");
    }
}

function av_recovery_user(string $identifier): ?array {
    $identifier = strtolower(trim($identifier));
    $matches = [];
    foreach (av_load_users() as $u) {
        if (empty($u['active']) || empty($u['email_verified_at'])) continue;
        if (strtolower($u['username']) === $identifier || strtolower($u['email'] ?? '') === $identifier) $matches[] = $u;
    }
    return count($matches) === 1 ? $matches[0] : null;
}

function av_recovery_issue(string $identifier, string $purpose = 'reset', ?array $user = null, string $email = ''): array {
    if ($purpose === 'reset') $user = av_recovery_user($identifier);
    if ($purpose === 'verify' && !$user) return ['ok' => false, 'error' => 'Please sign in first.'];
    $email = strtolower(trim($purpose === 'verify' ? $email : ($user['email'] ?? '')));
    $challenge = bin2hex(random_bytes(16));
    $code = (string) random_int(10000000, 99999999);
    $hash = password_hash($code, PASSWORD_DEFAULT); // also done for unknown accounts
    $issued = av_recovery_locked(function (&$state) use ($user, $email, $purpose, $challenge, $hash, $identifier) {
        $keys = ['ip:' . hash('sha256', av_client_ip()), 'account:' . hash('sha256', $purpose . ':' . ($user['id'] ?? strtolower(trim($identifier))))];
        foreach ($keys as $key) {
            $rate = $state['rates'][$key] ?? ['start' => time(), 'count' => 0, 'last' => 0];
            if ($rate['count'] >= ($key === $keys[0] ? 30 : 5) || ($key === $keys[1] && time() - $rate['last'] < 60)) {
                $existing = $challenge;
                foreach ($state['challenges'] as $id => $row) {
                    if ($user && $row['uid'] === $user['id'] && $row['purpose'] === $purpose) { $existing = $id; break; }
                }
                return ['ok' => true, 'challenge' => $existing, 'limited' => true];
            }
        }
        foreach ($keys as $key) {
            $rate = $state['rates'][$key] ?? ['start' => time(), 'count' => 0, 'last' => 0];
            $rate['count']++; $rate['last'] = time(); $state['rates'][$key] = $rate;
        }
        if ($user && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // A fresh code supersedes all earlier codes for the same account and purpose.
            foreach ($state['challenges'] as $id => $row) {
                if ($row['uid'] === $user['id'] && $row['purpose'] === $purpose) unset($state['challenges'][$id]);
            }
            $state['challenges'][$challenge] = ['uid' => $user['id'], 'email' => $email,
                'purpose' => $purpose, 'hash' => $hash, 'expires' => time() + AV_RECOVERY_TTL,
                'attempts' => 0, 'password_fingerprint' => hash('sha256', $user['pass_hash'])];
        }
        if (!av_recovery_save($state)) return ['ok' => false, 'error' => 'Recovery is temporarily unavailable. Please contact the office.'];
        return ['ok' => true, 'challenge' => $challenge];
    });
    if (!empty($issued['ok']) && empty($issued['limited']) && $user && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $accepted = av_recovery_send($email, $code, $purpose);
        av_audit($accepted ? 'recovery.code_requested' : 'recovery.mail_failed', $user['username'], ['purpose' => $purpose]);
        if (!$accepted) {
            av_recovery_locked(function (&$state) use ($challenge) {
                unset($state['challenges'][$challenge]);
                av_recovery_save($state);
                return ['ok' => true];
            });
            if ($purpose === 'verify') return ['ok' => false, 'error' => 'Email could not be sent. Contact the office to check outgoing mail.'];
        }
    }
    if ($purpose === 'verify' && !empty($issued['limited'])) {
        return ['ok' => false, 'error' => 'Please wait before requesting another code. At most five requests per hour are allowed.'];
    }
    unset($issued['limited']);
    return $issued;
}

function av_recovery_complete(string $challenge, string $code, string $purpose, string $password = '', ?string $userId = null): array {
    return av_recovery_locked(function (&$state) use ($challenge, $code, $purpose, $password, $userId) {
        $failure = ['ok' => false, 'error' => 'Code invalid, expired or already used. Request a new code.'];
        $row = $state['challenges'][$challenge] ?? null;
        if (!$row || $row['purpose'] !== $purpose || $row['attempts'] >= AV_RECOVERY_GUESSES
            || ($purpose === 'verify' && $row['uid'] !== $userId)) return $failure;
        $user = null;
        foreach (av_load_users() as $u) if ($u['id'] === $row['uid']) { $user = $u; break; }
        if (!$user || empty($user['active']) || !hash_equals($row['password_fingerprint'], hash('sha256', $user['pass_hash']))) return $failure;
        $state['challenges'][$challenge]['attempts']++;
        if (!av_recovery_save($state)) return ['ok' => false, 'error' => 'Recovery is temporarily unavailable.'];
        if (!preg_match('/^[0-9]{8}$/', $code) || !password_verify($code, $row['hash'])) return $failure;
        if ($purpose === 'reset') {
            if (empty($user['email_verified_at']) || $row['email'] !== ($user['email'] ?? '')) return $failure;
            $result = av_set_password($user['id'], $password);
            if (!$result['ok']) return $result;
            av_clear_must_change($user['id']);
            av_audit('user.self_password_reset', $user['username'], ['method' => 'email']);
        } else {
            $users = av_load_users();
            foreach ($users as $u) {
                if ($u['id'] !== $user['id'] && !empty($u['email_verified_at']) && strtolower($u['email'] ?? '') === $row['email']) {
                    return ['ok' => false, 'error' => 'This email is already verified on another account.'];
                }
            }
            foreach ($users as &$u) if ($u['id'] === $user['id']) {
                $u['email'] = $row['email']; $u['email_verified_at'] = date('c'); break;
            }
            unset($u);
            if (!av_save_users($users)) return ['ok' => false, 'error' => 'Could not save the email address.'];
            av_audit('recovery.email_verified', $user['username']);
        }
        unset($state['challenges'][$challenge]);
        if (!av_recovery_save($state)) return ['ok' => false, 'error' => 'Recovery changed your account but could not clear the code. Please sign in or contact the office.'];
        return ['ok' => true];
    });
}
