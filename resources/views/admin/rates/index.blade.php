@extends('layouts.app')

@section('title', 'Billing Rates')

@section('content')
<div class="alert alert-info">
  💡 These rate brackets drive automatic bill computation. Consumption falling within a bracket's min/max range is billed at: <strong>Base Charge + (billable m³ × Rate per m³)</strong>. No code changes needed — just update the values here.
</div>

<div class="card">
  <div class="card-header">
    <h3>Configured Billing Rate Brackets</h3>
    <button class="btn btn-primary btn-sm" id="addRateBtn" data-bs-toggle="modal" data-bs-target="#rateModal">+ Add Rate Bracket</button>
  </div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Rate Name</th><th>Min (m³)</th><th>Max (m³)</th><th>Rate/m³</th><th>Base Charge</th><th>Penalty %</th><th>Effective</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($rates as $r)
          <tr>
            <td>{{ $r->rate_name }}</td>
            <td>{{ number_format($r->min_consumption, 2) }}</td>
            <td>{{ $r->max_consumption !== null ? number_format($r->max_consumption, 2) : 'No limit' }}</td>
            <td>{{ formatCurrency($r->rate_per_cubic_meter) }}</td>
            <td>{{ formatCurrency($r->base_charge) }}</td>
            <td>{{ number_format($r->penalty_percentage, 2) }}%</td>
            <td>{{ formatDate($r->effective_date) }}</td>
            <td><span class="badge {{ $r->is_active ? 'badge-success' : 'badge-secondary' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td class="actions">
              <button class="btn btn-secondary btn-sm" onclick="openEditRate({{ json_encode($r) }}, '{{ route('admin.rates.update', $r) }}')">Edit</button>
              <form method="POST" action="{{ route('admin.rates.destroy', $r) }}" class="d-inline" data-confirm="Delete this rate bracket?">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="9">No billing rates configured yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="rateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.rates.store') }}" id="rateForm">
        <div class="modal-header"><h3 class="h6 mb-0" id="rateModalTitle">Add Rate Bracket</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <input type="hidden" name="_method" id="rateMethod" value="POST">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Rate Name *</label>
            <input type="text" name="rate_name" id="rate_name" class="form-control" required placeholder="e.g. Tier 2 (11-20 cu.m.)">
          </div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Min Consumption (m³) *</label><input type="number" step="0.01" name="min_consumption" id="min_consumption" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Max Consumption (m³)</label><input type="number" step="0.01" name="max_consumption" id="max_consumption" class="form-control" placeholder="Leave blank for no limit"></div>
            <div class="col-md-6"><label class="form-label">Rate per m³ *</label><input type="number" step="0.01" name="rate_per_cubic_meter" id="rate_per_cubic_meter" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Base Charge *</label><input type="number" step="0.01" name="base_charge" id="base_charge" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Penalty % (if overdue)</label><input type="number" step="0.01" name="penalty_percentage" id="penalty_percentage" class="form-control" value="0"></div>
            <div class="col-md-6"><label class="form-label">Effective Date *</label><input type="date" name="effective_date" id="effective_date" class="form-control" required value="{{ date('Y-m-d') }}"></div>
          </div>
          <div class="mb-3 mt-3">
            <label><input type="checkbox" name="is_active" id="is_active" value="1" checked> Active (used for new bill computations)</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Rate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditRate(r, action) {
  const form = document.getElementById('rateForm');
  form.action = action;
  document.getElementById('rateMethod').value = 'PUT';
  document.getElementById('rateModalTitle').textContent = 'Edit Rate Bracket';
  document.getElementById('rate_name').value = r.rate_name;
  document.getElementById('min_consumption').value = r.min_consumption;
  document.getElementById('max_consumption').value = r.max_consumption ?? '';
  document.getElementById('rate_per_cubic_meter').value = r.rate_per_cubic_meter;
  document.getElementById('base_charge').value = r.base_charge;
  document.getElementById('penalty_percentage').value = r.penalty_percentage;
  document.getElementById('effective_date').value = r.effective_date;
  document.getElementById('is_active').checked = r.is_active == 1;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('rateModal')).show();
}
document.getElementById('addRateBtn').addEventListener('click', () => {
  const form = document.getElementById('rateForm');
  form.reset();
  form.action = {{ Js::from(route('admin.rates.store')) }};
  document.getElementById('rateMethod').value = 'POST';
  document.getElementById('rateModalTitle').textContent = 'Add Rate Bracket';
});
</script>
@endsection
