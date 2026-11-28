import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {Window} from 'happy-dom';

test('Software details stay closed until selected; tabs support switching, keyboard and direct links', async () => {
  const downloadsHtml = execFileSync('php', ['downloads.php'], {encoding: 'utf8'});
  for (const hash of ['', '#asset-tracker', '#sentinel', '#device-health', '#moneytrack']) {
    const w = new Window({url: 'https://avesta.test/downloads.php' + hash, settings: {enableJavaScriptEvaluation:true,disableCSSFileLoading:true,disableJavaScriptFileLoading:true,suppressInsecureJavaScriptEnvironmentWarning:true}});
    w.document.write(downloadsHtml);
    w.eval(readFileSync('assets/avesta/software-tabs.js','utf8'));

    const panels = [...w.document.querySelectorAll('[role="tabpanel"]')];
    assert.equal(panels.filter(p => !p.hidden).length, hash ? 1 : 0);
    if (hash) assert.equal(w.document.querySelector(hash).hidden, false);

    const tabs = [...w.document.querySelectorAll('[role="tab"]')];
    const idOf = el => el && el.getAttribute('aria-controls');
    const assetTab = tabs.find(tab => idOf(tab) === 'asset-tracker');
    assert.ok(assetTab, 'the asset tracker should still have a tab');

    assetTab.click();
    assert.equal(w.document.querySelector('#asset-tracker').hidden, false);
    assert.equal(tabs.filter(t => !w.document.querySelector('#' + idOf(t)).hidden).length, 1,
      'exactly one panel open at a time');
    assert.equal(assetTab.getAttribute('aria-selected'), 'true');
    assert.ok(w.document.querySelector('#asset-tracker .hub-button.primary'),
      'the panel still offers an action');

    /* Right arrow moves to whichever tab is next, rather than to a product
     * named here: the catalogue is reordered whenever a build lands, and a
     * test that hard-codes the neighbour breaks every time it does.
     *
     * Compare ids, never the elements themselves. assert.equal on two DOM
     * nodes builds its failure diff by walking the node graph, which on a
     * nine-panel page takes the process out with a SIGKILL and prints
     * nothing — twenty minutes of a Saturday went on that.  */
    const next = tabs[(tabs.indexOf(assetTab) + 1) % tabs.length];
    assetTab.dispatchEvent(new w.KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true,cancelable:true}));
    assert.equal(idOf(w.document.activeElement), idOf(next),
      'right arrow should focus the next tab along');
    assert.equal(w.document.querySelector('#asset-tracker').hidden, true);
    assert.equal(w.document.querySelector('#' + idOf(next)).hidden, false);

    // Sentinel's package is not in the repository, so its card asks for a
    // copy instead of offering a link that would 404.
    const sentinel = w.document.querySelector('#sentinel');
    assert.equal(sentinel.querySelector('a[download]'), null);
    assert.ok(sentinel.querySelector('a[href^="mailto:"]'));

    await w.happyDOM.close();
  }
});

test('Portal tables gain one keyboard-scrollable container, including newly added tables', async () => {
  const w = new Window({settings:{enableJavaScriptEvaluation:true,suppressInsecureJavaScriptEnvironmentWarning:true}});
  w.document.write('<main><table id="first"><tr><td>Record</td></tr></table><div class="table-container"><table id="existing"></table></div></main>');
  w.eval(readFileSync('assets/avesta/device-layout.js','utf8'));
  assert.equal(w.document.querySelector('#first').parentElement.tabIndex,0);
  assert.equal(w.document.querySelectorAll('.device-table-scroll').length,1);
  const table=w.document.createElement('table');w.document.querySelector('main').append(table);
  await new Promise(resolve=>setTimeout(resolve,30));
  assert.equal(table.parentElement.getAttribute('role'),'region');
  assert.equal(w.document.querySelectorAll('.device-table-scroll').length,2);
  assert.equal(w.document.querySelectorAll('.device-table-scroll .device-table-scroll').length,0);
  await w.happyDOM.close();
});
