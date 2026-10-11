@extends('layouts.app')

@section('title', 'Pay Bill')

@push('styles')
<style>
  .pay-dialog{max-width:560px;margin:0 auto;padding:0;}
  .pay-dialog .pay-head{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);}
  .pay-dialog .pay-head h3{margin:0;font-size:16px;font-weight:700;}
  .pay-close{font-size:22px;line-height:1;color:var(--text-muted);text-decoration:none;padding:2px 6px;border-radius:8px;}
  .pay-close:hover{background:#f1f5f8;color:var(--text-dark);}
  .pay-body{padding:18px 20px 20px;}
  .pay-amount-label{font-size:12px;color:var(--text-muted);}
  .pay-amount{font-size:30px;font-weight:700;color:var(--primary-dark);line-height:1.15;}
  .due-chip{background:#fff3df;color:#9a5b00;border-radius:999px;padding:4px 12px;font-size:12px;font-weight:600;white-space:nowrap;}
  .pay-q{font-weight:600;font-size:14px;margin:18px 0 10px;}
  .method-tabs{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
  .method-tab{border:1.5px solid var(--border);border-radius:10px;padding:13px 12px;cursor:pointer;background:#fff;text-align:center;}
  .method-tab strong{display:block;font-size:14.5px;}
  .method-tab[aria-selected="true"]{border-color:var(--primary);background:#f0f7fb;box-shadow:0 0 0 1px var(--primary) inset;}
  .method-panel{border-top:1px solid var(--border);margin-top:16px;padding-top:16px;}
  .wallet-choices{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .wallet-btn{display:flex;align-items:center;justify-content:center;gap:10px;padding:14px 12px;border:2px solid var(--border);border-radius:12px;background:#fff;font-weight:700;font-size:16px;color:#1f2d3d;transition:border-color .15s,background .15s,transform .15s;}
  .wallet-btn:hover:not(:disabled){border-color:var(--wallet);background:#fafdff;transform:translateY(-1px);}
  .wallet-btn .dot{flex:none;width:36px;height:36px;border-radius:50%;background:var(--wallet);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;}
  .wallet-btn:disabled{opacity:.6;}
  .qr-row{display:grid;grid-template-columns:130px 1fr;gap:16px;align-items:start;}
  .qr-frame{border:1px solid var(--border);border-radius:10px;padding:6px;background:#fff;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;}
  .qr-frame img{width:100%;height:100%;object-fit:contain;image-rendering:pixelated;}
  .qr-steps{padding-left:18px;margin:8px 0 0;font-size:13px;}
  .qr-steps li{margin-bottom:3px;}
  .upload-box{background:#f4fafc;border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:14px;}
  .upload-box .form-label{font-size:12.5px;font-weight:600;margin-bottom:4px;}
  @media (max-width:520px){
    .qr-row{grid-template-columns:1fr;}
    .qr-frame{max-width:220px;margin:0 auto;}
  }
</style>
@endpush

@section('content')
@php
  $qrReady = $qr->isConfigured();
  // Nothing is open until the resident picks a way to pay — except after a failed
  // screenshot upload (back on Scan QR), or when only one way to pay exists.
  $tab = match (true) {
      (bool)old('receipt_tab') => 'qr',
      $paymongo && !$qrReady => 'wallet',
      !$paymongo && $qrReady => 'qr',
      default => null,
  };
@endphp
<div class="card pay-dialog">
  <div class="pay-head">
    <h3>Online Payment</h3>
    <a href="{{ route('resident.bill') }}" class="pay-close" aria-label="Close and go back to Current Bills">&times;</a>
  </div>

  <div class="pay-body">
    {{-- What is being paid --}}
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div>
        <div class="pay-amount-label">Amount due</div>
        <div class="pay-amount">{{ formatCurrency($amount) }}</div>
        <div class="small mt-1">{{ $consumer->full_name }} · Meter {{ $consumer->meter_number }}</div>
      </div>
      <span class="due-chip">Due {{ formatDate($bill->due_date) }}</span>
    </div>
    <button type="button" class="btn btn-link p-0 small mt-2 text-decoration-none" data-bs-toggle="modal" data-bs-target="#billDetailsModal">View bill details</button>

    @if (!$paymongo && !$qrReady)
      <div class="alert alert-warning mt-3 mb-0">Online payment is not available yet. Please pay in person at the barangay water office (Cash or GCash QR).</div>
    @else
      <div class="pay-q">How do you want to pay?</div>
      <div class="method-tabs" role="tablist">
        @if ($paymongo)
          <button type="button" class="method-tab" role="tab" id="tab-wallet" aria-controls="panel-wallet" aria-selected="{{ $tab === 'wallet' ? 'true' : 'false' }}">
            <strong>GCash / Maya</strong>
          </button>
        @endif
        @if ($qrReady)
          <button type="button" class="method-tab" role="tab" id="tab-qr" aria-controls="panel-qr" aria-selected="{{ $tab === 'qr' ? 'true' : 'false' }}">
            <strong>Scan QR</strong>
          </button>
        @endif
      </div>

      {{-- GCash / Maya through PayMongo: opens the wallet, confirmed automatically --}}
      @if ($paymongo)
        <div class="method-panel" role="tabpanel" id="panel-wallet" aria-labelledby="tab-wallet" @if ($tab !== 'wallet') hidden @endif>
          <form method="POST" action="{{ route('resident.bill.paymongo', $bill->bill_id) }}" id="paymongoForm" class="wallet-choices">
            @csrf
            @foreach (['gcash' => ['GCash', '#007dfe', 'G'], 'paymaya' => ['Maya', '#00b464', 'M']] as $code => [$name, $color, $letter])
              <button type="submit" name="wallet" value="{{ $code }}" class="wallet-btn" style="--wallet:{{ $color }};" aria-label="Pay with {{ $name }}">
                <span class="dot" aria-hidden="true">{{ $letter }}</span>{{ $name }}
              </button>
            @endforeach
          </form>
          <p class="text-muted small mt-3 mb-0">Tap your e-wallet — it opens straight to its payment screen. Your bill turns <strong>Paid</strong> as soon as you finish paying.</p>
          <div class="d-flex justify-content-end mt-3">
            <a href="{{ route('resident.bill') }}" class="btn btn-outline">Cancel</a>
          </div>
        </div>
      @endif

      {{-- The barangay's GCash QR: pay, then upload the receipt for the water office to verify --}}
      @if ($qrReady)
        <div class="method-panel" role="tabpanel" id="panel-qr" aria-labelledby="tab-qr" @if ($tab !== 'qr') hidden @endif>
          <form method="POST" action="{{ route('resident.bill.pay.submit', $bill->bill_id) }}" enctype="multipart/form-data" id="payForm">
            @csrf
            <input type="hidden" name="receipt_tab" value="1">
            <div class="qr-row">
              <div>
                <div class="qr-frame"><img src="{{ route('gcash.qr') }}" alt="Barangay GCash QR code — scan with the GCash app"></div>
                <a href="{{ route('gcash.qr') }}" download="barangay-gcash-qr.png" class="btn btn-outline btn-sm w-100 mt-2">Save QR image</a>
              </div>
              <div>
                <strong style="font-size:14px;">{{ setting('barangay_name', 'Barangay Adlay') }} Water Office</strong>
                @if ($qr->accountName() || $qr->number())
                  <div class="text-muted small">Collector: {{ $qr->accountName() }}{{ $qr->accountName() && $qr->number() ? ' · ' : '' }}{{ $qr->number() ? 'GCash ' . $qr->number() : '' }}</div>
                @endif
                <ol class="qr-steps">
                  <li>Scan this QR in GCash (or upload the saved image).</li>
                  <li>Pay exactly <strong>{{ formatCurrency($amount) }}</strong>.</li>
                  <li>Upload the receipt screenshot below.</li>
                </ol>
              </div>
            </div>

            <div class="upload-box">
              <label for="receipt" class="form-label">Receipt screenshot</label>
              <input type="file" id="receipt" name="receipt" class="form-control" accept="image/jpeg,image/png,image/webp" required>
              <div class="text-muted" style="font-size:11.5px;margin-top:4px;">Show the full receipt with the amount and Ref. No. JPG, PNG or WEBP, up to 5 MB.</div>
              <img id="receiptPreview" alt="Preview of your GCash receipt" hidden style="display:block;max-width:200px;max-height:300px;margin-top:10px;border:1px solid var(--border);border-radius:10px;">
            </div>

            <p class="text-muted small mt-3 mb-3">Your bill shows <strong>Pending</strong> until the water office confirms the payment.</p>
            <div class="d-flex justify-content-end gap-2">
              <a href="{{ route('resident.bill') }}" class="btn btn-outline">Cancel</a>
              <button type="submit" class="btn btn-primary" id="paidBtn" disabled>I Have Paid</button>
            </div>
          </form>
        </div>
      @endif
    @endif
  </div>
</div>

{{-- The bill, shown here in a pop-up instead of another tab. --}}
<div class="modal fade" id="billDetailsModal" tabindex="-1" aria-labelledby="billDetailsTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="h6 mb-0" id="billDetailsTitle">{{ billingPeriodLabel($bill->billing_period) }} Bill — Meter {{ $consumer->meter_number }}</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="billDetailsFrame" title="Water bill details" data-src="{{ route('bills.print', [$bill->bill_id, 'embed' => 1]) }}"
                style="width:100%;height:70vh;border:0;display:block;"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('billDetailsFrame').contentWindow.print()">🖨️ Print / Save as PDF</button>
      </div>
    </div>
  </div>
</div>
<script>
// Load the bill only when the pop-up is opened.
document.getElementById('billDetailsModal').addEventListener('show.bs.modal', function () {
  const frame = document.getElementById('billDetailsFrame');
  if (!frame.src) frame.src = frame.dataset.src;
});
</script>
@endsection

@push('scripts')
<script>
(function () {
  // Payment method choices: tap one to open it, tap it again to close it.
  const tabs = document.querySelectorAll('.method-tab');
  tabs.forEach(tab => tab.addEventListener('click', () => {
    const opening = tab.getAttribute('aria-selected') !== 'true';
    tabs.forEach(t => {
      const on = opening && t === tab;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
  }));

  // Receipt screenshot: preview it, and "I Have Paid" only once one is chosen.
  const receipt = document.getElementById('receipt');
  receipt?.addEventListener('change', function () {
    const preview = document.getElementById('receiptPreview');
    const f = this.files && this.files[0];
    if (f && f.size > 5 * 1024 * 1024) { alert('The screenshot is larger than 5 MB. Please choose a smaller image.'); this.value = ''; }
    const ok = this.files && this.files[0];
    preview.hidden = !ok;
    if (ok) preview.src = URL.createObjectURL(this.files[0]);
    document.getElementById('paidBtn').disabled = !ok;
  });
  // One submission only — stops double taps from sending the payment twice.
  document.getElementById('payForm')?.addEventListener('submit', function (e) {
    if (!confirm('Submit this GCash payment for verification?\n\nOnly continue if you have already completed the payment in GCash.')) {
      e.preventDefault();
      return;
    }
    const btn = document.getElementById('paidBtn');
    btn.disabled = true;
    btn.textContent = 'Checking the screenshot…';
  });

  // One click only. The chosen wallet is copied into a hidden field first,
  // because disabled buttons aren't sent with the form.
  document.getElementById('paymongoForm')?.addEventListener('submit', function (e) {
    const chosen = e.submitter;
    if (!chosen) return;
    const field = document.createElement('input');
    field.type = 'hidden'; field.name = 'wallet'; field.value = chosen.value;
    this.appendChild(field);
    this.querySelectorAll('.wallet-btn').forEach(b => { b.disabled = true; });
    chosen.lastChild.textContent = 'Opening…';
  });
})();
</script>
@endpush
