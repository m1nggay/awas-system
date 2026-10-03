{{-- Billing Report grouped by Purok (shared by the screen and the print/PDF view). --}}
@forelse ($groups as $group)
  <div class="card purok-report">
    <div class="card-header"><h3>{{ mb_strtoupper($group['purok']) }}</h3><span class="text-muted small">{{ billingPeriodLabel($period) }} · {{ count($group['rows']) }} {{ Str::plural('consumer', count($group['rows'])) }}</span></div>
    <div class="card-body no-pad">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 data-table">
          <thead>
            <tr><th>No.</th><th>Meter Number</th><th>Name</th><th>Present</th><th>Previous</th><th>Consumption</th><th>Total</th><th>Balance</th><th>Status</th><th>Date Paid</th></tr>
          </thead>
          <tbody>
          @php $sumTotal = 0; $sumBalance = 0; @endphp
          @foreach ($group['rows'] as $i => $r)
            @php $sumTotal += $r->total; $sumBalance += $r->balance; @endphp
            <tr>
              <td>{{ $i + 1 }}</td>
              <td><strong>{{ $r->meter_number ?: '—' }}</strong></td>
              <td>{{ $r->full_name }}</td>
              <td>{{ number_format($r->present, 2) }}</td>
              <td>{{ number_format($r->previous, 2) }}</td>
              <td>{{ number_format($r->consumption, 2) }}</td>
              <td>{{ formatCurrency($r->total) }}</td>
              <td>{{ formatCurrency($r->balance) }}</td>
              <td><span class="badge {{ billStatusBadgeClass($r->status) }}">{{ ucwords(str_replace('_', ' ', $r->status)) }}</span></td>
              <td>{{ $r->date_paid ?? '—' }}</td>
            </tr>
          @endforeach
          </tbody>
          <tfoot>
            <tr style="font-weight:600;">
              <td colspan="6" class="text-end">Total for {{ $group['purok'] }} ({{ count($group['rows']) }} {{ Str::plural('consumer', count($group['rows'])) }})<div class="text-muted" style="font-weight:400;font-size:11px;">Total = all bills · Balance = still unpaid</div></td>
              <td>{{ formatCurrency($sumTotal) }}</td>
              <td>{{ formatCurrency($sumBalance) }}</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
@empty
  <div class="card"><div class="card-body text-center text-muted">No bills found for {{ billingPeriodLabel($period) }}.</div></div>
@endforelse
