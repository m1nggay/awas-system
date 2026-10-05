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
</style>
@endpush

@section('content')
<div class="card">
  <div class="card-header">
    <h3>Payments</h3>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payModal">+ Record Payment</button>
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
        <span class="input-group-text">🔍</span>
        <input type="text" name="q" class="form-control" placeholder="Search by Meter Number, Name, or reference number..." value="{{ $search }}">
      </div>
      <select name="method" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All Methods</option>
        <option value="cash" @selected($methodFil === 'cash')>Cash</option>
        <option value="gcash" @selected($methodFil === 'gcash')>GCash QR</option>
      </select>
      <select name="channel" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">Online &amp; at the barangay</option>
        <option value="online" @selected($channelFil === 'online')>Online</option>
        <option value="counter" @selected($channelFil === 'counter')>At the barangay</option>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
      @if ($search !== '' || $methodFil !== '' || $channelFil !== '')
        <a href="{{ route('admin.payments.index', ['tab' => $tab]) }}" class="btn btn-outline btn-sm">Clear</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead>
          <tr><th>Reference</th><th>Meter Number</th><th>Name</th><th>Bill</th><th>Amount Paid</th><th>Method</th><th>Payment Date</th><th>Proof</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        @forelse ($payments as $p)
          <tr>
            <td>{{ $p->payment_reference }}</td>
            <td><strong>{{ $p->meter_number }}</strong></td>
            <td>{{ $p->full_name }}</td>
            <td>{{ billingPeriodLabel($p->billing_period) }}<div class="text-muted" style="font-size:11px;">Bill total {{ formatCurrency($p->bill_total) }}</div></td>
            <td><strong>{{ formatCurrency($p->amount_paid) }}</strong></td>
            <td>{{ paymentMethodLabel($p->payment_method) }}<div class="text-muted" style="font-size:11px;">{{ paymentChannelLabel($p->channel) }}</div></td>
            <td>{{ formatDateTime($p->payment_date) }}</td>
            <td>
              @if ($p->receipt_file)<a href="{{ route('admin.payments.receipt', $p->payment_id) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">📎 View screenshot</a>@endif
              @if ($p->payment_gateway_txn_id)<div class="text-muted" style="font-size:11.5px;">Ref. {{ $p->payment_gateway_txn_id }}</div>@endif
              @if (!$p->receipt_file && !$p->payment_gateway_txn_id)—@endif
            </td>
            <td>
              <span class="badge {{ paymentStatusBadgeClass($p->status) }}">{{ paymentStatusLabel($p->status) }}</span>
              @if ($p->status === 'verified' && $p->staff_name)<div class="text-muted" style="font-size:11px;">by {{ $p->staff_name }}</div>@endif
              @if ($p->rejection_reason)<div class="text-danger" style="font-size:11px;">{{ $p->rejection_reason }}</div>@endif
            </td>
            <td class="actions">
              <a href="{{ route('payments.receipt', $p->payment_id) }}" target="_blank" class="btn btn-outline btn-sm" title="View / download receipt">🧾 Receipt</a>
              @if ($p->status === 'pending')
                <form method="POST" action="{{ route('admin.payments.verify', $p->payment_id) }}" class="d-inline"
                      data-confirm="Verify this GCash payment?&#10;&#10;Only verify after checking the consumer's receipt screenshot and confirming that {{ formatCurrency($p->amount_paid) }} was received in the barangay GCash account.">
                  @csrf
                  <button class="btn btn-success btn-sm" type="submit">Verify</button>
                </form>
                <button class="btn btn-danger btn-sm" type="button"
                        onclick="openReject('{{ route('admin.payments.reject', $p->payment_id) }}', {{ json_encode($p->full_name . ' — ' . formatCurrency($p->amount_paid)) }})">Reject</button>
              @else
                <span class="text-muted" style="font-size:12px;">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="10">{{ $tab === 'pending' ? 'No payments waiting for verification. 🎉' : 'No payments found.' }}</td></tr>
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
          <div class="mb-3">
            <label for="billSearch" class="form-label">Water Bill *</label>
            <div class="input-group mb-2">
              <span class="input-group-text">🔍</span>
              <input type="search" id="billSearch" class="form-control" placeholder="Type a name or meter number to find the bill" autocomplete="off">
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
              <div class="text-muted text-center p-3" id="billHint">🔍 Type the consumer's name or meter number above to find the bill.</div>
              <div class="text-muted text-center p-3" id="billNoMatch" hidden>No bill matches your search.</div>
            </div>
            <div class="text-muted mt-1" style="font-size:11.5px;">Bills with an online GCash payment waiting for verification are not listed — verify or reject that payment first.</div>
          </div>

          <label class="form-label">Payment Method *</label>
          <div class="method-choice mb-3">
            <label><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash')><span>💵 Cash</span></label>
            <label><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')><span>📱 GCash QR</span></label>
          </div>

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
                <div class="text-muted" style="font-size:12px;">Check that the money arrived in the barangay GCash account, then upload a screenshot of the payment below.</div>
              </div>
            </div>
            <div class="mb-3">
              <label for="gcash_screenshot" class="form-label">Screenshot of the GCash Payment *</label>
              <input type="file" id="gcash_screenshot" name="gcash_screenshot" class="form-control" accept="image/jpeg,image/png,image/webp">
              <div class="text-muted" style="font-size:11.5px;">The GCash receipt showing the amount, date and Ref. No. JPG, PNG or WEBP, up to 5 MB.</div>
              <img id="gcashShotPreview" alt="Preview of the GCash payment screenshot" hidden style="display:block;max-width:200px;max-height:300px;margin:8px auto 0;border:1px solid var(--border);border-radius:10px;">
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
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

<script>
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

  function fillAmount() {
    const opt = picker.querySelector('input[name="bill_id"]:checked');
    if (opt && opt.dataset.balance) {
      amount.value = opt.dataset.balance;
      amount.max = opt.dataset.balance;
    }
  }
  // Names stay hidden until the admin searches; the chosen bill always stays visible.
  function filterBills() {
    const q = search.value.trim().toLowerCase();
    let shown = 0;
    picker.querySelectorAll('.bill-option').forEach(o => {
      const chosen = o.querySelector('input').checked;
      const match = q !== '' && o.dataset.search.includes(q);
      o.hidden = !(match || chosen);
      if (match) shown++;
    });
    document.getElementById('billHint').hidden = q !== '' || !!picker.querySelector('input[name="bill_id"]:checked') || !picker.querySelector('.bill-option');
    document.getElementById('billNoMatch').hidden = q === '' || shown > 0;
  }
  search.addEventListener('input', filterBills);
  picker.addEventListener('change', filterBills);
  filterBills();
  document.getElementById('gcash_screenshot').addEventListener('change', function () {
    const img = document.getElementById('gcashShotPreview'), f = this.files && this.files[0];
    if (f && f.size > 5 * 1024 * 1024) { alert('The screenshot is larger than 5 MB. Please choose a smaller image.'); this.value = ''; img.hidden = true; return; }
    img.hidden = !f;
    if (f) img.src = URL.createObjectURL(f);
  });
  // Keep the chosen bill in view when the modal opens.
  document.getElementById('payModal').addEventListener('shown.bs.modal', () => {
    picker.querySelector('input[name="bill_id"]:checked')?.closest('.bill-option').scrollIntoView({ block: 'nearest' });
  });
  function syncMethod() {
    const isGcash = document.querySelector('input[name="payment_method"]:checked')?.value === 'gcash';
    gcashBox.hidden = !isGcash;
    document.getElementById('gcash_screenshot').required = isGcash;

    submit.textContent = isGcash ? 'Confirm GCash Payment Received' : 'Confirm Cash Received';
  }
  picker.addEventListener('change', fillAmount);
  document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', syncMethod));
  document.getElementById('counterForm').addEventListener('submit', () => { submit.disabled = true; });
  if (!amount.value) fillAmount();
  syncMethod();

  @if ($preselectBillId || old('bill_id'))
  document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('payModal')).show());
  @endif
})();
</script>
@endsection
