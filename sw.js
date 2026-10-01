/* Avesta — service worker.
 *
 * WHAT IS CACHED, AND WHAT IS DELIBERATELY NOT.
 *
 * This site sits behind a login and holds NRC numbers, payslips and loan
 * records. A cache is a copy on the device, readable by anything that later
 * gets at that device. So:
 *
 *   cached      icons, the manifest — static, public, identical for everyone
 *   NEVER       api.php (records, enquiries, the audit log, sessions)
 *   NEVER       any page: loans.php, it.php, index.php, portal.php, app/*
 *
 * Pages are not cached because every one of them is rendered for a signed-in
 * person and carries their name and role. Caching them would leave one user's
 * page sitting on the device for the next person to open — and would show a
 * signed-out visitor a page they should have been redirected away from.
 *
 * The offline page below is a plain, personal-data-free notice. That is the
 * honest trade: the app installs and opens instantly, and tells you plainly
 * when it needs a connection, rather than showing stale money.
 */

const VERSION = 'avesta-v1';
const SHELL = [
  'icons/icon-192.png',
  'icons/icon-512.png',
  'icons/maskable-192.png',
  'icons/maskable-512.png',
  'manifest.json'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(VERSION)
      .then((c) => c.addAll(SHELL))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())   // a missing icon must not block install
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

const OFFLINE = `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Avesta — no connection</title>
<style>
 body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#163E33;color:#fff;
  min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;padding:28px;
  text-align:center;line-height:1.6}
 .m{width:56px;height:56px;border-radius:15px;margin:0 auto 16px;display:grid;place-items:center;
  border:2px solid #E9B06A;color:#E9B06A;font-weight:800;font-size:20px}
 h1{font-size:19px;margin:0 0 8px}
 p{font-size:14px;color:rgba(255,255,255,.7);max-width:24rem;margin:0 auto 18px}
 button{background:#E9B06A;color:#163E33;border:none;border-radius:9px;padding:12px 22px;
  font-size:14px;font-weight:700;font-family:inherit;cursor:pointer}
 a{color:#E9B06A}
</style></head><body><div>
 <div class="m">AE</div>
 <h1>No connection</h1>
 <p>Avesta needs a connection to show your account. Your details are kept on our
    servers, not on this device, so nothing is shown until you are back online.</p>
 <button onclick="location.reload()">Try again</button>
 <p style="margin-top:20px;font-size:12.5px">Urgent? Call
    <a href="tel:+260769974200">+260 769 974 200</a></p>
</div></body></html>`;

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                 // never touch POSTs

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;  // leave other hosts alone

  // Anything that can carry personal data goes straight to the network and is
  // never stored. If the network is down, say so rather than showing old data.
  const isData = url.pathname.endsWith('.php') || url.pathname.includes('/documents/');
  if (isData) {
    e.respondWith(
      fetch(req).catch(() =>
        req.mode === 'navigate'
          ? new Response(OFFLINE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } })
          : new Response('{"ok":false,"error":"offline"}',
                         { status: 503, headers: { 'Content-Type': 'application/json' } })
      )
    );
    return;
  }

  // Static, public assets only: serve from cache, refresh in the background.
  e.respondWith(
    caches.match(req).then((hit) => {
      const live = fetch(req).then((res) => {
        if (res && res.ok && res.type === 'basic') {
          const copy = res.clone();
          caches.open(VERSION).then((c) => c.put(req, copy));
        }
        return res;
      }).catch(() => hit);
      return hit || live;
    })
  );
});
