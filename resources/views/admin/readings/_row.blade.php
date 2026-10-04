{{-- One Meter Readings table row. Used by the page and by the admin's live updates. Needs $r, $no, $isAdmin. --}}
<tr>
  <td>{!! $no !!}</td>
  <td><strong>{{ $r->meter_number ?: '—' }}</strong></td>
  <td>{{ $r->full_name }}</td>
  <td>{{ number_format($r->previous_reading, 2) }}</td>
  <td>{{ number_format($r->current_reading, 2) }}</td>
  <td class="consumption-value">{{ number_format($r->consumption, 2) }} m³</td>
  <td>{{ $r->bill_total !== null ? formatCurrency($r->bill_total) : '—' }}</td>
  <td>{{ $r->bill_total !== null ? formatCurrency(max(0, $r->bill_total - $r->bill_paid)) : '—' }}</td>
  <td>
    @if ($r->bill_status)
      <span class="badge {{ billStatusBadgeClass($r->bill_status) }}">{{ str_replace('_', ' ', $r->bill_status) }}</span>
    @else — @endif
  </td>
  <td>{{ formatDate($r->reading_date) }}<div class="text-muted" style="font-size:11px;">{{ billingPeriodLabel($r->billing_period) }}</div></td>
  @if ($isAdmin)
    <td class="actions">
      <button class="btn btn-secondary btn-sm" onclick="openEditReading({{ json_encode($r) }}, '{{ route('admin.readings.update', $r->reading_id) }}')">Correct</button>
    </td>
  @endif
</tr>
