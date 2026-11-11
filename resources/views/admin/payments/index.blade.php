@extends('layouts.app')

@section('title', __('Payments'))

@push('styles')
<style>
  .bill-pick{max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;}
  .bill-option{display:flex;align-items:center;gap:12px;padding:10px 12px;margin:0;border-bottom:1px solid var(--border);cursor:pointer;}
  .bill-option:last-of-type{border-bottom:0;}
  .bill-option:hover{background:#f4fafc;}
  .bill-option:has(input:checked){background:#e8f6fb;box-shadow:inset 3px 0 0 var(--primary);}
  .bill-option input{flex:none;margin:0;}
  .bill-who{flex:1;min-width:0;display:flex;flex-direction:column;}
  .bill-who strong{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .bill-who small,.bill-bal small{color:var(--text-muted);font-size:12px;}
  .bill-bal{flex:none;text-align:right;font-weight:700;display:flex;flex-direction:column;}
  .pay-tabs .nav-link{font-weight:500;}
  .pay-tabs .count{font-size:11px;margin-left:4px;}
  .method-choice{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
  .method-choice label{border:2px solid var(--border);border-radius:10px;padding:12px;text-align:center;cursor:pointer;font-weight:600;}
  .method-choice input{display:none;}
  .method-choice input:checked + span{color:var(--primary-dark);}
  .method-choice label:has(input:checked){border-color:var(--primary);background:#f0fbff;}
  .counter-gcash{display:flex;flex-direction:column;align-items:center;gap:14px;}
  .counter-qr-wrap{width:100%;max-width:280px;}
  .counter-steps{width:100%;max-width:440px;font-size:13.5px;}
  .counter-qr{text-align:center;border:2px solid #007dfe;border-radius:12px;padding:10px;background:#fff;}
  .counter-qr img{width:100%;max-width:260px;aspect-ratio:1/1;object-fit:contain;}
  /* GCash QR: "How it works" as one neat panel with numbered steps */
  #qrIntro .counter-steps{max-width:none;background:#f4fafc;border:1px solid var(--border);border-radius:12px;padding:16px 18px;}
  #qrIntro .counter-steps > strong{display:block;font-size:14px;margin-bottom:10px;color:var(--primary-dark);}
  #qrIntro .counter-steps ol{list-style:none;counter-reset:qrstep;padding:0 !important;margin:0 0 14px !important;display:flex;flex-direction:column;gap:9px;}
  #qrIntro .counter-steps li{counter-increment:qrstep;position:relative;padding-left:32px;line-height:1.55;min-height:22px;}
  #qrIntro .counter-steps li::before{content:counter(qrstep);position:absolute;left:0;top:0;width:22px;height:22px;border-radius:50%;background:var(--primary);color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;}
  #qrStartBtn{width:100%;padding:11px;}
  /* Receipt screenshots: shown on the page (View screenshot pop-up and Verify), click to zoom. */
  .shot-box{display:block;min-height:120px;max-height:75vh;overflow:auto;background:#f3f6f8;border:1px solid var(--border);border-radius:10px;padding:8px;}
  .shot-box .spinner-border{display:block;margin:40px auto;}
  .shot-img{display:block;margin:0 auto;max-width:100%;max-height:calc(75vh - 18px);border-radius:8px;cursor:zoom-in;}
  .shot-img.zoomed{max-width:none;max-height:none;cursor:zoom-out;}
  #verifyModal .shot-box{max-height:440px;}
  #verifyModal .shot-img{max-height:420px;}
  #verifyModal .shot-img.zoomed{max-height:none;}

  /* Payments table: compact so it fits without a sideways scroll bar. */
  .pay-table .pay-sub{font-size:11.5px;color:var(--text-muted);}
  .pay-table .pay-ref{word-break:break-all;max-width:190px;}
  .pay-table td.actions{white-space:normal;min-width:150px;}
  .pay-table td.actions > .btn, .pay-table td.actions > form{margin:2px 0;}
  @media (min-width:577px){
    .pay-table td, .pay-table th{padding-left:10px;padding-right:10px;}
  }
</style>
@endpush

@section('content')
<div class="card">
  {{-- The page title (top bar) already says Payments. --}}
  <div class="card-header justify-content-end">
    <button class="btn btn-primary btn-sm btn-phone-block" data-bs-toggle="modal" data-bs-target="#payModal">{{ __('+ Record Payment') }}</button>
  </div>
  <div class="card-body">
    <ul class="nav nav-tabs pay-tabs mb-3">
      @foreach (['pending' => 'Pending Verification', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All Payments'] as $key => $label)
        <li class="nav-item">
          <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.payments.index', array_filter(['tab' => $key, 'q' => $search ?: null, 'method' => $methodFil ?: null, 'channel' => $channelFil ?: null])) }}">
            {{ __($label) }}<span class="badge {{ $key === 'pending' && $tabCounts['pending'] ? 'text-bg-warning' : 'text-bg-light' }} count">{{ $tabCounts[$key] }}</span>
          </a>
        </li>
      @endforeach
    </ul>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
        <input type="text" name="q" class="form-control" placeholder="{{ __('Search by Meter Number, Name, or reference number...') }}" value="{{ $search }}">
      </div>
      <select name="method" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">{{ __('All') }}</option>
        <option value="gcash" @selected($methodFil === 'gcash')>{{ __('GCash QR') }}</option>
        <option value="cash" @selected($methodFil === 'cash')>{{ __('Cash') }}</option>
        <option value="paymongo" @selected($methodFil === 'paymongo')>{{ __('E-wallet (PayMongo)') }}</option>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">{{ __('Filter') }}</button>
      @if ($search !== '' || $methodFil !== '' || $channelFil !== '')
        <a href="{{ route('admin.payments.index', ['tab' => $tab]) }}" class="btn btn-outline btn-sm">{{ __('Clear') }}</a>
      @endif
    </form>

    {{-- Fits a laptop screen without sideways scrolling (related details share a column); cards on phones. --}}
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table table-stack pay-table">
        <thead>
          <tr><th>{{ __('Consumer') }}</th><th>{{ __('Bill') }}</th><th>{{ __('Amount Paid') }}</th><th>{{ __('Method & Proof') }}</th><th>{{ __('Payment Date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr>
        </thead>
        <tbody>
        @forelse ($payments as $p)
          <tr>
            <td class="stack-title">
              <strong>{{ $p->full_name }}</strong>
              <div class="pay-sub">{{ __('Meter :meternumber · :paymentreference', ['meternumber' => $p->meter_number, 'paymentreference' => $p->payment_reference]) }}</div>
            </td>
            <td>{{ billingPeriodLabel($p->billing_period) }}<div class="pay-sub">{{ __('Bill total :amount', ['amount' => formatCurrency($p->bill_total)]) }}</div></td>
            <td><strong>{{ formatCurrency($p->amount_paid) }}</strong></td>
            <td>
              {{ paymentMethodLabel($p->payment_method, $p->wallet ?? null) }} <span class="pay-sub d-inline">· {{ paymentChannelLabel($p->channel) }}</span>
              @if ($p->payment_gateway_txn_id)<div class="pay-sub pay-ref">{{ $p->payment_method === 'paymongo' ? __('PayMongo') : __('Ref.') }} {{ $p->payment_gateway_txn_id }}</div>@endif
              @if ($p->receipt_file)<button type="button" class="btn btn-outline btn-sm mt-1" data-shot="{{ route('admin.payments.receipt', $p->payment_id) }}" data-shot-title="{{ $p->full_name }} — {{ formatCurrency($p->amount_paid) }}"><i class="ph ph-paperclip" aria-hidden="true"></i> {{ __('Screenshot') }}</button>@endif
            </td>
            <td>{{ formatDateTime($p->payment_date, 'M j, Y') }}<div class="pay-sub">{{ formatDateTime($p->payment_date, 'g:i A') }}</div></td>
            <td>
              <span class="badge {{ paymentStatusBadgeClass($p->status) }}">{{ $p->status === 'pending' && $p->payment_method === 'paymongo' ? __('Waiting for PayMongo') : paymentStatusLabel($p->status) }}</span>
              @if ($p->status === 'verified' && $p->payment_method === 'paymongo')<div class="text-muted" style="font-size:11px;">{{ __('by PayMongo:value', ['value' => $p->verified_at ? ' · ' . formatDateTime($p->verified_at) : '']) }}</div>@endif
              @if ($p->status === 'verified' && $p->staff_name)<div class="text-muted" style="font-size:11px;">{{ __('by :staffname:value', ['staffname' => $p->staff_name, 'value' => $p->verified_at ? ' · ' . formatDateTime($p->verified_at) : '']) }}</div>@endif
              @if ($p->status === 'rejected' && $p->staff_name)<div class="text-muted" style="font-size:11px;">{{ __('by :staffname', ['staffname' => $p->staff_name]) }}</div>@endif
              @if ($p->rejection_reason)<div class="text-danger" style="font-size:11px;">{{ $p->rejection_reason }}</div>@endif
            </td>
            <td class="actions">
              <a href="{{ route('payments.receipt', $p->payment_id) }}" target="_blank" class="btn btn-outline btn-sm" title="{{ __('View / download receipt') }}"><i class="ph ph-receipt" aria-hidden="true"></i> {{ __('Receipt') }}</a>
              @if ($p->status === 'pending' && $p->payment_method === 'paymongo')
                <form method="POST" action="{{ route('admin.payments.paymongo-check', $p->payment_id) }}" class="d-inline">
                  @csrf
                  <button class="btn btn-secondary btn-sm" type="submit" title="{{ __('Ask PayMongo: confirms it if paid, cancels it if not') }}">{{ __('Check PayMongo') }}</button>
                </form>
              @elseif ($p->status === 'pending' && $p->payment_method === 'gcash' && !$p->payment_gateway_txn_id)
                {{-- The screenshot wasn't read automatically: the Ref. No. and amount are typed in from it. --}}
                <button class="btn btn-success btn-sm" type="button"
                        onclick="openVerify({{ json_encode([
                          'action'  => route('admin.payments.verify', $p->payment_id),
                          'shot'    => $p->receipt_file ? route('admin.payments.receipt', $p->payment_id) : null,
                          'who'     => $p->full_name . ' · Meter ' . $p->meter_number . ' · ' . billingPeriodLabel($p->billing_period),
                          'amount'  => formatCurrency($p->amount_paid),
                        ]) }})">{{ __('Verify') }}</button>
                <button class="btn btn-danger btn-sm" type="button"
                        onclick="openReject('{{ route('admin.payments.reject', $p->payment_id) }}', {{ json_encode($p->full_name . ' — ' . formatCurrency($p->amount_paid)) }})">{{ __('Reject') }}</button>
              @elseif ($p->status === 'pending')
                <form method="POST" action="{{ route('admin.payments.verify', $p->payment_id) }}" class="d-inline"
                      data-confirm="{{ __('Verify this GCash payment?') }}&#10;&#10;{{ __('Only verify after checking the consumer\'s receipt screenshot and confirming that :amount was received in the barangay GCash account.', ['amount' => formatCurrency($p->amount_paid)]) }}">
                  @csrf
                  <button class="btn btn-success btn-sm" type="submit">{{ __('Verify') }}</button>
                </form>
                <button class="btn btn-danger btn-sm" type="button"
                        onclick="openReject('{{ route('admin.payments.reject', $p->payment_id) }}', {{ json_encode($p->full_name . ' — ' . formatCurrency($p->amount_paid)) }})">{{ __('Reject') }}</button>
              @endif
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="7">{{ $tab === 'pending' ? __('No payments waiting for verification.') : __('No payments found.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $payments])
  </div>
</div>

{{-- Consumer pays in person at the barangay: Cash or GCash QR --}}
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.payments.store') }}" id="counterForm" enctype="multipart/form-data">
        <div class="modal-header"><h3 class="h6 mb-0">{{ __('Record Payment') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          @if (session('pay_error'))
            <div class="alert alert-danger py-2 mb-3" role="alert">{{ session('pay_error') }}</div>
          @endif
          <div class="mb-3">
            <div class="input-group mb-2">
              <span class="input-group-text"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
              <input type="search" id="billSearch" class="form-control" placeholder="{{ __('Type a name or meter number to find the bill') }}" autocomplete="off" aria-label="{{ __('Find the water bill') }}">
            </div>
            <div class="bill-pick" id="billPick" role="radiogroup" aria-label="{{ __('Water bill') }}">
              @forelse ($payableBills as $pb)
                @php $bal = max(0, (float)$pb->total_amount - (float)$pb->amount_paid); @endphp
                <label class="bill-option" data-search="{{ mb_strtolower($pb->full_name . ' ' . $pb->meter_number) }}">
                  <input type="radio" name="bill_id" value="{{ $pb->bill_id }}" data-balance="{{ number_format($bal, 2, '.', '') }}" required
                         @checked((int)old('bill_id', $preselectBillId) === (int)$pb->bill_id)>
                  <span class="bill-who">
                    <strong>{{ $pb->full_name }}</strong>
                    <small>{{ __('Meter :meternumber · :period', ['meternumber' => $pb->meter_number, 'period' => billingPeriodLabel($pb->billing_period)]) }}</small>
                  </span>
                  <span class="bill-bal"><small>{{ __('Balance') }}</small>{{ formatCurrency($bal) }}</span>
                </label>
              @empty
                <div class="text-muted text-center p-3">{{ __('No unpaid bills.') }}</div>
              @endforelse
              <div class="text-muted text-center p-3" id="billNoMatch" hidden>{{ __('No bill matches your search.') }}</div>
            </div>
            <div class="text-muted mt-1" style="font-size:11.5px;">{{ __('Bills with an online GCash payment waiting for verification are not listed — verify or reject that payment first.') }}</div>
          </div>

          <label class="form-label">{{ __('Payment Method *') }}</label>
          <div class="method-choice mb-3">
            <label><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash')><span><i class="ph ph-money" aria-hidden="true"></i> {{ __('Cash') }}</span></label>
            <label><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')><span><i class="ph ph-device-mobile" aria-hidden="true"></i> {{ $paymongo ? __('GCash QR') : __('GCash QR') }}</span></label>
          </div>

          @if ($paymongo)
          {{-- A QR code for this bill's exact balance; PayMongo confirms the payment automatically. --}}
          <div id="gcashCounter" data-paymongo hidden>
            <div id="qrIntro" class="counter-gcash mb-3">
              <div class="counter-steps">
                <strong>{{ __('How it works') }}</strong>
                <ol class="mb-2 ps-3">
                  <li>{{ __('Click') }} <strong>{{ __('Show QR Code') }}</strong> {{ __('— it is made for this bill\'s exact amount.') }}</li>
                  <li>{{ __('The consumer scans it with') }} <strong>{{ __('GCash') }}</strong> {{ __('or any bank app and pays.') }}</li>
                  <li>{{ __('This screen shows') }} <strong>{{ __('Paid') }}</strong> {{ __('by itself — no screenshot, no checking.') }}</li>
                </ol>
                <button type="button" class="btn btn-primary" id="qrStartBtn">{{ __('Show QR Code') }}</button>
              </div>
            </div>
            <div id="qrShow" class="text-center mb-3" hidden>
              <div class="counter-qr d-inline-block">
                <img id="qrImg" alt="{{ __('QR code to pay this bill with GCash') }}" style="width:240px;height:240px;image-rendering:pixelated;">
                <div class="fw-bold mt-1" id="qrAmount"></div>
              </div>
              <div class="mt-2" id="qrState"><span class="spinner-border spinner-border-sm text-primary me-1"></span> {{ __('Waiting for the consumer to pay…') }}</div>
              <div class="text-muted small" id="qrCountdown"></div>
              <a id="qrTestLink" href="#" target="_blank" rel="noopener" class="small d-block mt-1" hidden>{{ __('Test mode: simulate the consumer paying') }}</a>
              <button type="button" class="btn btn-secondary btn-sm mt-2" id="qrCancelBtn">{{ __('Cancel QR') }}</button>
            </div>
            <div class="alert alert-danger py-2 small" id="qrError" hidden></div>
          </div>
          @else
          <div id="gcashCounter" hidden>
            <div class="counter-gcash mb-3">
              <div class="counter-qr-wrap">
                @if ($qr->isConfigured())
                  <div class="counter-qr">
                    <img src="{{ route('gcash.qr') }}" alt="{{ __('Barangay GCash QR code') }}">
                    @if ($qr->accountName())<div style="font-size:12.5px;"><strong>{{ $qr->accountName() }}</strong></div>@endif
                    @if ($qr->number())<div class="text-muted" style="font-size:12px;">{{ $qr->number() }}</div>@endif
                  </div>
                @else
                  <div class="alert alert-warning small mb-0">{{ __('No GCash QR code uploaded yet. Add it in') }} <a href="{{ route('admin.settings.edit') }}">{{ __('System Settings') }}</a>.</div>
                @endif
              </div>
              <div class="counter-steps">
                <strong>{{ __('Ask the consumer to:') }}</strong>
                <ol class="mb-2 ps-3">
                  <li>{{ __('Open the GCash app and tap') }} <strong>{{ __('Scan QR') }}</strong>.</li>
                  <li>{{ __('Scan this QR code.') }}</li>
                  <li>{{ __('Enter the amount below and complete the payment.') }}</li>
                  <li>{{ __('Show you the GCash receipt.') }}</li>
                </ol>
                <div class="text-muted" style="font-size:12px;">{{ __('Check that the money arrived in the barangay GCash account, then upload a screenshot of the receipt below.') }}</div>
              </div>
            </div>
            <div class="mb-3">
              <label for="gcash_screenshot" class="form-label">{{ __('Screenshot of the GCash Payment *') }}</label>
              <input type="file" id="gcash_screenshot" name="gcash_screenshot" class="form-control" accept="image/jpeg,image/png,image/webp">
              <div class="text-muted" style="font-size:11.5px;">{{ __('The full GCash receipt. The system reads its Ref. No. and amount, refuses a Ref. No. that was already used, and checks that the amount equals the bill balance. JPG, PNG or WEBP, up to 5 MB.') }}</div>
              <img id="gcashShotPreview" alt="{{ __('Preview of the GCash payment screenshot') }}" hidden style="display:block;max-width:200px;max-height:300px;margin:8px auto 0;border:1px solid var(--border);border-radius:10px;">
            </div>
          </div>
          @endif

          <div class="row g-3">
            <div class="col-md-6" id="amountField">
              <label for="amount_paid" class="form-label">{{ __('Amount Paid *') }}</label>
              <input type="number" step="0.01" min="0.01" id="amount_paid" name="amount_paid" class="form-control" required value="{{ old('amount_paid') }}">
              <div class="text-muted" style="font-size:11.5px;">{{ __('Filled with the bill balance; a full payment marks the bill Paid.') }}</div>
            </div>
            <div class="col-md-6">
              <label for="remarks" class="form-label">{{ __('Remarks') }}</label>
              <input type="text" id="remarks" name="remarks" class="form-control" value="{{ old('remarks') }}">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary" id="counterSubmit">{{ __('Confirm Payment Received') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="verifyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <form method="POST" action="" id="verifyForm">
        @csrf
        <div class="modal-header"><h3 class="h6 mb-0">{{ __('Verify GCash Payment') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6 text-center">
              <div class="shot-box">
                <img id="verifyShot" class="shot-img" alt="{{ __('Consumer\'s GCash receipt screenshot') }}" title="{{ __('Click to zoom in or out') }}">
              </div>
              <div class="text-muted small mt-1">{{ __('Click the screenshot to zoom in or out.') }}</div>
            </div>
            <div class="col-md-6">
              <p class="mb-1 small text-muted" id="verifyWho"></p>
              <p class="mb-3">{{ __('Amount to receive:') }} <strong id="verifyAmount"></strong></p>
              <div class="mb-3">
                <label for="verify_ref" class="form-label">{{ __('GCash Ref. No. on the screenshot *') }}</label>
                <input type="text" id="verify_ref" name="gcash_reference" class="form-control" required inputmode="numeric"
                       pattern="[\d\s-]{13,17}" maxlength="17" autocomplete="off" placeholder="e.g. 1234 567 890123">
                <div class="text-muted" style="font-size:11.5px;">{{ __('13 digits. A Ref. No. already used for another payment is refused.') }}</div>
              </div>
              <div class="mb-3">
                <label for="verify_amount" class="form-label">{{ __('Amount on the screenshot (₱) *') }}</label>
                <input type="number" step="0.01" min="0" id="verify_amount" name="amount_seen" class="form-control" required>
                <div class="text-muted" style="font-size:11.5px;">{{ __('Must equal the amount to receive.') }}</div>
              </div>
              <div class="alert alert-warning small mb-0">{{ __('Also check the barangay GCash app that this payment really arrived before verifying.') }}</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-success">{{ __('Verify Payment') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="" id="rejectForm">
        @csrf
        <div class="modal-header"><h3 class="h6 mb-0">{{ __('Reject GCash Payment') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <p class="mb-2" id="rejectWho"></p>
          <label for="rejection_reason" class="form-label">{{ __('Reason (shown to the consumer) *') }}</label>
          <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="3" maxlength="255" required
                    placeholder="{{ __('e.g. No GCash payment with this reference number was received.') }}"></textarea>
          <p class="text-muted small mt-2 mb-0">{{ __('The bill stays unpaid and the consumer can pay again.') }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-danger">{{ __('Reject Payment') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- "View screenshot": the consumer's receipt in a pop-up on this page (click it to zoom). --}}
<div class="modal fade" id="shotModal" tabindex="-1" aria-labelledby="shotTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header"><h3 class="h6 mb-0" id="shotTitle">{{ __('GCash screenshot') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
      <div class="modal-body shot-viewer">
        <div class="shot-box">
          <div class="spinner-border text-primary" role="status" id="shotLoading"><span class="visually-hidden">{{ __('Loading…') }}</span></div>
          <img id="shotImg" class="shot-img" alt="{{ __('Consumer\'s GCash receipt screenshot') }}" title="{{ __('Click to zoom in or out') }}" hidden>
        </div>
        <div class="text-muted small text-center mt-2">{{ __('Click the screenshot to zoom in or out.') }}</div>
      </div>
    </div>
  </div>
</div>

<script>
// Screenshots open in a pop-up here (no new tab); clicking one zooms it.
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-shot]');
  if (btn) {
    const img = document.getElementById('shotImg'), loading = document.getElementById('shotLoading');
    document.getElementById('shotTitle').textContent = {{ Js::from(__('GCash screenshot — ')) }} + (btn.dataset.shotTitle || '');
    img.hidden = true; loading.hidden = false; img.classList.remove('zoomed');
    img.onload = () => { loading.hidden = true; img.hidden = false; };
    img.onerror = () => { loading.hidden = true; img.hidden = true; document.getElementById('shotTitle').textContent = {{ Js::from(__('The screenshot could not be loaded.')) }}; };
    img.src = btn.dataset.shot;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('shotModal')).show();
    return;
  }
  const shot = e.target.closest('.shot-img');
  if (shot) shot.classList.toggle('zoomed');
});
</script>

<script>
function openVerify(p) {
  const form = document.getElementById('verifyForm');
  form.action = p.action;
  form.reset();
  document.getElementById('verifyWho').textContent = p.who;
  document.getElementById('verifyAmount').textContent = p.amount;
  const img = document.getElementById('verifyShot');
  img.hidden = !p.shot; img.classList.remove('zoomed');
  if (p.shot) img.src = p.shot;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('verifyModal')).show();
}
function openReject(action, who) {
  document.getElementById('rejectForm').action = action;
  document.getElementById('rejectWho').textContent = who;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('rejectModal')).show();
}
(function () {
  const picker = document.getElementById('billPick');
  const search = document.getElementById('billSearch');
  const amount = document.getElementById('amount_paid');
  const gcashBox = document.getElementById('gcashCounter');
  const submit = document.getElementById('counterSubmit');

  const isGcash = () => document.querySelector('input[name="payment_method"]:checked')?.value === 'gcash';
  function fillAmount() {
    const opt = picker.querySelector('input[name="bill_id"]:checked');
    if (opt && opt.dataset.balance) {
      amount.value = opt.dataset.balance;
      amount.max = opt.dataset.balance;
    }
  }
  // Names stay hidden until the admin searches.
  function filterBills() {
    const q = search.value.trim().toLowerCase();
    let shown = 0;
    picker.querySelectorAll('.bill-option').forEach(o => {
      const match = q !== '' && o.dataset.search.includes(q);
      o.hidden = !match;
      if (match) shown++;
    });
    // The list box only appears once there is something to show.
    picker.hidden = q === '';
    document.getElementById('billNoMatch').hidden = q === '' || shown > 0;
  }
  search.addEventListener('input', filterBills);
  picker.addEventListener('change', filterBills);
  filterBills();
  document.getElementById('gcash_screenshot')?.addEventListener('change', function () {
    const img = document.getElementById('gcashShotPreview'), f = this.files && this.files[0];
    if (f && f.size > 5 * 1024 * 1024) { alert({{ Js::from(__('The screenshot is larger than 5 MB. Please choose a smaller image.')) }}); this.value = ''; img.hidden = true; return; }
    img.hidden = !f;
    if (f) img.src = URL.createObjectURL(f);
  });
  // Keep the chosen bill in view when the modal opens.
  document.getElementById('payModal').addEventListener('shown.bs.modal', () => {
    picker.querySelector('input[name="bill_id"]:checked')?.closest('.bill-option').scrollIntoView({ block: 'nearest' });
  });
  const qrMode = gcashBox.hasAttribute('data-paymongo');
  function syncMethod() {
    const gcash = isGcash();
    gcashBox.hidden = !gcash;
    const shot = document.getElementById('gcash_screenshot');
    if (shot) shot.required = gcash;
    // GCash: the amount comes from the QR / screenshot, so the field is not needed.
    document.getElementById('amountField').hidden = gcash;
    amount.disabled = gcash;
    // GCash QR is confirmed by PayMongo itself — there is nothing to submit.
    submit.hidden = gcash && qrMode;

    submit.textContent = gcash ? {{ Js::from(__('Confirm GCash Payment Received')) }} : {{ Js::from(__('Confirm Cash Received')) }};
  }

  // ---- GCash QR (PayMongo): show the code, then wait until it is paid ----
  if (qrMode) {
    const $ = id => document.getElementById(id);
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const post = (url, body) => fetch(url, { method: 'POST', credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });
    let current = null, poll = null, tick = null;

    function reset(message) {
      clearInterval(poll); clearInterval(tick); current = null;
      $('qrShow').hidden = true; $('qrIntro').hidden = false;
      $('qrStartBtn').disabled = false; $('qrStartBtn').textContent = {{ Js::from(__('Show QR Code')) }};
      $('qrError').hidden = !message; $('qrError').textContent = message || '';
      document.querySelectorAll('#payModal input[name="bill_id"], #payModal input[name="payment_method"]').forEach(i => { i.disabled = false; });
    }

    $('qrStartBtn').addEventListener('click', () => {
      const bill = picker.querySelector('input[name="bill_id"]:checked');
      if (!bill) { reset({{ Js::from(__('Search for the consumer and choose the bill first.')) }}); return; }
      $('qrStartBtn').disabled = true; $('qrStartBtn').textContent = {{ Js::from(__('Creating QR code…')) }}; $('qrError').hidden = true;
      post({{ Js::from(route('admin.payments.qr')) }}, { bill_id: bill.value })
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
          if (!ok || !d.image) { reset(d.error || {{ Js::from(__('The QR code could not be created. Please try again.')) }}); return; }
          current = d;
          // The bill and method can't change while its QR is on screen.
          document.querySelectorAll('#payModal input[name="bill_id"], #payModal input[name="payment_method"]').forEach(i => { i.disabled = true; });
          $('qrImg').src = d.image;
          $('qrAmount').textContent = '₱' + Number(d.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
          $('qrTestLink').hidden = !d.test_url; if (d.test_url) $('qrTestLink').href = d.test_url;
          $('qrIntro').hidden = true; $('qrShow').hidden = false;
          poll = setInterval(checkStatus, 3000);
          if (d.expires_at) {
            const end = new Date(d.expires_at).getTime();
            const show = () => {
              const s = Math.max(0, Math.round((end - Date.now()) / 1000));
              $('qrCountdown').textContent = {{ Js::from(__('QR code valid for :time')) }}.replace(':time', Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'));
            };
            show(); tick = setInterval(show, 1000);
          }
        })
        .catch(() => reset({{ Js::from(__('The QR code could not be created. Please try again.')) }}));
    });

    function checkStatus() {
      if (!current) return;
      fetch({{ Js::from(route('admin.payments.qr-status', ['payment' => '__ID__'])) }}.replace('__ID__', current.payment_id),
        { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(d => {
          if (d.status === 'paid') {
            clearInterval(poll); clearInterval(tick);
            $('qrState').innerHTML = '<span class="badge badge-success" style="font-size:14px;"><i class="ph ph-check-circle" aria-hidden="true"></i> Paid</span>';
            $('qrCancelBtn').hidden = true;
            setTimeout(() => { location.href = d.redirect; }, 1200);
          } else if (d.status === 'expired' || d.status === 'gone') {
            reset({{ Js::from(__('The QR code expired before it was paid. Click Show QR Code to make a new one.')) }});
          }
        })
        .catch(() => { /* try again on the next tick */ });
    }

    function cancelCurrent() {
      if (!current) return;
      const id = current.payment_id;
      reset();
      post({{ Js::from(route('admin.payments.qr-cancel', ['payment' => '__ID__'])) }}.replace('__ID__', id))
        .then(r => r.json())
        .then(d => { if (d.paid) location.href = {{ Js::from(route('admin.payments.index', ['tab' => 'paid'])) }}; });
    }
    $('qrCancelBtn').addEventListener('click', cancelCurrent);
    // Closing the pop-up with a QR still on screen cancels it, so it can't be paid later by mistake.
    document.getElementById('payModal').addEventListener('hide.bs.modal', cancelCurrent);
  }
  picker.addEventListener('change', fillAmount);
  document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', syncMethod));
  document.getElementById('counterForm').addEventListener('submit', () => {
    submit.disabled = true;
    if (isGcash()) submit.textContent = {{ Js::from(__('Checking the screenshot…')) }};
  });
  if (!amount.value) fillAmount();
  syncMethod();

  @if ($preselectBillId || old('bill_id') || session('pay_error'))
  document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('payModal')).show());
  @endif
})();
</script>
@endsection
