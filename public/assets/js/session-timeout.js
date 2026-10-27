/**
 * session-timeout.js — the inactivity sign-out, made visible (every signed-in page).
 *
 * The server signs a user out after the idle time (IdleTimeout). This page:
 *  - one minute before, shows "You'll be signed out in 60 seconds" with a
 *    "Stay signed in" button;
 *  - at the end, goes to the login page ("Your session expired…") instead of
 *    waiting for the next click.
 * Before warning it asks the server how much time is really left, so activity
 * in another tab keeps this one signed in too. Those checks are sent as
 * background requests, so they never count as activity themselves.
 */
(function () {
  'use strict';

  var cfg = window.AWAS_SESSION;
  if (!cfg || !window.fetch) return;

  var WARN = 60;              // seconds of warning before the sign-out
  var timer = null, tick = null, box = null;
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;

  function signedOut() { window.location.href = cfg.loginUrl; }

  function schedule(seconds) {
    clearTimeout(timer);
    timer = setTimeout(check, Math.max(5, seconds - WARN) * 1000);
  }

  function check() {
    fetch(cfg.statusUrl, { credentials: 'same-origin', cache: 'no-store',
      headers: { 'Accept': 'application/json', 'X-AWAS-Background': '1', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        if (res.status === 401 || res.status === 419 || res.redirected) { signedOut(); return null; }
        return res.ok ? res.json() : null;
      })
      .then(function (d) {
        if (!d) { schedule(WARN + 30); return; }       // network trouble: look again shortly
        if (d.remaining > WARN + 5) { hide(); schedule(d.remaining); }
        else if (d.remaining <= 0) { signedOut(); }
        else { warn(d.remaining); }
      })
      .catch(function () { schedule(WARN + 30); });
  }

  function warn(seconds) {
    if (!box) {
      box = document.createElement('div');
      box.className = 'session-warning';
      box.setAttribute('role', 'alertdialog');
      box.setAttribute('aria-live', 'assertive');
      box.innerHTML = '<i class="ph ph-clock-countdown" aria-hidden="true"></i>'
        + '<div><strong>Still there?</strong><span>You\'ll be signed out in <b data-left></b> seconds due to inactivity.</span></div>'
        + '<button type="button" class="btn btn-primary btn-sm">Stay signed in</button>';
      box.querySelector('button').addEventListener('click', stay);
      document.body.appendChild(box);
    }
    var end = Date.now() + seconds * 1000;
    var left = box.querySelector('[data-left]');
    clearInterval(tick);
    var show = function () {
      var s = Math.max(0, Math.round((end - Date.now()) / 1000));
      left.textContent = s;
      if (s <= 0) { clearInterval(tick); check(); }   // the server decides: signed out, or active in another tab
    };
    show();
    tick = setInterval(show, 1000);
    box.hidden = false;
    box.querySelector('button').focus();
  }

  function hide() {
    clearInterval(tick);
    if (box) box.hidden = true;
  }

  function stay() {
    var btn = box.querySelector('button');
    btn.disabled = true;
    fetch(cfg.keepAliveUrl, { method: 'POST', credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        if (res.status === 401 || res.status === 419) { signedOut(); return null; }
        return res.ok ? res.json() : null;
      })
      .then(function (d) { if (d) { hide(); schedule(d.remaining); } })
      .catch(function () {})
      .then(function () { btn.disabled = false; });
  }

  // Coming back to a tab that sat in the background: timers there may have been slowed, so look now.
  document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });

  schedule(cfg.timeout);
})();
