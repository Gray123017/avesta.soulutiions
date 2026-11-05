<?php
/**
 * A borrower's own account.
 *
 * Their loans, what they have paid, what is left, and — when a loan has run
 * past its date — exactly what the late charge did to the figure. The price of
 * credit is published before anybody borrows; the arithmetic afterwards should
 * not be something they have to ring up and ask about.
 *
 * A separate entry point, like employer.php and for the same reason: this page
 * never loads a line of the staff portal.
 *
 * Everything here comes from getRecords, which filters to the signed-in
 * account on the server. Nothing on this page asks for a record by id.
 */
require __DIR__ . '/auth.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$me = av_require_login(['borrower'], 'login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My account — Avesta Enterprises</title>
<style>
  :root{--green:#163e33;--gold:#d98e3b;--cream:#f7f3ec;--ink:#0f2e24;--red:#b4232a}
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
       background:var(--cream);color:var(--ink);font-size:10pt;line-height:1.55}
  header{background:var(--green);color:#fff;padding:16px 22px;display:flex;
         justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
  header .brand{font-weight:800;letter-spacing:.5px}
  header .who{font-size:9pt;opacity:.85}
  header a{color:var(--gold);text-decoration:none;font-size:9pt;font-weight:700;margin-left:16px}
  main{max-width:820px;margin:0 auto;padding:22px}
  h1{font-size:15pt;margin:0 0 4px}
  .sub{color:#5c6b64;font-size:9pt;margin:0 0 20px;max-width:66ch}
  .loan{background:#fff;border:1px solid #e6e1d8;border-top:3px solid var(--gold);
        border-radius:10px;padding:18px 20px;margin-bottom:16px}
  .loan.late{border-top-color:var(--red)}
  .loan h2{font-size:11pt;margin:0 0 2px}
  .ref{font-size:8pt;color:#8a8a8a;text-transform:uppercase;letter-spacing:.6px}
  .grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin:14px 0}
  .g .v{font-size:12.5pt;font-weight:800;color:var(--green)}
  .g .l{font-size:7.5pt;text-transform:uppercase;letter-spacing:.5px;color:#8a8a8a;font-weight:700}
  .bar{height:8px;background:#efe9df;border-radius:5px;overflow:hidden;margin:4px 0 14px}
  .bar>span{display:block;height:100%;background:var(--green)}
  .late-box{background:#fdf1f1;border-left:4px solid var(--red);border-radius:8px;
            padding:12px 14px;font-size:9pt;margin-top:4px}
  .late-box strong{color:var(--red)}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th{text-align:left;font-size:7.5pt;text-transform:uppercase;letter-spacing:.5px;
     color:#8a8a8a;padding:6px 0;border-bottom:1px solid #eee}
  td{padding:7px 0;border-bottom:1px solid #f4f1ea;font-size:9.5pt}
  .muted{color:#8a8a8a}
  footer{max-width:820px;margin:0 auto;padding:0 22px 40px;color:#8a8a8a;font-size:8.5pt}
</style>
</head>
<body>
<header>
  <div>
    <div class="brand">AVESTA ENTERPRISES</div>
    <div class="who"><?= htmlspecialchars($me['name'] ?? '', ENT_QUOTES) ?></div>
  </div>
  <div>
    <a href="index.php">Apply for a loan</a>
    <a href="logout.php">Sign out</a>
  </div>
</header>

<main>
  <h1>My account</h1>
  <p class="sub">Your loans with Avesta, what you have paid and what is left. Nobody else can
     see this page, and you cannot see anybody else's.</p>
  <div id="out"><p class="muted">Loading…</p></div>
</main>

<footer>
  Something here look wrong? Contact Avesta Enterprises before the due date — we would rather
  fix a mistake than charge you for one.
</footer>

<script>
(function () {
  function kwacha(n) {
    return 'K ' + Number(n || 0).toLocaleString('en-ZM',
      { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function g(value, label) {
    var d = el('div', 'g');
    d.appendChild(el('div', 'v', value));
    d.appendChild(el('div', 'l', label));
    return d;
  }

  function loanCard(rec) {
    var s = rec._summary || {};
    var card = el('div', 'loan' + (s.days_overdue > 0 ? ' late' : ''));

    card.appendChild(el('h2', null, kwacha(s.principal) + ' borrowed'));
    card.appendChild(el('div', 'ref', (rec._key || '') + ' · ' + (rec.status || '')));

    var pct = Math.max(0, Math.min(100, s.percent_paid || 0));
    var bar = el('div', 'bar');
    var fill = el('span');
    fill.style.width = pct + '%';
    bar.appendChild(fill);

    var grid = el('div', 'grid');
    grid.appendChild(g(kwacha(s.total_due), 'Total to repay'));
    grid.appendChild(g(kwacha(s.paid), 'Paid so far'));
    grid.appendChild(g(kwacha(s.balance), 'Still to pay'));
    grid.appendChild(g(s.due_date || '—', 'Due by'));
    card.appendChild(grid);
    card.appendChild(bar);

    if (s.days_overdue > 0) {
      card.appendChild(el('p', null, 'This loan is ' + s.days_overdue + ' day(s) past its due date.'));
    } else if (s.days_left !== null && s.days_left !== undefined && !s.settled) {
      card.appendChild(el('p', 'muted', s.days_left + ' day(s) to go.'));
    }

    // The late charge, shown as arithmetic rather than as a bigger number with
    // no explanation. penalty_applied is what the server decided; this only
    // reads it back.
    if (s.penalty_applied) {
      var box = el('div', 'late-box');
      box.appendChild(el('div', null,
        'Because this loan passed its due date, the rate changed from '
        + s.signed_rate + '% to ' + s.penalty_rate + '%.'));
      var work = el('div');
      work.style.marginTop = '6px';
      work.appendChild(el('span', null, 'At the agreed rate you would owe '
        + kwacha(s.signed_total) + '. '));
      var extra = el('strong', null, 'The late charge adds ' + kwacha(s.penalty_amount) + '.');
      work.appendChild(extra);
      work.appendChild(el('span', null, ' Your total is now ' + kwacha(s.total_due) + '.'));
      box.appendChild(work);
      box.appendChild(el('div', 'muted',
        'It does not grow further the longer it runs — 35% is the whole late charge.'));
      card.appendChild(box);
    }

    var payments = rec._payments || [];
    if (payments.length) {
      var table = el('table');
      var head = el('thead'), hr = el('tr');
      ['Date', 'Amount', 'How'].forEach(function (h) { hr.appendChild(el('th', null, h)); });
      head.appendChild(hr); table.appendChild(head);
      var body = el('tbody');
      payments.forEach(function (p) {
        var tr = el('tr');
        tr.appendChild(el('td', null, p.date || '—'));
        tr.appendChild(el('td', null, kwacha(p.amount)));
        tr.appendChild(el('td', null, p.method || '—'));
        body.appendChild(tr);
      });
      table.appendChild(body);
      card.appendChild(el('div', 'ref', 'Payments received'));
      card.appendChild(table);
    }
    return card;
  }

  fetch('/api.php?action=getRecords', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var out = document.getElementById('out');
      out.textContent = '';
      var records = (d && d.records) || [];
      if (!records.length) {
        out.appendChild(el('p', 'muted',
          'You have no applications yet. Use "Apply for a loan" above to start one.'));
        return;
      }
      records.forEach(function (r) { out.appendChild(loanCard(r)); });
    })
    .catch(function () {
      document.getElementById('out').textContent =
        'Could not load your account. Please try again in a moment.';
    });
})();
</script>
</body>
</html>
