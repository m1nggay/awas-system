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

  // Scan QR (QR Ph from PayMongo): show the code, then ask every few seconds whether it was paid.
  const qrBox = $('[data-qrph]');
  if (qrBox) {
    const q = sel => qrBox.querySelector(sel);
    const intro = q('[data-qr-intro]'), show = q('[data-qr-show]'), err = q('[data-qr-error]');
    const startBtn = q('[data-qr-start]'), cancelBtn = q('[data-qr-cancel]');
    const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const post = url => fetch(url, { method: 'POST', credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let current = null, poll = null, tick = null;

    const stop = () => { clearInterval(poll); clearInterval(tick); poll = tick = null; };
    const reset = message => {
      stop(); current = null;
      show.hidden = true; intro.hidden = false;
      startBtn.disabled = false; startBtn.innerHTML = '<i class="ph ph-qr-code" aria-hidden="true"></i> Show QR code';
      err.hidden = !message; err.textContent = message || '';
    };
    // Stops checking once the pop-up is closed (the panel is removed from the page).
    const alive = () => document.body.contains(root);

    const checkStatus = () => {
      if (!current) return;
      if (!alive()) { stop(); return; }
      fetch(qrBox.dataset.statusUrl.replace('__ID__', current.payment_id), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(d => {
          if (d.status === 'paid') {
            stop();
            q('[data-qr-state]').innerHTML = '<i class="ph-fill ph-check-circle text-success" aria-hidden="true"></i> <strong>Paid!</strong> Opening your receipt…';
            setTimeout(() => { location.href = d.redirect; }, 1200);
          } else if (d.status === 'expired' || d.status === 'gone') {
            reset('The QR code expired before it was paid. Tap Show QR code to make a new one.');
          }
        })
        .catch(() => { /* try again on the next check */ });
    };

    startBtn.addEventListener('click', () => {
      startBtn.disabled = true; startBtn.textContent = 'Making your QR…'; err.hidden = true;
      post(qrBox.dataset.startUrl)
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
          if (!ok || !d.image) { reset(d.error || 'The QR code could not be created. Please try again.'); return; }
          current = d;
          q('[data-qr-img]').src = d.image;
          q('[data-qr-save]').href = d.image;
          q('[data-qr-amount]').textContent = 'Pay exactly ' + peso(d.amount);
          const test = q('[data-qr-test]');
          test.hidden = !d.test_url; if (d.test_url) test.href = d.test_url;
          intro.hidden = true; show.hidden = false;
          poll = setInterval(checkStatus, 3000);
          if (d.expires_at) {
            const end = new Date(d.expires_at).getTime(), out = q('[data-qr-countdown]');
            const count = () => {
              const left = Math.max(0, Math.round((end - Date.now()) / 1000));
              out.textContent = 'This QR code works for ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0') + ' more.';
              if (left === 0) checkStatus();
            };
            count(); tick = setInterval(count, 1000);
          }
        })
        .catch(() => reset('Could not connect. Please check your internet and try again.'));
    });

    cancelBtn.addEventListener('click', () => {
      if (!current) { reset(); return; }
      cancelBtn.disabled = true;
      const id = current.payment_id;
      stop();
      post(qrBox.dataset.cancelUrl.replace('__ID__', id))
        .then(r => r.json())
        .then(d => { if (d.paid) { location.href = d.redirect; } else { cancelBtn.disabled = false; reset(); } })
        .catch(() => { cancelBtn.disabled = false; reset(); });
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
