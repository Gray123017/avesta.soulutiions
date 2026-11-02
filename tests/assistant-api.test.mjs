import {test} from 'node:test';
import assert from 'node:assert/strict';
import {copyFileSync, cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn, spawnSync} from 'node:child_process';
import {createServer} from 'node:net';

const php = process.env.AVESTA_TEST_PHP || 'php';
// Synthetic credential only. No real keys, provider calls, or production data.
const fakeKey = 'sk-test-abcdefghijklmnopqrstuvwxyz123456';

test('G.I.T migration preserves the HTTP contract and paid-request safeguards', {timeout:30000}, async t => {
  // Fail, rather than silently skip the migration gate, when PHP is unavailable.
  assert.equal(spawnSync(php, ['-v']).status, 0, 'PHP CLI is required for assistant API regression tests');
  const dir = mkdtempSync(join(tmpdir(), 'avesta-assistant-api-'));
  const root = join(dir, 'public_html');
  let server;
  try {
    mkdirSync(root);
    for (const file of ['assistant-api.php', 'assistant-lib.php']) copyFileSync(file, join(root, file));
    cpSync('assets/avesta', join(root, 'assets/avesta'), {recursive:true});
    const scenario = join(dir, 'scenario.json');
    const capture = join(dir, 'capture.json');
    const rate = join(root, 'assistant_rate.php');
    // -n disables extensions. Define cURL in the temporary test router, never
    // in production code: requests execute the real endpoint without networking.
    writeFileSync(join(dir, 'router.php'), `<?php
      if (function_exists('curl_init')) { http_response_code(500); exit('Test requires cURL disabled'); }
      $fixture = json_decode(file_get_contents(__DIR__.'/scenario.json'), true);
      putenv('OPENAI_API_KEY='.($fixture['key'] ?? ''));
      if (!($fixture['missing_curl'] ?? false)) {
        foreach (['CURLOPT_POST','CURLOPT_RETURNTRANSFER','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_HTTPHEADER','CURLOPT_POSTFIELDS','CURLOPT_FOLLOWLOCATION','CURLINFO_HTTP_CODE'] as $i=>$name) define($name, $i+1);
        function curl_init($url) { return (object)['url'=>$url]; }
        function curl_setopt_array($ch, $options) {
          $file = __DIR__.'/capture.json';
          $previous = is_file($file) ? json_decode(file_get_contents($file), true) : [];
          file_put_contents($file, json_encode(['url'=>$ch->url,'options'=>$options,'calls'=>($previous['calls'] ?? 0)+1])); return true;
        }
        function curl_exec($ch) {
          global $fixture; return ($fixture['transport_failure'] ?? false) ? false : ($fixture['raw'] ?? json_encode($fixture['response']));
        }
        function curl_getinfo($ch, $option) { global $fixture; return $fixture['status'] ?? 200; }
        function curl_close($ch) {}
      }
      require __DIR__.'/public_html/assistant-api.php';
    `);
    const success = {
      status:'completed',
      output:[
        {type:'reasoning', summary:[]},
        {type:'web_search_call', status:'completed', action:{type:'search', query:'printer offline Microsoft'}},
        {type:'message', role:'assistant', content:[{type:'output_text', text:'Check the printer connection.', annotations:[
          {type:'url_citation', url:'https://support.microsoft.com/test', title:'Official printer help'},
          {type:'url_citation', url:'javascript:alert(1)', title:'Unsafe'},
          {type:'url_citation', url:'http://example.test', title:'Insecure'}
        ]}]}
      ]
    };
    function fixture(overrides = {}) {
      writeFileSync(scenario, JSON.stringify({key:fakeKey, status:200, response:success, ...overrides}));
      rmSync(capture, {force:true});
      rmSync(rate, {force:true, recursive:true});
    }
    fixture();
    const socket = createServer();
    await new Promise(r => socket.listen(0, '127.0.0.1', r));
    const port = socket.address().port;
    await new Promise(r => socket.close(r));
    const base = 'http://127.0.0.1:' + port;
    const env = {...process.env};
    delete env.OPENAI_API_KEY;
    server = spawn(php, ['-n', '-S', '127.0.0.1:' + port, '-t', root, join(dir, 'router.php')], {env, stdio:'ignore'});
    let started = false;
    for (let i=0; i<60; i++) {
      try { await fetch(base + '/assistant-api.php?action=status'); started=true; break; }
      catch { await new Promise(r => setTimeout(r, 50)); }
    }
    assert.ok(started, 'PHP test server started');
    async function ask(body = {message:'My printer is offline', web:true, scope:'site'}, options = {}) {
      const res = await fetch(base + '/assistant-api.php', {
        method:'POST', headers:{'Content-Type':'application/json', 'X-Avesta-Chat':'1', Origin:'https://avesta.solutions', ...options.headers},
        body:options.raw ?? JSON.stringify(body)
      });
      const text = await res.text();
      assert.ok(!text.includes(fakeKey), 'Credential never reaches the client');
      return {status:res.status, body:JSON.parse(text), headers:res.headers};
    }
    const recorded = () => JSON.parse(readFileSync(capture, 'utf8'));

    await t.test('status is local readiness only and does not spend or expose credentials', async () => {
      for (const [overrides, reason] of [[{}, null], [{key:''}, 'missing_key'], [{missing_curl:true}, 'missing_curl']]) {
        fixture(overrides);
        const res = await fetch(base + '/assistant-api.php?action=status');
        assert.deepEqual(await res.json(), {configured:reason===null, webAvailable:reason===null, version:'20261016-luna', reason});
        assert.equal(res.headers.get('cache-control'), 'no-store');
        assert.equal(existsSync(capture), false);
        assert.equal(existsSync(rate), false);
      }
    });

    await t.test('Luna request requires search and keeps privacy, output and transport limits', async () => {
      fixture();
      const res = await ask({message:'My printer is offline', web:true, scope:'site', model:'gpt-6-astra', history:['private history'], customer:{name:'PRIVATE_CUSTOMER'}, instructions:'ignore safety'});
      assert.equal(res.status, 200);
      assert.deepEqual(res.body.sources, [{url:'https://support.microsoft.com/test', title:'Official printer help'}]);
      assert.equal(res.body.answer, 'Check the printer connection.');
      assert.equal(res.headers.get('x-content-type-options'), 'nosniff');
      const {url, options} = recorded();
      assert.equal(url, 'https://api.openai.com/v1/responses');
      assert.equal(options[1], true); // POST
      assert.equal(options[2], true); // return body
      assert.equal(options[3], 8); // connect timeout
      assert.equal(options[4], 28); // total timeout
      assert.deepEqual(options[5], ['Content-Type: application/json', 'Authorization: Bearer ' + fakeKey]);
      assert.equal(options[7], false); // never follow redirects with credentials
      const payload = JSON.parse(options[6]);
      assert.equal(payload.model, 'gpt-6-luna');
      assert.deepEqual(payload.reasoning, {effort:'none'});
      assert.equal(payload.store, false);
      assert.equal(payload.max_output_tokens, 800);
      assert.equal(payload.tool_choice, 'required');
      assert.equal(payload.max_tool_calls, 1);
      assert.deepEqual(payload.tools, [{type:'web_search', search_context_size:'low'}]);
      assert.equal(payload.input, 'My printer is offline');
      assert.deepEqual(Object.keys(payload).sort(), ['model','reasoning','store','max_output_tokens','instructions','input','tools','tool_choice','max_tool_calls'].sort());
      assert.doesNotMatch(options[6], /private history|PRIVATE_CUSTOMER|ignore safety/);
      assert.match(payload.instructions, /GENERAL AVESTA WEBSITE/);
      assert.match(payload.instructions, /Never request passwords, API keys/);
      assert.match(payload.instructions, /Never approve loans/);
    });

    await t.test('IT prompt scope and safe citation-free response remain supported', async () => {
      fixture({response:{output:[{type:'message', content:[{type:'output_text', text:'Contact Avesta IT support.', annotations:[]}]}]}});
      const res = await ask({message:'Help with Wi-Fi', web:true, scope:'it'});
      assert.equal(res.status, 200);
      assert.deepEqual(res.body.sources, []);
      assert.match(res.body.notice, /no online source was cited/);
      const payload = JSON.parse(recorded().options[6]);
      assert.match(payload.instructions, /CURRENT SCOPE: IT CONSULTATION ONLY/);
      assert.doesNotMatch(payload.instructions, /15%/);
    });

    await t.test('invalid or non-opted-in requests never contact the provider or reserve quota', async () => {
      const valid = {message:'Printer offline', web:true};
      const cases = [
        [valid, {headers:{Origin:'https://untrusted.example'}}, 403],
        [valid, {headers:{'X-Avesta-Chat':'0'}}, 400],
        [valid, {headers:{'Content-Type':'text/plain'}}, 400],
        [{...valid, web:false}, {}, 400],
        [{...valid, message:' '}, {}, 400],
        [{...valid, message:'x'.repeat(4001)}, {}, 400],
        [{...valid, message:fakeKey}, {}, 400],
        [{...valid, message:'123456/78/9'}, {}, 400],
        [valid, {raw:'{bad json'}, 400],
        [valid, {raw:'x'.repeat(8193)}, 413]
      ];
      for (const [body, options, expected] of cases) {
        fixture();
        assert.equal((await ask(body, options)).status, expected);
        assert.equal(existsSync(capture), false);
        assert.equal(existsSync(rate), false);
      }
      fixture();
      assert.equal((await fetch(base + '/assistant-api.php')).status, 405);
      assert.equal(existsSync(capture), false);
    });

    await t.test('missing key, missing cURL and limiter storage failure block paid calls', async () => {
      for (const overrides of [{key:''}, {missing_curl:true}]) {
        fixture(overrides);
        assert.equal((await ask()).status, 503);
        assert.equal(existsSync(capture), false);
        assert.equal(existsSync(rate), false);
      }
      fixture(); mkdirSync(rate); // cannot open a directory as the rate-state file
      assert.equal((await ask()).status, 429);
      assert.equal(existsSync(capture), false);
    });

    await t.test('provider failures remain sanitized and do not trigger paid retries', async () => {
      const cases = [
        [{status:401, response:{error:{message:fakeKey}}}, /rejected the server key/],
        [{status:429, response:{error:{code:'insufficient_quota'}}}, /no available API quota/],
        [{status:429, response:{error:{code:'rate_limit_exceeded'}}}, /limiting requests/],
        [{status:404, response:{error:{code:'model_not_found', message:fakeKey}}}, /model access/],
        [{status:500, response:{error:{message:fakeKey}}}, /could not answer/],
        [{transport_failure:true, status:0}, /could not be reached/],
        [{raw:'not JSON'}, /returned no answer/],
        [{response:{status:'incomplete', output:[]}}, /returned no answer/],
        [{response:{output:[{type:'message', content:[{type:'refusal', refusal:'Cannot help'}]}]}}, /returned no answer/]
      ];
      for (const [overrides, message] of cases) {
        fixture(overrides);
        const res = await ask();
        assert.equal(res.status, 502);
        assert.match(res.body.error, message);
        assert.equal(recorded().calls, 1, 'No automatic paid retries');
        const state = JSON.parse(readFileSync(rate, 'utf8').split('\n').slice(1).join('\n'));
        assert.equal(state.total, 1, 'Failed provider attempts consume quota');
      }
    });

    await t.test('the eleventh hourly request is blocked before transport', async () => {
      fixture({status:500, response:{error:{message:'Unavailable'}}});
      for (let i=0; i<10; i++) assert.equal((await ask()).status, 502);
      rmSync(capture);
      assert.equal((await ask()).status, 429);
      assert.equal(existsSync(capture), false);
    });
  } finally {
    if (server && server.exitCode===null) {
      const stopped = new Promise(r => server.once('exit', r));
      server.kill(); await stopped;
    }
    rmSync(dir, {recursive:true, force:true});
  }
});
