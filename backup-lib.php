<?php
/**
 * The backup.
 *
 * Applicant records and identity documents exist in exactly one place — this
 * server — and they are kept out of Git deliberately: the repository is public
 * and GitHub sits outside Zambia, which the Data Protection Act 2021 does not
 * allow for personal data. So the only copy that is not Hostinger's is the one
 * the owner downloads, and this file is what builds it.
 *
 * Three decisions worth knowing:
 *
 * 1. An ALLOWLIST, not an exclude list. A backup built by excluding known
 *    secrets ships the next secret nobody thought to exclude. Every file in
 *    the archive is named below with the reason it is there.
 *
 * 2. STREAMED, never staged. The archive is written straight to the browser,
 *    so no copy of the whole loan book is ever left sitting in a temporary
 *    directory waiting to be cleaned up — and nothing can fail to clean up.
 *
 * 3. An UNCOMPRESSED POSIX tar, built here in PHP rather than with ZipArchive.
 *    Shared hosting may or may not have the zip extension, and a backup that
 *    works on one host and 500s on the next is not a backup. Scans and PDFs
 *    are already compressed, so there is almost nothing to squeeze out.
 *
 * The archive is NOT encrypted. See av_backup_manifest()'s warnings: it holds
 * NRC scans and password hashes in the clear, and whoever holds the file holds
 * those. Encrypting it properly needs libzip built with AES, which cannot be
 * relied on here; the honest answer is to say so rather than to ship
 * ZipCrypto and call it protected.
 */

require_once __DIR__ . '/auth.php';

const AV_BACKUP_VERSION      = 1;
const AV_BACKUP_MIN_INTERVAL = 300;   // seconds between downloads — see av_backup_wait()

/**
 * What goes in, and why. Keyed by the path inside the archive.
 *
 * Anything new that stores applicant data belongs on this list AND in the
 * .htaccess deny list. tests/backup.test.mjs holds the two together, so
 * adding a store to one and forgetting the other fails the build.
 */
function av_backup_allowlist(): array {
    return [
        'records_data.php'  => 'Every loan application ever submitted.',
        'users.php'         => 'Accounts, roles and password hashes.',
        'enquiries.php'     => 'Enquiries sent through the website.',
        'employers.php'     => 'The employer register: who is signed up, their branches, '
                             . 'and which member of staff is on which payroll number.',
        'remittances.php'   => 'What each employer has deducted at payroll and sent in, '
                             . 'and how each payment was split across their staff.',
        'it_questions.php'  => 'Questions put to the IT help desk.',
        'password_resets.php' => 'Password resets still in flight.',
        'email_recovery.php'  => 'Recovery-email checks still in flight.',
        'audit_log.jsonl'   => 'Who viewed which identity document, and when. '
                             . 'The Data Protection Act expects this to be kept.',
        'assets/avesta/rates.json'      => 'The price of credit on the day this was taken.',
        'assets/avesta/categories.json' => 'The three ways in, as published at the time.',
    ];
}

/**
 * Deliberately left out:
 *
 *   avesta-secrets.php   API keys. A backup is a file that gets emailed and
 *                        copied onto laptops; keys do not belong in one. They
 *                        are typed back in by hand.
 *   login_attempts.php   Lockout counters. Restoring them would lock people
 *   enquiry_rate.json    out of an account for something that happened before
 *   ithelp_rate.json     the restore.
 *   assistant_rate.php
 *   rates_cache.php      Exchange rates. Refetches itself within the hour.
 *   *.lock, *.tmp        A half-finished write is not a record.
 *   everything else      Source code. It is in Git, which is where it belongs.
 */
function av_backup_excluded_suffixes(): array {
    return ['.lock', '.tmp'];
}

/**
 * The files to archive: ['path/inside/archive' => '/absolute/path/on/disk'].
 *
 * $root exists so this can be pointed at a sandbox in tests. In production it
 * is the directory this file sits in, which on Hostinger is the document root.
 */
function av_backup_sources(?string $root = null): array {
    $root = rtrim($root ?? __DIR__, '/');
    $out  = [];

    foreach (array_keys(av_backup_allowlist()) as $rel) {
        // A store that has not been read since the guarded format arrived may
        // still be sitting there as plain .json. Take whichever exists; taking
        // only the .php would quietly back up nothing at all.
        $candidates = [$rel];
        if (substr($rel, -4) === '.php') $candidates[] = substr($rel, 0, -4) . '.json';

        foreach ($candidates as $c) {
            $abs = $root . '/' . $c;
            if (is_file($abs) && is_readable($abs)) $out[$c] = $abs;
        }
    }

    foreach (av_backup_document_files($root) as $rel => $abs) $out[$rel] = $abs;

    ksort($out);
    return $out;
}

/**
 * Every file under documents/, including documents/.htaccess.
 *
 * The .htaccess is the only thing standing between the NRC scans and the open
 * web. Restoring the folder without it publishes every identity document on
 * the server, so it travels with them rather than being treated as config.
 */
function av_backup_document_files(string $root): array {
    $dir = $root . '/documents';
    if (!is_dir($dir)) return [];

    $out = [];
    $walk = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($walk as $file) {
        if (!$file->isFile() || !$file->isReadable()) continue;

        $name = $file->getFilename();
        foreach (av_backup_excluded_suffixes() as $suffix) {
            if (substr($name, -strlen($suffix)) === $suffix) continue 2;
        }

        $rel = 'documents/' . str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
        $out[$rel] = $file->getPathname();
    }
    ksort($out);
    return $out;
}

/** How many entries a guarded store holds, without caring what shape they are. */
function av_backup_store_count(string $root, string $name): int {
    foreach ([$name . '.php', $name . '.json'] as $candidate) {
        $path = $root . '/' . $candidate;
        if (!is_file($path)) continue;
        $data = av_guarded_decode((string) @file_get_contents($path));
        return is_array($data) ? count($data) : 0;
    }
    return 0;
}

/**
 * What is in the archive, how to put it back, and what it is not.
 *
 * This travels inside the archive as MANIFEST.json, so a file found on a
 * memory stick in two years' time explains itself without this codebase.
 */
function av_backup_manifest(?string $root = null, ?int $now = null): array {
    $root = rtrim($root ?? __DIR__, '/');
    $now  = $now ?? time();
    $why  = av_backup_allowlist();

    $files = [];
    $bytes = 0;
    foreach (av_backup_sources($root) as $rel => $abs) {
        $size   = (int) filesize($abs);
        $bytes += $size;
        $files[] = [
            'path'   => $rel,
            'bytes'  => $size,
            'sha256' => hash_file('sha256', $abs),
            'why'    => $why[$rel] ?? (strpos($rel, 'documents/') === 0
                        ? 'An applicant document, or the rule that keeps it private.'
                        : 'Applicant data.'),
        ];
    }

    $documents = 0;
    foreach (av_backup_document_files($root) as $rel => $_) {
        if (basename($rel)[0] !== '.') $documents++;   // .htaccess is protection, not a document
    }

    return [
        'format'       => 'avesta-backup',
        'version'      => AV_BACKUP_VERSION,
        'generated'    => date('c', $now),
        'generated_by' => av_user()['username'] ?? 'script',
        'site'         => $_SERVER['HTTP_HOST'] ?? 'avesta.solutions',
        'counts'       => [
            'records'   => av_backup_store_count($root, 'records_data'),
            'accounts'  => av_backup_store_count($root, 'users'),
            'documents' => $documents,
            'files'     => count($files),
            'bytes'     => $bytes,
        ],
        'files'   => $files,
        'restore' => [
            'how' => [
                '1. Deploy the site from GitHub as normal. That brings back the code; '
                . 'none of the files in this archive are in Git, and none of the code is in here.',
                '2. Unpack this archive into the document root (public_html), keeping the paths exactly.',
                '3. Check that documents/.htaccess arrived. Then open /documents/ in a browser: '
                . 'it must answer 403 or 404. If it lists files, stop and fix that before telling '
                . 'anyone the site is back — every NRC scan is public until you do.',
                '4. Sign in. Password hashes are restored, so existing passwords still work.',
                '5. Verify each file against the sha256 above before trusting this archive.',
            ],
            'not_included' => [
                'avesta-secrets.php — API keys. Type them back in.',
                'Lockout and rate-limit counters. They rebuild themselves, and restoring them '
                . 'would lock people out for something that happened before the restore.',
                'Source code. It is in Git.',
            ],
        ],
        'warnings' => [
            'NOT ENCRYPTED. This archive holds NRC scans, payslips, bank statements and '
            . 'password hashes in the clear. Whoever holds this file holds those. Keep it on '
            . 'encrypted storage, do not email it, and delete old copies.',
            'Personal data. Under the Data Protection Act 2021 this must be stored in Zambia. '
            . 'A cloud drive hosted abroad is not a safe place to keep it.',
        ],
    ];
}

/**
 * The manifest as it is written into the archive.
 *
 * One function, because the exact bytes are needed twice: once to measure the
 * archive for Content-Length, once to write it. Encoding it differently in
 * those two places would hang the download a byte short.
 */
function av_backup_manifest_json(array $manifest): string {
    return json_encode($manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** A file of $n bytes occupies this many bytes of tar blocks. */
function av_tar_block_len(int $n): int {
    return (int) (ceil($n / 512) * 512);
}

/**
 * Exactly how long the archive will be, from the sizes the manifest recorded.
 *
 * Worth the arithmetic: with a Content-Length the browser shows a real
 * progress bar and knows when the file is whole, which on a Zambian mobile
 * connection is the difference between a trusted download and a truncated one
 * nobody notices. It is computed from the manifest the stream itself will use,
 * so the two cannot disagree.
 */
function av_backup_size(array $manifest): int {
    $total = 512 + av_tar_block_len(strlen(av_backup_manifest_json($manifest)));
    foreach ($manifest['files'] as $f) {
        $total += 512 + av_tar_block_len((int) $f['bytes']);
    }
    $total += 1024;                                    // the two closing zero blocks
    $tail = $total % 10240;
    return $tail === 0 ? $total : $total + (10240 - $tail);
}

// ── POSIX tar ────────────────────────────────────────────────────────────────
// Written out here rather than shelled out to tar or handed to ZipArchive,
// because neither can be relied on across hosts. The format is 1979 simple:
// a 512-byte header per file, the bytes padded to 512, two zero blocks at the
// end. tests/backup.test.mjs hands the result to GNU tar and compares every
// byte that comes back, which is the only check that means anything here.

/** One ustar header block. Throws rather than silently truncating a path. */
function av_tar_header(string $path, int $size, int $mtime, int $mode = 0644): string {
    $name   = $path;
    $prefix = '';

    if (strlen($name) > 100) {
        // ustar carries a long path as prefix + '/' + name. Split at the
        // earliest slash that leaves the name part within its 100 bytes.
        $split = -1;
        for ($i = 0, $len = strlen($name); $i < $len; $i++) {
            if ($name[$i] !== '/') continue;
            if ($len - $i - 1 <= 100) { $split = $i; break; }
        }
        if ($split < 1 || $split > 155) {
            throw new RuntimeException('Path will not fit in a tar archive: ' . $path);
        }
        $prefix = substr($name, 0, $split);
        $name   = substr($name, $split + 1);
    }

    $h  = pack('a100', $name);
    $h .= pack('a8',  sprintf('%07o', $mode) . "\0");
    $h .= pack('a8',  sprintf('%07o', 0) . "\0");          // uid
    $h .= pack('a8',  sprintf('%07o', 0) . "\0");          // gid
    $h .= pack('a12', sprintf('%011o', $size) . "\0");
    $h .= pack('a12', sprintf('%011o', $mtime) . "\0");
    $h .= '        ';                                       // checksum, filled in below
    $h .= '0';                                              // a plain file
    $h .= pack('a100', '');                                 // link target
    $h .= pack('a6',  'ustar');                             // magic, NUL-terminated
    $h .= '00';                                             // version
    $h .= pack('a32', 'avesta');                            // owner name
    $h .= pack('a32', 'avesta');                            // group name
    $h .= pack('a8',  sprintf('%07o', 0) . "\0");           // device major
    $h .= pack('a8',  sprintf('%07o', 0) . "\0");           // device minor
    $h .= pack('a155', $prefix);
    $h .= pack('a12', '');                                  // pad to 512

    $sum = 0;
    for ($i = 0; $i < 512; $i++) $sum += ord($h[$i]);

    return substr_replace($h, pack('a8', sprintf('%06o', $sum) . "\0 "), 148, 8);
}

/** Pad whatever was just written up to the next 512-byte boundary. */
function av_tar_pad($out, int $written): int {
    $short = $written % 512;
    if ($short === 0) return 0;
    $pad = 512 - $short;
    fwrite($out, str_repeat("\0", $pad));
    return $pad;
}

/**
 * Build the archive onto an open stream.
 *
 * Entries are written one at a time, file bytes copied in 64 KB chunks, so a
 * folder of scans larger than the PHP memory limit still goes out whole.
 * Returns what was written, for the manifest and the audit log.
 */
function av_backup_stream(?string $root, $out, ?int $now = null, ?array $manifest = null): array {
    $root = rtrim($root ?? __DIR__, '/');
    $now  = $now ?? time();

    // The caller passes the manifest it already built, because building one
    // hashes every document on the server and doing that twice per download
    // would double the slowest part of the job.
    $manifest = $manifest ?? av_backup_manifest($root, $now);
    $sources  = av_backup_sources($root);

    // Sizes come from the manifest, never from a second filesize() call. The
    // audit log is appended to while this runs, so asking twice would get two
    // answers and the archive would not match its own Content-Length.
    $sizes = [];
    foreach ($manifest['files'] as $f) $sizes[$f['path']] = (int) $f['bytes'];

    $bytes   = 0;
    $entries = [];

    // The manifest goes first, so anyone looking at the archive reads what it
    // is before they reach a single scan.
    $json = av_backup_manifest_json($manifest);
    $bytes += fwrite($out, av_tar_header('MANIFEST.json', strlen($json), $now));
    $bytes += fwrite($out, $json);
    $bytes += av_tar_pad($out, strlen($json));
    $entries[] = ['path' => 'MANIFEST.json', 'bytes' => strlen($json)];

    // The manifest drives the writing, not the directory. A file that appeared
    // after the snapshot is not written — it is not in the length already
    // promised to the browser — and a file that has since become unreadable is
    // still written, at its promised size, zero-filled. Either way the archive
    // is exactly as long as it said it would be, and the recorded sha256 is
    // what tells the owner a file came back wrong.
    foreach ($manifest['files'] as $f) {
        $rel  = $f['path'];
        $size = (int) $f['bytes'];
        $abs  = $sources[$rel] ?? null;

        $bytes += fwrite($out, av_tar_header($rel, $size,
            ($abs && @filemtime($abs)) ? (int) filemtime($abs) : $now));

        $sent = 0;
        $in   = $abs === null ? false : @fopen($abs, 'rb');
        if ($in !== false) {
            while ($sent < $size && !feof($in)) {
                $chunk = fread($in, min(65536, $size - $sent));
                if ($chunk === false || $chunk === '') break;
                $sent += fwrite($out, $chunk);
            }
            fclose($in);
        }
        if ($sent < $size) $sent += fwrite($out, str_repeat("\0", $size - $sent));

        $bytes += $sent;
        $bytes += av_tar_pad($out, $size);

        $entries[] = ['path' => $rel, 'bytes' => $size,
                      'read' => $in !== false && $sent === $size];
        fflush($out);
    }

    // Two zero blocks end the archive, then pad out to tar's 10 KB record so
    // older readers do not complain about an unexpected end of file.
    $bytes += fwrite($out, str_repeat("\0", 1024));
    $tail = $bytes % 10240;
    if ($tail !== 0) $bytes += fwrite($out, str_repeat("\0", 10240 - $tail));

    return ['bytes' => $bytes, 'entries' => $entries, 'manifest' => $manifest];
}

/** What the downloaded file is called. Sorts correctly in a folder listing. */
function av_backup_name(?int $now = null): string {
    return 'avesta-backup-' . date('Y-m-d-Hi', $now ?? time()) . '.tar';
}

/**
 * Seconds to wait before another backup may be taken, or 0.
 *
 * Zipping up every document is the most expensive thing this site can be asked
 * to do, and on shared hosting a handful of repeats would take the whole site
 * down. The audit log is the record of when the last one happened, so there is
 * no extra state file to protect.
 */
function av_backup_wait(?int $now = null): int {
    $now  = $now ?? time();
    $last = av_audit_read(1, '', 'backup.downloaded');
    if (!$last) return 0;

    $at = strtotime($last[0]['ts'] ?? '');
    if (!$at) return 0;

    $left = AV_BACKUP_MIN_INTERVAL - ($now - $at);
    return $left > 0 ? $left : 0;
}
