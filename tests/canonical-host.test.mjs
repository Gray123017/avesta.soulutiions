import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/* One address for the site, and it has to be one that answers.
 *
 * Found by comparing the live site against the repository: every public page
 * was serving
 *
 *     <link rel="canonical" href="https://www.avesta.solutions/loans.php">
 *
 * while www.avesta.solutions had no DNS record at all. A canonical tag is an
 * instruction to a search engine — index me at this address, not the one you
 * arrived on — so every public page was pointing Google at a hostname that
 * does not resolve, the sitemap listed five unreachable URLs, robots.txt
 * pointed at an unreachable sitemap, and every shared link carried an og:url
 * that goes nowhere.
 *
 * The apex is what answers, so the apex is the address. If www is ever given
 * a DNS record it should redirect here rather than become the canonical: one
 * address, and the one that has the history.
 */

const CANONICAL = 'https://avesta.solutions/';

const app      = readFileSync('app/index.php', 'utf8');
const sitemap  = readFileSync('sitemap.xml', 'utf8');
const robots   = readFileSync('robots.txt', 'utf8');

test('the canonical host is the one that resolves', () => {
  const m = app.match(/\$avBase\s*=\s*'([^']+)'/);
  assert.ok(m, '$avBase should still be where the canonical host is decided');
  assert.equal(m[1], CANONICAL,
    'www.avesta.solutions has no DNS record, so it cannot be the canonical host');
});

test('the sitemap lists addresses a crawler can reach', () => {
  const urls = [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
  assert.ok(urls.length >= 5, `expected the pages to be listed, found ${urls.length}`);
  for (const url of urls) {
    assert.ok(url.startsWith(CANONICAL),
      `the sitemap offers ${url}, which is not on the host that answers`);
  }
});

test('robots.txt points at a sitemap that exists', () => {
  const m = robots.match(/^Sitemap:\s*(\S+)/m);
  assert.ok(m, 'robots.txt should still name the sitemap');
  assert.equal(m[1], CANONICAL + 'sitemap.xml');
});

test('no public page advertises a host of its own', () => {
  // One place decides the address. A second copy of it somewhere is how the
  // sitemap and the canonical tag came to disagree with reality in the first
  // place, and nobody noticed for months.
  for (const file of ['app/index.php', 'index.php', 'loans.php', 'it.php', 'legal.php',
                      'sitemap.xml', 'robots.txt']) {
    const src = readFileSync(file, 'utf8');
    const stray = [...src.matchAll(/https?:\/\/www\.avesta\.solutions/g)];
    assert.equal(stray.length, 0,
      `${file} still names www.avesta.solutions, which does not resolve`);
  }
});

test('the chat endpoint still accepts both hosts', () => {
  // The opposite rule, and deliberately so: this is an allow-list of origins a
  // browser may be on, not an address the site advertises. Leaving www on it
  // costs nothing and means the day a www record is added, nothing breaks.
  const chat = readFileSync('assistant-api.php', 'utf8');
  assert.match(chat, /https:\/\/www\.avesta\.solutions/,
    'www should stay in the CORS allow-list for the day it starts resolving');
  assert.match(chat, /https:\/\/avesta\.solutions/);
});
