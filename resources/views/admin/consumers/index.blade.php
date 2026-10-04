@extends('layouts.app')

@section('title', 'Consumers')

@section('content')
@include('partials.reader-puroks')
@php $isReader = auth()->user()->isMeterReader(); @endphp
<div class="card">
  <div class="card-header">
    <h3>Consumer Accounts</h3>
    @unless ($isReader)
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">+ Add Consumer</button>
    @endunless
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('admin.consumers.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text">🔍</span>
        <input type="search" name="q" class="form-control" placeholder="Search by Meter Number, Name or Purok..." value="{{ $search }}" aria-label="Search consumers">
        <button type="submit" class="btn btn-primary">Search</button>
      </div>
      <select name="purok" class="form-select w-auto" onchange="this.form.submit()">
        <option value="0">All Puroks</option>
        @foreach ($puroks as $pk)
          <option value="{{ $pk->purok_id }}" @selected($purokFil === (int)$pk->purok_id)>{{ $pk->purok_name }}</option>
        @endforeach
      </select>
      <select name="type" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All Types</option>
        @foreach (\App\Models\Consumer::TYPES as $v => $l)
          <option value="{{ $v }}" @selected($typeFil === $v)>{{ $l }}</option>
        @endforeach
      </select>
      <select name="status" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active" @selected($statusFil === 'active')>Active</option>
        <option value="disconnected" @selected($statusFil === 'disconnected')>Disconnected</option>
        <option value="inactive" @selected($statusFil === 'inactive')>Inactive</option>
      </select>
      @if ($search !== '' || $purokFil || $statusFil !== '' || $typeFil !== '')
        <a href="{{ route('admin.consumers.index') }}" class="btn btn-outline btn-sm">Clear</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table consumer-table">
        <thead>
          <tr><th>No.</th><th>Meter Number</th><th>Name</th><th>Purok</th><th>Type of Consumer</th><th>Status</th>@unless ($isReader)<th>Balance</th>@endunless @if ($isReader)<th>Last Reading</th><th>This Month</th>@endif<th>Actions</th></tr>
        </thead>
        <tbody>
        @forelse ($consumers as $i => $c)
          <tr>
            <td>{{ $consumers->firstItem() + $i }}</td>
            <td><strong>{{ $c->meter_number ?: '—' }}</strong></td>
            <td class="consumer-name">{{ $c->display_name }}<div class="text-muted small consumer-addr">{{ $c->address }}</div></td>
            <td>{{ $c->purok_name }}</td>
            <td>{{ consumerTypeLabel($c->consumer_type) }}</td>
            <td><span class="badge {{ $c->status === 'active' ? 'badge-success' : ($c->status === 'disconnected' ? 'badge-danger' : 'badge-secondary') }}">{{ $c->status }}</span></td>
            @unless ($isReader)
              <td>@if ((float)$c->unpaid_balance > 0)<span class="text-danger fw-semibold">{{ formatCurrency($c->unpaid_balance) }}</span>@if ($c->status === 'disconnected')<div class="text-muted" style="font-size:11px;">unpaid before disconnection</div>@endif @else<span class="text-muted">₱0.00</span>@endif</td>
            @endunless
            @if ($isReader)
              @php $last = $lastReadings[$c->consumer_id] ?? null; @endphp
              <td>@if ($last){{ number_format($last->current_reading, 2) }}<div class="text-muted small">{{ billingPeriodLabel($last->billing_period) }}</div>@else<span class="text-muted">—</span>@endif</td>
              <td>@if ($last && $last->billing_period === $currentPeriod)<span class="badge badge-success">Read</span>@else<span class="badge badge-warning">Not yet read</span>@endif</td>
            @endif
            <td class="actions">
              @if ($isReader)
                @if ($c->status === 'active' && !($last && $last->billing_period === $currentPeriod))
                  <a class="btn btn-primary btn-sm" href="{{ route('admin.readings.index', ['record' => 1, 'meter' => $c->meter_number]) }}">Record Reading</a>
                @else
                  <span class="text-muted small">—</span>
                @endif
              @else
              <a class="btn btn-outline btn-sm" href="{{ route('admin.consumers.history', $c) }}" title="View History">History</a>
              <button class="btn btn-secondary btn-sm" onclick="openEditModal({{ json_encode($c) }}, '{{ route('admin.consumers.update', $c) }}')">Edit</button>
              <form method="POST" action="{{ route('admin.consumers.destroy', $c) }}" class="d-inline" data-confirm="Delete this consumer? This cannot be undone.">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
              @endif
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="{{ $isReader ? 9 : 8 }}">No consumers found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $consumers])
  </div>
</div>

@unless ($isReader)
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.consumers.store') }}">
        <div class="modal-header"><h3 class="h6 mb-0">Add New Consumer</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          @include('admin.consumers._form-fields', ['isEdit' => false])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Consumer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="" id="editForm">
        <div class="modal-header"><h3 class="h6 mb-0">Edit Consumer</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf @method('PUT')
        <div class="modal-body">
          @include('admin.consumers._form-fields', ['isEdit' => true])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Consumer</button>
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
@endsection
