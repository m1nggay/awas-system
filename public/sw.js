/**
 * sw.js — AWAS service worker: pages keep working when the internet suddenly goes.
 *
 *  - Pages: always asked from the server first. Each page opened while online is kept on this
 *    device; offline, the kept copy is shown, or offline.html ("You're offline", Try Again, and the
 *    pages that can be opened now) when the page was never opened here.
 *  - Styles, scripts, fonts and pictures: shown from this device at once, refreshed in the background.
 *  - Anything else (saving, live updates, meter photos) is left to the page, which handles errors itself.
 *
 * Privacy: kept pages hold names and bills, so they are all removed on logout (offline.js) and
 * whenever the login page is shown (e.g. another user signing in on this device).
 */
const PAGES = 'awas-pages-v1';
const STATIC = 'awas-static-v1';
const BASE = new URL('./', self.location).pathname;   // where AWAS lives on the server
const OFFLINE = BASE + 'offline.html';
const CDN = ['cdn.jsdelivr.net', 'unpkg.com', 'fonts.googleapis.com', 'fonts.gstatic.com'];
const MAX_PAGES = 40;

self.addEventListener('install', event => {
  event.waitUntil(caches.open(STATIC).then(c => c.addAll([OFFLINE, BASE + 'assets/img/logo-round.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys()
    .then(keys => Promise.all(keys.filter(k => k.startsWith('awas-') && k !== PAGES && k !== STATIC).map(k => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('message', event => {
  if (event.data === 'forget-pages') event.waitUntil(caches.delete(PAGES));
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  if (req.mode === 'navigate' && url.origin === self.location.origin) {
    event.respondWith(page(req, url));
  } else if ((url.origin === self.location.origin && url.pathname.startsWith(BASE + 'assets/')) || CDN.includes(url.hostname)) {
    event.respondWith(asset(req));
  }
});

async function page(req, url) {
  try {
    const res = await fetch(req);
    const html = (res.headers.get('content-type') || '').includes('text/html');
    if (url.pathname === BASE + 'login') {
      await caches.delete(PAGES);
    } else if (res.ok && html && !res.redirected) {
      const cache = await caches.open(PAGES);
      const key = url.origin + url.pathname + url.search;
      await cache.delete(key);              // re-added last: the list shows the newest pages first
      await cache.put(key, res.clone());
      const keys = await cache.keys();
      await Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_PAGES)).map(k => cache.delete(k)));
    }
    return res;
  } catch (e) {
    const kept = await caches.match(url.origin + url.pathname + url.search, { cacheName: PAGES });
    return kept || (await offlinePage()) || new Response('You are offline.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
  }
}

/** offline.html, shown at the address that was asked for: its logo and links point back to AWAS's own folder. */
async function offlinePage() {
  const res = await caches.match(OFFLINE);
  if (!res) return null;
  const html = (await res.text()).replace('<head>', '<head><base href="' + BASE + '">');
  return new Response(html, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

async function asset(req) {
  const cache = await caches.open(STATIC);
  const kept = await cache.match(req);
  const fresh = fetch(req).then(res => {
    if (res.ok || res.type === 'opaque') cache.put(req, res.clone());
    return res;
  }).catch(() => null);
  return kept || (await fresh) || new Response('', { status: 504 });
}
