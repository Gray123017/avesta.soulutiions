import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';

test('Software details stay closed until selected; tabs support switching, keyboard and direct links', async () => {
  for (const hash of ['', '#asset-tracker', '#device-health']) {
    const w = new Window({url: 'https://avesta.test/downloads.php' + hash, settings: {enableJavaScriptEvaluation:true,disableCSSFileLoading:true,disableJavaScriptFileLoading:true,suppressInsecureJavaScriptEnvironmentWarning:true}});
    w.document.write(readFileSync('downloads.php','utf8'));
    w.eval(readFileSync('assets/avesta/software-tabs.js','utf8'));
    const panels = [...w.document.querySelectorAll('[role="tabpanel"]')];
    assert.equal(panels.filter(p => !p.hidden).length, hash ? 1 : 0);
    if (hash) assert.equal(w.document.querySelector(hash).hidden, false);
    const tabs = [...w.document.querySelectorAll('[role="tab"]')];
    tabs[0].click();
    assert.equal(panels[0].hidden,false);assert.equal(panels[1].hidden,true);
    assert.equal(tabs[0].getAttribute('aria-selected'),'true');
    assert.ok(panels[0].querySelector('a[download]').href.endsWith('GIT-Asset-Tracker-1.5.1-Windows.zip'));
    tabs[0].dispatchEvent(new w.KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true,cancelable:true}));
    assert.equal(panels[0].hidden,true);assert.equal(panels[1].hidden,false);
    assert.equal(w.document.activeElement,tabs[1]);
    assert.equal(panels[1].querySelectorAll('a[download]').length,2);
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
