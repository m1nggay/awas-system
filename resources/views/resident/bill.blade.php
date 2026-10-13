@extends('layouts.app')

@section('title', 'Current Bills')

@push('styles')
<style>
  .bill-pay-row{display:flex;justify-content:space-between;align-items:flex-start;gap:14px 24px;flex-wrap:wrap;}
  .bill-dates{font-size:14px;line-height:1.9;}
  .bill-pay{text-align:right;flex:0 1 380px;}
  .bill-pay .p-3{text-align:left;}
  @media (max-width:640px){ .bill-pay{text-align:left;flex-basis:100%;} .bill-pay .btn-lg{width:100%;} }
  .bill-box{border:1px solid var(--border);border-radius:12px;padding:14px 16px;height:100%;}
  .bill-box h4{font-size:13.5px;font-weight:700;margin-bottom:10px;}
  .bill-line{display:flex;justify-content:space-between;gap:12px;padding:5px 0;font-size:14px;}
  .bill-line + .bill-line{border-top:1px dashed var(--border);}
  .bill-line.total{font-weight:700;}
  .bill-total{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:10px;padding:12px 14px;border-radius:10px;background:#e8f6fb;border:1px solid #bfe3f0;font-size:20px;font-weight:700;color:var(--primary-dark);}
</style>
@include('resident._pay-styles')
@endpush

@section('content')
@if ($receiptId = session('receipt_payment_id'))
  <div class="card" style="border-left:4px solid #1a9b5b;">
    <div class="card-body d-flex flex-wrap align-items-center gap-2">
      <div class="flex-grow-1"><strong><i class="ph ph-receipt" aria-hidden="true"></i> Your payment receipt is ready.</strong></div>
      <a href="{{ route('payments.receipt', $receiptId) }}" class="btn btn-outline btn-sm"><i class="ph ph-receipt" aria-hidden="true"></i> View Receipt</a>
      <a href="{{ route('payments.receipt', ['payment' => $receiptId, 'download' => 1]) }}" class="btn btn-primary btn-sm"><i class="ph ph-download-simple" aria-hidden="true"></i> Download Receipt</a>
    </div>
  </div>
@endif
@if ($openBills->count() > 1)
  <div class="alert alert-warning">You have {{ $openBills->count() }} unpaid bills. Total unpaid balance: <strong>{{ formatCurrency($totalBalance) }}</strong></div>
@endif
@forelse ($openBills as $bill)
  @php $balance = (float)$bill->total_amount - (float)$bill->amount_paid; @endphp
  <div class="card">
    <div class="card-header">
      <h3>{{ date('F', strtotime($bill->billing_period . '-01')) }} Bill {{ substr($bill->billing_period, 0, 4) }}</h3>
      @if (($lastPayment[$bill->bill_id] ?? null)?->status === 'pending')
        <span class="badge badge-warning">{{ $lastPayment[$bill->bill_id]->payment_method === 'paymongo' ? 'Payment Not Finished' : 'Pending Verification' }}</span>
      @else
        <span class="badge {{ billStatusBadgeClass($bill->status) }}">{{ str_replace('_', ' ', $bill->status) }}</span>
      @endif
    </div>
    <div class="card-body">
      @php $last = $lastPayment[$bill->bill_id] ?? null; @endphp
      @if ($last && in_array($last->status, ['rejected', 'failed'], true))
        <div class="alert alert-danger small">
          <strong>Your GCash payment of {{ formatCurrency($last->amount_paid) }} was rejected.</strong>
          @if ($last->rejection_reason)<br>Reason: {{ $last->rejection_reason }}@endif
          <br>Please check your GCash receipt and pay again, or visit the barangay water office.
        </div>
      @endif

      {{-- What to pay, by when, and the way to pay it. --}}
      <div class="bill-box">
        <h4><i class="ph ph-drop" aria-hidden="true"></i> Water Consumption</h4>
        <div class="bill-line"><span class="text-muted">Previous reading</span><strong>{{ $bill->previous_reading !== null ? number_format($bill->previous_reading, 2) : '—' }}</strong></div>
        <div class="bill-line"><span class="text-muted">Present reading</span><strong>{{ $bill->current_reading !== null ? number_format($bill->current_reading, 2) : '—' }}</strong></div>
        <div class="bill-line"><span class="text-muted">Consumption</span><strong>{{ number_format($bill->consumption, 2) }} m³</strong></div>
        <div class="bill-total"><span>Total</span><span>{{ formatCurrency($bill->total_amount) }}</span></div>
        @if ($bill->discount_amount > 0 || $bill->penalty_amount > 0)
          <div class="text-muted small mt-1">
            Includes{{ $bill->discount_amount > 0 ? ' a senior citizen discount of − ' . formatCurrency($bill->discount_amount) : '' }}{{ $bill->discount_amount > 0 && $bill->penalty_amount > 0 ? ' and' : '' }}{{ $bill->penalty_amount > 0 ? ' a late penalty of + ' . formatCurrency($bill->penalty_amount) : '' }}.
          </div>
        @endif
        @if ((float)$bill->amount_paid > 0)
          <div class="small mt-1">Already paid <strong class="text-success">{{ formatCurrency($bill->amount_paid) }}</strong> · Balance to pay <strong class="text-primary-dark">{{ formatCurrency($balance) }}</strong></div>
        @endif
        {{-- Dates on the left, the way to pay on the right. --}}
        <div class="bill-pay-row mt-3">
        <div class="bill-dates">
          <div>Due <strong>{{ formatDate($bill->due_date) }}</strong></div>
          @if (!empty($bill->disconnection_date))
            <div>Disconnection <strong class="text-danger">{{ formatDate($bill->disconnection_date) }}</strong></div>
          @endif
        </div>
        <div class="bill-pay">
          @if ($last && $last->status === 'pending' && $last->payment_method === 'paymongo')
            <div class="p-3" style="background:#fff8e6;border:1px solid #f1d58a;border-radius:10px;">
              <div class="mb-1"><span class="badge badge-warning">Payment Not Finished</span></div>
              <div>You started a {{ paymentMethodLabel('paymongo', $last->wallet) }} payment of <strong>{{ formatCurrency($last->amount_paid) }}</strong>, but it was not completed.</div>
              <div class="text-muted small mt-1">If you already paid, refresh this page in a moment. Otherwise, pay again.</div>
              <button type="button" class="btn btn-primary w-100 mt-2" data-pay-url="{{ route('resident.bill.pay', [$bill->bill_id, 'partial' => 1]) }}" data-pay-page="{{ route('resident.bill.pay', $bill->bill_id) }}">Pay Bill</button>
            </div>
          @elseif ($last && $last->status === 'pending')
            <div class="p-3" style="background:#fff8e6;border:1px solid #f1d58a;border-radius:10px;">
              <div class="mb-1"><span class="badge badge-warning">Pending Verification</span></div>
              <div>Your GCash payment of <strong>{{ formatCurrency($last->amount_paid) }}</strong>
                was submitted on {{ formatDateTime($last->payment_date) }}.</div>
              <div class="text-muted small mt-1">The water office will verify it. Your bill changes to <strong>Paid</strong> once verified — no need to pay again.</div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <a href="{{ route('payments.receipt', $last->payment_id) }}" class="btn btn-outline btn-sm"><i class="ph ph-receipt" aria-hidden="true"></i> View Receipt</a>
                <a href="{{ route('payments.receipt', ['payment' => $last->payment_id, 'download' => 1]) }}" class="btn btn-outline btn-sm"><i class="ph ph-download-simple" aria-hidden="true"></i> Download Receipt</a>
              </div>
            </div>
          @else
            <button type="button" class="btn btn-primary btn-lg px-5" data-pay-url="{{ route('resident.bill.pay', [$bill->bill_id, 'partial' => 1]) }}" data-pay-page="{{ route('resident.bill.pay', $bill->bill_id) }}"><i class="ph ph-wallet" aria-hidden="true"></i> Pay Bill</button>
          @endif
        </div>
        </div>
      </div>

      <button type="button" class="btn btn-outline btn-sm mt-3" data-bill-download="{{ route('bills.print', [$bill->bill_id, 'embed' => 1, 'download' => 1]) }}"><i class="ph ph-download-simple" aria-hidden="true"></i> Download Bill</button>
    </div>
  </div>
@empty
  <div class="card"><div class="card-body text-center"><p style="font-size:15px;">You have no outstanding bills right now.</p></div></div>
@endforelse

{{-- "Pay Bill" opens the Online Payment panel here instead of another page. --}}
<div class="modal fade" id="payModal" tabindex="-1" aria-label="Online Payment" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down" style="max-width:560px;">
    <div class="modal-content" id="payModalContent"></div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/pay-panel.js') }}?v={{ @filemtime(public_path('assets/js/pay-panel.js')) ?: '1' }}"></script>
<script>
// "Download Bill": the bill page makes the PDF in a hidden frame, so nothing opens.
(function () {
  let busy = null;
  document.querySelectorAll('[data-bill-download]').forEach(btn => btn.addEventListener('click', () => {
    if (busy) return;
    busy = btn;
    btn.disabled = true;
    btn.textContent = 'Preparing PDF…';
    const frame = document.createElement('iframe');
    frame.hidden = true;
    frame.src = btn.dataset.billDownload;
    document.body.appendChild(frame);
    const done = () => {
      btn.disabled = false;
      btn.innerHTML = '<i class="ph ph-download-simple" aria-hidden="true"></i> Download Bill';
      busy = null;
      setTimeout(() => frame.remove(), 2000);
    };
    window.addEventListener('message', function onMsg(e) {
      if (e.origin === location.origin && e.data === 'awas-bill-downloaded') { window.removeEventListener('message', onMsg); done(); }
    });
    setTimeout(() => { if (busy === btn) done(); }, 20000);   // never stay stuck
  }));
})();
</script>
<script>
(function () {
  const modal = document.getElementById('payModal'), content = document.getElementById('payModalContent');
  document.querySelectorAll('[data-pay-url]').forEach(btn => btn.addEventListener('click', () => {
    content.innerHTML = '<div class="text-center text-muted p-5">Loading…</div>';
    bootstrap.Modal.getOrCreateInstance(modal).show();
    fetch(btn.dataset.payUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(r => {
        // Already paid / waiting for verification: the server sends back to Current Bills with a message.
        if (r.redirected) { location.href = r.url; return null; }
        return r.ok ? r.text() : Promise.reject();
      })
      .then(html => {
        if (html === null) return;
        content.innerHTML = html;
        initPayPanel(content.querySelector('[data-pay-panel]'));
      })
      .catch(() => { location.href = btn.dataset.payPage; });   // fall back to the Pay Bill page
  }));
  modal.addEventListener('hidden.bs.modal', () => { content.innerHTML = ''; });
})();
</script>
@endpush
