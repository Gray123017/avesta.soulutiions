import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Window } from 'happy-dom';

/* Location is not optional.
 *
 * It used to be a button somebody could walk past, which meant most
 * applications arrived with no location at all and the field was worthless.
 * Now the application cannot be submitted without either a captured position
 * or a stated reason why there is not one — and a stated reason is recorded
 * and shown to staff, rather than leaving the same blank as before.
 *
 * The reason it is not an absolute block: a phone with location switched off,
 * or a browser that denies the permission outright, would otherwise lose the
 * application entirely with nothing anybody can do about it. "Declined, and
 * here is why" is information staff can act on. A blank is not.
 */

const source = readFileSync('app/index.php', 'utf8');

function section(start, end) {
  const a = source.indexOf(start), b = source.indexOf(end, a + start.length);
  assert.ok(a >= 0 && b > a, 'could not find section: ' + start);
  return source.slice(a, b);
}

const MARKUP = section('<div class="f-head">📍 Location Verification', '<!-- SIGNATURES -->');
const JS = section('// ── LOCATION REQUIRED ───', '// ── END LOCATION REQUIRED');

function boot() {
  const w = new Window({ settings: {
    enableJavaScriptEvaluation: true, suppressInsecureJavaScriptEnvironmentWarning: true } });
  w.document.body.innerHTML = '<div class="f-section">' + MARKUP + '</div>';
  w.eval('function saveProgress(){}' + JS);
  return w;
}
const $ = (w, id) => w.document.getElementById(id);

test('with no location and no reason, the application cannot go', async () => {
  const w = boot();
  assert.equal(w.avGpsState(), 'none');
  assert.match(w.avGpsMissing(), /location/i);
  await w.happyDOM.close();
});

test('a captured position satisfies it', async () => {
  const w = boot();
  $(w, 'gps_lat').value = '-12.958870';
  $(w, 'gps_lng').value = '28.636690';
  $(w, 'gps_accuracy').value = '14';

  assert.equal(w.avGpsState(), 'captured');
  assert.equal(w.avGpsMissing(), '');
  await w.happyDOM.close();
});

test('a stated reason satisfies it, and is what gets recorded', async () => {
  const w = boot();
  // The applicant says why there is no position. This is the path that keeps
  // somebody with location switched off from losing their application.
  $(w, 'gps_declined_reason').value = 'Phone has no GPS and I am indoors at the branch';
  $(w, 'gps-declined').checked = true;
  $(w, 'gps-declined').dispatchEvent(new w.Event('change'));

  assert.equal(w.avGpsState(), 'declined');
  assert.equal(w.avGpsMissing(), '');
  await w.happyDOM.close();
});

test('saying you declined without saying why does not count', async () => {
  const w = boot();
  $(w, 'gps-declined').checked = true;
  $(w, 'gps-declined').dispatchEvent(new w.Event('change'));

  // A tick box with nothing behind it is the old blank field wearing a hat.
  assert.equal(w.avGpsState(), 'none');
  assert.match(w.avGpsMissing(), /why/i);

  $(w, 'gps_declined_reason').value = '   ';
  assert.equal(w.avGpsState(), 'none');
  await w.happyDOM.close();
});

test('a captured position wins even if the box was ticked earlier', async () => {
  const w = boot();
  $(w, 'gps-declined').checked = true;
  $(w, 'gps_declined_reason').value = 'Denied at first';
  $(w, 'gps-declined').dispatchEvent(new w.Event('change'));
  assert.equal(w.avGpsState(), 'declined');

  // They then allowed it. The real position is the better answer.
  $(w, 'gps_lat').value = '-12.958870';
  $(w, 'gps_lng').value = '28.636690';
  assert.equal(w.avGpsState(), 'captured');
  await w.happyDOM.close();
});

test('the reason box only appears once they say they cannot', async () => {
  const w = boot();
  const row = $(w, 'gps-declined-row');
  assert.equal(row.style.display, 'none');

  $(w, 'gps-declined').checked = true;
  $(w, 'gps-declined').dispatchEvent(new w.Event('change'));
  assert.notEqual(row.style.display, 'none');

  $(w, 'gps-declined').checked = false;
  $(w, 'gps-declined').dispatchEvent(new w.Event('change'));
  assert.equal(row.style.display, 'none');
  await w.happyDOM.close();
});

test('the application carries the location, or why it has none', () => {
  // The whole data object: the location fields sit after security_type.
  const payload = section('const data = {\n', '\n  };');
  assert.match(payload, /gps_latitude:/);
  assert.match(payload, /gps_status:\s*avGpsState\(\)/);
  assert.match(payload, /gps_declined_reason:/);
});

test('step 5 will not pass without it', () => {
  // The gate, not just the helper: the wizard has to refuse to move on.
  const validate = section('function validateStep(', 'function updateFieldCounter');
  assert.match(validate, /avGpsMissing\(\)/);
  assert.match(validate, /errorMessages\.push/);
});

test('the consent copy says it is required, not requested', () => {
  // It used to say Avesta "requests" the location, next to a button nobody
  // had to press. The words and the behaviour have to agree.
  assert.doesNotMatch(MARKUP, /requests your current GPS location/);
  assert.match(MARKUP, /required/i);
});
