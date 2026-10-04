<?php
/**
 * AVESTA ENTERPRISES — Loan Records API
 * Stores loan application data in records_data.json.
 * Stores uploaded documents (NRC, payslips, etc) as real files in /documents/.
 * No database needed. Works with any PHP 7.4+ hosting (cPanel, webspacekit, etc).
 *
 * Upload this file to the SAME folder as index.html and admin.html.
 */

// ── CONFIG ─────────────────────────────────────────────────────────────────
define('DATA_FILE', __DIR__ . '/records_data.json');
define('LOCK_FILE',  __DIR__ . '/records_data.lock');
define('DOCS_DIR',   __DIR__ . '/documents');
define('MAX_RECORDS', 5000); // safety cap

// Document fields that may contain base64 file uploads
$DOC_FIELDS = [
    'doc_nrc_front', 'doc_nrc_back', 'doc_passport',
    'doc_collateral1', 'doc_collateral2', 'doc_collateral3',
    'doc_salary1', 'doc_salary2', 'doc_payslip',
    'doc_bank_statement', 'doc_utility_bill', 'doc_employer_letter', 'doc_gtr_nrc'
];

// NOTE: post_max_size and upload_max_filesize CANNOT be changed via ini_set()
// at runtime — PHP ignores them outside php.ini/.htaccess/.user.ini. A
// .user.ini file is created below (auto-applies on most cPanel hosts after
// a few minutes, no restart needed). memory_limit and max_execution_time
// CAN be changed at runtime, so those ini_set calls below are effective.
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '60');

// Auto-create a .user.ini next to this file so cPanel/Apache hosts raise
// the upload limit without needing manual php.ini access. Takes a few
// minutes to take effect after first being created.
$userIniPath = __DIR__ . '/.user.ini';
if (!defined('AVESTA_LIB') && !file_exists($userIniPath)) {
    @file_put_contents($userIniPath,
        "upload_max_filesize = 20M\n" .
        "post_max_size = 25M\n" .
        "memory_limit = 256M\n" .
        "max_execution_time = 60\n"
    );
}

require_once __DIR__ . '/auth.php';

/**
 * Endpoint guard. Replaces the old wide-open CORS policy: the browser now
 * sends the session cookie, so requests must come from this site and carry a
 * valid session. Anything reading or changing applicant data goes through here.
 */
function requireRole(array $roles) {
    $u = av_user();
    if ($u === null) {
        jsonOut(['ok' => false, 'error' => 'Not signed in', 'auth' => 'required'], 401);
    }
    // Signed in with a temporary password: the pages already send them to the
    // change screen, and the API must refuse too. Otherwise a password read out
    // over the phone still reaches every record through the interface.
    if (!empty($_SESSION['must_change'])) {
        jsonOut(['ok' => false, 'error' => 'Please set your own password first.',
                 'auth' => 'change_password'], 403);
    }
    if ($roles && $u['role'] !== 'super_admin' && !in_array($u['role'], $roles, true)) {
        av_audit('api.forbidden', $_GET['action'] ?? '', ['role' => $u['role']]);
        jsonOut(['ok' => false, 'error' => 'Your account cannot do that', 'auth' => 'forbidden'], 403);
    }
    return $u;
}

function av_require_manage_target(array $me, string $targetId): array {
    $target = null;
    foreach (av_load_users() as $row) {
        if (($row['id'] ?? '') === $targetId) { $target = $row; break; }
    }
    if ($target === null) jsonOut(['ok' => false, 'error' => 'User not found'], 404);
    if (($target['role'] ?? '') === 'super_admin' && ($me['role'] ?? '') !== 'super_admin') {
        av_audit('api.forbidden_privileged_account', $target['username'] ?? $targetId);
        jsonOut(['ok' => false, 'error' => 'Only a Super Admin can manage a Super Admin account.'], 403);
    }
    return $target;
}

// ── CORS HEADERS — same-origin only now that sessions are in use ────────────
// Skipped when another PHP page includes this file for fxConvert() — sending
// JSON headers into an HTML page would break it.
if (!defined('AVESTA_LIB')) {
    // A wildcard origin cannot carry cookies, and would hand applicant data to
    // any site that asked. Reflect this site's own origin instead.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin !== '' && $host !== '' && parse_url($origin, PHP_URL_HOST) === explode(':', $host)[0]) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ── DOCUMENTS FOLDER: NEVER SERVED DIRECTLY ─────────────────────────────────
// This folder holds NRCs, payslips and collateral photos. It used to get a
// rule that only switched off folder listings — every file inside was still
// downloadable by anyone who had, or guessed, the link. Now the web server
// refuses the whole folder, and staff see documents through ?action=doc,
// which checks who is asking.
//
// Checked on every request rather than only when the folder is created: a
// folder made by FTP, or a rule file deleted by accident, is put right on the
// next page load instead of staying open.
const DOCS_RULES = "# Identity documents and collateral photos. Never served directly:\n"
    . "# staff and the borrower view them through api.php?action=doc.\n"
    . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
    . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
    . "Options -Indexes\n";
if (!defined('AVESTA_LIB')) {
    if (!is_dir(DOCS_DIR)) @mkdir(DOCS_DIR, 0755, true);
    $rules = DOCS_DIR . '/.htaccess';
    if (!is_file($rules) || @file_get_contents($rules) !== DOCS_RULES) {
        @file_put_contents($rules, DOCS_RULES, LOCK_EX);
    }
}

// ── HELPERS ───────────────────────────────────────────────────────────────────

function loadRecords() {
    // Guarded store: not fetchable over the web on any server. See auth.php.
    $data = avStoreRead(DATA_FILE);
    if ($data === null) { avStoreWrite(DATA_FILE, []); return []; }
    return is_array($data) ? $data : [];
}

function saveRecords($records) {
    $fp = fopen(LOCK_FILE, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $ok = avStoreWrite(DATA_FILE, $records);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $ok;
    }
    if ($fp) fclose($fp);
    return avStoreWrite(DATA_FILE, $records);
}

function jsonOut($obj, $code = 200) {
    http_response_code($code);
    echo json_encode($obj, JSON_UNESCAPED_UNICODE);
    exit;
}

// Converts PHP ini size strings like "8M", "256K", "1G" into a raw byte count
function iniSizeToBytes($val) {
    $val = trim((string) $val);
    if ($val === '' || $val === '-1') return PHP_INT_MAX; // unlimited
    $last = strtolower(substr($val, -1));
    $num  = (int) $val;
    switch ($last) {
        case 'g': return $num * 1024 * 1024 * 1024;
        case 'm': return $num * 1024 * 1024;
        case 'k': return $num * 1024;
        default:  return (int) $val;
    }
}

function sanitize($v) {
    if (is_array($v)) return array_map('sanitize', $v);
    if (is_string($v)) return trim($v);
    return $v;
}

function safeExtFromMime($mime) {
    $map = [
        'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];
    return $map[$mime] ?? 'bin';
}

/**
 * Extracts base64 document fields from a payload, saves each as a real file
 * in DOCS_DIR, and replaces the *_base64 field with a *_url field pointing
 * to the saved file. Returns the modified payload (without base64 blobs).
 */
function extractDocuments(array $payload, string $recordKey) {
    global $DOC_FIELDS;

    $folder = DOCS_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $recordKey);

    foreach ($DOC_FIELDS as $field) {
        $b64Key = $field . '_base64';
        if (empty($payload[$b64Key])) {
            unset($payload[$b64Key]);
            continue;
        }

        $b64  = $payload[$b64Key];
        $mime = $payload[$field . '_mimetype'] ?? 'application/octet-stream';

        // Strip data URI prefix if present (e.g. "data:image/png;base64,....")
        if (strpos($b64, 'base64,') !== false) {
            $b64 = substr($b64, strpos($b64, 'base64,') + 7);
        }

        // Strip any whitespace/newlines that may have crept in (some browsers/
        // proxies wrap long base64 strings), then decode permissively.
        $b64 = preg_replace('/\s+/', '', $b64);
        $bytes = base64_decode($b64, true);
        if ($bytes === false || strlen($bytes) === 0) {
            throw new RuntimeException('A document could not be decoded. Please select the file again and retry.', 422);
        }
        if (safeExtFromMime($mime) === 'bin') {
            throw new RuntimeException('Unsupported document format. Please upload a PDF, JPEG, PNG, GIF or WebP file.', 422);
        }

        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            throw new RuntimeException('Documents could not be saved. Please contact Avesta to check server storage permissions.', 500);
        }

        $ext      = safeExtFromMime($mime);
        $filename = $field . '.' . $ext;
        $filepath = $folder . '/' . $filename;

        // Write beside the destination and rename only after every byte is saved.
        // A failed upload must not erase an existing document or report success.
        $tmp = @tempnam($folder, '.upload-');
        if (!$tmp || @file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes)
            || !@rename($tmp, $filepath)) {
            if ($tmp && is_file($tmp)) @unlink($tmp);
            throw new RuntimeException('Documents could not be saved. Please contact Avesta to check server storage permissions.', 500);
        }
        $payload[$field . '_url'] = 'documents/' .
            rawurlencode(basename($folder)) . '/' . rawurlencode($filename);

        // Remove the heavy base64 blob from the JSON record
        unset($payload[$b64Key]);
    }

    return $payload;
}

/**
 * Deletes a record's document folder (used when a record is deleted).
 */
function deleteDocumentsFolder(string $recordKey) {
    $folder = DOCS_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $recordKey);
    if (is_dir($folder)) {
        $files = glob($folder . '/*');
        foreach ($files as $f) {
            if (is_file($f)) @unlink($f);
        }
        @rmdir($folder);
    }
}

// ── EXCHANGE RATE SUPPORT (currency converter) ───────────────────────────────
// Rates are fetched server-side once an hour and cached to rates_cache.json,
// so the browser makes one same-origin call instead of hitting a third-party
// feed. The full USD-based table is returned — every currency pair is just a
// cross-rate from it, so one request covers all conversions.
//
// OPTIONAL — official Bank of Zambia rate:
// Leave BOZ_JSON empty and the kwacha uses the market mid rate. To use the
// OFFICIAL BoZ figure instead (what ZRA and auditors expect), open
// https://www.boz.zm/markets-securities in Chrome, press F12 → Network → XHR,
// reload the page, find the request carrying the rate data, and paste its URL
// into BOZ_JSON below. boz.zm is a JavaScript app with no documented API, so
// this URL has to be read off the network tab — it cannot be guessed.

define('RATES_CACHE',  __DIR__ . '/rates_cache.json');
define('RATES_TTL',    3600);   // market rates: refresh hourly
define('BOZ_TTL',      10800);  // BoZ publishes once a day, ~16:00 weekdays
define('BOZ_JSON',     '');     // <── optional: a readable BoZ JSON endpoint, if one ever appears
define('BOZ_PUSH_TOKEN', '');   // <── set a long random string to enable ?action=setBoz
define('RATES_FEED',   'https://open.er-api.com/v6/latest/USD');
define('RATES_BACKUP', 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json');

function fxHttpGet($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT      => 'avesta-fx/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? $body : null;
    }
    // Fallback for hosts without the curl extension
    $ctx  = stream_context_create(['http' => ['timeout' => 12, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

function fxCleanTable($pairs) {
    $out = [];
    foreach ($pairs as $code => $val) {
        $up = strtoupper((string) $code);
        if (preg_match('/^[A-Z]{3}$/', $up) && is_numeric($val) && (float) $val > 0) {
            $out[$up] = (float) $val;
        }
    }
    return $out;
}

function fxFetchMarket() {
    $raw = fxHttpGet(RATES_FEED);
    if ($raw !== null) {
        $d = json_decode($raw, true);
        if (!empty($d['rates']['ZMW']) && !empty($d['rates']['USD'])) {
            return [
                'table'  => fxCleanTable($d['rates']),
                'as_of'  => isset($d['time_last_update_unix'])
                    ? date('Y-m-d H:i', (int) $d['time_last_update_unix'])
                    : date('Y-m-d H:i'),
                'source' => 'open.er-api.com',
            ];
        }
    }
    $raw = fxHttpGet(RATES_BACKUP);   // second feed if the first is down
    if ($raw !== null) {
        $d = json_decode($raw, true);
        if (!empty($d['usd'])) {
            $t = fxCleanTable($d['usd']);
            if (!empty($t['ZMW'])) {
                return ['table'  => $t,
                        'as_of'  => $d['date'] ?? date('Y-m-d'),
                        'source' => 'currency-api'];
            }
        }
    }
    return null;
}

// Walks an arbitrary JSON tree looking for the USD row, whatever shape BoZ uses
function fxHarvestUsd($node, &$out) {
    if (!is_array($node)) return;
    foreach ($node as $key => $val) {
        if (!is_array($val)) continue;

        $code = null;
        foreach (['currency', 'code', 'currencyCode', 'symbol'] as $k) {
            if (!empty($val[$k]) && is_string($val[$k])) {
                $code = strtoupper(trim($val[$k]));
                break;
            }
        }
        if ($code === null && is_string($key) && preg_match('/^[A-Z]{3}$/', strtoupper($key))) {
            $code = strtoupper($key);
        }

        if ($code === 'USD') {
            $buy = $sell = $mid = null;
            foreach ($val as $k2 => $v2) {
                if (!is_numeric($v2) || (float) $v2 <= 0) continue;
                $k2l = strtolower((string) $k2);
                if (strpos($k2l, 'buy') !== false || strpos($k2l, 'bid') !== false) {
                    $buy = (float) $v2;
                } elseif (strpos($k2l, 'sell') !== false || strpos($k2l, 'ask') !== false) {
                    $sell = (float) $v2;
                } elseif (strpos($k2l, 'mid') !== false || $k2l === 'rate' || $k2l === 'value') {
                    $mid = (float) $v2;
                }
            }
            if ($mid === null && $buy !== null && $sell !== null) $mid = ($buy + $sell) / 2;
            if ($mid !== null && $mid > 1) {   // kwacha per dollar is well above 1
                $out = ['mid' => $mid, 'buy' => $buy, 'sell' => $sell];
                return;
            }
        }

        fxHarvestUsd($val, $out);
        if ($out) return;
    }
}

function fxFetchBoz() {
    if (BOZ_JSON === '') return null;
    $raw = fxHttpGet(BOZ_JSON);
    if ($raw === null) return null;
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;

    $usd = null;
    fxHarvestUsd($data, $usd);
    if (empty($usd['mid'])) return null;

    return [
        'zmw_per_usd' => $usd['mid'],
        'buy'         => $usd['buy'],
        'sell'        => $usd['sell'],
        'as_of'       => preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $raw, $m) ? $m[1] : date('Y-m-d'),
        'source'      => 'Bank of Zambia',
    ];
}

/**
 * Reads the cached rate table. Never makes an outbound call, so it is safe to
 * call on every page render. Returns null if ?action=rates has never run.
 * The official BoZ kwacha rate overrides the market one when available.
 */
function fxTable() {
    if (!file_exists(RATES_CACHE)) return null;
    $d = json_decode(@file_get_contents(RATES_CACHE), true);
    if (empty($d['market']['table']['USD'])) return null;

    $t = $d['market']['table'];
    if (!empty($d['boz']['zmw_per_usd'])) {
        $t['ZMW'] = (float) $d['boz']['zmw_per_usd'];
    }
    return $t;
}

/**
 * Convert between any two currencies using the cached table.
 * Returns null if rates are unavailable or a currency is unknown.
 *
 * Use this for loan documents, statements — anything printed. Store the
 * ORIGINAL currency plus the rate used, and convert at display time. Never
 * write a converted figure into a record, or the next rate refresh silently
 * rewrites history.
 *
 *   define('AVESTA_LIB', true);
 *   require __DIR__ . '/api.php';
 *   $usd = fxConvert(50000, 'ZMW', 'USD');
 */
function fxConvert($amount, $from, $to) {
    $t    = fxTable();
    $from = strtoupper($from);
    $to   = strtoupper($to);
    if (!$t || empty($t[$from]) || empty($t[$to])) return null;
    return (float) $amount * ($t[$to] / $t[$from]);
}

// Let other PHP pages reuse fxConvert() without running the API router:
//   define('AVESTA_LIB', true); require __DIR__ . '/api.php';
if (defined('AVESTA_LIB')) return;


// ── LOAN MATHS ───────────────────────────────────────────────────────────────
/**
 * Works out where a loan stands: what is owed, what has been paid, what is
 * left, when it falls due and how late it is.
 *
 * Money is handled in ngwee (integer hundredths) wherever it is compared or
 * summed. Floats lose a ngwee here and there, and "balance == 0" then never
 * quite becomes true, leaving loans that look unpaid forever.
 */
function loanSummary(array $r): array {
    $toNgwee = function ($v) {
        $n = (float) preg_replace('/[^0-9.\-]/', '', (string) $v);
        return (int) round($n * 100);
    };

    $principal = $toNgwee($r['loan_amt'] ?? 0);
    $total     = $toNgwee($r['total_repay'] ?? 0);
    $rate      = (float) preg_replace('/[^0-9.]/', '', (string) ($r['int_rate'] ?? 0));

    // Fall back to principal + interest if the form's total was never filled
    if ($total <= 0 && $principal > 0) {
        $total = (int) round($principal * (1 + $rate / 100));
    }

    $paid = 0;
    $payments = is_array($r['_payments'] ?? null) ? $r['_payments'] : [];
    foreach ($payments as $p) {
        $paid += $toNgwee($p['amount'] ?? 0);
    }

    $balance = $total - $paid;
    if ($balance < 0) $balance = 0;          // overpayment is not a negative debt

    // Due date: disbursement plus the agreed number of weeks
    $weeks = (int) preg_replace('/[^0-9]/', '', (string) ($r['sel_dur'] ?? 0));
    $due = null;
    $start = $r['disburse_date'] ?? '';
    if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && $weeks > 0) {
        $ts = strtotime($start . ' +' . $weeks . ' weeks');
        if ($ts) $due = date('Y-m-d', $ts);
    }

    $daysLeft = null;
    if ($due !== null) {
        $daysLeft = (int) floor((strtotime($due) - strtotime(date('Y-m-d'))) / 86400);
    }

    $settled = $total > 0 && $balance === 0;
    $state = 'pending';
    if ($settled)                                   $state = 'settled';
    elseif ($due === null)                          $state = 'no_due_date';
    elseif ($daysLeft < 0)                          $state = 'overdue';
    elseif ($daysLeft <= 7)                         $state = 'due_soon';
    else                                            $state = 'on_track';

    return [
        'principal'    => round($principal / 100, 2),
        'total_due'    => round($total / 100, 2),
        'paid'         => round($paid / 100, 2),
        'balance'      => round($balance / 100, 2),
        'rate'         => $rate,
        'weeks'        => $weeks,
        'due_date'     => $due,
        'days_left'    => $daysLeft,
        'days_overdue' => ($daysLeft !== null && $daysLeft < 0) ? -$daysLeft : 0,
        'payments'     => count($payments),
        'settled'      => $settled,
        'state'        => $state,
        'percent_paid' => $total > 0 ? (int) round($paid * 100 / $total) : 0,
    ];
}

// ── ROUTER ────────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── PING / HEALTH CHECK ──────────────────────────────────────────────────────
if ($action === 'ping') {
    $records = loadRecords();
    jsonOut([
        'ok'      => true,
        'message' => 'Avesta JSON API is running',
        'records' => count($records),
        'php'     => phpversion(),
        'docs_writable'    => is_writable(DOCS_DIR),
        'recovery_mail_available' => function_exists('mail'),
        'post_max_size'    => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'memory_limit'     => ini_get('memory_limit'),
        'user_ini_active'  => file_exists(__DIR__ . '/.user.ini'),
        'ts'      => date('c'),
    ]);
}

// ── SUBMIT NEW APPLICATION (from index.html) ─────────────────────────────────
if ($method === 'POST' && ($action === 'submit' || $action === '')) {
    // Every page is behind the gate, so an application can only come from a
    // signed-in visitor. Leaving this open would let anyone fill the store
    // with junk records and documents.
    requireRole(['admin', 'staff', 'borrower']);
    $raw = file_get_contents('php://input');

    // Detect the classic "request was too big" failure mode: Content-Length
    // header shows the real size the browser sent, but PHP discarded the
    // body because it exceeded post_max_size. In that case $raw is empty
    // or much shorter than what was actually sent.
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 0 && strlen($raw) === 0 && empty($_POST)) {
        $postMaxBytes = iniSizeToBytes(ini_get('post_max_size'));
        jsonOut([
            'ok' => false,
            'error' => 'Upload too large for server limit. Sent ' .
                round($contentLength / 1024 / 1024, 1) . 'MB but server allows ' .
                round($postMaxBytes / 1024 / 1024, 1) . 'MB (post_max_size). ' .
                'Try fewer/smaller documents, or ask your host to raise post_max_size.',
            'sent_mb' => round($contentLength / 1024 / 1024, 1),
            'limit_mb' => round($postMaxBytes / 1024 / 1024, 1),
        ], 413);
    }

    $payload = null;
    if (!empty($_POST['payload'])) {
        $payload = json_decode($_POST['payload'], true);
    } elseif ($raw) {
        $payload = json_decode($raw, true);
    }

    if (!is_array($payload)) {
        $jsonErr = json_last_error_msg();
        jsonOut(['ok' => false, 'error' => 'Invalid or missing JSON payload (' . $jsonErr . '). Received ' . strlen($raw) . ' bytes.'], 400);
    }

    $records = loadRecords();

    if (count($records) >= MAX_RECORDS) {
        jsonOut(['ok' => false, 'error' => 'Record limit reached. Contact admin.'], 507);
    }

    $payload = sanitize($payload);

    // Server-authoritative loan maths. Never trust rate/total figures supplied
    // by browser JavaScript: a borrower can edit client-side fields.
    $loanAmount = (float) preg_replace('/[^0-9.\-]/', '', (string) ($payload['loan_amt'] ?? $payload['loan_amount'] ?? 0));
    $durationText = trim((string) ($payload['sel_dur'] ?? $payload['loan_duration'] ?? ''));
    if ($loanAmount <= 0 || $loanAmount > 10000000) {
        jsonOut(['ok' => false, 'error' => 'Enter a valid loan amount greater than zero.'], 422);
    }
    if (!preg_match('/^(\d+)\s+Weeks?/i', $durationText, $m)) {
        jsonOut(['ok' => false, 'error' => 'Select a valid loan duration before submitting.'], 422);
    }
    $weeks = (int) $m[1];
    if ($weeks < 1) {
        jsonOut(['ok' => false, 'error' => 'Loan duration is outside the supported range.'], 422);
    }
    $canonicalRate = $weeks <= 4 ? [1 => 15, 2 => 20, 3 => 25, 4 => 30][$weeks] : 30 + (($weeks - 4) * 5);
    $canonicalInterest = round($loanAmount * $canonicalRate / 100, 2);
    $canonicalTotal = round($loanAmount + $canonicalInterest, 2);
    $payload['loan_amt'] = round($loanAmount, 2);
    $payload['loan_amount'] = round($loanAmount, 2);
    $payload['int_rate'] = $canonicalRate;
    $payload['interest_rate'] = $canonicalRate;
    $payload['total_int'] = $canonicalInterest;
    $payload['total_interest'] = $canonicalInterest;
    $payload['total_repay'] = $canonicalTotal;
    $payload['total_repayment'] = $canonicalTotal;
    $payload['_loan_math_source'] = 'server';

    if (empty($payload['_key'])) {
        $payload['_key'] = 'avesta_submission_' . str_replace(
            ['.', ' ', ':', '-', '/'], '_',
            $payload['submitted_at'] ?? (string) (microtime(true) * 1000)
        );
    }
    if (empty($payload['status'])) {
        $payload['status'] = 'New Application';
    }
    // Stamp the submitting account so a borrower can retrieve their own record
    $submitter = av_user();
    if ($submitter !== null && empty($payload['_owner'])) {
        $payload['_owner']    = $submitter['id'];
        $payload['_owner_by'] = $submitter['username'];
    }

    // Extract base64 documents to real files, replace with URLs
    try {
        $payload = extractDocuments($payload, $payload['_key']);
    } catch (RuntimeException $e) {
        jsonOut(['ok' => false, 'error' => $e->getMessage()], $e->getCode() === 422 ? 422 : 500);
    }

    $records[] = $payload;

    if (saveRecords($records)) {
        av_audit('application.submitted', $payload['_key']);
        jsonOut(['ok' => true, 'message' => 'Saved', '_key' => $payload['_key']]);
    } else {
        jsonOut(['ok' => false, 'error' => 'Failed to write to disk — check folder permissions'], 500);
    }
}

// ── GET ALL RECORDS (admin portal + staff tab) ───────────────────────────────
if ($action === 'getRecords' || $action === 'list') {
    $me = requireRole(['admin', 'staff', 'borrower']);
    $records = loadRecords();

    // A borrower sees only their own application, never anyone else's
    if ($me['role'] === 'borrower') {
        $mine = array_values(array_filter(loadRecords(), function ($r) use ($me) {
            return ($r['_owner'] ?? '') === $me['id'];
        }));
        foreach ($mine as &$__m) { $__m['_summary'] = loanSummary($__m); }
        unset($__m);
        jsonOut(['records' => $mine, 'total' => count($mine)]);
    }
    av_audit('records.list');
    // Attach the loan position so the portal never recomputes it in JavaScript
    // and risk the two disagreeing about what a borrower owes.
    foreach ($records as &$__r) { $__r['_summary'] = loanSummary($__r); }
    unset($__r);
    jsonOut(['records' => $records, 'total' => count($records)]);
}

// ── UPDATE STATUS ─────────────────────────────────────────────────────────────
if ($action === 'updateStatus') {
    requireRole(['admin', 'staff']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $key    = $_POST['key'] ?? '';
    $status = $_POST['status'] ?? '';

    if (!$key || !$status) {
        jsonOut(['ok' => false, 'msg' => 'Missing key or status'], 400);
    }

    $records = loadRecords();
    $found = false;
    foreach ($records as &$r) {
        if (($r['_key'] ?? '') === $key) {
            av_audit('record.status_changed', $key,
                     ['from' => $r['status'] ?? null, 'to' => $status]);
            $r['status'] = $status;
            $found = true;
            break;
        }
    }
    unset($r);

    if (!$found) {
        jsonOut(['ok' => false, 'msg' => 'Record not found'], 404);
    }

    if (saveRecords($records)) {
        jsonOut(['ok' => true]);
    } else {
        jsonOut(['ok' => false, 'error' => 'Failed to save'], 500);
    }
}

// ── DELETE RECORD ──────────────────────────────────────────────────────────────
if ($action === 'deleteRecord') {
    requireRole(['admin']);   // deletion is irreversible — admins only
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $key = $_POST['key'] ?? '';

    if (!$key) {
        jsonOut(['ok' => false, 'msg' => 'Missing key'], 400);
    }

    $records = loadRecords();
    $before  = count($records);
    $records = array_values(array_filter($records, function($r) use ($key) {
        return ($r['_key'] ?? '') !== $key;
    }));

    if (count($records) === $before) {
        jsonOut(['ok' => false, 'msg' => 'Record not found'], 404);
    }

    av_audit('record.deleted', $key);
    deleteDocumentsFolder($key);

    if (saveRecords($records)) {
        jsonOut(['ok' => true]);
    } else {
        jsonOut(['ok' => false, 'error' => 'Failed to save'], 500);
    }
}

// ── UPDATE FULL RECORD (used by Edit form in staff/admin) ────────────────────
if ($action === 'updateRecord') {
    requireRole(['admin', 'staff']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $raw = file_get_contents('php://input');
    $payload = !empty($_POST['payload']) ? json_decode($_POST['payload'], true) : json_decode($raw, true);

    if (!is_array($payload) || empty($payload['_key'])) {
        jsonOut(['ok' => false, 'error' => 'Invalid payload or missing _key'], 400);
    }

    // If new documents were attached during edit, extract them too
    try {
        $payload = extractDocuments($payload, $payload['_key']);
    } catch (RuntimeException $e) {
        jsonOut(['ok' => false, 'error' => $e->getMessage()], $e->getCode() === 422 ? 422 : 500);
    }

    $records = loadRecords();
    $found = false;
    foreach ($records as &$r) {
        if (($r['_key'] ?? '') === $payload['_key']) {
            av_audit('record.edited', $payload['_key'],
                     ['fields' => array_keys($payload)]);
            $r = array_merge($r, sanitize($payload));
            $found = true;
            break;
        }
    }
    unset($r);

    if (!$found) {
        jsonOut(['ok' => false, 'msg' => 'Record not found'], 404);
    }

    if (saveRecords($records)) {
        jsonOut(['ok' => true]);
    } else {
        jsonOut(['ok' => false, 'error' => 'Failed to save'], 500);
    }
}

// ── EXCHANGE RATES (currency converter on index.html + admin portal) ─────────
if ($action === 'rates') {
    $now   = time();
    $force = isset($_GET['refresh']);
    $cache = file_exists(RATES_CACHE)
        ? (json_decode(@file_get_contents(RATES_CACHE), true) ?: [])
        : [];

    $marketFresh = !empty($cache['market']['table'])
        && ($now - (int) ($cache['market']['at'] ?? 0)) < RATES_TTL;
    $bozFresh = !empty($cache['boz'])
        && ($now - (int) ($cache['boz']['at'] ?? 0)) < BOZ_TTL;

    if ($force || !$marketFresh) {
        $m = fxFetchMarket();
        if ($m !== null) { $m['at'] = $now; $cache['market'] = $m; }
    }
    if ($force || !$bozFresh) {
        $b = fxFetchBoz();
        if ($b !== null) { $b['at'] = $now; $cache['boz'] = $b; }
    }

    // If the market feed is unreachable but we hold an official BoZ rate,
    // serve a minimal kwacha/dollar table rather than nothing. ZMW<->USD is
    // the conversion that actually matters here, and it needs only that rate.
    if (empty($cache['market']['table']) && !empty($cache['boz']['zmw_per_usd'])) {
        $cache['market'] = [
            'table'  => ['USD' => 1.0, 'ZMW' => (float) $cache['boz']['zmw_per_usd']],
            'as_of'  => $cache['boz']['as_of'] ?? date('Y-m-d'),
            'source' => 'Bank of Zambia (kwacha only — market feed unreachable)',
            'at'     => (int) ($cache['boz']['at'] ?? $now),
        ];
    }

    // Nothing fresh and nothing cached — say so plainly rather than guessing
    if (empty($cache['market']['table'])) {
        jsonOut([
            'ok'    => false,
            'error' => 'Could not reach any rate source. Check that this server allows outbound HTTPS connections.',
        ], 502);
    }

    @file_put_contents(RATES_CACHE, json_encode($cache));

    $market = $cache['market'];
    $boz    = $cache['boz'] ?? null;
    $mAt    = (int) ($market['at'] ?? 0);

    jsonOut([
        'ok'         => true,
        'base'       => 'USD',
        'table'      => $market['table'],
        'as_of'      => $market['as_of'] ?? null,
        'feed'       => $market['source'] ?? null,
        'fetched_at' => $mAt,
        'stale'      => ($now - $mAt) > RATES_TTL,
        'boz'        => $boz ? [
            'official'    => true,
            'zmw_per_usd' => $boz['zmw_per_usd'],
            'buy'         => $boz['buy'],
            'sell'        => $boz['sell'],
            'as_of'       => $boz['as_of'],
            'source'      => $boz['source'],
            'stale'       => ($now - (int) ($boz['at'] ?? 0)) > BOZ_TTL,
        ] : [
            'official' => false,
            'note'     => 'BoZ endpoint not configured — set BOZ_JSON in api.php to use the official rate.',
        ],
    ]);
}

// ── SET OFFICIAL BANK OF ZAMBIA RATE ─────────────────────────────────────────
// boz.zm is a JavaScript app with no readable API and no static file (the
// DAILY_RATES.xlsx link returns the app shell, not a spreadsheet), so the
// official rate has to be pushed in rather than pulled. Three ways to do it:
//
//   1. By hand from the admin portal — Currency Rates page, takes 20 seconds.
//   2. From a scheduled browser-automation job (TinyFish, Playwright, etc.)
//      that reads boz.zm once a day and POSTs the result here.
//   3. curl, from your own cron:
//        curl -X POST "https://yourdomain/api.php?action=setBoz" \
//             -H "Content-Type: application/json" \
//             -d '{"token":"YOUR_TOKEN","buy":19.5746,"sell":19.6246,"as_of":"2026-09-16"}'
//
// Set BOZ_PUSH_TOKEN below to something long and random before using this.
// While it is empty the endpoint refuses every request.
if ($action === 'setBoz') {
    // Two ways in, and only one of them needs a token:
    //   a signed-in admin or staff member — the session is the proof, or
    //   an unattended job (cron, browser automation) holding BOZ_PUSH_TOKEN.
    // Requiring the token from a signed-in user would mean the admin panel
    // could never save a rate on a fresh install.
    $bodyPeek = json_decode((string) file_get_contents('php://input'), true);
    $hasToken = BOZ_PUSH_TOKEN !== '' && is_array($bodyPeek)
                && hash_equals(BOZ_PUSH_TOKEN, (string) ($bodyPeek['token'] ?? ''));
    if (!$hasToken) {
        requireRole(['admin', 'staff']);
    }

    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = $_POST;

    $token = (string) ($body['token'] ?? $_GET['token'] ?? '');
    // hash_equals compares in constant time so the token can't be guessed
    // one character at a time by timing the responses
    if (!hash_equals(BOZ_PUSH_TOKEN, $token)) {
        jsonOut(['ok' => false, 'error' => 'Not authorised'], 401);
    }

    $buy  = isset($body['buy'])  ? (float) $body['buy']  : 0;
    $sell = isset($body['sell']) ? (float) $body['sell'] : 0;
    $mid  = isset($body['mid'])  ? (float) $body['mid']  : 0;
    if ($mid <= 0 && $buy > 0 && $sell > 0) $mid = ($buy + $sell) / 2;
    if ($mid <= 0 && $buy > 0)  $mid = $buy;
    if ($mid <= 0 && $sell > 0) $mid = $sell;

    // Sanity band. A fat-fingered 1.96 or 196 would silently misprice every
    // quote on the site, so refuse anything outside plausible kwacha territory.
    if ($mid < 5 || $mid > 200) {
        jsonOut(['ok' => false,
                 'error' => 'Rate ' . $mid . ' is outside the plausible range (5-200 kwacha per dollar). Not saved.'], 422);
    }

    $asOf = (string) ($body['as_of'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) $asOf = date('Y-m-d');

    $cache = file_exists(RATES_CACHE)
        ? (json_decode(@file_get_contents(RATES_CACHE), true) ?: []) : [];

    // The converter needs the market table for non-kwacha pairs; fetch it if
    // this is the first thing to ever populate the cache.
    if (empty($cache['market']['table'])) {
        $m = fxFetchMarket();
        if ($m !== null) { $m['at'] = time(); $cache['market'] = $m; }
    }

    $cache['boz'] = [
        'zmw_per_usd' => $mid,
        'buy'         => $buy > 0 ? $buy : null,
        'sell'        => $sell > 0 ? $sell : null,
        'as_of'       => $asOf,
        'source'      => (string) ($body['source'] ?? 'Bank of Zambia'),
        'at'          => time(),
    ];

    if (@file_put_contents(RATES_CACHE, json_encode($cache)) === false) {
        jsonOut(['ok' => false, 'error' => 'Could not write rates_cache.json — check folder permissions'], 500);
    }

    jsonOut(['ok' => true, 'saved' => $cache['boz'],
             'message' => 'Official rate saved: K' . number_format($mid, 4) . ' per USD (' . $asOf . ')']);
}

// ── WHO AM I ─────────────────────────────────────────────────────────────────
if ($action === 'me') {
    $u = av_user();
    jsonOut($u ? ['ok' => true, 'user' => $u] : ['ok' => false, 'auth' => 'required'], $u ? 200 : 401);
}

// ── USER MANAGEMENT (admins only) ────────────────────────────────────────────
if ($action === 'users') {
    requireRole(['admin']);
    $out = [];
    foreach (av_load_users() as $u) {
        unset($u['pass_hash'], $u['auth_version']);          // never leaves the server
        $out[] = $u;
    }
    jsonOut(['ok' => true, 'users' => $out, 'total' => count($out)]);
}

if ($action === 'createUser') {
    requireRole(['admin']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $b = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $r = av_create_user((string) ($b['username'] ?? ''), (string) ($b['password'] ?? ''),
                        (string) ($b['name'] ?? ''), (string) ($b['role'] ?? 'staff'), (string) ($b['email'] ?? ''));
    if ($r['ok']) av_audit('user.created', $r['user']['username'], ['role' => $r['user']['role']]);
    jsonOut($r, $r['ok'] ? 200 : 400);
}

if ($action === 'setUserActive') {
    $me = requireRole(['admin']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $b  = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $id = (string) ($b['id'] ?? '');
    $on = !empty($b['active']);

    av_require_manage_target($me, $id);

    if ($id === $me['id'] && !$on) {
        jsonOut(['ok' => false, 'error' => 'You cannot disable your own account.'], 400);
    }

    $users = av_load_users();
    $hit = false;
    foreach ($users as &$u) {
        if (($u['id'] ?? '') === $id) { $u['active'] = $on; $hit = true; break; }
    }
    unset($u);
    if (!$hit) jsonOut(['ok' => false, 'error' => 'User not found'], 404);

    // Never leave the system with no way in
    $liveAdmins = 0;
    foreach ($users as $u) {
        if (in_array(($u['role'] ?? ''), ['admin', 'super_admin'], true) && !empty($u['active'])) $liveAdmins++;
    }
    if ($liveAdmins === 0) {
        jsonOut(['ok' => false, 'error' => 'That would leave no active administrator.'], 400);
    }

    av_save_users($users);
    av_audit($on ? 'user.enabled' : 'user.disabled', $id);
    jsonOut(['ok' => true]);
}

if ($action === 'changePassword') {
    $me = requireRole(['admin', 'staff', 'borrower']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $b  = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;

    $target = (string) ($b['id'] ?? $me['id']);
    if ($target !== $me['id'] && !in_array($me['role'], ['admin', 'super_admin'], true)) {
        jsonOut(['ok' => false, 'error' => 'You can only change your own password.'], 403);
    }

    // Changing your own password requires proving you know the current one
    if ($target === $me['id']) {
        $row = av_find_user($me['username']);
        if (!$row || !password_verify((string) ($b['current'] ?? ''), $row['pass_hash'])) {
            jsonOut(['ok' => false, 'error' => 'Your current password is not correct.'], 400);
        }
    }

    $r = av_set_password($target, (string) ($b['password'] ?? ''));
    if ($r['ok']) {
        av_audit('user.password_changed', $target);
        // A temporary password has now been replaced by the person's own
        av_clear_must_change($target);
    }
    jsonOut($r, $r['ok'] ? 200 : 400);
}

// ── AUDIT LOG (admins only) ──────────────────────────────────────────────────
if ($action === 'auditLog') {
    requireRole(['admin']);
    $limit  = min(1000, max(1, (int) ($_GET['limit'] ?? 200)));
    $rows = av_audit_read($limit, (string) ($_GET['user'] ?? ''), (string) ($_GET['event'] ?? ''));
    jsonOut(['ok' => true, 'entries' => $rows, 'total' => count($rows)]);
}

// ── RECORD A REPAYMENT ───────────────────────────────────────────────────────
if ($action === 'addPayment') {
    $me = requireRole(['admin', 'staff']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);

    $b   = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $key = trim((string) ($b['key'] ?? ''));
    $amt = (float) preg_replace('/[^0-9.\-]/', '', (string) ($b['amount'] ?? 0));

    if ($key === '')  jsonOut(['ok' => false, 'error' => 'Which loan? No record key given.'], 400);
    if ($amt <= 0)    jsonOut(['ok' => false, 'error' => 'Enter an amount greater than zero.'], 400);
    if ($amt > 10000000) jsonOut(['ok' => false, 'error' => 'That amount looks wrong. Check it and try again.'], 422);

    $date = (string) ($b['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    // A payment dated in the future is almost always a typing slip
    if (strtotime($date) > strtotime('+1 day')) {
        jsonOut(['ok' => false, 'error' => 'That date is in the future. Payments are recorded when received.'], 422);
    }

    $records = loadRecords();
    $found = false;
    $summary = null;

    foreach ($records as &$r) {
        if (($r['_key'] ?? '') !== $key) continue;
        $found = true;

        if (!isset($r['_payments']) || !is_array($r['_payments'])) $r['_payments'] = [];

        $before = loanSummary($r);
        // Warn rather than refuse: a small overpayment is a real thing that
        // happens, and refusing it would leave staff unable to record reality.
        $overpay = $amt > $before['balance'] + 0.005;

        $r['_payments'][] = [
            'id'        => 'p_' . bin2hex(random_bytes(6)),
            'amount'    => round($amt, 2),
            'date'      => $date,
            'method'    => substr(trim((string) ($b['method'] ?? 'Cash')), 0, 40),
            'note'      => substr(trim((string) ($b['note'] ?? '')), 0, 200),
            'taken_by'  => $me['username'],
            'recorded'  => date('c'),
        ];

        $summary = loanSummary($r);

        // Settle the loan automatically once nothing is left to pay
        if ($summary['settled'] && ($r['status'] ?? '') !== 'Repaid') {
            av_audit('record.status_changed', $key,
                     ['from' => $r['status'] ?? null, 'to' => 'Repaid', 'reason' => 'balance cleared']);
            $r['status'] = 'Repaid';
        }

        av_audit('payment.recorded', $key, [
            'amount'  => round($amt, 2),
            'date'    => $date,
            'method'  => $r['_payments'][count($r['_payments']) - 1]['method'],
            'balance' => $summary['balance'],
        ]);
        break;
    }
    unset($r);

    if (!$found) jsonOut(['ok' => false, 'error' => 'That loan was not found.'], 404);
    if (!saveRecords($records)) {
        jsonOut(['ok' => false, 'error' => 'Could not save the payment — check folder permissions.'], 500);
    }

    jsonOut(['ok' => true, 'summary' => $summary,
             'overpaid' => $overpay ?? false,
             'message' => 'Payment of K' . number_format($amt, 2) . ' recorded. Balance K'
                        . number_format($summary['balance'], 2) . '.']);
}

// ── REMOVE A REPAYMENT (admins only — it rewrites the borrower's balance) ────
if ($action === 'deletePayment') {
    requireRole(['admin']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);

    $b   = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $key = trim((string) ($b['key'] ?? ''));
    $pid = trim((string) ($b['payment_id'] ?? ''));
    if ($key === '' || $pid === '') jsonOut(['ok' => false, 'error' => 'Missing loan or payment id.'], 400);

    $records = loadRecords();
    $found = false; $removed = null; $summary = null;

    foreach ($records as &$r) {
        if (($r['_key'] ?? '') !== $key) continue;
        $found = true;
        $keep = [];
        foreach (($r['_payments'] ?? []) as $p) {
            if (($p['id'] ?? '') === $pid) { $removed = $p; continue; }
            $keep[] = $p;
        }
        $r['_payments'] = $keep;
        $summary = loanSummary($r);
        if (!$summary['settled'] && ($r['status'] ?? '') === 'Repaid') {
            $r['status'] = 'Approved';    // no longer settled, so put it back
        }
        break;
    }
    unset($r);

    if (!$found)   jsonOut(['ok' => false, 'error' => 'That loan was not found.'], 404);
    if (!$removed) jsonOut(['ok' => false, 'error' => 'That payment was not found.'], 404);

    av_audit('payment.deleted', $key, ['amount' => $removed['amount'] ?? null,
                                       'date' => $removed['date'] ?? null,
                                       'balance_now' => $summary['balance']]);
    if (!saveRecords($records)) jsonOut(['ok' => false, 'error' => 'Could not save the change.'], 500);
    jsonOut(['ok' => true, 'summary' => $summary, 'message' => 'Payment removed.']);
}

// ── PORTFOLIO AT A GLANCE ────────────────────────────────────────────────────
if ($action === 'loanBook') {
    requireRole(['admin', 'staff']);
    $out = ['lent' => 0.0, 'due' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0,
            'overdue_amount' => 0.0, 'overdue_count' => 0, 'due_this_week' => 0.0,
            'due_this_week_count' => 0, 'settled' => 0, 'active' => 0, 'loans' => 0,
            'overpaid' => 0.0, 'overpaid_count' => 0];

    foreach (loadRecords() as $r) {
        $s = loanSummary($r);
        if ($s['total_due'] <= 0) continue;          // application with no figures yet
        $out['loans']++;
        $out['lent']      += $s['principal'];
        $out['due']       += $s['total_due'];
        $out['outstanding'] += $s['balance'];

        // Only money that settles a debt counts as collected. Counting the
        // full figure would let one mistyped payment push the collection
        // rate past 100% and turn outstanding negative — a number someone
        // would reasonably act on.
        $applied = min($s['paid'], $s['total_due']);
        $out['collected'] += $applied;
        if ($s['paid'] > $s['total_due']) {
            $out['overpaid'] += $s['paid'] - $s['total_due'];
            $out['overpaid_count']++;
        }
        if ($s['settled']) { $out['settled']++; continue; }
        $out['active']++;
        if ($s['state'] === 'overdue') {
            $out['overdue_count']++;
            $out['overdue_amount'] += $s['balance'];
        } elseif ($s['state'] === 'due_soon') {
            $out['due_this_week_count']++;
            $out['due_this_week'] += $s['balance'];
        }
    }
    foreach (['lent','due','collected','outstanding','overdue_amount','due_this_week','overpaid'] as $k) {
        $out[$k] = round($out[$k], 2);
    }
    $out['collection_rate'] = $out['due'] > 0 ? (int) round($out['collected'] * 100 / $out['due']) : 0;
    jsonOut(['ok' => true, 'book' => $out]);
}

// The mbstring extension is not on every shared host, and calling mb_strlen()
// without it is a fatal error, not a warning — the form would simply return a
// blank page. These fall back to the byte functions when it is missing.
function avLen($str) {
    return function_exists('mb_strlen') ? mb_strlen((string) $str, 'UTF-8') : strlen((string) $str);
}
function avCut($str, $len) {
    return function_exists('mb_substr')
        ? mb_substr((string) $str, 0, $len, 'UTF-8')
        : substr((string) $str, 0, $len);
}

// ── IT ENQUIRY (public — the consulting office has no login) ─────────────────
// Kept deliberately separate from loan applications: different office,
// different data, and nothing here is personal data under the meaning of the
// Act beyond a name and a phone number someone volunteered.
define('ENQUIRY_FILE', __DIR__ . '/enquiries.json');
define('ENQUIRY_THROTTLE', __DIR__ . '/enquiry_rate.json');
define('ITHELP_THROTTLE', __DIR__ . '/ithelp_rate.json');
define('ITHELP_LIMIT', 200);

if ($action === 'submitEnquiry') {
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);

    $b = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;

    // Honeypot: a hidden field no person ever sees. Answer as though it worked,
    // so the bot has nothing to learn and does not come back to try again.
    if (trim((string) ($b['website'] ?? '')) !== '') {
        jsonOut(['ok' => true, 'message' => 'Enquiry received.']);
    }

    $name   = trim((string) ($b['name'] ?? ''));
    $phone  = trim((string) ($b['phone'] ?? ''));
    $detail = trim((string) ($b['detail'] ?? ''));

    if (avLen($name) < 2)    jsonOut(['ok' => false, 'error' => 'Please give us a name to reply to.'], 400);
    if (avLen($phone) < 6)   jsonOut(['ok' => false, 'error' => 'We need a phone number to reach you on.'], 400);
    if (avLen($detail) < 10) jsonOut(['ok' => false, 'error' => 'Tell us a little more about what you need.'], 400);

    // Rate limit by IP. Public forms get scraped and stuffed; without this the
    // file fills with junk and real enquiries get buried.
    $ip = av_client_ip();
    $now = time();
    $rate = is_file(ENQUIRY_THROTTLE)
        ? (json_decode((string) @file_get_contents(ENQUIRY_THROTTLE), true) ?: []) : [];
    foreach ($rate as $k => $v) {
        if ($now - (int) ($v['last'] ?? 0) > 3600) unset($rate[$k]);
    }
    $mine = $rate[$ip] ?? ['count' => 0, 'last' => 0];
    if ($mine['count'] >= 5 && $now - (int) $mine['last'] < 3600) {
        jsonOut(['ok' => false,
                 'error' => 'That is several enquiries in a short time. Please call +260 769 974 200.'], 429);
    }
    $rate[$ip] = ['count' => (int) $mine['count'] + 1, 'last' => $now];
    @file_put_contents(ENQUIRY_THROTTLE, json_encode($rate), LOCK_EX);

    $rows = avStoreRead(ENQUIRY_FILE);
    if (!is_array($rows)) $rows = [];
    if (count($rows) >= 5000) {
        jsonOut(['ok' => false, 'error' => 'Could not save the enquiry. Please call us instead.'], 507);
    }

    $rows[] = [
        'id'      => 'e_' . bin2hex(random_bytes(6)),
        'name'    => avCut($name, 80),
        'phone'   => avCut($phone, 30),
        'service' => avCut(trim((string) ($b['service'] ?? '')), 60),
        'detail'  => avCut($detail, 1200),
        'status'  => 'New',
        'at'      => date('c'),
        'ip'      => $ip,
    ];

    if (!avStoreWrite(ENQUIRY_FILE, $rows)) {
        jsonOut(['ok' => false, 'error' => 'Could not save the enquiry. Please call us instead.'], 500);
    }

    av_audit('enquiry.received', $rows[count($rows) - 1]['id'],
             ['service' => $rows[count($rows) - 1]['service']]);
    jsonOut(['ok' => true, 'message' => 'Enquiry received.']);
}

// ── READ / UPDATE IT ENQUIRIES (staff) ───────────────────────────────────────
if ($action === 'enquiries') {
    requireRole(['admin', 'staff']);
    $rows = avStoreRead(ENQUIRY_FILE);
    if (!is_array($rows)) $rows = [];
    $rows = array_reverse($rows);                 // newest first
    $new = 0;
    foreach ($rows as $r) if (($r['status'] ?? '') === 'New') $new++;
    jsonOut(['ok' => true, 'enquiries' => $rows, 'total' => count($rows), 'new' => $new]);
}

if ($action === 'updateEnquiry') {
    requireRole(['admin', 'staff']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $b  = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $id = trim((string) ($b['id'] ?? ''));
    $st = trim((string) ($b['status'] ?? ''));
    if ($id === '' || $st === '') jsonOut(['ok' => false, 'error' => 'Missing id or status'], 400);

    $rows = avStoreRead(ENQUIRY_FILE);
    if (!is_array($rows)) $rows = [];
    $hit = false;
    foreach ($rows as &$r) {
        if (($r['id'] ?? '') === $id) {
            av_audit('enquiry.status_changed', $id, ['from' => $r['status'] ?? null, 'to' => $st]);
            $r['status'] = avCut($st, 40);
            $hit = true;
            break;
        }
    }
    unset($r);
    if (!$hit) jsonOut(['ok' => false, 'error' => 'Enquiry not found'], 404);
    avStoreWrite(ENQUIRY_FILE, $rows);
    jsonOut(['ok' => true]);
}

// ── IT TROUBLESHOOTING ASSISTANT ─────────────────────────────────────────────
// Answers come from it-help.php: written by hand, safe by construction, free
// to run and available with no outbound internet.
//
// OPTIONAL MODEL: set IT_HELP_MODEL to an endpoint that takes {question} and
// returns {answer} if you later want unmatched questions handled by a language
// model. The curated answers still go first — the model only sees what they
// could not match. Leave it empty and unmatched questions are handed to a
// person, which is the honest answer and costs nothing.
define('IT_HELP_MODEL', getenv('IT_HELP_AI_ENDPOINT') ?: '');
define('IT_HELP_MODEL_TOKEN', getenv('IT_HELP_AI_TOKEN') ?: '');
define('IT_HELP_LOG', __DIR__ . '/it_questions.json');


/** Build a conservative diagnostic envelope before any optional AI call. */
function itHelpDiagnostic(string $q, ?array $hit = null): array {
    $text = strtolower($q);
    $codes = [];
    if (preg_match_all('/\\b(?:0x[0-9a-f]{4,16}|[a-z]{1,5}[-_ ]?\\d{2,6}|\\d{3,5})\\b/i', $q, $m)) {
        $codes = array_values(array_unique(array_slice($m[0], 0, 5)));
    }
    $families = [
        'printer' => ['printer','print','scanner','toner','drum','paper jam'],
        'network' => ['wifi','wi-fi','internet','network','router','dns','dhcp','ping'],
        'windows' => ['windows','blue screen','bsod','bitlocker','update','registry'],
        'email'   => ['outlook','email','mail','smtp','imap','exchange'],
        'storage' => ['disk','drive','ssd','hard drive','files','backup','restore'],
        'security'=> ['virus','malware','ransomware','hacked','phishing','scam'],
        'power'   => ['power','boot','start','battery','charger','black screen'],
    ];
    $category = 'general';
    foreach ($families as $name => $words) {
        foreach ($words as $w) if (strpos($text, $w) !== false) { $category = $name; break 2; }
    }
    $high = ['ransomware','encrypted','burning','smoke','sparks','electric shock','data loss','diskpart','format','factory reset'];
    $medium = ['registry','bios','firmware','bitlocker','administrator','admin password','delete system','partition'];
    $risk = 'low';
    foreach ($high as $w) if (strpos($text, $w) !== false) { $risk = 'high'; break; }
    if ($risk === 'low') foreach ($medium as $w) if (strpos($text, $w) !== false) { $risk = 'medium'; break; }
    return [
        'category' => $category,
        'error_codes' => $codes,
        'risk' => $risk,
        'confidence' => $hit ? 'high' : 'low',
        'source_mode' => $hit ? 'curated' : (IT_HELP_MODEL !== '' ? 'ai_fallback_available' : 'human_escalation'),
    ];
}

/** Optional server-side AI/retrieval fallback. Secrets never reach the browser. */
function itHelpModelFallback(string $q, array $diag): ?array {
    if (IT_HELP_MODEL === '' || !function_exists('curl_init')) return null;
    $payload = json_encode([
        'question' => $q,
        'diagnostic' => $diag,
        'requirements' => [
            'Use official vendor documentation first when web retrieval is available.',
            'Cite source titles and URLs in a sources array.',
            'Do not recommend destructive actions without a clear warning and human escalation.',
            'Return JSON with answer, steps, stop, sources, confidence.'
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init(IT_HELP_MODEL);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (IT_HELP_MODEL_TOKEN !== '') $headers[] = 'Authorization: Bearer ' . IT_HELP_MODEL_TOKEN;
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>12, CURLOPT_FOLLOWLOCATION=>false]);
    $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) return null;
    $d = json_decode($raw, true);
    if (!is_array($d) || trim((string)($d['answer'] ?? '')) === '') return null;
    $sources = [];
    foreach (($d['sources'] ?? []) as $src) {
        if (!is_array($src)) continue;
        $url = trim((string)($src['url'] ?? ''));
        if ($url !== '' && !preg_match('#^https://#i', $url)) continue;
        $sources[] = ['title'=>avCut(trim((string)($src['title'] ?? 'Source')), 120), 'url'=>avCut($url, 500)];
        if (count($sources) >= 5) break;
    }
    return [
        'answer'=>avCut(trim((string)$d['answer']), 4000),
        'steps'=>array_slice(array_values(array_filter(array_map('strval', $d['steps'] ?? []))), 0, 8),
        'stop'=>avCut(trim((string)($d['stop'] ?? '')), 1000),
        'sources'=>$sources,
        'confidence'=>in_array(($d['confidence'] ?? ''), ['low','medium','high'], true) ? $d['confidence'] : 'low'
    ];
}

if ($action === 'itHelp') {
    // Public: it answers IT questions and holds nothing personal. It is also
    // the best reason for a stranger to visit the site at all.
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);

    require_once __DIR__ . '/it-help.php';

    $b = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $q = trim((string) ($b['question'] ?? ''));

    if ($q === '') {
        jsonOut(['ok' => true, 'matched' => false, 'topics' => itHelpTopics(),
                 'answer' => 'Tell me what is happening and I will see what it usually is.']);
    }
    // avLen/avCut, not mb_* directly: mbstring is missing on plenty of
    // shared hosts, and an undefined function is a 500 with no body.
    if (avLen($q) > 500) $q = avCut($q, 500);

    // Rate limit by address. The assistant is open to the public now, which is
    // the point of it — but an open endpoint that writes every question to a
    // file is an invitation to fill that file with junk and bury the real
    // questions. Generous enough that nobody troubleshooting notices it.
    $ip   = av_client_ip();
    $now  = time();
    $rate = is_file(ITHELP_THROTTLE)
        ? (json_decode((string) @file_get_contents(ITHELP_THROTTLE), true) ?: []) : [];
    foreach ($rate as $k => $v) {
        if (!is_array($v) || ($v['at'] ?? 0) < $now - 900) unset($rate[$k]);
    }
    $key  = 'ip:' . substr(hash('sha256', $ip), 0, 16);
    $seen = $rate[$key] ?? ['n' => 0, 'at' => $now];
    // 200 in fifteen minutes. An office, a school or an internet cafe shares one
    // public address here, so a low limit would shut out a whole building; this
    // stops a script filling the file without ever troubling real people.
    if (($seen['at'] ?? 0) >= $now - 900 && ($seen['n'] ?? 0) >= ITHELP_LIMIT) {
        jsonOut(['ok' => true, 'matched' => false,
                 'answer' => 'That is a lot of questions in a short time. Give it a few minutes, '
                           . 'or call us on 0769 974 200 and a person will help you straight away.'], 429);
    }
    $rate[$key] = ['n' => (($seen['at'] ?? 0) >= $now - 900 ? ($seen['n'] ?? 0) : 0) + 1, 'at' => $now];
    @file_put_contents(ITHELP_THROTTLE, json_encode($rate), LOCK_EX);

    // A tapped suggestion names its topic exactly; answer that topic directly
    // rather than re-reading the title as though it were a question.
    $topic = trim((string) ($b['topic'] ?? ''));
    if ($topic !== '' && ($byTitle = itHelpByTitle($topic))) {
        $read = ['matched' => true, 'entry' => $byTitle, 'keywords' => [], 'also' => [],
                 'corrected' => [], 'lending' => false];
    } else {
        $read = itHelpAnswer($q);
    }
    $hit = $read['matched'] ? $read['entry'] : null;
    $diag = itHelpDiagnostic($q, $hit);

    // Keep what people ask. Unanswered questions are the list of what to write
    // next, and the common ones tell you what to stock and staff for.
    $log = [];
    if (is_file(IT_HELP_LOG)) {
        $d = json_decode((string) @file_get_contents(IT_HELP_LOG), true);
        if (is_array($d)) $log = $d;
    }
    if (count($log) < 20000) {
        $log[] = ['q' => $topic !== '' ? '[topic] ' . $topic : $q,
                  'matched' => $hit['title'] ?? null,
                  'corrected' => $read['corrected'] ?? [],
                  'at' => date('c'), 'by' => (av_user()['username'] ?? null)];
        @file_put_contents(IT_HELP_LOG, json_encode($log), LOCK_EX);
    }

    if ($hit) {
        jsonOut(['ok' => true, 'matched' => true,
                 'title' => $hit['title'], 'answer' => $hit['answer'],
                 'steps' => $hit['try'], 'stop' => $hit['stop'],
                 'service' => $hit['service'],
                 'keywords' => $read['keywords'], 'also' => $read['also'],
                 'corrected' => $read['corrected'], 'diagnostic' => $diag,
                 'confidence' => 'high', 'source_mode' => 'curated']);
    }

    if (!empty($read['choices'])) {
        jsonOut(['ok' => true, 'matched' => false, 'choices' => $read['choices'],
                 'answer' => 'That could be a few different things. Which is closest?']);
    }

    if (!empty($read['lending'])) {
        jsonOut(['ok' => true, 'matched' => false, 'lending' => true,
                 'answer' => 'This assistant is for IT problems. For loans, Avesta Lending '
                           . 'can help — see the loan terms and apply online there.']);
    }

    $model = itHelpModelFallback($q, $diag);
    if ($model) {
        jsonOut(['ok'=>true, 'matched'=>false, 'ai'=>true, 'answer'=>$model['answer'],
                 'steps'=>$model['steps'], 'stop'=>$model['stop'], 'sources'=>$model['sources'],
                 'confidence'=>$model['confidence'], 'source_mode'=>'ai_retrieval', 'diagnostic'=>$diag]);
    }

    jsonOut(['ok' => true, 'matched' => false,
             'answer' => 'I do not have a reliable answer for that one, and I would rather '
                       . 'say so than guess at something that could cost you data. '
                       . 'Send it through below and a person will come back to you.',
             'topics' => itHelpTopics(), 'diagnostic' => $diag, 'confidence'=>'low',
             'source_mode' => IT_HELP_MODEL !== '' ? 'ai_unavailable' : 'human_escalation']);
}

if ($action === 'itQuestions') {
    requireRole(['admin', 'staff']);
    $log = [];
    if (is_file(IT_HELP_LOG)) {
        $d = json_decode((string) @file_get_contents(IT_HELP_LOG), true);
        if (is_array($d)) $log = $d;
    }
    $unmatched = array_values(array_filter($log, fn($e) => empty($e['matched'])));
    $counts = [];
    foreach ($log as $e) {
        $k = $e['matched'] ?? '(no answer yet)';
        $counts[$k] = ($counts[$k] ?? 0) + 1;
    }
    arsort($counts);
    jsonOut(['ok' => true, 'total' => count($log),
             'unmatched' => array_slice(array_reverse($unmatched), 0, 100),
             'by_topic' => $counts]);
}

// ── VIEW A DOCUMENT ──────────────────────────────────────────────────────────
// The only way a document leaves the server. Staff may view any application's
// documents; a borrower only their own; anyone else nothing. Every view is
// written to the audit log — these are identity documents, and the Data
// Protection Act expects to know who looked at them.
if ($action === 'doc') {
    $me = requireRole(['admin', 'staff', 'borrower']);
    global $DOC_FIELDS;
    $key   = (string) ($_GET['key'] ?? '');
    $field = (string) ($_GET['field'] ?? '');

    if (!in_array($field, $DOC_FIELDS, true)) jsonOut(['ok' => false, 'error' => 'Unknown document'], 404);

    $record = null;
    foreach (loadRecords() as $r) {
        if (($r['_key'] ?? '') === $key) { $record = $r; break; }
    }
    if ($record === null) jsonOut(['ok' => false, 'error' => 'Application not found'], 404);

    if ($me['role'] === 'borrower' && ($record['_owner'] ?? '') !== $me['id']) {
        av_audit('document.denied', $key, ['field' => $field]);
        jsonOut(['ok' => false, 'error' => 'Not your application'], 403);
    }

    // Built from the record key and a known field name — never from anything
    // stored in the record — and then confirmed to sit inside the folder.
    $folder = DOCS_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    $found  = glob($folder . '/' . $field . '.*') ?: [];
    $path   = $found ? realpath($found[0]) : false;
    $root   = realpath(DOCS_DIR);
    if (!$path || !$root || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
        jsonOut(['ok' => false, 'error' => 'That document was not uploaded'], 404);
    }

    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
              'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!isset($types[$ext])) jsonOut(['ok' => false, 'error' => 'This file type cannot be shown'], 415);

    av_audit('document.viewed', $key, ['field' => $field]);

    header_remove('Content-Type');
    header('Content-Type: ' . $types[$ext]);
    header('Content-Length: ' . filesize($path));
    $disposition = ($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline';
    $originalName = basename(str_replace('\\', '/', (string) ($record[$field . '_filename'] ?? '')));
    $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName);
    if ($originalName === '') $originalName = $field . '.' . $ext;
    header('Content-Disposition: ' . $disposition . '; filename="' . $field . '.' . $ext
        . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

// ── FORGOT PASSWORD: THE REQUEST ─────────────────────────────────────────────
// Open to signed-out visitors, because someone who cannot sign in is exactly
// who needs it. It answers the same way whether or not the account exists.
if ($action === 'requestReset') {
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $b = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $username = (string) ($b['username'] ?? '');
    $phone    = (string) ($b['phone'] ?? '');
    if (trim($username) === '' || strlen(preg_replace('/[^0-9]/', '', $phone)) < 9) {
        jsonOut(['ok' => false, 'error' => 'Enter your username and the phone number on your account.'], 400);
    }
    av_request_reset($username, $phone);
    jsonOut(['ok' => true, 'message' => 'If that account exists, the office has been told. '
           . 'Call 0769 974 200 to confirm who you are, and we will give you a temporary password.']);
}

// ── FORGOT PASSWORD: WHAT THE ADMIN SEES ─────────────────────────────────────
if ($action === 'resetRequests') {
    requireRole(['admin']);
    $rows = array_reverse(av_resets_load());
    $open = array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'open'));
    jsonOut(['ok' => true, 'requests' => array_slice($rows, 0, 200),
             'open' => count($open)]);
}

// ── FORGOT PASSWORD: THE ADMIN ACTS ──────────────────────────────────────────
if ($action === 'resetPassword') {
    $me = requireRole(['admin']);
    if ($method !== 'POST') jsonOut(['ok' => false, 'error' => 'POST required'], 405);
    $b  = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $id = (string) ($b['id'] ?? '');
    if ($id === '') jsonOut(['ok' => false, 'error' => 'Which account?'], 400);
    if ($id === $me['id']) {
        jsonOut(['ok' => false, 'error' => 'Use Change password for your own account.'], 400);
    }
    av_require_manage_target($me, $id);
    $r = av_admin_reset_password($id, $me['username']);
    jsonOut($r, $r['ok'] ? 200 : 400);
}

// ── WHO HAS AN ACCOUNT ───────────────────────────────────────────────────────
// Every account, with enough context to recognise a person and see whether
// they have actually applied for anything.
if ($action === 'accounts') {
    requireRole(['admin']);
    $apps = [];
    foreach (loadRecords() as $r) {
        $owner = $r['_owner'] ?? '';
        if ($owner === '') continue;
        if (!isset($apps[$owner])) $apps[$owner] = ['count' => 0, 'latest' => null, 'status' => null];
        $apps[$owner]['count']++;
        $at = $r['submitted_at'] ?? '';
        if ($at && (!$apps[$owner]['latest'] || $at > $apps[$owner]['latest'])) {
            $apps[$owner]['latest'] = $at;
            $apps[$owner]['status'] = $r['status'] ?? 'New';
        }
    }
    $openResets = [];
    foreach (av_resets_load() as $row) {
        if (($row['status'] ?? '') === 'open') $openResets[strtolower($row['username'])] = $row['requested_at'] ?? '';
    }

    $recoveryEvents = array_values(array_filter(av_audit_read(2000), function ($row) {
        return in_array($row['action'] ?? '', ['user.self_password_reset', 'user.password_reset', 'recovery.mail_failed', 'recovery.email_verified'], true);
    }));
    $out = [];
    foreach (av_load_users() as $u) {
        unset($u['pass_hash'], $u['auth_version']);
        $id = $u['id'] ?? '';
        $u['applications']   = $apps[$id]['count'] ?? 0;
        $u['latest_apply']   = $apps[$id]['latest'] ?? null;
        $u['latest_status']  = $apps[$id]['status'] ?? null;
        $u['reset_pending']  = $openResets[strtolower($u['username'] ?? '')] ?? null;
        $u['must_change']    = !empty($u['must_change']);
        $out[] = $u;
    }
    usort($out, fn($a, $b) => ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''));

    $counts = ['super_admin' => 0, 'admin' => 0, 'staff' => 0, 'borrower' => 0, 'inactive' => 0, 'never_signed_in' => 0];
    foreach ($out as $u) {
        $counts[$u['role']] = ($counts[$u['role']] ?? 0) + 1;
        if (empty($u['active'])) $counts['inactive']++;
        if (empty($u['last_login'])) $counts['never_signed_in']++;
    }
    av_audit('accounts.list');
    jsonOut(['ok' => true, 'accounts' => $out, 'total' => count($out),
             'counts' => $counts, 'open_resets' => count($openResets),
             'recovery_events' => array_slice($recoveryEvents, 0, 100)]);
}

// ── DEFAULT ───────────────────────────────────────────────────────────────────
jsonOut(['ok' => true, 'message' => 'Avesta API ready. Use ?action=ping to test.']);
