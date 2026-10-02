@extends('layouts.app')

@section('title', 'Water Bills')

@section('content')
<div class="card">
  <div class="card-header">
    <h3>Water Bills</h3>
    <span class="text-muted small">Bills are generated automatically when the meter reader records a reading.</span>
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('admin.bills.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text">🔍</span>
        <input type="text" name="q" class="form-control" placeholder="Search by name or meter number..." value="{{ $search }}">
      </div>
      <select name="status" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="unpaid" @selected($statusFil === 'unpaid')>Unpaid</option>
        <option value="partially_paid" @selected($statusFil === 'partially_paid')>Partially Paid</option>
        <option value="paid" @selected($statusFil === 'paid')>Paid</option>
        <option value="overdue" @selected($statusFil === 'overdue')>Overdue</option>
      </select>
      <select name="period" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All periods</option>
        @foreach ($periods as $p)
          <option value="{{ $p }}" @selected($periodFil === $p)>{{ billingPeriodLabel($p) }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
      @if ($search !== '' || $statusFil !== '' || $periodFil !== '')
        <a href="{{ route('admin.bills.index') }}" class="btn btn-outline btn-sm">Clear</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Meter Number</th><th>Name</th><th>Period</th><th>Consumption</th><th>Total</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($bills as $b)
          <tr>
            <td>{{ $b->meter_number ?: '—' }}</td>
            <td><a href="{{ route('admin.consumers.history', $b->consumer_id) }}" title="All bills of this consumer">{{ $b->full_name }}</a></td>
            <td>{{ billingPeriodLabel($b->billing_period) }}</td>
            <td>{{ number_format($b->consumption, 2) }} m³</td>
            <td>{{ formatCurrency($b->total_amount) }}@if ($b->amount_paid > 0 && $b->status !== 'paid')<div class="text-muted" style="font-size:11px;">Paid: {{ formatCurrency($b->amount_paid) }}</div>@endif</td>
            <td>{{ formatDate($b->due_date) }}</td>
            <td>
              <span class="badge {{ billStatusBadgeClass($b->status) }}">{{ str_replace('_', ' ', $b->status) }}</span>
              @if (isset($pendingBillIds[$b->bill_id]))<div><span class="badge badge-warning">Pending Verification</span></div>@endif
            </td>
            <td class="actions">
              <a class="btn btn-outline btn-sm" href="{{ route('bills.print', $b->bill_id) }}" target="_blank">View Bill</a>
              @if (isset($pendingBillIds[$b->bill_id]))
                <a class="btn btn-warning btn-sm" href="{{ route('admin.payments.index', ['tab' => 'pending', 'q' => $b->meter_number]) }}">Verify Payment</a>
              @elseif ($b->status !== 'paid')
                <a class="btn btn-primary btn-sm" href="{{ route('admin.payments.index', ['bill_id' => $b->bill_id]) }}">Record Payment</a>
              @endif
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="8">No bills found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $bills])
  </div>
</div>
@endsection
