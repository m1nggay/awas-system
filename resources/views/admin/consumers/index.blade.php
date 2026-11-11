@extends('layouts.app')

@section('title', __('Consumers'))

@section('content')
@php $isReader = auth()->user()->isMeterReader(); @endphp
@if ($isReader)
  {{-- Meter Reader: a phone-first to-do list of meters (see _reader-list). --}}
  @include('admin.consumers._reader-list')
@else
<div class="card">
  @unless ($isReader)
    <div class="card-header justify-content-end">
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">{{ __('+ Add Consumer') }}</button>
    </div>
  @endunless
  <div class="card-body">
    <form method="GET" action="{{ route('admin.consumers.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3 filter-bar">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
        <input type="search" name="q" class="form-control" placeholder="{{ __('Search by Meter Number, Name or Purok...') }}" value="{{ $search }}" aria-label="{{ __('Search consumers') }}">
        <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
      </div>
      <select name="purok" class="form-select w-auto" onchange="this.form.submit()">
        <option value="0">{{ __('All Puroks') }}</option>
        @foreach ($puroks as $pk)
          <option value="{{ $pk->purok_id }}" @selected($purokFil === (int)$pk->purok_id)>{{ $pk->purok_name }}</option>
        @endforeach
      </select>
      <select name="type" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">{{ __('All Types') }}</option>
        @foreach (\App\Models\Consumer::TYPES as $v => $l)
          <option value="{{ $v }}" @selected($typeFil === $v)>{{ __($l) }}</option>
        @endforeach
      </select>
      <select name="status" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">{{ __('All Status') }}</option>
        <option value="active" @selected($statusFil === 'active')>{{ __('Active') }}</option>
        <option value="disconnected" @selected($statusFil === 'disconnected')>{{ __('Disconnected') }}</option>
        <option value="inactive" @selected($statusFil === 'inactive')>{{ __('Inactive') }}</option>
      </select>
      @if ($search !== '' || $purokFil || $statusFil !== '' || $typeFil !== '')
        <a href="{{ route('admin.consumers.index') }}" class="btn btn-outline btn-sm">{{ __('Clear') }}</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table consumer-table {{ $isReader ? 'table-stack' : '' }}">
        <thead>
          <tr><th>{{ __('No.') }}</th><th>{{ __('Meter Number') }}</th><th>{{ __('Name') }}</th><th>{{ __('Purok') }}</th><th>{{ __('Type of Consumer') }}</th><th>{{ __('Status') }}</th>@unless ($isReader)<th>{{ __('Balance') }}</th>@endunless @if ($isReader)<th>{{ __('Last Reading') }}</th><th>{{ __('This Month') }}</th>@endif<th>{{ __('Actions') }}</th></tr>
        </thead>
        <tbody>
        @forelse ($consumers as $i => $c)
          <tr>
            <td class="stack-hide">{{ $consumers->firstItem() + $i }}</td>
            <td class="stack-title"><strong>{{ $c->meter_number ?: '—' }}</strong></td>
            <td class="consumer-name">{{ $c->display_name }}<div class="text-muted small consumer-addr">{{ $c->address }}</div></td>
            <td>{{ $c->purok_name }}</td>
            <td>{{ consumerTypeLabel($c->consumer_type) }}</td>
            <td><span class="badge {{ $c->status === 'active' ? 'badge-success' : ($c->status === 'disconnected' ? 'badge-danger' : 'badge-inactive') }}">{{ $c->status }}</span></td>
            @unless ($isReader)
              <td>@if ((float)$c->unpaid_balance > 0)<span class="text-danger fw-semibold">{{ formatCurrency($c->unpaid_balance) }}</span>@if ($c->status === 'disconnected')<div class="text-muted" style="font-size:11px;">{{ __('unpaid before disconnection') }}</div>@endif @else<span class="text-muted">₱0.00</span>@endif</td>
            @endunless
            @if ($isReader)
              @php $last = $lastReadings[$c->consumer_id] ?? null; @endphp
              <td>@if ($last){{ number_format($last->current_reading, 2) }}<div class="text-muted small">{{ billingPeriodLabel($last->billing_period) }}</div>@else<span class="text-muted">—</span>@endif</td>
              <td>@if ($last && $last->billing_period === $currentPeriod)<span class="badge badge-success">{{ __('Read') }}</span>@else<span class="badge badge-warning">{{ __('Not yet read') }}</span>@endif</td>
            @endif
            <td class="actions">
              @if ($isReader)
                @if ($c->status === 'active' && !($last && $last->billing_period === $currentPeriod))
                  <a class="btn btn-primary btn-sm" href="{{ route('admin.readings.index', ['record' => 1, 'meter' => $c->meter_number]) }}">{{ __('Record Reading') }}</a>
                @else
                  <span class="text-muted small">—</span>
                @endif
              @else
              <a class="btn btn-outline btn-sm" href="{{ route('admin.consumers.history', $c) }}" title="{{ __('View History') }}" data-history="{{ route('admin.consumers.history', [$c, 'partial' => 1]) }}" data-history-name="{{ $c->display_name }}">{{ __('History') }}</a>
              <button class="btn btn-secondary btn-sm" onclick="openEditModal({{ json_encode($c) }}, '{{ route('admin.consumers.update', $c) }}')">{{ __('Edit') }}</button>
              @endif
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="{{ $isReader ? 9 : 8 }}">{{ __('No consumers found.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $consumers])
  </div>
</div>
@endif

@unless ($isReader)
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.consumers.store') }}">
        <div class="modal-header"><h3 class="h6 mb-0">{{ __('Add New Consumer') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          @include('admin.consumers._form-fields', ['isEdit' => false])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save Consumer') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="" id="editForm">
        <div class="modal-header"><h3 class="h6 mb-0">{{ __('Edit Consumer') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf @method('PUT')
        <div class="modal-body">
          @include('admin.consumers._form-fields', ['isEdit' => true])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Update Consumer') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditModal(c, action) {
  document.getElementById('editForm').action = action;
  document.getElementById('edit_meter_number').value = c.meter_number || '';
  document.getElementById('edit_full_name').value = c.full_name;
  document.getElementById('edit_address').value = c.address;
  document.getElementById('edit_purok_id').value = c.purok_id;
  document.getElementById('edit_consumer_type').value = c.consumer_type || 'residential';
  document.getElementById('edit_contact_number').value = c.contact_number || '';
  document.getElementById('edit_email').value = c.email || '';
  document.getElementById('edit_connection_date').value = c.connection_date || '';
  document.getElementById('edit_is_senior').checked = !!c.is_senior;
  document.getElementById('edit_status').value = c.status;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).show();
}
</script>
@endunless

{{-- Consumer history pop-up (filled by the History buttons) --}}
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header"><h3 class="h6 mb-0">{{ __('History —') }} <span id="historyName"></span></h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
      <div class="modal-body" id="historyBody" style="background:#f4fafc;"></div>
    </div>
  </div>
</div>
<script>
// History opens here in a pop-up instead of a separate page.
document.addEventListener('click', function (e) {
  const link = e.target.closest('[data-history]');
  if (!link || e.ctrlKey || e.metaKey || e.shiftKey) return;   // Ctrl-click still opens the full page
  e.preventDefault();
  const body = document.getElementById('historyBody');
  document.getElementById('historyName').textContent = link.dataset.historyName;
  body.innerHTML = '<div class="text-center text-muted p-5">' + {{ Js::from(__('Loading…')) }} + '</div>';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('historyModal')).show();
  fetch(link.dataset.history, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
    .then(r => r.ok ? r.text() : Promise.reject())
    .then(html => { body.innerHTML = html; })
    .catch(() => { body.innerHTML = '<div class="alert alert-danger m-0">' + {{ Js::from(__('Could not load the history.')) }} + ' <a href="' + link.href + '">' + {{ Js::from(__('Open it as a page')) }} + '</a>.</div>'; });
});
</script>
@endsection
