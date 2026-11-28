<?php
/**
 * AVESTA — asset-version.php
 *
 * One place that decides an asset's cache-busting version.
 *
 * Hostinger's CDN serves assets with max-age=604800. An asset edited without a
 * new ?v= therefore stays stale in front of visitors for up to a week. That is
 * not hypothetical: on 28 November 2026 app.js gained the explainer and the
 * rotating advert, the hand-written version string was left alone, and the
 * live site kept serving the old file while every test passed and every guard
 * was green. The bug was only visible by loading the real site.
 *
 * Hand-written version strings fail that way because they depend on somebody
 * remembering. This derives the version from the file's own contents, so it
 * changes if and only if the file changes — never otherwise, so an unchanged
 * asset stays cached and costs a visitor nothing.
 *
 * No side effects: this file defines two functions and does nothing else, so
 * any page can require it, including the public ones that deliberately do not
 * start a session.
 */
declare(strict_types=1);

function av_asset_version(string $path): string
{
    static $cache = [];
    $key = ltrim($path, '/');
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    // __DIR__ is the web root, so this resolves the same way from app/ as
    // it does from the root itself.
    $file = __DIR__ . '/' . $key;
    // md5 rather than something faster: it is available everywhere, and the
    // test suite recomputes these same ten characters in Node to check the
    // one version string PHP cannot reach — app.js's own import of domain.js.
    $hash = is_file($file) ? hash_file('md5', $file) : '';
    // A missing file is a broken tag either way; a constant marker at least
    // keeps it out of the cache under a plausible-looking version.
    return $cache[$key] = ($hash === '' ? 'missing' : substr($hash, 0, 10));
}

function av_asset(string $path): string
{
    return '/' . ltrim($path, '/') . '?v=' . av_asset_version($path);
}
