import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, mkdirSync, copyFileSync, cpSync, writeFileSync, readFileSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn, spawnSync} from 'node:child_process';
import {createServer} from 'node:net';
import {Script} from 'node:vm';

const php = process.env.AVESTA_TEST_PHP || 'php';
const hasPhp = spawnSync(php, ['-v']).status === 0;
test('PHP documents: upload, list, preview, download, access controls and save failures', {skip:!hasPhp, timeout:30000}, async () => {
  const dir = mkdtempSync(join(tmpdir(), 'avesta-documents-'));
  let server;
  try {
    for (const file of ['api.php','auth.php','portal.php','apply.php']) copyFileSync(file, join(dir,file));
    cpSync('app', join(dir,'app'), {recursive:true});
    mkdirSync(join(dir,'sessions'));
    // Test-only sessions in a temporary copy. Never creates live accounts or applications.
    writeFileSync(join(dir,'fixture.php'), `<?php
      require __DIR__.'/auth.php';
      foreach (['admin','staff','borrower','other'] as $role) {
        session_id('test-'.$role); av_session_start();
        $_SESSION = ['uid'=>$role,'username'=>$role,'name'=>'Test',
          'role'=>$role==='other'?'borrower':$role,'seen'=>time(),'started'=>time()];
        session_write_close();
      }
    `);
    const config = ['-d', 'session.save_path='+join(dir,'sessions')];
    const fixture = spawnSync(php, [...config, join(dir,'fixture.php')], {encoding:'utf8'});
    assert.equal(fixture.status, 0, fixture.stderr);
    const socket = createServer();
    await new Promise(resolve=>socket.listen(0,'127.0.0.1',resolve));
    const port = socket.address().port;
    await new Promise(resolve=>socket.close(resolve));
    const base = 'http://127.0.0.1:'+port;
    server = spawn(php, [...config, '-S','127.0.0.1:'+port,'-t',dir], {stdio:'ignore'});
    const request = (path, role, data) => fetch(base+path, {
      headers: {...(role ? {Cookie:'AVESTASESS=test-'+role}:{}), ...(data ? {'Content-Type':'application/json'}:{})},
      ...(data ? {method:'POST',body:JSON.stringify(data)}:{}),
    });
    for(let i=0;i<40;i++) {
      try { await request('/api.php?action=ping'); break; }
      catch { await new Promise(resolve=>setTimeout(resolve,50)); }
    }
    const jpg = Buffer.from([0xff,0xd8,0xff,0xd9]);
    const pdf = Buffer.from('%PDF-1.4\nTest fixture\n%%EOF');
    const payload = {
      _key:'test_documents', loan_amount:1000, loan_duration:'2 Weeks',
      doc_nrc_front_filename:`NRC "front" O'Brien.jpg`, doc_nrc_front_mimetype:'image/jpeg', doc_nrc_front_base64:jpg.toString('base64'),
      doc_payslip_filename:'Payslip.pdf', doc_payslip_mimetype:'application/pdf', doc_payslip_base64:pdf.toString('base64'),
    };
    let response = await request('/api.php?action=submit', 'borrower', payload);
    assert.equal(response.status,200,await response.clone().text());
    assert.equal((await response.json()).ok,true);
    const records = await (await request('/api.php?action=getRecords','admin')).json();
    assert.equal(records.total,1);
    assert.ok(records.records[0].doc_nrc_front_url);
    assert.equal(records.records[0].doc_nrc_front_base64,undefined);
    for(const role of ['admin','staff','borrower']) {
      for(const [field,bytes,mime] of [['doc_nrc_front',jpg,'image/jpeg'],['doc_payslip',pdf,'application/pdf']]) {
        for(const download of [false,true]) {
          response = await request(`/api.php?action=doc&key=test_documents&field=${field}${download?'&download=1':''}`,role);
          assert.equal(response.status,200);
          assert.equal(response.headers.get('content-type'),mime);
          assert.match(response.headers.get('content-disposition'),download?/^attachment;/:/^inline;/);
          assert.equal(response.headers.get('cache-control'),'private, no-store');
          assert.deepEqual(Buffer.from(await response.arrayBuffer()),bytes);
        }
      }
    }
    const url = '/api.php?action=doc&key=test_documents&field=doc_payslip';
    assert.equal((await request(url)).status,401);
    assert.equal((await request(url,'other')).status,403);
    assert.equal((await request(url.replace('doc_payslip','doc_utility_bill'),'admin')).status,404);
    assert.equal((await request(url.replace('doc_payslip','../auth'),'admin')).status,404);
    response = await request('/api.php?action=submit','borrower',{...payload,_key:'invalid',doc_nrc_front_base64:'not base64!!'});
    assert.equal(response.status,422);
    assert.equal((await response.json()).ok,false);
    response = await request('/api.php?action=updateRecord','admin',{_key:'test_documents',doc_nrc_front_base64:'invalid!'});
    assert.equal(response.status,422);
    writeFileSync(join(dir,'documents','blocked'),'A file prevents folder creation');
    response = await request('/api.php?action=submit','borrower',{...payload,_key:'blocked'});
    assert.equal(response.status,500);
    assert.equal((await response.json()).ok,false);
    assert.equal((await (await request('/api.php?action=getRecords','admin')).json()).total,1);
    assert.deepEqual(readFileSync(join(dir,'documents','test_documents','doc_nrc_front.jpg')),jpg);
    // Compile the actual authenticated HTML's inline JavaScript, including both viewers.
    for(const page of ['/portal.php','/apply.php']) {
      const html = await (await request(page,'admin')).text();
      for(const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
        if(/\bsrc=|type=["']module/.test(match[1])) continue;
        new Script(match[2],{filename:page});
      }
    }
  } finally {
    if(server) { const closed = new Promise(resolve=>server.once('exit',resolve)); server.kill(); await closed; }
    rmSync(dir,{recursive:true,force:true});
  }
});
