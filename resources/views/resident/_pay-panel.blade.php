{{-- The Online Payment panel: on the Pay Bill page, and loaded into the Current Bills pop-up ($inModal).
     Needs $bill, $consumer, $amount, $qr, $paymongo, $wallets. Its script is assets/js/pay-panel.js (initPayPanel).
     With PayMongo: a QR Ph code made for this bill (confirmed automatically), plus the e-wallets PayMongo
     has enabled. Without PayMongo: the barangay's own QR and a receipt screenshot for the office to verify. --}}
@php
  $inModal = $inModal ?? false;
  $wallets = $wallets ?? [];
  // Scan QR: PayMongo's QR Ph (PAYMONGO_QRPH=true), otherwise the barangay's own GCash QR.
  // The "Pay with GCash" button goes through PayMongo whenever PayMongo is set up.
  $paymongoQr = $paymongoQr ?? $paymongo;
  $qrReady = !$paymongoQr && $qr->isConfigured();
  // Nothing is open until the resident picks a way to pay — except after a failed
  // screenshot upload (back on Scan QR), or when only one way to pay exists.
  $tab = match (true) {
      (bool)old('receipt_tab') => 'qr',
      $paymongoQr && !$wallets => 'qrph',
      $qrReady && !$wallets => 'qr',
      !$paymongoQr && !$qrReady && (bool)$wallets => 'wallet',
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

    @if (!$paymongoQr && !$qrReady && !$wallets)
      <div class="alert alert-warning mt-3 mb-0">Online payment is not available yet. Please pay in person at the barangay water office (Cash or GCash QR).</div>
    @else
      <div class="pay-q">How do you want to pay?</div>
      {{-- Each way to pay is a dropdown: tap it to show its options right underneath. --}}
      <div class="method-list">

      {{-- QR Ph from PayMongo for this bill's exact amount: any GCash, Maya or bank app, confirmed automatically --}}
      @if ($paymongoQr)
        <div class="method-item">
        <button type="button" class="method-tab" id="tab-qrph" aria-controls="panel-qrph" aria-expanded="{{ $tab === 'qrph' ? 'true' : 'false' }}">
          <strong>Scan QR</strong><span class="method-arrow" aria-hidden="true">▾</span>
        </button>
        <div class="method-panel" role="region" id="panel-qrph" aria-labelledby="tab-qrph" @if ($tab !== 'qrph') hidden @endif
             data-qrph data-start-url="{{ route('resident.bill.qr', $bill->bill_id) }}"
             data-status-url="{{ route('resident.bill.qr.status', ['payment' => '__ID__']) }}"
             data-cancel-url="{{ route('resident.bill.qr.cancel', ['payment' => '__ID__']) }}">
          <div data-qr-intro>
            <p class="small mb-2">Pay with <strong>GCash, Maya</strong> or any bank app that scans <strong>QR Ph</strong>. The QR is made for this bill's exact amount, and your bill turns <strong>Paid</strong> by itself — no screenshot needed.</p>
            <div class="d-flex justify-content-end gap-2">
              @if ($inModal)
                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
              @else
                <a href="{{ route('resident.bill') }}" class="btn btn-outline">Cancel</a>
              @endif
              <button type="button" class="btn btn-primary" data-qr-start><i class="ph ph-qr-code" aria-hidden="true"></i> Show QR code</button>
            </div>
          </div>
          <div data-qr-show hidden>
            <div class="qr-row">
              <div>
                <div class="qr-frame"><img data-qr-img alt="QR code to pay this bill — scan with GCash, Maya or a bank app"></div>
                <a data-qr-save download="awas-bill-qr.png" class="btn btn-outline btn-sm w-100 mt-2"><i class="ph ph-download-simple" aria-hidden="true"></i> Save QR image</a>
              </div>
              <div>
                <strong style="font-size:15px;" data-qr-amount></strong>
                <ol class="qr-steps">
                  <li><strong>On another phone:</strong> open GCash or Maya, tap <strong>Scan QR</strong>, and scan this code.</li>
                  <li><strong>On this phone:</strong> tap <strong>Save QR image</strong>, then in GCash or Maya tap <strong>Scan QR → Upload QR</strong> and pick the saved image.</li>
                  <li>Pay. Keep this page open — it shows <strong>Paid</strong> by itself.</li>
                </ol>
              </div>
            </div>
            <div class="qr-wait mt-2" data-qr-state><span class="spinner-border spinner-border-sm text-primary me-1" aria-hidden="true"></span> Waiting for your payment…</div>
            <div class="text-muted small" data-qr-countdown></div>
            <a data-qr-test href="#" target="_blank" rel="noopener" class="small d-block mt-1" hidden>Test mode: simulate paying</a>
            <div class="d-flex justify-content-end mt-3">
              <button type="button" class="btn btn-outline" data-qr-cancel>Cancel</button>
            </div>
          </div>
          <div class="alert alert-danger py-2 small mt-2 mb-0" data-qr-error hidden></div>
        </div>
        </div>
      @endif

      {{-- GCash / Maya through PayMongo: opens the wallet, confirmed automatically (only the ones PayMongo enabled) --}}
      @if ($paymongo && $wallets)
        <div class="method-item">
        <button type="button" class="method-tab" id="tab-wallet" aria-controls="panel-wallet" aria-expanded="{{ $tab === 'wallet' ? 'true' : 'false' }}">
          <strong>{{ count($wallets) === 1 ? 'Open ' . reset($wallets) . ' app' : 'E-wallet' }}</strong><span class="method-arrow" aria-hidden="true">▾</span>
        </button>
        <div class="method-panel" role="region" id="panel-wallet" aria-labelledby="tab-wallet" @if ($tab !== 'wallet') hidden @endif>
          @php $single = count($wallets) === 1; @endphp
          {{-- One e-wallet: a single, centered button in its color that shows the amount. --}}
          <form method="POST" action="{{ route('resident.bill.paymongo', $bill->bill_id) }}" class="wallet-choices {{ $single ? 'single' : '' }}" data-paymongo-form>
            @csrf
            @foreach (array_intersect_key(['gcash' => ['GCash', '#007dfe', 'G'], 'paymaya' => ['Maya', '#00b464', 'M']], $wallets) as $code => [$name, $color, $letter])
              <button type="submit" name="wallet" value="{{ $code }}" class="wallet-btn {{ $single ? 'solid' : '' }}" style="--wallet:{{ $color }};" aria-label="Pay {{ formatCurrency($amount) }} with {{ $name }}">
                <span class="dot" aria-hidden="true">{{ $letter }}</span>{{ $single ? 'Pay ' . formatCurrency($amount) . ' with ' . $name : $name }}
              </button>
            @endforeach
          </form>
          <p class="text-muted small mt-3 mb-0 {{ $single ? 'wallet-note' : '' }}">
            @if ($single)
              {{ reset($wallets) }} opens straight to its payment screen. Your bill turns <strong>Paid</strong> as soon as you finish paying.
            @else
              Tap your e-wallet — it opens straight to its payment screen. Your bill turns <strong>Paid</strong> as soon as you finish paying.
            @endif
          </p>
          <div class="d-flex {{ $single ? 'justify-content-center' : 'justify-content-end' }} mt-3">
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
