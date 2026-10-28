/**
 * offline.js — every page: what happens when the internet suddenly goes.
 *
 *  - A bar at the top says "No internet connection" while the phone/computer is offline,
 *    and "Back online" for a moment when it returns.
 *  - Submitting a form while offline is stopped before the browser tries: the page and
 *    everything typed stay as they are, and the bar explains why. Forms that work offline
 *    on their own (data-offline-ok, e.g. Record Meter Reading, which saves on the phone) are left alone.
 *  - A form that was sent but gets no answer (connection lost on the way) shows a note after
 *    20 seconds, instead of leaving the user staring at a page that never loads.
 */
(function () {
  var bar, hideTimer, slowTimer;

  function show(text, kind) {
    if (!bar) {
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

  window.addEventListener('offline', function () {
    show('No internet connection. What you type stays on this page; send it when you are back online.', 'off');
  });
  window.addEventListener('online', function () {
    show('Back online.', 'on');
    hide(2500);
  });

  // Capture phase: runs before the page's own submit handlers.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-offline-ok')) return;
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

  // Opening another page of AWAS while offline would only show the browser's error page: stay here.
  document.addEventListener('click', function (e) {
    if (!offline() || e.defaultPrevented || e.button !== 0) return;
    var link = e.target.closest && e.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
    var href = link.getAttribute('href') || '';
    if (href.charAt(0) === '#' || /^(javascript|mailto|tel):/.test(href)) return;
    if (new URL(link.href, location.href).origin !== location.origin) return;
    e.preventDefault();
    show('No internet connection. This page can be opened when you are back online.', 'off');
  }, true);

  // The next page is loading (or the user stayed): no note needed.
  window.addEventListener('pagehide', function () { clearTimeout(slowTimer); });

  document.addEventListener('DOMContentLoaded', function () {
    if (offline()) show('No internet connection. What you type stays on this page; send it when you are back online.', 'off');
  });
})();
