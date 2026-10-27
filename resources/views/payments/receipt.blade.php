<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment Receipt — {{ $p->payment_reference }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<style>
  /* Same look as the water billing statement (bills/print). */
  body{background:#fff;padding:30px;}
  .receipt{max-width:760px;margin:0 auto;border:1px solid var(--border);border-radius:10px;overflow:hidden;background:#fff;}
  .receipt-head{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;padding:24px 30px;display:flex;justify-content:space-between;align-items:center;gap:12px;}
  .receipt-body{padding:26px 30px;}
  .info{display:grid;grid-template-columns:1fr 1fr;gap:4px 30px;}
  .row{display:flex;justify-content:space-between;gap:12px;margin-bottom:6px;font-size:13.5px;}
  .row strong{text-align:right;}
  .status-note{font-size:12px;margin-top:10px;}
  table.receipt-table{width:100%;border-collapse:collapse;margin-top:16px;}
  table.receipt-table th, table.receipt-table td{border:1px solid var(--border);padding:9px 12px;font-size:13px;text-align:left;}
  table.receipt-table th{background:#f4fafc;}
  .num{text-align:right !important;}
  .totals{margin-top:18px;border-top:2px solid var(--border);padding-top:12px;}
  .totals .row.grand{font-size:18px;font-weight:700;color:var(--primary-dark);}
  .totals .total-highlight{background:#e8f6fb;border:1px solid #bfe3f0;border-radius:8px;padding:10px 12px;margin-bottom:10px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  /* ₱ must show on every phone font */
  body, .receipt{font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,'Noto Sans',Arial,sans-serif;}
  .top-bar{max-width:760px;margin:0 auto 14px;}
  .actions-bottom{max-width:760px;margin:16px auto 0;display:flex;flex-wrap:wrap;gap:10px;}
  .actions-bottom .btn{flex:1 1 200px;justify-content:center;}
  @media (max-width:640px){
    body{padding:14px 10px;}
    .info{grid-template-columns:1fr;}
    .receipt-head{padding:16px;flex-wrap:wrap;gap:8px;}
    .receipt-body{padding:16px;}
    table.receipt-table{display:block;overflow-x:auto;}
    table.receipt-table th, table.receipt-table td{padding:7px 8px;font-size:12px;white-space:nowrap;}
    .totals .row.grand{font-size:16px;}
  }
  /* Shown inside a pop-up: the pop-up has its own buttons. */
  body.embed{padding:0;}
  body.embed .top-bar, body.embed .actions-bottom{display:none;}
  body.embed .receipt{max-width:none;border:0;border-radius:0;}
</style>
</head>
<body @class(['embed' => request()->boolean('embed')])>
@php
  $isPaid = $p->status === 'verified';
  $isPending = $p->status === 'pending';
  // In the Payment History pop-up only the payment is shown; the bill itself is in Billing History.
  $paymentOnly = request()->boolean('embed');
@endphp
  <div class="top-bar no-print">
    <a href="{{ $backUrl }}" class="btn btn-secondary">&larr; Back</a>
  </div>

  <div class="receipt" id="receipt">
    <div class="receipt-head">
      <div>
        <div style="display:flex;align-items:center;gap:8px;font-size:20px;font-weight:700;">
          <img src="{{ asset('assets/img/logo-round.png') }}" alt="AWAS logo" style="width:34px;height:34px;object-fit:contain;">AWAS
        </div>
        <div style="font-size:12px;opacity:.85;">Adlay Water Augmentation System</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:16px;font-weight:700;">Payment Receipt</div>
        <div style="font-size:12px;opacity:.85;">{{ $barangayName }}</div>
      </div>
    </div>

    <div class="receipt-body">
      <div class="info">
        <div>
          @unless ($paymentOnly)
            <div class="row"><span class="text-muted">Meter Number</span><strong>{{ $p->meter_number ?: '—' }}</strong></div>
            <div class="row"><span class="text-muted">Name</span><strong>{{ $p->full_name }}</strong></div>
            <div class="row"><span class="text-muted">Purok</span><strong>{{ $p->purok_name }}</strong></div>
          @else
            <div class="row"><span class="text-muted">Payment Reference</span><strong>{{ $p->payment_reference }}</strong></div>
            <div class="row"><span class="text-muted">For the Bill of</span><strong>{{ billingPeriodLabel($p->billing_period) }}</strong></div>
          @endunless
        </div>
        <div>
          @unless ($paymentOnly)
            <div class="row"><span class="text-muted">Payment Reference</span><strong>{{ $p->payment_reference }}</strong></div>
            <div class="row"><span class="text-muted">Month of</span><strong>{{ billingPeriodLabel($p->billing_period) }}</strong></div>
          @endunless
          <div class="row"><span class="text-muted">Status</span><span class="badge {{ paymentStatusBadgeClass($p->status) }}">{{ $isPaid ? 'Paid' : paymentStatusLabel($p->status) }}</span></div>
          @if ($isPaid && $p->verified_at)
            <div class="row"><span class="text-muted">Verified On</span><strong>{{ formatDateTime($p->verified_at) }}</strong></div>
          @endif
        </div>
      </div>
      @if ($isPending)
        <div class="status-note text-muted">Acknowledgment of your submitted payment. It becomes an official receipt once the water office verifies it.</div>
      @elseif (!$isPaid && $p->rejection_reason)
        <div class="status-note text-danger">Reason: {{ $p->rejection_reason }}</div>
      @endif

      @unless ($paymentOnly)
        <table class="receipt-table">
          <thead><tr><th>Previous Reading</th><th>Present Reading</th><th>Consumption</th><th class="num">Bill Total</th></tr></thead>
          <tbody>
            <tr>
              <td>{{ $p->previous_reading !== null ? number_format($p->previous_reading, 2) : '—' }}</td>
              <td>{{ $p->current_reading !== null ? number_format($p->current_reading, 2) : '—' }}</td>
              <td>{{ number_format($p->consumption, 2) }} m³</td>
              <td class="num"><strong>{{ formatCurrency($p->total_amount) }}</strong></td>
            </tr>
          </tbody>
        </table>
      @endunless

      <table class="receipt-table">
        <thead><tr><th>Payment Date</th><th>Method</th><th>GCash Ref. No.</th><th class="num">Amount</th></tr></thead>
        <tbody>
          <tr>
            <td>{{ formatDateTime($p->payment_date) }}</td>
            <td>{{ paymentMethodLabel($p->payment_method, $p->wallet ?? null) }}<div class="text-muted" style="font-size:11.5px;">{{ paymentChannelLabel($p->channel ?? null) }}</div></td>
            <td>{{ $p->payment_gateway_txn_id && !str_starts_with($p->payment_gateway_txn_id, 'pi_') && !str_starts_with($p->payment_gateway_txn_id, 'cs_') ? $p->payment_gateway_txn_id : '—' }}</td>
            <td class="num">{{ formatCurrency($p->amount_paid) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="totals">
        <div class="row grand total-highlight"><span>{{ $isPaid ? 'Amount Paid' : 'Amount Submitted' }}</span><span>{{ formatCurrency($p->amount_paid) }}</span></div>
        @if ($isPaid)
          <div class="row"><span>Remaining Balance <small class="text-muted">(this bill)</small></span><strong>{{ formatCurrency($balance) }}</strong></div>
        @endif
      </div>

      <p class="text-muted" style="margin-top:24px;font-size:11.5px;">
        This is a system-generated payment receipt from AWAS — Adlay Water Augmentation System of {{ $barangayName }}.
        Printed {{ formatDateTime(now()) }}.
      </p>
    </div>
  </div>

  <div class="actions-bottom no-print">
    <button type="button" class="btn btn-outline" onclick="window.print()"><i class="ph ph-printer" aria-hidden="true"></i> Print</button>
    <button type="button" class="btn btn-primary" id="downloadBtn"><i class="ph ph-download-simple" aria-hidden="true"></i> Download Receipt (PDF)</button>
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
      .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="ph ph-download-simple" aria-hidden="true"></i> Download Receipt (PDF)'; });
  }

  btn.addEventListener('click', download);
  @if ($autoDownload)
    window.addEventListener('load', download);
  @endif
})();
</script>
</body>
</html>
