@extends('layouts.app')

@section('title', 'Current Bills')

@push('styles')
<style>
  .bill-summary{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px 24px;padding:18px 20px;border-radius:12px;background:#f0f7fb;border:1px solid var(--border);}
  .bill-summary .label{font-size:12.5px;color:var(--text-muted);}
  .bill-summary .amount{font-size:30px;font-weight:700;color:var(--primary-dark);line-height:1.15;}
  .bill-summary .dates{font-size:13px;margin-top:4px;}
  .bill-summary .action{flex:0 1 340px;min-width:240px;}
  .bill-box{border:1px solid var(--border);border-radius:12px;padding:14px 16px;height:100%;}
  .bill-box h4{font-size:13.5px;font-weight:700;margin-bottom:10px;}
  .bill-line{display:flex;justify-content:space-between;gap:12px;padding:5px 0;font-size:14px;}
  .bill-line + .bill-line{border-top:1px dashed var(--border);}
  .bill-line.total{font-weight:700;}
  @media (max-width:640px){ .bill-summary .action{flex-basis:100%;min-width:0;} .bill-summary .amount{font-size:26px;} }
</style>
@endpush

@section('content')
@if ($receiptId = session('receipt_payment_id'))
  <div class="card" style="border-left:4px solid #1a9b5b;">
    <div class="card-body d-flex flex-wrap align-items-center gap-2">
      <div class="flex-grow-1"><strong>🧾 Your payment receipt is ready.</strong></div>
      <a href="{{ route('payments.receipt', $receiptId) }}" class="btn btn-outline btn-sm">🧾 View Receipt</a>
      <a href="{{ route('payments.receipt', ['payment' => $receiptId, 'download' => 1]) }}" class="btn btn-primary btn-sm">⬇️ Download Receipt</a>
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
      <h3>{{ billingPeriodLabel($bill->billing_period) }} Bill — Meter {{ $consumer->meter_number }}</h3>
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
      <div class="bill-summary mb-3">
        <div>
          <div class="label">Amount to pay</div>
          <div class="amount">{{ formatCurrency($balance) }}</div>
          <div class="dates">
            Due <strong>{{ formatDate($bill->due_date) }}</strong>
            @if (!empty($bill->disconnection_date))
              <span class="text-muted">·</span> Disconnection <strong class="text-danger">{{ formatDate($bill->disconnection_date) }}</strong>
            @endif
          </div>
        </div>
        <div class="action">
          @if ($last && $last->status === 'pending' && $last->payment_method === 'paymongo')
            <div class="p-3" style="background:#fff8e6;border:1px solid #f1d58a;border-radius:10px;">
              <div class="mb-1"><span class="badge badge-warning">Payment Not Finished</span></div>
              <div>You started a {{ paymentMethodLabel('paymongo', $last->wallet) }} payment of <strong>{{ formatCurrency($last->amount_paid) }}</strong>, but it was not completed.</div>
              <div class="text-muted small mt-1">If you already paid, refresh this page in a moment. Otherwise, pay again.</div>
              <a href="{{ route('resident.bill.pay', $bill->bill_id) }}" class="btn btn-primary w-100 mt-2">Pay Bill</a>
            </div>
          @elseif ($last && $last->status === 'pending')
            <div class="p-3" style="background:#fff8e6;border:1px solid #f1d58a;border-radius:10px;">
              <div class="mb-1"><span class="badge badge-warning">Pending Verification</span></div>
              <div>Your GCash payment of <strong>{{ formatCurrency($last->amount_paid) }}</strong>
                was submitted on {{ formatDateTime($last->payment_date) }}.</div>
              <div class="text-muted small mt-1">The water office will verify it. Your bill changes to <strong>Paid</strong> once verified — no need to pay again.</div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <a href="{{ route('payments.receipt', $last->payment_id) }}" class="btn btn-outline btn-sm">🧾 View Receipt</a>
                <a href="{{ route('payments.receipt', ['payment' => $last->payment_id, 'download' => 1]) }}" class="btn btn-outline btn-sm">⬇️ Download Receipt</a>
              </div>
            </div>
          @else
            <a href="{{ route('resident.bill.pay', $bill->bill_id) }}" class="btn btn-primary btn-lg w-100">💳 Pay Bill</a>
            <p class="text-muted mt-2 small mb-0 text-center">GCash or Maya — or pay in person at the barangay water office.</p>
          @endif
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="bill-box">
            <h4>📏 Meter Reading</h4>
            <div class="bill-line"><span class="text-muted">Previous reading</span><strong>{{ $bill->previous_reading !== null ? number_format($bill->previous_reading, 2) : '—' }}</strong></div>
            <div class="bill-line"><span class="text-muted">Present reading</span><strong>{{ $bill->current_reading !== null ? number_format($bill->current_reading, 2) : '—' }}</strong></div>
            <div class="bill-line total"><span>Consumption</span><span>{{ number_format($bill->consumption, 2) }} m³</span></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="bill-box">
            <h4>🧾 Charges</h4>
            <div class="bill-line"><span class="text-muted">Water charge</span><strong>{{ formatCurrency($bill->amount_due) }}</strong></div>
            @if ($bill->discount_amount > 0)
              <div class="bill-line"><span class="text-muted">Senior citizen discount</span><strong class="text-success">− {{ formatCurrency($bill->discount_amount) }}</strong></div>
            @endif
            @if ($bill->penalty_amount > 0)
              <div class="bill-line"><span class="text-muted">Late penalty</span><strong class="text-danger">+ {{ formatCurrency($bill->penalty_amount) }}</strong></div>
            @endif
            @if ((float)$bill->amount_paid > 0)
              <div class="bill-line"><span class="text-muted">Bill total</span><strong>{{ formatCurrency($bill->total_amount) }}</strong></div>
              <div class="bill-line"><span class="text-muted">Already paid</span><strong class="text-success">− {{ formatCurrency($bill->amount_paid) }}</strong></div>
            @endif
            <div class="bill-line total"><span>Balance</span><span class="text-primary-dark">{{ formatCurrency($balance) }}</span></div>
          </div>
        </div>
      </div>

      <a href="{{ route('bills.print', $bill->bill_id) }}" target="_blank" class="btn btn-outline btn-sm mt-3">🖨️ Print / Download Bill</a>
    </div>
  </div>
@empty
  <div class="card"><div class="card-body text-center"><p style="font-size:15px;">🎉 You have no outstanding bills right now.</p></div></div>
@endforelse
@endsection
