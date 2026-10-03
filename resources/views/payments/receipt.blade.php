<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment Receipt — {{ $p->payment_reference }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<style>
  body{background:#fff;padding:24px 16px;}
  .receipt{max-width:640px;margin:0 auto;border:1px solid var(--border);border-radius:10px;overflow:hidden;background:#fff;position:relative;}
  .receipt-head{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;padding:20px 26px;display:flex;justify-content:space-between;align-items:center;gap:12px;}
  .receipt-body{padding:22px 26px;}
  .row{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px dashed var(--border);font-size:13.5px;}
  .row strong{text-align:right;}
  .section-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--primary-dark);margin:18px 0 4px;}
  .amount-box{margin-top:18px;border-top:2px solid var(--border);padding-top:12px;display:flex;justify-content:space-between;align-items:center;font-size:18px;font-weight:700;color:var(--primary-dark);}
  .stamp{display:inline-block;padding:4px 14px;border:2px solid;border-radius:8px;font-weight:800;letter-spacing:.08em;font-size:14px;}
  .stamp.paid{color:#1a9b5b;border-color:#1a9b5b;}
  .stamp.pending{color:#b7791f;border-color:#e0a93b;}
  .stamp.rejected{color:#e5533d;border-color:#e5533d;}
  .actions-bar{max-width:640px;margin:0 auto 14px;display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;}
</style>
</head>
<body>
@php
  $isPaid = $p->status === 'verified';
  $isPending = $p->status === 'pending';
  $stampClass = $isPaid ? 'paid' : ($isPending ? 'pending' : 'rejected');
@endphp
  <div class="actions-bar no-print">
    <a href="{{ $backUrl }}" class="btn btn-secondary">&larr; Back</a>
    <button type="button" class="btn btn-outline" onclick="window.print()">🖨️ Print</button>
    <button type="button" class="btn btn-primary" id="downloadBtn">⬇️ Download Receipt (PDF)</button>
  </div>

  <div class="receipt" id="receipt">
    <div class="receipt-head">
      <div>
        <div style="display:flex;align-items:center;gap:8px;font-size:20px;font-weight:700;">
          <img src="{{ asset('assets/img/logo.png') }}" alt="AWAS logo" style="width:32px;height:32px;object-fit:contain;">AWAS
        </div>
        <div style="font-size:12px;opacity:.85;">{{ $barangayName }} — Water Payment Receipt</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:12px;opacity:.85;">Receipt No.</div>
        <div style="font-size:16px;font-weight:700;">{{ $p->payment_reference }}</div>
      </div>
    </div>

    <div class="receipt-body">
      <div style="text-align:center;margin-bottom:10px;">
        <span class="stamp {{ $stampClass }}">{{ $isPaid ? 'PAID' : strtoupper(paymentStatusLabel($p->status)) }}</span>
        @if ($isPending)
          <div class="text-muted" style="font-size:12px;margin-top:6px;">Acknowledgment of your submitted payment. It becomes an official receipt once the water office verifies it.</div>
        @elseif (!$isPaid && $p->rejection_reason)
          <div class="text-danger" style="font-size:12px;margin-top:6px;">Reason: {{ $p->rejection_reason }}</div>
        @endif
      </div>

      <div class="section-title">Consumer</div>
      <div class="row"><span class="text-muted">Meter Number</span><strong>{{ $p->meter_number ?: '—' }}</strong></div>
      <div class="row"><span class="text-muted">Name</span><strong>{{ $p->full_name }}</strong></div>
      <div class="row"><span class="text-muted">Purok</span><strong>{{ $p->purok_name }}</strong></div>

      <div class="section-title">Bill</div>
      <div class="row"><span class="text-muted">Billing Period</span><strong>{{ billingPeriodLabel($p->billing_period) }}</strong></div>
      <div class="row"><span class="text-muted">Previous Reading</span><strong>{{ $p->previous_reading !== null ? number_format($p->previous_reading, 2) : '—' }}</strong></div>
      <div class="row"><span class="text-muted">Present Reading</span><strong>{{ $p->current_reading !== null ? number_format($p->current_reading, 2) : '—' }}</strong></div>
      <div class="row"><span class="text-muted">Consumption</span><strong>{{ number_format($p->consumption, 2) }} m³</strong></div>
      <div class="row"><span class="text-muted">Total</span><strong>{{ formatCurrency($p->total_amount) }}</strong></div>

      <div class="section-title">Payment</div>
      <div class="row"><span class="text-muted">Payment Date</span><strong>{{ formatDateTime($p->payment_date) }}</strong></div>
      <div class="row"><span class="text-muted">Method</span><strong>{{ paymentMethodLabel($p->payment_method) }} · {{ paymentChannelLabel($p->channel ?? null) }}</strong></div>
      @if ($p->payment_gateway_txn_id)
        <div class="row"><span class="text-muted">GCash Ref. No.</span><strong>{{ $p->payment_gateway_txn_id }}</strong></div>
      @endif
      <div class="row"><span class="text-muted">Status</span><strong>{{ paymentStatusLabel($p->status) }}</strong></div>
      @if ($isPaid && $p->verified_at)
        <div class="row"><span class="text-muted">Verified On</span><strong>{{ formatDateTime($p->verified_at) }}</strong></div>
      @endif
      @if ($isPaid)
        <div class="row"><span class="text-muted">Remaining Balance (this bill)</span><strong>{{ formatCurrency($balance) }}</strong></div>
      @endif

      <div class="amount-box"><span>Amount Paid</span><span>{{ formatCurrency($p->amount_paid) }}</span></div>

      <p class="text-muted" style="margin-top:20px;font-size:11px;">
        System-generated receipt from AWAS — Adlay Water Augmentation System of {{ $barangayName }}.
        Printed {{ formatDateTime(now()) }}.
      </p>
    </div>
  </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
(function () {
  const btn = document.getElementById('downloadBtn');
  const fileName = {{ Js::from('AWAS-Receipt-' . $p->payment_reference . '.pdf') }};

  function download() {
    // Without the PDF libraries (e.g. offline), fall back to the browser's "Save as PDF".
    if (!window.html2canvas || !window.jspdf) { window.print(); return; }
    btn.disabled = true;
    btn.textContent = 'Preparing PDF…';
    html2canvas(document.getElementById('receipt'), { scale: 2, backgroundColor: '#ffffff', useCORS: true })
      .then(function (canvas) {
        const pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4' });
        const margin = 12, width = 210 - margin * 2;
        const height = Math.min(canvas.height * width / canvas.width, 297 - margin * 2);
        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', margin, margin, width, height);
        pdf.save(fileName);
      })
      .catch(function () { window.print(); })
      .finally(function () { btn.disabled = false; btn.textContent = '⬇️ Download Receipt (PDF)'; });
  }

  btn.addEventListener('click', download);
  @if ($autoDownload)
    window.addEventListener('load', download);
  @endif
})();
</script>
</body>
</html>
