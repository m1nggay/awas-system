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
 * Phones: tables (.data-table) are shown as one card per row (see style.css).
 * Each cell gets its column name so the card can show "Label  value".
 */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('table.data-table').forEach(function (table) {
    var labels = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
      return th.textContent.trim();
    });
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      var col = 0;
      Array.prototype.forEach.call(tr.children, function (td) {
        if (labels[col] && !td.hasAttribute('data-label')) td.setAttribute('data-label', labels[col]);
        col += td.colSpan || 1;
      });
    });
  });
});
