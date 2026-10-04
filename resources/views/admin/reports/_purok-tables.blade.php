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
          @foreach ($group['rows'] as $i => $r)
            @php $isDisc = $r->status === 'disconnected'; @endphp
            <tr>
              <td>{{ $i + 1 }}</td>
              <td><strong>{{ $r->meter_number ?: '—' }}</strong></td>
              <td>{{ $r->full_name }}</td>
              <td>{{ $r->present !== null ? number_format($r->present, 2) : '—' }}</td>
              <td>{{ $r->previous !== null ? number_format($r->previous, 2) : '—' }}</td>
              <td>{{ $r->consumption !== null ? number_format($r->consumption, 2) : '—' }}</td>
              <td>{{ $r->total !== null ? formatCurrency($r->total) : '—' }}</td>
              <td @if ($isDisc && $r->balance > 0) class="text-danger fw-semibold" @endif>{{ formatCurrency($r->balance) }}</td>
              <td>
                @if ($isDisc)
                  <span class="badge badge-danger">Disconnected</span>
                  <div class="text-muted" style="font-size:11px;">Unpaid before disconnection</div>
                @else
                  <span class="badge {{ billStatusBadgeClass($r->status) }}">{{ ucwords(str_replace('_', ' ', $r->status)) }}</span>
                @endif
              </td>
              <td>{{ $r->date_paid ?? '—' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@empty
  <div class="card"><div class="card-body text-center text-muted">No bills found for {{ billingPeriodLabel($period) }}.</div></div>
@endforelse
