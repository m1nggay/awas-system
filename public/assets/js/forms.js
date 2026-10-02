/**
 * forms.js — small form helpers shared by the signed-in and sign-in layouts:
 *  - password requirement checklist ([data-password-hint="<input id>"]) that
 *    also blocks submitting a password that doesn't meet the rule;
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

  function initPasswordHints() {
    document.querySelectorAll('[data-password-hint]').forEach(function (hint) {
      var input = document.getElementById(hint.getAttribute('data-password-hint'));
      if (!input) return;

      function render() {
        hint.querySelectorAll('[data-rule]').forEach(function (li) {
          var ok = RULES[li.getAttribute('data-rule')](input.value);
          li.textContent = (ok ? '✓ ' : '○ ') + li.textContent.replace(/^[✓○] /, '');
          li.style.color = ok ? '#1a7a68' : '';
        });
        input.setCustomValidity(input.value === '' || passwordOk(input.value)
          ? '' : 'Password must contain at least 8 characters, including a capital letter, a number, and a symbol.');
      }
      input.addEventListener('input', render);
      render();
    });
  }

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
        btn.textContent = label + ' in ' + left + (left === 1 ? ' second' : ' seconds');
        left--;
        setTimeout(tick, 1000);
      })();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initPasswordHints();
    initDigitsOnly();
    initCountdowns();
  });
})();
