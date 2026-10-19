{{-- One Meter Readings table row. Used by the page and by the admin's live updates. Needs $r, $no, $canManage. --}}
<tr>
  <td class="stack-hide">{!! $no !!}</td>
  <td class="stack-title"><strong>{{ $r->meter_number ?: '—' }}</strong></td>
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
  @if ($canManage)
    <td class="actions">
      <button class="btn btn-outline btn-sm" onclick="openEditReading({{ json_encode($r) }}, '{{ route('admin.readings.update', $r->reading_id) }}')">Edit</button>
      <form method="POST" action="{{ route('admin.readings.destroy', $r->reading_id) }}" class="d-inline"
            data-confirm="Delete the {{ billingPeriodLabel($r->billing_period) }} reading of meter {{ $r->meter_number }} ({{ $r->full_name }})?&#10;&#10;Its bill is deleted too. This cannot be undone.">
        @csrf @method('DELETE')
        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
      </form>
    </td>
  @endif
</tr>
