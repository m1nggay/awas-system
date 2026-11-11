/**
 * forms.js — small form helpers shared by the signed-in and sign-in layouts:
 *  - password requirement checklist ([data-password-hint="<input id>"]) that
 *    also blocks submitting a password that doesn't meet the rule, with a
 *    Weak / Medium / Strong indicator;
 *  - a show/hide (eye) button on every password field;
 *  - digits-only inputs ([data-digits-only], e.g. Meter Number; optional data-max-digits);
 *  - countdown buttons ([data-countdown="<seconds>"]), e.g. "Resend code in 59 seconds".
 * The server validates everything again; these only give instant feedback.
 */
(function () {
  'use strict';

  var RULES = {
    length: function (v) { return v.length >= 8; },
    capital: function (v) { return /[A-Z]/.test(v); },
    number: function (v) { return /\d/.test(v); },
    symbol: function (v) { return /[^A-Za-z0-9]/.test(v); },
  };

  function passwordOk(v) {
    return Object.keys(RULES).every(function (k) { return RULES[k](v); });
  }

  /** Weak: misses a rule. Medium: meets the rules. Strong: meets them with 12+ characters. */
  function strength(v) {
    if (!passwordOk(v)) return { level: 'weak', label: __t('Weak password'), width: Math.max(10, Object.keys(RULES).filter(function (k) { return RULES[k](v); }).length * 15) };
    if (v.length >= 12) return { level: 'strong', label: __t('Strong password'), width: 100 };
    return { level: 'medium', label: __t('Medium password — use 12 or more characters to make it strong'), width: 66 };
  }

  function initPasswordHints() {
    document.querySelectorAll('[data-password-hint]').forEach(function (hint) {
      var input = document.getElementById(hint.getAttribute('data-password-hint'));
      if (!input) return;

      var meter = document.createElement('div');
      meter.className = 'pw-strength';
      meter.setAttribute('aria-live', 'polite');
      meter.innerHTML = '<div class="pw-strength-bar"><span></span></div><div class="pw-strength-label"></div>';
      hint.insertBefore(meter, hint.firstChild);

      function render() {
        var st = strength(input.value);
        meter.hidden = input.value === '';
        meter.className = 'pw-strength ' + st.level;
        meter.querySelector('span').style.width = st.width + '%';
        meter.querySelector('.pw-strength-label').textContent = st.label;
        hint.querySelectorAll('[data-rule]').forEach(function (li) {
          var ok = RULES[li.getAttribute('data-rule')](input.value);
          li.textContent = (ok ? '✓ ' : '○ ') + li.textContent.replace(/^[✓○] /, '');
          li.style.color = ok ? '#1a7a68' : '';
        });
        input.setCustomValidity(input.value === '' || passwordOk(input.value)
          ? '' : __t('Password must contain at least 8 characters, including an uppercase letter, a number, and a symbol.'));
      }
      input.addEventListener('input', render);
      render();
    });
  }

  /** Eye button inside every password field to show or hide what was typed. */
  function initPasswordToggles() {
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
      if (input.closest('.pw-wrap')) return;
      var wrap = document.createElement('div');
      wrap.className = 'pw-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pw-toggle';
      btn.setAttribute('aria-label', __t('Show password'));
      btn.title = __t('Show password');
      btn.innerHTML = EYE;
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = show ? EYE_OFF : EYE;
        btn.title = show ? __t('Hide password') : __t('Show password');
        btn.setAttribute('aria-label', btn.title);
        input.focus();
      });
      wrap.appendChild(btn);
    });
  }

  var EYE = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

  function initDigitsOnly() {
    document.querySelectorAll('[data-digits-only]').forEach(function (input) {
      input.addEventListener('input', function () {
        var cleaned = input.value.replace(/\D+/g, '');
        var max = parseInt(input.getAttribute('data-max-digits'), 10);
        if (max > 0) cleaned = cleaned.slice(0, max);
        if (cleaned !== input.value) input.value = cleaned;
      });
    });
  }

  function initCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach(function (btn) {
      var left = parseInt(btn.getAttribute('data-countdown'), 10) || 0;
      var label = btn.getAttribute('data-label') || btn.textContent.trim();
      if (left <= 0) return;

      btn.disabled = true;
      (function tick() {
        if (left <= 0) {
          btn.disabled = false;
          btn.textContent = label;
          return;
        }
        btn.textContent = __t(left === 1 ? ':label in 1 second' : ':label in :n seconds', { label: label, n: left });
        left--;
        setTimeout(tick, 1000);
      })();
    });
  }

  /**
   * <form data-loading-form>: once it really submits, its submit button shows a spinner
   * (and data-loading-text) and is disabled, so it can't be sent twice. Listens on the
   * document, so a form handler that cancels the submit (e.g. a confirm) runs first.
   */
  function initLoadingForms() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (e.defaultPrevented || !form.hasAttribute || !form.hasAttribute('data-loading-form')) return;
      var btn = form.querySelector('button[type="submit"], button:not([type])');
      if (!btn || btn.disabled) return;
      btn.dataset.label = btn.innerHTML;
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
      btn.innerHTML = '<span class="spinner-border" role="status" aria-hidden="true"></span> '
        + (btn.getAttribute('data-loading-text') || btn.textContent.trim());
    });
    // Back button: the browser may show the page as it was left — give the button back.
    window.addEventListener('pageshow', function () {
      document.querySelectorAll('form[data-loading-form] [aria-busy="true"]').forEach(function (btn) {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
      });
    });
  }

  /**
   * <input data-match="password">: a confirm-password field. Shows "✓ Passwords match" /
   * "✗ Passwords don't match" in [data-match-status="<its id>"] and blocks submitting a mismatch.
   */
  function initPasswordMatch() {
    document.querySelectorAll('[data-match]').forEach(function (confirm) {
      var original = document.getElementById(confirm.getAttribute('data-match'));
      var out = document.querySelector('[data-match-status="' + confirm.id + '"]');
      if (!original) return;
      function render() {
        var match = confirm.value === original.value;
        confirm.setCustomValidity(confirm.value === '' || match ? '' : __t('Passwords do not match.'));
        if (!out) return;
        if (confirm.value === '') { out.textContent = ''; out.className = 'field-status'; return; }
        out.textContent = match ? '✓ ' + __t('Passwords match') : '✗ ' + __t('Passwords don\'t match');
        out.className = 'field-status ' + (match ? 'ok' : 'bad');
      }
      confirm.addEventListener('input', render);
      original.addEventListener('input', function () { if (confirm.value !== '') render(); });
      render();
    });
  }

  initLoadingForms();

  document.addEventListener('DOMContentLoaded', function () {
    initPasswordToggles();
    initPasswordHints();
    initPasswordMatch();
    initDigitsOnly();
    initCountdowns();
  });
})();
