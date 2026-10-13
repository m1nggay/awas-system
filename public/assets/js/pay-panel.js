/**
 * pay-panel.js — the Online Payment panel (resources/views/resident/_pay-panel.blade.php),
 * on the Pay Bill page and inside the Current Bills pop-up. Call initPayPanel(root)
 * after the panel is on the page (the pop-up loads it later).
 */
window.initPayPanel = function (root) {
  if (!root) return;
  const $ = sel => root.querySelector(sel);

  // "View bill details": show the bill right here; tap again to hide it.
  const detailsBtn = $('[data-bill-details]'), detailsFrame = $('.bill-details-frame');
  detailsBtn?.addEventListener('click', () => {
    const show = detailsFrame.hidden;
    if (show && !detailsFrame.src) detailsFrame.src = detailsBtn.dataset.src;
    detailsFrame.hidden = !show;
    detailsBtn.setAttribute('aria-expanded', String(show));
    detailsBtn.textContent = show ? 'Hide bill details' : 'View bill details';
  });

  // Payment method dropdowns: tap one to open its options, tap again to close; one open at a time.
  const tabs = root.querySelectorAll('.method-tab');
  tabs.forEach(tab => tab.addEventListener('click', () => {
    const opening = tab.getAttribute('aria-expanded') !== 'true';
    tabs.forEach(t => {
      const on = opening && t === tab;
      t.setAttribute('aria-expanded', on ? 'true' : 'false');
      root.querySelector('#' + t.getAttribute('aria-controls')).hidden = !on;
    });
  }));

  // Receipt screenshot: preview it, and "I Have Paid" only once one is chosen.
  const receiptForm = $('[data-receipt-form]');
  if (receiptForm) {
    const file = receiptForm.querySelector('input[type="file"]'), preview = receiptForm.querySelector('.receipt-preview');
    const paidBtn = receiptForm.querySelector('[data-paid-btn]');
    file.addEventListener('change', function () {
      const f = this.files && this.files[0];
      if (f && f.size > 5 * 1024 * 1024) { alert('The screenshot is larger than 5 MB. Please choose a smaller image.'); this.value = ''; }
      const ok = this.files && this.files[0];
      preview.hidden = !ok;
      if (ok) preview.src = URL.createObjectURL(this.files[0]);
      paidBtn.disabled = !ok;
    });
    // One submission only — stops double taps from sending the payment twice.
    receiptForm.addEventListener('submit', e => {
      if (!confirm('Submit this GCash payment for verification?\n\nOnly continue if you have already completed the payment in GCash.')) {
        e.preventDefault();
        return;
      }
      paidBtn.disabled = true;
      paidBtn.textContent = 'Checking the screenshot…';
    });
  }

  // One click only. The chosen wallet is copied into a hidden field first,
  // because disabled buttons aren't sent with the form.
  $('[data-paymongo-form]')?.addEventListener('submit', function (e) {
    const chosen = e.submitter;
    if (!chosen) return;
    const field = document.createElement('input');
    field.type = 'hidden'; field.name = 'wallet'; field.value = chosen.value;
    this.appendChild(field);
    this.querySelectorAll('.wallet-btn').forEach(b => { b.disabled = true; });
    chosen.lastChild.textContent = 'Opening…';
  });
};
