@extends('layouts.app')

@section('title', 'Payments')

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
    <button class="btn btn-primary btn-sm btn-phone-block" data-bs-toggle="modal" data-bs-target="#payModal">+ Record Payment</button>
  </div>
  <div class="card-body">
    <ul class="nav nav-tabs pay-tabs mb-3">
      @foreach (['pending' => 'Pending Verification', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All Payments'] as $key => $label)
        <li class="nav-item">
          <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.payments.index', array_filter(['tab' => $key, 'q' => $search ?: null, 'method' => $methodFil ?: null, 'channel' => $channelFil ?: null])) }}">
            {{ $label }}<span class="badge {{ $key === 'pending' && $tabCounts['pending'] ? 'text-bg-warning' : 'text-bg-light' }} count">{{ $tabCounts[$key] }}</span>
          </a>
        </li>
      @endforeach
    </ul>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Search by Meter Number, Name, or reference number..." value="{{ $search }}">
      </div>
      <select name="method" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="gcash" @selected($methodFil === 'gcash')>GCash QR</option>
        <option value="cash" @selected($methodFil === 'cash')>Cash</option>
        <option value="paymongo" @selected($methodFil === 'paymongo')>E-wallet (PayMongo)</option>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
      @if ($search !== '' || $methodFil !== '' || $channelFil !== '')
        <a href="{{ route('admin.payments.index', ['tab' => $tab]) }}" class="btn btn-outline btn-sm">Clear</a>
      @endif
    </form>

    {{-- Fits a laptop screen without sideways scrolling (related details share a column); cards on phones. --}}
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table table-stack pay-table">
        <thead>
          <tr><th>Consumer</th><th>Bill</th><th>Amount Paid</th><th>Method &amp; Proof</th><th>Payment Date</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        @forelse ($payments as $p)
          <tr>
            <td class="stack-title">
              <strong>{{ $p->full_name }}</strong>
              <div class="pay-sub">Meter {{ $p->meter_number }} · {{ $p->payment_reference }}</div>
            </td>
            <td>{{ billingPeriodLabel($p->billing_period) }}<div class="pay-sub">Bill total {{ formatCurrency($p->bill_total) }}</div></td>
            <td><strong>{{ formatCurrency($p->amount_paid) }}</strong></td>
            <td>
              {{ paymentMethodLabel($p->payment_method, $p->wallet ?? null) }} <span class="pay-sub d-inline">· {{ paymentChannelLabel($p->channel) }}</span>
              @if ($p->payment_gateway_txn_id)<div class="pay-sub pay-ref">{{ $p->payment_method === 'paymongo' ? 'PayMongo' : 'Ref.' }} {{ $p->payment_gateway_txn_id }}</div>@endif
              @if ($p->receipt_file)<button type="button" class="btn btn-outline btn-sm mt-1" data-shot="{{ route('admin.payments.receipt', $p->payment_id) }}" data-shot-title="{{ $p->full_name }} — {{ formatCurrency($p->amount_paid) }}"><i class="ph ph-paperclip" aria-hidden="true"></i> Screenshot</button>@endif
            </td>
            <td>{{ formatDateTime($p->payment_date, 'M j, Y') }}<div class="pay-sub">{{ formatDateTime($p->payment_date, 'g:i A') }}</div></td>
            <td>
              <span class="badge {{ paymentStatusBadgeClass($p->status) }}">{{ $p->status === 'pending' && $p->payment_method === 'paymongo' ? 'Waiting for PayMongo' : paymentStatusLabel($p->status) }}</span>
              @if ($p->status === 'verified' && $p->payment_method === 'paymongo')<div class="text-muted" style="font-size:11px;">by PayMongo{{ $p->verified_at ? ' · ' . formatDateTime($p->verified_at) : '' }}</div>@endif
              @if ($p->status === 'verified' && $p->staff_name)<div class="text-muted" style="font-size:11px;">by {{ $p->staff_name }}{{ $p->verified_at ? ' · ' . formatDateTime($p->verified_at) : '' }}</div>@endif
              @if ($p->status === 'rejected' && $p->staff_name)<div class="text-muted" style="font-size:11px;">by {{ $p->staff_name }}</div>@endif
              @if ($p->rejection_reason)<div class="text-danger" style="font-size:11px;">{{ $p->rejection_reason }}</div>@endif
            </td>
            <td class="actions">
              <a href="{{ route('payments.receipt', $p->payment_id) }}" target="_blank" class="btn btn-outline btn-sm" title="View / download receipt"><i class="ph ph-receipt" aria-hidden="true"></i> Receipt</a>
              @if ($p->status === 'pending' && $p->payment_method === 'paymongo')
                <form method="POST" action="{{ route('admin.payments.paymongo-check', $p->payment_id) }}" class="d-inline">
                  @csrf
                  <button class="btn btn-secondary btn-sm" type="submit" title="Ask PayMongo: confirms it if paid, cancels it if not">Check PayMongo</button>
                </form>
              @elseif ($p->status === 'pending' && $p->payment_method === 'gcash' && !$p->payment_gateway_txn_id)
                {{-- The screenshot wasn't read automatically: the Ref. No. and amount are typed in from it. --}}
                <button class="btn btn-success btn-sm" type="button"
                        onclick="openVerify({{ json_encode([
                          'action'  => route('admin.payments.verify', $p->payment_id),
                          'shot'    => $p->receipt_file ? route('admin.payments.receipt', $p->payment_id) : null,
                          'who'     => $p->full_name . ' · Meter ' . $p->meter_number . ' · ' . billingPeriodLabel($p->billing_period),
                          'amount'  => formatCurrency($p->amount_paid),
                        ]) }})">Verify</button>
                <button class="btn btn-danger btn-sm" type="button"
                        onclick="openReject('{{ route('admin.payments.reject', $p->payment_id) }}', {{ json_encode($p->full_name . ' — ' . formatCurrency($p->amount_paid)) }})">Reject</button>
              @elseif ($p->status === 'pending')
                <form method="POST" action="{{ route('admin.payments.verify', $p->payment_id) }}" class="d-inline"
                      data-confirm="Verify this GCash payment?&#10;&#10;Only verify after checking the consumer's receipt screenshot and confirming that {{ formatCurrency($p->amount_paid) }} was received in the barangay GCash account.">
                  @csrf
                  <button class="btn btn-success btn-sm" type="submit">Verify</button>
                </form>
                <button class="btn btn-danger btn-sm" type="button"
                        onclick="openReject('{{ route('admin.payments.reject', $p->payment_id) }}', {{ json_encode($p->full_name . ' — ' . formatCurrency($p->amount_paid)) }})">Reject</button>
              @endif
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="7">{{ $tab === 'pending' ? 'No payments waiting for verification.' : 'No payments found.' }}</td></tr>
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
        <div class="modal-header"><h3 class="h6 mb-0">Record Payment</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          @if (session('pay_error'))
            <div class="alert alert-danger py-2 mb-3" role="alert">{{ session('pay_error') }}</div>
          @endif
          <div class="mb-3">
            <div class="input-group mb-2">
              <span class="input-group-text"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
              <input type="search" id="billSearch" class="form-control" placeholder="Type a name or meter number to find the bill" autocomplete="off" aria-label="Find the water bill">
            </div>
            <div class="bill-pick" id="billPick" role="radiogroup" aria-label="Water bill">
              @forelse ($payableBills as $pb)
                @php $bal = max(0, (float)$pb->total_amount - (float)$pb->amount_paid); @endphp
                <label class="bill-option" data-search="{{ mb_strtolower($pb->full_name . ' ' . $pb->meter_number) }}">
                  <input type="radio" name="bill_id" value="{{ $pb->bill_id }}" data-balance="{{ number_format($bal, 2, '.', '') }}" required
                         @checked((int)old('bill_id', $preselectBillId) === (int)$pb->bill_id)>
                  <span class="bill-who">
                    <strong>{{ $pb->full_name }}</strong>
                    <small>Meter {{ $pb->meter_number }} · {{ billingPeriodLabel($pb->billing_period) }}</small>
                  </span>
                  <span class="bill-bal"><small>Balance</small>{{ formatCurrency($bal) }}</span>
                </label>
              @empty
                <div class="text-muted text-center p-3">No unpaid bills.</div>
              @endforelse
              <div class="text-muted text-center p-3" id="billNoMatch" hidden>No bill matches your search.</div>
            </div>
            <div class="text-muted mt-1" style="font-size:11.5px;">Bills with an online GCash payment waiting for verification are not listed — verify or reject that payment first.</div>
          </div>

          <label class="form-label">Payment Method *</label>
          <div class="method-choice mb-3">
            <label><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash')><span><i class="ph ph-money" aria-hidden="true"></i> Cash</span></label>
            <label><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')><span><i class="ph ph-device-mobile" aria-hidden="true"></i> {{ $paymongo ? 'GCash QR' : 'GCash QR' }}</span></label>
          </div>

          @if ($paymongo)
          {{-- A QR code for this bill's exact balance; PayMongo confirms the payment automatically. --}}
          <div id="gcashCounter" data-paymongo hidden>
            <div id="qrIntro" class="counter-gcash mb-3">
              <div class="counter-steps">
                <strong>How it works</strong>
                <ol class="mb-2 ps-3">
                  <li>Click <strong>Show QR Code</strong> — it is made for this bill's exact amount.</li>
                  <li>The consumer scans it with <strong>GCash</strong> or any bank app and pays.</li>
                  <li>This screen shows <strong>Paid</strong> by itself — no screenshot, no checking.</li>
                </ol>
                <button type="button" class="btn btn-primary" id="qrStartBtn">Show QR Code</button>
              </div>
            </div>
            <div id="qrShow" class="text-center mb-3" hidden>
              <div class="counter-qr d-inline-block">
                <img id="qrImg" alt="QR code to pay this bill with GCash" style="width:240px;height:240px;image-rendering:pixelated;">
                <div class="fw-bold mt-1" id="qrAmount"></div>
              </div>
              <div class="mt-2" id="qrState"><span class="spinner-border spinner-border-sm text-primary me-1"></span> Waiting for the consumer to pay…</div>
              <div class="text-muted small" id="qrCountdown"></div>
              <a id="qrTestLink" href="#" target="_blank" rel="noopener" class="small d-block mt-1" hidden>Test mode: simulate the consumer paying</a>
              <button type="button" class="btn btn-secondary btn-sm mt-2" id="qrCancelBtn">Cancel QR</button>
            </div>
            <div class="alert alert-danger py-2 small" id="qrError" hidden></div>
          </div>
          @else
          <div id="gcashCounter" hidden>
            <div class="counter-gcash mb-3">
              <div class="counter-qr-wrap">
                @if ($qr->isConfigured())
                  <div class="counter-qr">
                    <img src="{{ route('gcash.qr') }}" alt="Barangay GCash QR code">
                    @if ($qr->accountName())<div style="font-size:12.5px;"><strong>{{ $qr->accountName() }}</strong></div>@endif
                    @if ($qr->number())<div class="text-muted" style="font-size:12px;">{{ $qr->number() }}</div>@endif
                  </div>
                @else
                  <div class="alert alert-warning small mb-0">No GCash QR code uploaded yet. Add it in <a href="{{ route('admin.settings.edit') }}">System Settings</a>.</div>
                @endif
              </div>
              <div class="counter-steps">
                <strong>Ask the consumer to:</strong>
                <ol class="mb-2 ps-3">
                  <li>Open the GCash app and tap <strong>Scan QR</strong>.</li>
                  <li>Scan this QR code.</li>
                  <li>Enter the amount below and complete the payment.</li>
                  <li>Show you the GCash receipt.</li>
                </ol>
                <div class="text-muted" style="font-size:12px;">Check that the money arrived in the barangay GCash account, then upload a screenshot of the receipt below.</div>
              </div>
            </div>
            <div class="mb-3">
              <label for="gcash_screenshot" class="form-label">Screenshot of the GCash Payment *</label>
              <input type="file" id="gcash_screenshot" name="gcash_screenshot" class="form-control" accept="image/jpeg,image/png,image/webp">
              <div class="text-muted" style="font-size:11.5px;">The full GCash receipt. The system reads its Ref. No. and amount, refuses a Ref. No. that was already used, and checks that the amount equals the bill balance. JPG, PNG or WEBP, up to 5 MB.</div>
              <img id="gcashShotPreview" alt="Preview of the GCash payment screenshot" hidden style="display:block;max-width:200px;max-height:300px;margin:8px auto 0;border:1px solid var(--border);border-radius:10px;">
            </div>
          </div>
          @endif

          <div class="row g-3">
            <div class="col-md-6" id="amountField">
              <label for="amount_paid" class="form-label">Amount Paid *</label>
              <input type="number" step="0.01" min="0.01" id="amount_paid" name="amount_paid" class="form-control" required value="{{ old('amount_paid') }}">
              <div class="text-muted" style="font-size:11.5px;">Filled with the bill balance; a full payment marks the bill Paid.</div>
            </div>
            <div class="col-md-6">
              <label for="remarks" class="form-label">Remarks</label>
              <input type="text" id="remarks" name="remarks" class="form-control" value="{{ old('remarks') }}">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="counterSubmit">Confirm Payment Received</button>
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
        <div class="modal-header"><h3 class="h6 mb-0">Verify GCash Payment</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6 text-center">
              <div class="shot-box">
                <img id="verifyShot" class="shot-img" alt="Consumer's GCash receipt screenshot" title="Click to zoom in or out">
              </div>
              <div class="text-muted small mt-1">Click the screenshot to zoom in or out.</div>
            </div>
            <div class="col-md-6">
              <p class="mb-1 small text-muted" id="verifyWho"></p>
              <p class="mb-3">Amount to receive: <strong id="verifyAmount"></strong></p>
              <div class="mb-3">
                <label for="verify_ref" class="form-label">GCash Ref. No. on the screenshot *</label>
                <input type="text" id="verify_ref" name="gcash_reference" class="form-control" required inputmode="numeric"
                       pattern="[\d\s-]{13,17}" maxlength="17" autocomplete="off" placeholder="e.g. 1234 567 890123">
                <div class="text-muted" style="font-size:11.5px;">13 digits. A Ref. No. already used for another payment is refused.</div>
              </div>
              <div class="mb-3">
                <label for="verify_amount" class="form-label">Amount on the screenshot (₱) *</label>
                <input type="number" step="0.01" min="0" id="verify_amount" name="amount_seen" class="form-control" required>
                <div class="text-muted" style="font-size:11.5px;">Must equal the amount to receive.</div>
              </div>
              <div class="alert alert-warning small mb-0">Also check the barangay GCash app that this payment really arrived before verifying.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Verify Payment</button>
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
        <div class="modal-header"><h3 class="h6 mb-0">Reject GCash Payment</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <p class="mb-2" id="rejectWho"></p>
          <label for="rejection_reason" class="form-label">Reason (shown to the consumer) *</label>
          <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="3" maxlength="255" required
                    placeholder="e.g. No GCash payment with this reference number was received."></textarea>
          <p class="text-muted small mt-2 mb-0">The bill stays unpaid and the consumer can pay again.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- "View screenshot": the consumer's receipt in a pop-up on this page (click it to zoom). --}}
<div class="modal fade" id="shotModal" tabindex="-1" aria-labelledby="shotTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header"><h3 class="h6 mb-0" id="shotTitle">GCash screenshot</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body shot-viewer">
        <div class="shot-box">
          <div class="spinner-border text-primary" role="status" id="shotLoading"><span class="visually-hidden">Loading…</span></div>
          <img id="shotImg" class="shot-img" alt="Consumer's GCash receipt screenshot" title="Click to zoom in or out" hidden>
        </div>
        <div class="text-muted small text-center mt-2">Click the screenshot to zoom in or out.</div>
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
    document.getElementById('shotTitle').textContent = 'GCash screenshot — ' + (btn.dataset.shotTitle || '');
    img.hidden = true; loading.hidden = false; img.classList.remove('zoomed');
    img.onload = () => { loading.hidden = true; img.hidden = false; };
    img.onerror = () => { loading.hidden = true; img.hidden = true; document.getElementById('shotTitle').textContent = 'The screenshot could not be loaded.'; };
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
    if (f && f.size > 5 * 1024 * 1024) { alert('The screenshot is larger than 5 MB. Please choose a smaller image.'); this.value = ''; img.hidden = true; return; }
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

    submit.textContent = gcash ? 'Confirm GCash Payment Received' : 'Confirm Cash Received';
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
      $('qrStartBtn').disabled = false; $('qrStartBtn').textContent = 'Show QR Code';
      $('qrError').hidden = !message; $('qrError').textContent = message || '';
      document.querySelectorAll('#payModal input[name="bill_id"], #payModal input[name="payment_method"]').forEach(i => { i.disabled = false; });
    }

    $('qrStartBtn').addEventListener('click', () => {
      const bill = picker.querySelector('input[name="bill_id"]:checked');
      if (!bill) { reset('Search for the consumer and choose the bill first.'); return; }
      $('qrStartBtn').disabled = true; $('qrStartBtn').textContent = 'Creating QR code…'; $('qrError').hidden = true;
      post({{ Js::from(route('admin.payments.qr')) }}, { bill_id: bill.value })
        .then(r => r.json())
        .then(d => {
          if (d.error) { reset(d.error); return; }
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
              $('qrCountdown').textContent = 'QR code valid for ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
            };
            show(); tick = setInterval(show, 1000);
          }
        })
        .catch(() => reset('The QR code could not be created. Please try again.'));
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
            reset('The QR code expired before it was paid. Click Show QR Code to make a new one.');
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
    if (isGcash()) submit.textContent = 'Checking the screenshot…';
  });
  if (!amount.value) fillAmount();
  syncMethod();

  @if ($preselectBillId || old('bill_id') || session('pay_error'))
  document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('payModal')).show());
  @endif
})();
</script>
@endsection
