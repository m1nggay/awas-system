/**
 * live.js — automatic checking for the administrator (no WebSocket needed).
 *
 * About every 20 seconds the page asks /admin/live-updates for meter readings
 * and notifications newer than the ones it already has, then:
 *  - shows a small pop-up for each new reading;
 *  - updates the notification bell (count + list);
 *  - on the Meter Readings page (page 1, no filters) adds the new rows to the top.
 * Checking pauses while the tab is hidden and resumes (with an immediate
 * check) when the admin comes back.
 */
(function () {
  'use strict';

  var body = document.body;
  var url = body.getAttribute('data-live-url');
  if (!url || !window.fetch) return;

  var lastReading = parseInt(body.getAttribute('data-live-reading'), 10) || 0;
  var lastNotif = parseInt(body.getAttribute('data-live-notif'), 10) || 0;
  var INTERVAL = 20000;
  var timer = null, busy = false;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // ---- pop-ups (bottom-right) ----
  var stack = document.createElement('div');
  stack.className = 'live-toasts';
  stack.setAttribute('aria-live', 'polite');
  body.appendChild(stack);

  function toast(title, text) {
    var el = document.createElement('div');
    el.className = 'live-toast';
    el.innerHTML = '<div class="live-toast-title">' + esc(title) + '</div><div class="live-toast-text">' + esc(text) + '</div>';
    el.addEventListener('click', function () { el.remove(); });
    stack.appendChild(el);
    setTimeout(function () { el.classList.add('hide'); setTimeout(function () { el.remove(); }, 400); }, 8000);
  }

  // ---- notification bell ----
  function updateBell(unread, items) {
    var btn = document.getElementById('notifBtn');
    if (btn) {
      var badge = btn.querySelector('.notif-badge');
      if (unread > 0) {
        if (!badge) { badge = document.createElement('span'); badge.className = 'notif-badge'; btn.appendChild(badge); }
        badge.textContent = unread > 9 ? '9+' : unread;
      } else if (badge) {
        badge.remove();
      }
    }
    var panel = document.getElementById('notifPanel');
    if (!panel || !items.length) return;
    var empty = panel.querySelector('.notif-panel-empty');
    if (empty) empty.remove();
    var header = panel.querySelector('.notif-panel-header');
    items.forEach(function (n) {
      var div = document.createElement('div');
      div.className = 'notif-panel-item unread';
      div.innerHTML = '<div class="notif-panel-title">' + esc(n.title) + '</div><div class="notif-panel-msg">' + esc(n.message)
        + '</div><div class="notif-panel-time">' + esc(n.time) + '</div>';
      header.insertAdjacentElement('afterend', div);
    });
    var all = panel.querySelectorAll('.notif-panel-item');
    for (var i = 8; i < all.length; i++) all[i].remove();   // keep the newest 8, like the page
  }

  // ---- Meter Readings table ----
  function addRows(readings) {
    var table = document.querySelector('table[data-live-readings]');
    if (!table) return;
    var tbody = table.tBodies[0];
    var emptyRow = tbody.querySelector('tr.empty-row');
    if (emptyRow) emptyRow.remove();
    readings.forEach(function (r) {
      var tmp = document.createElement('tbody');
      tmp.innerHTML = r.row.trim();
      var tr = tmp.firstElementChild;
      if (!tr) return;
      tr.classList.add('live-new');
      tbody.insertBefore(tr, tbody.firstChild);
    });
  }

  function check() {
    if (busy || document.hidden) return;
    busy = true;
    fetch(url + '?reading=' + lastReading + '&notif=' + lastNotif, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (res) {
        if (res.status === 401 || res.status === 403 || res.redirected) { stop(); return null; }  // signed out
        return res.ok ? res.json() : null;
      })
      .then(function (d) {
        if (!d) return;
        lastReading = Math.max(lastReading, d.reading || 0);
        lastNotif = Math.max(lastNotif, d.notif || 0);
        if (d.readings && d.readings.length) {
          var shown = d.readings.slice(-3);
          shown.forEach(function (r) { toast('🧮 New meter reading', r.text); });
          if (d.readings.length > 3) toast('🧮 New meter readings', '+' + (d.readings.length - 3) + ' more new readings.');
          addRows(d.readings);
        }
        updateBell(d.unread || 0, d.notifications || []);
      })
      .catch(function () { /* offline for a moment — try again next time */ })
      .then(function () { busy = false; });
  }

  function start() { if (!timer) timer = setInterval(check, INTERVAL); }
  function stop() { clearInterval(timer); timer = null; }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { stop(); } else { check(); start(); }
  });
  start();
})();
