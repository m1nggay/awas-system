{{-- Consumer history content: shown in the pop-up on the Consumers page and on the full History page. --}}
<div class="card">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-4 col-md-6"><div class="text-muted small">{{ __('Meter Number') }}</div><strong>{{ $consumer->meter_number ?: '—' }}</strong></div>
      <div class="col-lg-4 col-md-6"><div class="text-muted small">{{ __('Name') }}</div><strong>{{ $consumer->full_name }}</strong></div>
      <div class="col-lg-4 col-md-6"><div class="text-muted small">{{ __('Purok') }}</div><strong>{{ $consumer->purok_name }}</strong></div>
      <div class="col-lg-4 col-md-6"><div class="text-muted small">{{ __('Address') }}</div><strong>{{ $consumer->address }}</strong></div>
      <div class="col-lg-4 col-md-6"><div class="text-muted small">{{ __('Type of Consumer') }}</div><strong>{{ consumerTypeLabel($consumer->consumer_type) }}</strong></div>
      <div class="col-lg-4 col-md-6"><div class="text-muted small">{{ __('Status') }}</div><span class="badge {{ $consumer->status === 'active' ? 'badge-success' : ($consumer->status === 'disconnected' ? 'badge-danger' : 'badge-inactive') }}">{{ $consumer->status }}</span></div>
      <div class="col-lg-4 col-md-6"><div class="text-muted small">{{ __('Unpaid Balance') }}</div><strong class="{{ $balance > 0 ? 'text-danger' : '' }}">{{ formatCurrency($balance) }}</strong></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>{{ __('Meter Reading History') }}</h3></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Previous Reading') }}</th><th>{{ __('Present Reading') }}</th><th>{{ __('Consumption') }}</th><th>{{ __('Reading Date') }}</th><th>{{ __('Remarks') }}</th></tr></thead>
        <tbody>
        @forelse ($readings as $r)
          <tr>
            <td>{{ billingPeriodLabel($r->billing_period) }}</td>
            <td>{{ number_format($r->previous_reading, 2) }}</td>
            <td>{{ number_format($r->current_reading, 2) }}</td>
            <td class="consumption-value">{{ number_format($r->consumption, 2) }} m³</td>
            <td>{{ formatDate($r->reading_date) }}</td>
            <td>{{ $r->remarks ?: '—' }}</td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="6">{{ __('No meter readings recorded yet.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>{{ __('Bills') }}</h3></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Consumption') }}</th><th>{{ __('Total') }}</th><th>{{ __('Balance') }}</th><th>{{ __('Status') }}</th><th>{{ __('Due Date') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($bills as $b)
          <tr>
            <td>{{ billingPeriodLabel($b->billing_period) }}</td>
            <td>{{ number_format($b->consumption, 2) }} m³</td>
            <td>{{ formatCurrency($b->total_amount) }}</td>
            <td>{{ formatCurrency(max(0, $b->total_amount - $b->amount_paid)) }}</td>
            <td><span class="badge {{ billStatusBadgeClass($b->status) }}">{{ str_replace('_', ' ', $b->status) }}</span></td>
            <td>{{ formatDate($b->due_date) }}</td>
            <td><a class="btn btn-outline btn-sm" href="{{ route('bills.print', $b->bill_id) }}" target="_blank">{{ __('View Bill') }}</a></td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="7">{{ __('No bills generated yet.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>{{ __('Payment Transactions') }}</h3></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      @include('resident._payments-table', ['payments' => $payments, 'emptyText' => 'No payments yet.'])
    </div>
  </div>
</div>
