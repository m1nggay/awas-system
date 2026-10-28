/**
 * offline.js — every page: what happens when the internet suddenly goes.
 *
 *  - Starts the service worker (sw.js): pages opened before can still be opened offline, and a page
 *    that was never opened shows "You're offline" with Try Again and the pages that can be opened.
 *  - A bar at the bottom says "No internet connection" while offline, and "Back online" when it returns.
 *  - Submitting a form while offline is stopped before the browser tries: the page and everything
 *    typed stay as they are. Forms that work offline on their own (data-offline-ok, e.g. Record Meter
 *    Reading, which saves on the phone) are left alone.
 *  - A form that was sent but gets no answer (connection lost on the way) shows a note after 20 seconds.
 *  - Logging out removes the pages kept on this device (they hold names and bills).
 */
(function () {
  var PAGES = 'awas-pages-v1';
  var bar, hideTimer, slowTimer;
  var swUrl = document.currentScript && new URL('../../sw.js', document.currentScript.src).href;

  if ('serviceWorker' in navigator && window.isSecureContext && swUrl) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(swUrl).catch(function (e) { console.warn('AWAS offline pages unavailable', e); });
    });
  }

  function show(text, kind) {
    if (!bar) {
      // Its own look, so the bar is the same on every page (some pages don't load style.css).
      var css = document.createElement('style');
      css.textContent = '.net-bar{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:2000;'
        + 'width:max-content;max-width:calc(100% - 32px);padding:10px 16px;border-radius:10px;font:600 13.5px/1.4 system-ui,sans-serif;'
        + 'color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.18);text-align:center;}'
        + '.net-bar[hidden]{display:none;}.net-bar.is-off{background:#b8382a;}.net-bar.is-slow{background:#946313;}.net-bar.is-on{background:#1e7e34;}';
      document.head.appendChild(css);
      bar = document.createElement('div');
      bar.className = 'net-bar';
      bar.setAttribute('role', 'status');
      bar.setAttribute('aria-live', 'polite');
      document.body.appendChild(bar);
    }
    clearTimeout(hideTimer);
    bar.className = 'net-bar is-' + kind;
    bar.textContent = text;
    bar.hidden = false;
  }
  function hide(after) {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(function () { if (bar) bar.hidden = true; }, after || 0);
  }
  var offline = function () { return navigator.onLine === false; };
  var OFF = 'No internet connection. Pages you opened before can still be viewed; sending waits until you are back online.';

  window.addEventListener('offline', function () { show(OFF, 'off'); });
  window.addEventListener('online', function () {
    show('Back online.', 'on');
    hide(2500);
  });

  // Capture phase: runs before the page's own submit handlers.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (/\/logout$/.test(new URL(form.action, location.href).pathname)) forgetPages();
    if (form.hasAttribute('data-offline-ok')) return;
    if (offline()) {
      e.preventDefault();
      e.stopImmediatePropagation();
      show('No internet connection. Nothing was sent — your entries are still here. Try again when you are back online.', 'off');
      return;
    }
    // Sent: if the next page doesn't come within 20 seconds, the connection was probably lost.
    clearTimeout(slowTimer);
    slowTimer = setTimeout(function () {
      show(offline() ? 'The internet was lost while sending. Check your connection, then try again.'
        : 'This is taking long — the internet may be slow. Please wait, or check your connection and try again.', 'slow');
    }, 20000);
  }, true);

  function forgetPages() {
    try {
      if (window.caches) caches.delete(PAGES);
      if (navigator.serviceWorker && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage('forget-pages');
    } catch (e) { /* nothing kept */ }
  }

  // The next page is loading (or the user stayed): no note needed.
  window.addEventListener('pagehide', function () { clearTimeout(slowTimer); });

  document.addEventListener('DOMContentLoaded', function () {
    if (offline()) show(OFF, 'off');
  });
})();
