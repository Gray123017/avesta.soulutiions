import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Window} from 'happy-dom';

const admin = readFileSync('app/admin.php', 'utf8');
const staff = readFileSync('app/index.php', 'utf8');
function between(source, start, end) {
  const a = source.indexOf(start);
  assert.ok(a >= 0, start);
  const b = source.indexOf(end, a + start.length);
  assert.ok(b > a, end);
  return source.slice(a, b);
}
const record = {
  _key: 'application_test',
  doc_nrc_front_url: 'documents/application_test/doc_nrc_front.jpg',
  doc_nrc_front_filename: `NRC "front" O'Brien.jpg`,
  doc_nrc_front_mimetype: 'image/jpeg',
  doc_payslip_url: 'documents/application_test/doc_payslip.pdf',
  doc_payslip_filename: 'Payslip.pdf',
  doc_payslip_mimetype: 'application/pdf',
};

for (const mode of ['admin', 'staff']) {
  test(`${mode}: document tiles open by mouse and keyboard and downloads use authenticated API`, async () => {
    const w = new Window({url: 'https://avesta.test/portal.php', settings: {
      enableJavaScriptEvaluation: true, disableJavaScriptFileLoading: true,
      suppressInsecureJavaScriptEnvironmentWarning: true,
    }});
    const source = mode === 'admin' ? admin : staff;
    const map = between(source, 'Object.entries(DOC_LABELS).map(([k,meta]) => {', "}).join('')}") + "}).join('')";
    w.__record = record;
    const setup = `const r = window.__record;
      const allRecords = [r], staffAllRecords = [r];
      const DOC_LABELS = {doc_nrc_front: {label:'NRC Front',icon:'NRC'},doc_payslip:{label:'Payslip',icon:'PDF'}};
      function getScriptUrl(){return '/api.php';}
      function getStaffScriptUrl(){return '/api.php';}
      window.__tiles = ${map};`;
    w.eval(setup);
    const container = mode === 'admin' ? 'modal-body' : 'staff-records';
    const viewer = mode === 'admin' ? 'doc-viewer' : 'doc-viewer-modal';
    const title = mode === 'admin' ? 'dv-title' : 'doc-viewer-title';
    const download = mode === 'admin' ? 'dv-dl' : 'doc-viewer-download';
    const body = mode === 'admin' ? 'dv-body' : 'doc-viewer-body';
    w.document.body.innerHTML = `<div id="${container}" data-record-key="application_test">${w.__tiles}</div>
      <div id="${viewer}"><span id="${title}"></span><a id="${download}"></a><div id="${body}"></div></div>`;
    const script = mode === 'admin'
      ? between(admin, 'function activateDocumentTile', 'function closeDV')
      : between(staff, 'function activateStaffDocumentTile', 'function closeDocViewer');
    w.eval(setup + script);
    const tiles = w.document.querySelectorAll('[data-document-field]');
    assert.equal(tiles.length, 2);
    assert.equal(tiles[0].hasAttribute('onclick'), false);
    tiles[0].querySelector('div').click();
    assert.equal(w.document.getElementById(title).textContent, record.doc_nrc_front_filename);
    assert.equal(new URL(w.document.querySelector(`#${body} img`).src).pathname, '/api.php');
    let url = new URL(w.document.getElementById(download).href);
    assert.equal(url.searchParams.get('key'), record._key);
    assert.equal(url.searchParams.get('field'), 'doc_nrc_front');
    assert.equal(url.searchParams.get('download'), '1');
    tiles[1].dispatchEvent(new w.KeyboardEvent('keydown', {key:'Enter', bubbles:true}));
    assert.equal(w.document.querySelector(`#${body} embed`).type, 'application/pdf');
    url = new URL(w.document.getElementById(download).href);
    assert.equal(url.searchParams.get('field'), 'doc_payslip');
    assert.equal(url.searchParams.get('action'), 'doc');
    await w.happyDOM.close();
  });
}

test('Asset Tracker has a direct ZIP download without the pending-upload notice', () => {
  const w = new Window();
  w.document.write(readFileSync('downloads.php','utf8'));
  const a = w.document.querySelector('#asset-tracker a[download]');
  assert.equal(a.getAttribute('href'), '/downloads/asset-tracker/GIT-Asset-Tracker-1.5.1-Windows.zip');
  assert.ok(!w.document.querySelector('#asset-tracker').textContent.includes('upload to this website is pending'));
  w.happyDOM.abort();
});
