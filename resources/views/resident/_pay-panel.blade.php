{{-- The Online Payment panel: on the Pay Bill page, and loaded into the Current Bills pop-up ($inModal).
     Needs $bill, $consumer, $amount, $qr, $paymongo. Its script is assets/js/pay-panel.js (initPayPanel). --}}
@php
  $inModal = $inModal ?? false;
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
<div class="card pay-dialog" data-pay-panel>
  <div class="pay-head">
    <h3>Online Payment</h3>
    @if ($inModal)
      <button type="button" class="pay-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
    @else
      <a href="{{ route('resident.bill') }}" class="pay-close" aria-label="Close and go back to Current Bills">&times;</a>
    @endif
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
    {{-- The bill opens right here (tap again to hide it). --}}
    <button type="button" class="btn btn-link p-0 small mt-2 text-decoration-none" data-bill-details
            data-src="{{ route('bills.print', [$bill->bill_id, 'embed' => 1]) }}" aria-expanded="false">View bill details</button>
    <iframe class="bill-details-frame" title="Water bill details" hidden></iframe>

    @if (!$paymongo && !$qrReady)
      <div class="alert alert-warning mt-3 mb-0">Online payment is not available yet. Please pay in person at the barangay water office (Cash or GCash QR).</div>
    @else
      <div class="pay-q">How do you want to pay?</div>
      {{-- Each way to pay is a dropdown: tap it to show its options right underneath. --}}
      <div class="method-list">

      {{-- GCash / Maya through PayMongo: opens the wallet, confirmed automatically --}}
      @if ($paymongo)
        <div class="method-item">
        <button type="button" class="method-tab" id="tab-wallet" aria-controls="panel-wallet" aria-expanded="{{ $tab === 'wallet' ? 'true' : 'false' }}">
          <strong>E-wallet</strong><span class="method-arrow" aria-hidden="true">▾</span>
        </button>
        <div class="method-panel" role="region" id="panel-wallet" aria-labelledby="tab-wallet" @if ($tab !== 'wallet') hidden @endif>
          <form method="POST" action="{{ route('resident.bill.paymongo', $bill->bill_id) }}" class="wallet-choices" data-paymongo-form>
            @csrf
            @foreach (['gcash' => ['GCash', '#007dfe', 'G'], 'paymaya' => ['Maya', '#00b464', 'M']] as $code => [$name, $color, $letter])
              <button type="submit" name="wallet" value="{{ $code }}" class="wallet-btn" style="--wallet:{{ $color }};" aria-label="Pay with {{ $name }}">
                <span class="dot" aria-hidden="true">{{ $letter }}</span>{{ $name }}
              </button>
            @endforeach
          </form>
          <p class="text-muted small mt-3 mb-0">Tap your e-wallet — it opens straight to its payment screen. Your bill turns <strong>Paid</strong> as soon as you finish paying.</p>
          <div class="d-flex justify-content-end mt-3">
            @if ($inModal)
              <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
            @else
              <a href="{{ route('resident.bill') }}" class="btn btn-outline">Cancel</a>
            @endif
          </div>
        </div>
        </div>
      @endif

      {{-- The barangay's GCash QR: pay, then upload the receipt for the water office to verify --}}
      @if ($qrReady)
        <div class="method-item">
        <button type="button" class="method-tab" id="tab-qr" aria-controls="panel-qr" aria-expanded="{{ $tab === 'qr' ? 'true' : 'false' }}">
          <strong>Scan QR</strong><span class="method-arrow" aria-hidden="true">▾</span>
        </button>
        <div class="method-panel" role="region" id="panel-qr" aria-labelledby="tab-qr" @if ($tab !== 'qr') hidden @endif>
          <form method="POST" action="{{ route('resident.bill.pay.submit', $bill->bill_id) }}" enctype="multipart/form-data" data-receipt-form>
            @csrf
            <input type="hidden" name="receipt_tab" value="1">
            <div class="qr-row">
              <div>
                <div class="qr-frame"><img src="{{ route('gcash.qr') }}" alt="Barangay GCash QR code — scan with the GCash app"></div>
                <a href="{{ route('gcash.qr') }}" download="awas-gcash-qr.jpg" class="btn btn-outline btn-sm w-100 mt-2">Save QR image</a>
              </div>
              <div>
                <strong style="font-size:14px;">AWAS Office</strong>
                @if ($qr->accountName())
                  <div class="text-muted small">Administrator: {{ $qr->accountName() }}</div>
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
              <img class="receipt-preview" alt="Preview of your GCash receipt" hidden style="display:block;max-width:200px;max-height:300px;margin-top:10px;border:1px solid var(--border);border-radius:10px;">
            </div>

            <p class="text-muted small mt-3 mb-3">Your bill shows <strong>Pending</strong> until the water office confirms the payment.</p>
            <div class="d-flex justify-content-end gap-2">
              @if ($inModal)
                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
              @else
                <a href="{{ route('resident.bill') }}" class="btn btn-outline">Cancel</a>
              @endif
              <button type="submit" class="btn btn-primary" data-paid-btn disabled>I Have Paid</button>
            </div>
          </form>
        </div>
        </div>
      @endif
      </div>{{-- .method-list --}}
    @endif
  </div>
</div>
