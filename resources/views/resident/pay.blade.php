@extends('layouts.app')

@section('title', 'GCash Payment')

@push('styles')
<style>
  .gcash-card{max-width:900px;margin:0 auto;}
  .gcash-head{background:linear-gradient(135deg,#0057e4,#007dfe);color:#fff;border-radius:12px 12px 0 0;padding:16px 20px;display:flex;align-items:center;gap:10px;}
  .gcash-head h3{margin:0;font-size:18px;color:#fff;}
  .gcash-summary .row-item{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px dashed var(--border);font-size:14px;}
  .gcash-summary .amount{font-size:26px;font-weight:700;color:var(--primary-dark);}
  .qr-box{background:#fff;border:2px solid #007dfe;border-radius:14px;padding:12px;max-width:364px;margin:0 auto;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;}
  .qr-box img{display:block;width:100%;height:100%;object-fit:contain;image-rendering:pixelated;}
  .qr-owner{text-align:center;margin-top:10px;font-size:14px;}
  .steps{counter-reset:step;list-style:none;padding:0;margin:0;}
  .steps li{counter-increment:step;display:flex;gap:10px;align-items:flex-start;padding:6px 0;font-size:14px;}
  .steps li > span{flex:1;min-width:0;}
  .steps li::before{content:counter(step);flex:none;width:24px;height:24px;border-radius:50%;background:#007dfe;color:#fff;font-weight:700;font-size:12.5px;display:flex;align-items:center;justify-content:center;}
</style>
@endpush

@section('content')
<div class="card gcash-card p-0">
  <div class="gcash-head"><span style="font-size:22px;">📱</span><h3>GCash Payment</h3></div>
  <div class="card-body">

    <div class="row g-4">
      <div class="col-md-6 order-md-2">
        @if ($qr->isConfigured())
          <div class="qr-box">
            <img src="{{ route('gcash.qr') }}" alt="Barangay GCash QR code — scan with the GCash app">
          </div>
          @if ($qr->accountName() || $qr->number())
            <div class="qr-owner">
              @if ($qr->accountName())<div><strong>{{ $qr->accountName() }}</strong></div>@endif
              @if ($qr->number())<div class="text-muted">GCash No. {{ $qr->number() }}</div>@endif
            </div>
          @endif
        @else
          <div class="alert alert-warning mb-0">
            Online GCash payment is not available yet — the barangay has not uploaded its GCash QR code.
            Please pay in person at the barangay water office (Cash or GCash QR).
          </div>
        @endif
      </div>

      <div class="col-md-6 order-md-1">
        <div class="gcash-summary mb-3">
          <div class="row-item"><span class="text-muted">Meter Number</span><strong>{{ $consumer->meter_number }}</strong></div>
          <div class="row-item"><span class="text-muted">Name</span><strong>{{ $consumer->full_name }}</strong></div>
          <div class="row-item"><span class="text-muted">Due Date</span><strong>{{ formatDate($bill->due_date) }}</strong></div>
          @if (!empty($bill->disconnection_date))
          <div class="row-item"><span class="text-muted">Disconnection Date</span><strong class="text-danger">{{ formatDate($bill->disconnection_date) }}</strong></div>
          @endif
          <div class="row-item"><span class="text-muted">Previous Reading</span><strong>{{ $bill->previous_reading !== null ? number_format($bill->previous_reading, 2) : '—' }}</strong></div>
          <div class="row-item"><span class="text-muted">Present Reading</span><strong>{{ $bill->current_reading !== null ? number_format($bill->current_reading, 2) : '—' }}</strong></div>
          <div class="row-item"><span class="text-muted">Consumption</span><strong>{{ number_format($bill->consumption, 2) }} m³</strong></div>
          <div class="row-item"><span class="text-muted">Total</span><strong>{{ formatCurrency($bill->total_amount) }}</strong></div>
          @if ((float)$bill->amount_paid > 0)
          <div class="row-item"><span class="text-muted">Amount Paid</span><strong>{{ formatCurrency($bill->amount_paid) }}</strong></div>
          @endif
          <div class="row-item" style="border-bottom:0;"><span class="text-muted">Amount Due</span><span class="amount">{{ formatCurrency($amount) }}</span></div>
        </div>

        @if ($qr->isConfigured())
        <h4 class="fs-6 mb-2">How to pay</h4>
        <ol class="steps mb-3">
          <li><span>Open the <strong>GCash</strong> app on your phone.</span></li>
          <li><span>Tap <strong>Scan QR</strong>.</span></li>
          <li><span>Scan the barangay GCash QR code shown here.</span></li>
          <li><span>Enter or confirm the amount: <strong>{{ formatCurrency($amount) }}</strong>.</span></li>
          <li><span>Complete the payment in GCash.</span></li>
          <li><span>Take a <strong>screenshot</strong> of the GCash receipt, attach it below, then tap <strong>I Have Paid</strong>.</span></li>
        </ol>
        @endif
      </div>
    </div>

    @if ($qr->isConfigured())
      <hr>
      <form method="POST" action="{{ route('resident.bill.pay.submit', $bill->bill_id) }}" enctype="multipart/form-data" id="payForm">
        @csrf
        <label for="receipt" class="form-label">GCash Receipt Screenshot *</label>
        <input type="file" id="receipt" name="receipt" class="form-control form-control-lg" accept="image/jpeg,image/png,image/webp" required>
        <div class="text-muted" style="font-size:12px;">The screenshot of your completed GCash payment (it shows the amount, date and Ref. No.). JPG, PNG or WEBP, up to 5 MB.</div>
        <img id="receiptPreview" alt="Preview of your GCash receipt" hidden style="display:block;max-width:240px;max-height:360px;margin-top:10px;border:1px solid var(--border);border-radius:10px;">
        <div class="alert alert-info mt-3 mb-3 small">
          After you tap <strong>I Have Paid</strong>, your payment will show as <strong>Pending Verification</strong>.
          Your bill changes to <strong>Paid</strong> only after the barangay water office confirms the money was received.
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary btn-lg flex-grow-1" id="paidBtn">✅ I Have Paid</button>
          <a href="{{ route('resident.bill') }}" class="btn btn-secondary btn-lg">Cancel</a>
        </div>
      </form>
    @else
      <div class="mt-3"><a href="{{ route('resident.bill') }}" class="btn btn-secondary">Back to Current Bills</a></div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('receipt')?.addEventListener('change', function () {
  const preview = document.getElementById('receiptPreview');
  const f = this.files && this.files[0];
  if (f && f.size > 5 * 1024 * 1024) { alert('The screenshot is larger than 5 MB. Please choose a smaller image.'); this.value = ''; preview.hidden = true; return; }
  preview.hidden = !f;
  if (f) preview.src = URL.createObjectURL(f);
});
// One submission only — stops double taps from sending the payment twice.
document.getElementById('payForm')?.addEventListener('submit', function (e) {
  if (!confirm('Submit this GCash payment for verification?\n\nOnly continue if you have already completed the payment in GCash.')) {
    e.preventDefault();
    return;
  }
  const btn = document.getElementById('paidBtn');
  btn.disabled = true;
  btn.textContent = 'Submitting…';
});
</script>
@endpush
