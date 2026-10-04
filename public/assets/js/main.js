/**
 * main.js — shared UI behaviors: client-side table search/filter,
 * confirm-before-delete. Modals/sidebar/notif dropdown are Bootstrap's own
 * JS components now (data-bs-* attributes), no custom JS needed for those.
 */

document.addEventListener('DOMContentLoaded', function () {

  // ---- Confirm before destructive actions ----------------------------
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('submit', function (e) {
      if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
        e.preventDefault();
      }
    });
    el.addEventListener('click', function (e) {
      if (el.tagName === 'A' && !confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
        e.preventDefault();
      }
    });
  });

  // ---- Client-side live search for tables -----------------------------
  document.querySelectorAll('[data-table-search]').forEach(input => {
    const targetTable = document.getElementById(input.getAttribute('data-table-search'));
    if (!targetTable) return;
    input.addEventListener('keyup', () => {
      const term = input.value.toLowerCase();
      targetTable.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
      });
    });
  });

  // ---- Auto-dismiss flash alerts --------------------------------------
  document.querySelectorAll('.alert[data-autohide]').forEach(alert => {
    setTimeout(() => { alert.style.transition = 'opacity .4s'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 400); }, 4000);
  });
});

/** Auto-calculate consumption preview in the meter reading form. */
function updateConsumptionPreview() {
  const prev = parseFloat(document.getElementById('previous_reading')?.value) || 0;
  const curr = parseFloat(document.getElementById('current_reading')?.value) || 0;
  const out = document.getElementById('consumptionPreview');
  if (out) {
    const diff = curr - prev;
    out.textContent = (isFinite(diff) ? diff.toFixed(2) : '0.00') + ' cu.m.';
    out.style.color = diff < 0 ? '#e5533d' : '#0077b6';
  }
}

/**
 * Notification bell: opening it marks the notifications as read, so the red
 * count disappears right away (the items stay highlighted until the bell closes).
 */
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('notifBtn');
  if (!btn || !btn.getAttribute('data-read-url')) return;
  var token = document.querySelector('meta[name="csrf-token"]');

  btn.addEventListener('shown.bs.dropdown', function () {
    var badge = btn.querySelector('.notif-badge');
    if (!badge) return;
    badge.remove();
    fetch(btn.getAttribute('data-read-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': token ? token.content : '', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).catch(function () { /* will be marked next time */ });
  });
  btn.addEventListener('hidden.bs.dropdown', function () {
    document.querySelectorAll('#notifPanel .notif-panel-item.unread').forEach(function (el) { el.classList.remove('unread'); });
  });
});
