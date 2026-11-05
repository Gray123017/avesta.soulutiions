<?php
/**
 * The employer portal.
 *
 * A separate entry point on purpose. portal.php serves app/admin.php, which is
 * seven thousand lines written for people who work at Avesta; a company signing
 * in should not be served any of it, and keeping them apart means that cannot
 * happen by a mistake in a role check deep inside a page.
 *
 * Everything on this page comes from one endpoint, employerPortal, which reads
 * the employer from the signed-in account and returns a projection that
 * carries no documents, no NRC numbers, no addresses and nothing at all about
 * anybody else's staff.
 */
require __DIR__ . '/auth.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$me = av_require_login(['employer'], 'login.php');
$employerId = av_employer_of($me);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payroll — Avesta Enterprises</title>
<style>
  :root{--green:#163e33;--gold:#d98e3b;--cream:#f7f3ec;--ink:#0f2e24;--red:#b4232a}
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
       background:var(--cream);color:var(--ink);font-size:10pt;line-height:1.55}
  header{background:var(--green);color:#fff;padding:16px 22px;display:flex;
         justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
  header .brand{font-weight:800;letter-spacing:.5px}
  header .who{font-size:9pt;opacity:.85}
  header a{color:var(--gold);text-decoration:none;font-size:9pt;font-weight:700}
  main{max-width:1100px;margin:0 auto;padding:22px}
  h1{font-size:15pt;margin:0 0 4px}
  .sub{color:#5c6b64;font-size:9pt;margin:0 0 18px;max-width:70ch}
  .cards{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:20px}
  .card{background:#fff;border:1px solid #e6e1d8;border-top:3px solid var(--gold);
        border-radius:9px;padding:14px 16px}
  .card .v{font-size:17pt;font-weight:800;color:var(--green)}
  .card .l{font-size:7.5pt;text-transform:uppercase;letter-spacing:.6px;color:#8a8a8a;font-weight:700}
  .bar{display:flex;gap:10px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap}
  label{display:block;font-size:7.5pt;text-transform:uppercase;letter-spacing:.5px;
        color:#8a8a8a;font-weight:700;margin-bottom:4px}
  input,button{font-family:inherit;font-size:9.5pt}
  input{padding:8px 11px;border:1.5px solid #ddd;border-radius:7px;background:#fff}
  button{padding:9px 16px;border:none;border-radius:7px;background:var(--green);
         color:#fff;font-weight:700;cursor:pointer}
  button:hover{background:var(--gold);color:var(--green)}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:9px;overflow:hidden}
  th{background:#efe9df;text-align:left;padding:10px 12px;font-size:7.5pt;
     text-transform:uppercase;letter-spacing:.6px;color:var(--green)}
  td{padding:11px 12px;border-top:1px solid #f0ece4;font-size:9.5pt;vertical-align:top}
  .muted{color:#8a8a8a}
  .flag{color:var(--red);font-weight:700}
  .note{background:#fff;border-left:4px solid var(--gold);border-radius:8px;
        padding:12px 14px;margin-bottom:18px;font-size:9pt}
  footer{max-width:1100px;margin:0 auto;padding:0 22px 40px;color:#8a8a8a;font-size:8.5pt}
</style>
</head>
<body>
<header>
  <div>
    <div class="brand">AVESTA ENTERPRISES</div>
    <div class="who"><?= htmlspecialchars($me['name'] ?? '', ENT_QUOTES) ?> · payroll check-off</div>
  </div>
  <a href="logout.php">Sign out</a>
</header>

<main>
  <h1 id="emp-name">Your payroll</h1>
  <p class="sub">The staff you have enrolled with Avesta, what comes off each salary this month,
     and what is still owed. You see only your own staff, and only what you need to run the
     deduction &mdash; never the documents or personal details they gave us.</p>

  <div class="note">
    Deduct the amounts in <strong>This month</strong> from the matching payroll numbers and remit
    the total to Avesta. If somebody leaves, tell us: the balance is settled from their final pay.
  </div>

  <div class="bar">
    <div><label for="period">Month</label><input id="period" type="month"></div>
    <button type="button" id="go">Show</button>
    <button type="button" id="copy" style="background:#fff;color:var(--green);border:1.5px solid #ddd">Copy for payroll</button>
  </div>

  <div class="cards" id="totals"></div>
  <div id="out"></div>
  <p id="msg" aria-live="polite"></p>
</main>

<footer>
  Questions about a deduction? Contact Avesta Enterprises. Amounts shown are what was outstanding
  when this page loaded.
</footer>

<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var current = null;

  function kwacha(ngwee) {
    return 'K ' + ((ngwee || 0) / 100).toLocaleString('en-ZM',
      { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;     // textContent: these are people
    return e;
  }
  function say(t, bad) { $('msg').textContent = t || ''; $('msg').style.color = bad ? '#b4232a' : '#163e33'; }

  function card(v, l) {
    var d = el('div', 'card');
    d.appendChild(el('div', 'v', v));
    d.appendChild(el('div', 'l', l));
    return d;
  }

  function render(view) {
    current = view;
    if (view.employer) $('emp-name').textContent = view.employer.name + ' — payroll';

    var t = $('totals');
    t.textContent = '';
    t.appendChild(card(String(view.totals.staff), 'Staff enrolled'));
    t.appendChild(card(String(view.totals.with_loans), 'With a loan'));
    t.appendChild(card(kwacha(view.totals.due_this_month_ngwee), 'To deduct this month'));
    t.appendChild(card(kwacha(view.totals.outstanding_ngwee), 'Still owed in total'));

    var table = el('table');
    var head = el('thead'), hr = el('tr');
    ['Payroll no.', 'Name', 'Branch', 'This month', 'Still owed', 'Due by'].forEach(function (h) {
      hr.appendChild(el('th', null, h)); });
    head.appendChild(hr);
    table.appendChild(head);

    var body = el('tbody');
    view.staff.forEach(function (p) {
      var due = 0, owed = 0, dates = [], late = false;
      p.loans.forEach(function (l) {
        due += l.due_this_month_ngwee; owed += l.outstanding_ngwee;
        if (l.due_date) dates.push(l.due_date);
        if (l.overdue) late = true;
      });

      var tr = el('tr');
      tr.appendChild(el('td', null, p.payroll_no || '—'));

      var who = el('td');
      who.appendChild(el('div', null, p.name));
      if (p.left) {
        who.appendChild(el('div', 'flag',
          'Left ' + (p.left_at || '') + ' — settle from final pay'));
      }
      tr.appendChild(who);

      tr.appendChild(el('td', null, p.branch || '—'));
      tr.appendChild(el('td', p.loans.length ? null : 'muted',
        p.loans.length ? kwacha(due) : '—'));
      tr.appendChild(el('td', p.loans.length ? null : 'muted',
        p.loans.length ? kwacha(owed) : '—'));

      var when = el('td', late ? 'flag' : null,
        dates.length ? dates.sort()[0] + (late ? ' — overdue' : '') : '—');
      tr.appendChild(when);
      body.appendChild(tr);
    });
    table.appendChild(body);

    $('out').textContent = '';
    if (!view.staff.length) {
      $('out').appendChild(el('p', 'muted',
        'Nobody is enrolled yet. Avesta adds your staff to the register.'));
    } else {
      $('out').appendChild(table);
    }
  }

  function load() {
    say('Loading…');
    fetch('/api.php?action=employerPortal&period=' + encodeURIComponent($('period').value),
      { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) { say(d && d.error ? d.error : 'Could not load your payroll.', true); return; }
        say('');
        render(d.view);
      })
      .catch(function () { say('Could not reach Avesta. Try again in a moment.', true); });
  }

  // The thing a payroll officer actually does with this page.
  $('copy').addEventListener('click', function () {
    if (!current) return;
    var lines = ['Avesta Enterprises — deductions for ' + current.period,
                 (current.employer ? current.employer.name : ''), ''];
    current.staff.forEach(function (p) {
      var due = 0;
      p.loans.forEach(function (l) { due += l.due_this_month_ngwee; });
      if (due > 0) lines.push([p.payroll_no, p.name, kwacha(due)].join('\t'));
    });
    lines.push('', 'Total to remit: ' + kwacha(current.totals.due_this_month_ngwee));
    var text = lines.join('\n');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () { say('Copied.'); },
        function () { window.prompt('Copy this:', text); });
    } else { window.prompt('Copy this:', text); }
  });

  $('go').addEventListener('click', load);
  $('period').value = new Date().toISOString().slice(0, 7);
  load();
})();
</script>
</body>
</html>
