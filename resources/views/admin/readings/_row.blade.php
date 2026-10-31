{{-- One Meter Readings table row. Used by the page and by the admin's live updates. Needs $r, $no, $canManage. --}}
<tr>
  <td class="stack-hide">{!! $no !!}</td>
  <td class="stack-title"><strong>{{ $r->meter_number ?: '—' }}</strong></td>
  <td>{{ $r->full_name }}</td>
  <td>{{ number_format($r->previous_reading, 2) }}</td>
  <td>{{ number_format($r->current_reading, 2) }}@if (!empty($r->photo_file))<div><a href="{{ route('admin.readings.photo', $r->reading_id) }}" target="_blank" rel="noopener" class="reading-photo-link"><i class="ph ph-camera" aria-hidden="true"></i> Photo</a></div>@endif
    {{-- Typed without internet: what its photo check said. --}}
    @switch($r->photo_check ?? null)
      @case('pending')<div><span class="badge badge-info" title="Typed without internet. The photo is checked automatically.">Typed · photo not checked yet</span></div>@break
      @case('match')<div><span class="badge badge-success" title="Typed without internet; the photo shows the same number.">Typed · photo matches</span></div>@break
      @case('mismatch')<div><span class="badge badge-danger">Photo shows {{ ltrim((string)$r->photo_meter, '0') !== ltrim((string)$r->meter_number, '0') ? 'meter ' . $r->photo_meter : number_format($r->photo_reading, 0, '.', '') }}</span></div>@break
      @case('unreadable')<div><span class="badge badge-warning" title="Typed without internet; the photo could not be read.">Typed · photo unclear</span></div>@break
      @case('kept')<div><span class="badge badge-secondary" title="Reviewed by the administrator: the typed reading was kept.">Typed · reviewed</span></div>@break
      @case('corrected')<div><span class="badge badge-secondary" title="Reviewed by the administrator: the photo's reading was used.">Photo's reading used</span></div>@break
    @endswitch
    @if (in_array($r->photo_check ?? null, \App\Http\Controllers\Admin\MeterReadingController::NEEDS_REVIEW, true) && auth()->user()->isAdmin())
      <div class="d-flex flex-wrap gap-1 mt-1">
        <form method="POST" action="{{ route('admin.readings.review', $r->reading_id) }}" data-confirm="Keep the typed reading {{ number_format($r->current_reading, 2) }} for meter {{ $r->meter_number }}?">
          @csrf <input type="hidden" name="decision" value="keep"><button class="btn btn-outline btn-sm" type="submit">Keep typed</button>
        </form>
        @if ($r->photo_check === 'mismatch' && $r->photo_reading !== null && ltrim((string)$r->photo_meter, '0') === ltrim((string)$r->meter_number, '0'))
          <form method="POST" action="{{ route('admin.readings.review', $r->reading_id) }}" data-confirm="Use the photo's reading {{ number_format($r->photo_reading, 2) }} instead of the typed {{ number_format($r->current_reading, 2) }} for meter {{ $r->meter_number }}?&#10;&#10;Its bill is re-computed if it has no payments yet.">
            @csrf <input type="hidden" name="decision" value="photo"><button class="btn btn-primary btn-sm" type="submit">Use photo's</button>
          </form>
        @endif
      </div>
    @endif
  </td>
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
      <button class="btn btn-outline btn-sm" onclick="openEditReading({{ json_encode($r) }}, '{{ route('admin.readings.update', $r->reading_id) }}')"><i class="ph ph-camera" aria-hidden="true"></i> Correct</button>
      <form method="POST" action="{{ route('admin.readings.destroy', $r->reading_id) }}" class="d-inline"
            data-confirm="Delete the {{ billingPeriodLabel($r->billing_period) }} reading of meter {{ $r->meter_number }} ({{ $r->full_name }})?&#10;&#10;Its bill is deleted too. This cannot be undone.">
        @csrf @method('DELETE')
        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
      </form>
    </td>
  @endif
</tr>
