@extends('layouts.app')

@section('title', 'Billing History')

@section('content')
<div class="card">
  <div class="card-header">
    <h3>Billing History</h3>
    <span class="text-muted small">Your past, fully paid bills. Unpaid bills are under <a href="{{ route('resident.bill') }}">Current Bills</a>.</span>
  </div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Billing Period</th><th>Previous Reading</th><th>Present Reading</th><th>Consumption</th><th>Total</th><th>Status</th><th>Date Paid</th><th></th></tr></thead>
        <tbody>
        @forelse ($bills as $b)
          <tr>
            <td>{{ billingPeriodLabel($b->billing_period) }}</td>
            <td>{{ $b->previous_reading !== null ? number_format($b->previous_reading, 2) : '—' }}</td>
            <td>{{ $b->current_reading !== null ? number_format($b->current_reading, 2) : '—' }}</td>
            <td>{{ number_format($b->consumption, 2) }} m³</td>
            <td>{{ formatCurrency($b->total_amount) }}</td>
            <td><span class="badge {{ billStatusBadgeClass($b->status) }}">{{ str_replace('_', ' ', $b->status) }}</span></td>
            <td>{{ isset($datePaid[$b->bill_id]) ? formatDate($datePaid[$b->bill_id]) : '—' }}</td>
            <td><a class="btn btn-outline btn-sm" href="{{ route('bills.print', $b->bill_id) }}" target="_blank">View Bill</a></td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="8">No paid bills yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
