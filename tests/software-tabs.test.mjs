import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {Window} from 'happy-dom';

test('Software details stay closed until selected; tabs support switching, keyboard and direct links', async () => {
  const downloadsHtml = execFileSync('php', ['downloads.php'], {encoding: 'utf8'});
  for (const hash of ['', '#asset-tracker', '#sentinel', '#device-health']) {
    const w = new Window({url: 'https://avesta.test/downloads.php' + hash, settings: {enableJavaScriptEvaluation:true,disableCSSFileLoading:true,disableJavaScriptFileLoading:true,suppressInsecureJavaScriptEnvironmentWarning:true}});
    w.document.write(downloadsHtml);
    w.eval(readFileSync('assets/avesta/software-tabs.js','utf8'));

    const panels = [...w.document.querySelectorAll('[role="tabpanel"]')];
    assert.equal(panels.filter(p => !p.hidden).length, hash ? 1 : 0);
    if (hash) assert.equal(w.document.querySelector(hash).hidden, false);

    const tabs = [...w.document.querySelectorAll('[role="tab"]')];
    const assetTab = tabs.find(tab => tab.getAttribute('aria-controls') === 'asset-tracker');
    const sentinelTab = tabs.find(tab => tab.getAttribute('aria-controls') === 'sentinel');

    assetTab.click();
    assert.equal(w.document.querySelector('#asset-tracker').hidden, false);
    assert.equal(w.document.querySelector('#sentinel').hidden, true);
    assert.equal(assetTab.getAttribute('aria-selected'),'true');
    assert.ok(w.document.querySelector('#asset-tracker a[download]').href.endsWith('GIT-Asset-Tracker-1.5.1-Windows.zip'));

    assetTab.dispatchEvent(new w.KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true,cancelable:true}));
    assert.equal(w.document.activeElement, sentinelTab);
    assert.equal(w.document.querySelector('#asset-tracker').hidden, true);
    assert.equal(w.document.querySelector('#sentinel').hidden, false);
    assert.ok(w.document.querySelector('#sentinel a[download]').href.endsWith('Avanto-Sentinel-Windows-x64.rar'));

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
