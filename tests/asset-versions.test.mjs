import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, readdirSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {execFileSync, spawnSync} from 'node:child_process';

/* Cache-busting, and the deploy bug that produced this file.
 *
 * Hostinger's CDN serves assets with max-age=604800. On 28 November 2026
 * app.js gained the explainer and the rotating advert, the hand-written ?v=
 * string beside it was left alone, and the live site went on serving the week
 * old file. Every test passed. Every guard was green. The only thing that
 * caught it was loading avesta.solutions in a real browser and finding the
 * explainer absent.
 *
 * av_asset() now derives the version from the file's own contents, so it
 * changes exactly when the file does. These tests keep it that way, and cover
 * the one version string PHP cannot reach: app.js imports domain.js by a URL
 * written inside the JavaScript, so if domain.js changes while app.js does
 * not, the browser keeps the cached app.js and with it the old import.
 */

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;
const version = file => createHash('md5').update(readFileSync(file)).digest('hex').slice(0, 10);

const pages = ['index.php', 'login.php', 'legal.php', 'recovery.php', 'downloads.php',
               'app/index.php', 'app/admin.php'];

test('THE ONE THAT MATTERS: no page writes its own cache-busting version by hand', () => {
  for (const page of pages) {
    const source = readFileSync(page, 'utf8');
    const handwritten = source.match(/(?:href|src)="\/assets\/[^"]*\?v=[^"]*"/g) || [];
    assert.deepEqual(handwritten, [],
      page + ' pins an asset version by hand, so editing that asset ships nothing '
      + 'for up to a week behind the CDN. Use av_asset() instead.');
  }
});

test('every asset tag points at a file that is actually there', {skip: !hasPhp}, () => {
  for (const page of pages) {
    for (const [, path] of readFileSync(page, 'utf8').matchAll(/av_asset\('([^']+)'\)/g)) {
      const out = execFileSync(php, ['-r',
        `require "asset-version.php"; echo av_asset_version(${JSON.stringify(path)});`],
        {encoding: 'utf8'});
      assert.notEqual(out, 'missing', page + ' links ' + path + ', which does not exist');
      assert.equal(out, version('.' + path), path + ' must be versioned by its own contents');
    }
  }
});

test('the version changes when the file does, and not otherwise', {skip: !hasPhp}, () => {
  const ask = () => execFileSync(php, ['-r',
    'require "asset-version.php"; echo av_asset("/assets/avesta/app.js");'], {encoding: 'utf8'});
  assert.equal(ask(), ask(), 'the same bytes must give the same version, or nothing ever caches');
  assert.match(ask(), /^\/assets\/avesta\/app\.js\?v=[0-9a-f]{10}$/);

  const before = ask();
  const path = 'assets/avesta/app.js';
  const original = readFileSync(path);
  try {
    execFileSync('cp', [path, path + '.bak']);
    execFileSync('sh', ['-c', `printf '\\n// touched by a test\\n' >> ${path}`]);
    assert.notEqual(ask(), before, 'an edited asset must get a new version');
  } finally {
    execFileSync('cp', [path + '.bak', path]);
    execFileSync('rm', ['-f', path + '.bak']);
    assert.deepEqual(readFileSync(path), original, 'the test has to put the file back');
  }
});

test("app.js's own import of domain.js is pinned to what domain.js now contains", () => {
  /* PHP rewrites the tags in the HTML, but not the import inside the module.
   * So when domain.js changes, this string has to change with it — and the
   * failure message carries the value to paste in. */
  const source = readFileSync('assets/avesta/app.js', 'utf8');
  const found = source.match(/from\s*'\.\/domain\.js\?v=([^']+)'/);
  assert.ok(found, 'app.js should import domain.js with a version on it');
  assert.equal(found[1], version('assets/avesta/domain.js'),
    'domain.js has changed since this was last pinned. Change the import in '
    + "assets/avesta/app.js to './domain.js?v=" + version('assets/avesta/domain.js') + "'.");
});

test('no asset is left unreferenced by every page, and none is referenced twice on one page', () => {
  // Two tags for one file is two downloads of it on a metered connection.
  for (const page of pages) {
    const used = [...readFileSync(page, 'utf8').matchAll(/av_asset\('([^']+)'\)/g)].map(m => m[1]);
    assert.deepEqual([...new Set(used)], used, page + ' links the same asset twice');
  }
  const linked = new Set(pages.flatMap(p =>
    [...readFileSync(p, 'utf8').matchAll(/av_asset\('\/assets\/avesta\/([^']+)'\)/g)].map(m => m[1])));
  // domain.js and the two guide modules arrive through imports rather than tags.
  const viaImport = new Set(['domain.js', 'guide.js', 'guided.js']);
  // Three files no page loads and no module imports, as of 28 November 2026:
  //
  //   optional-depth.js, optional-depth.css — their own first line says they
  //     are staging only, active solely on elements marked enabled. Nothing
  //     marks any.
  //   software-hub.js — downloads.php loads software-hub.css and
  //     software-tabs.js, but never this one.
  //
  // They are dead weight in a public repository. They stay on this list rather
  // than being deleted because that is Gray's call, not this test's. Take them
  // off it when they go — and nothing new should ever join it.
  const staged = new Set(['optional-depth.js', 'optional-depth.css', 'software-hub.js']);
  for (const name of readdirSync('assets/avesta')) {
    if (!/\.(js|css)$/.test(name) || viaImport.has(name) || staged.has(name)) continue;
    const importedSomewhere = readdirSync('assets/avesta')
      .filter(f => f.endsWith('.js'))
      .some(f => readFileSync('assets/avesta/' + f, 'utf8').includes('./' + name));
    assert.ok(linked.has(name) || importedSomewhere,
      name + ' is in the assets folder but nothing loads it');
  }
});
