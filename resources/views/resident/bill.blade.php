@extends('layouts.app')

@section('title', 'Current Bills')

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
        <span class="badge badge-warning">Pending Verification</span>
      @else
        <span class="badge {{ billStatusBadgeClass($bill->status) }}">{{ str_replace('_', ' ', $bill->status) }}</span>
      @endif
    </div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Previous Reading</span><strong>{{ $bill->previous_reading !== null ? number_format($bill->previous_reading, 2) : '—' }}</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Present Reading</span><strong>{{ $bill->current_reading !== null ? number_format($bill->current_reading, 2) : '—' }}</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Consumption</span><strong>{{ number_format($bill->consumption, 2) }} m³</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Sub-total</span><strong>{{ formatCurrency($bill->amount_due) }}</strong></div>
          @if ($bill->discount_amount > 0)
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Senior Discount</span><strong class="text-success">− {{ formatCurrency($bill->discount_amount) }}</strong></div>
          @endif
          @if ($bill->penalty_amount > 0)
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Penalty</span><strong class="text-danger">{{ formatCurrency($bill->penalty_amount) }}</strong></div>
          @endif
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total</span><strong>{{ formatCurrency($bill->total_amount) }}</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Amount Paid</span><strong>{{ formatCurrency($bill->amount_paid) }}</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Due Date</span><strong>{{ formatDate($bill->due_date) }}</strong></div>
          @if (!empty($bill->disconnection_date))
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Disconnection Date</span><strong class="text-danger">{{ formatDate($bill->disconnection_date) }}</strong></div>
          @endif
          <div class="d-flex justify-content-between fs-5 mt-3 pt-3 border-top"><span>Balance</span><strong class="text-primary-dark">{{ formatCurrency($balance) }}</strong></div>
          <a href="{{ route('bills.print', $bill->bill_id) }}" target="_blank" class="btn btn-outline btn-sm mt-3">🖨️ Print / Download Bill</a>
        </div>
        <div class="col-lg-6">
          @php $last = $lastPayment[$bill->bill_id] ?? null; @endphp
          <h4 class="mb-3 fs-6">💳 Payment</h4>
          @if ($last && $last->status === 'pending')
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
            @if ($last && in_array($last->status, ['rejected', 'failed'], true))
              <div class="alert alert-danger small">
                <strong>Your GCash payment of {{ formatCurrency($last->amount_paid) }} was rejected.</strong>
                @if ($last->rejection_reason)<br>Reason: {{ $last->rejection_reason }}@endif
                <br>Please check your GCash receipt and pay again, or visit the barangay water office.
              </div>
            @endif
            <p class="mb-2">Amount to pay: <strong style="font-size:18px;">{{ formatCurrency($balance) }}</strong></p>
            <a href="{{ route('resident.bill.pay', $bill->bill_id) }}" class="btn btn-primary w-100">Pay Bill</a>
            <p class="text-muted mt-2 small mb-0">Online payment is through the barangay's <strong>GCash QR code</strong>.
              You can also pay in person at the barangay water office (Cash or GCash QR).</p>
          @endif
        </div>
      </div>
    </div>
  </div>
@empty
  <div class="card"><div class="card-body text-center"><p style="font-size:15px;">🎉 You have no outstanding bills right now.</p></div></div>
@endforelse
@endsection
