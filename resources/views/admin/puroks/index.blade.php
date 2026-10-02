@extends('layouts.app')

@section('title', 'Puroks')

@section('content')
<div class="card">
  <div class="card-header">
    <h3>Puroks</h3>
    <button class="btn btn-primary btn-sm" id="addPurokBtn" data-bs-toggle="modal" data-bs-target="#purokModal">+ Add Purok</button>
  </div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Purok Name</th><th>Description</th><th>Consumers</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($puroks as $p)
          <tr>
            <td>{{ $p->purok_name }}</td>
            <td>{{ $p->description ?: '—' }}</td>
            <td>{{ number_format($p->consumer_count) }}</td>
            <td class="actions">
              <button class="btn btn-secondary btn-sm" onclick="openEditPurok({{ json_encode($p) }}, '{{ route('admin.puroks.update', $p->purok_id) }}')">Edit</button>
              <form method="POST" action="{{ route('admin.puroks.destroy', $p->purok_id) }}" class="d-inline" data-confirm="Delete this purok?">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="4">No puroks yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="purokModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.puroks.store') }}" id="purokForm">
        <div class="modal-header"><h3 class="h6 mb-0" id="purokModalTitle">Add Purok</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <input type="hidden" name="_method" id="purokMethod" value="POST">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Purok Name *</label><input type="text" name="purok_name" id="purok_name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Description</label><input type="text" name="description" id="description" class="form-control"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditPurok(p, action) {
  document.getElementById('purokForm').action = action;
  document.getElementById('purokMethod').value = 'PUT';
  document.getElementById('purokModalTitle').textContent = 'Edit Purok';
  document.getElementById('purok_name').value = p.purok_name;
  document.getElementById('description').value = p.description || '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('purokModal')).show();
}
document.getElementById('addPurokBtn').addEventListener('click', () => {
  const form = document.getElementById('purokForm');
  form.reset();
  form.action = {{ Js::from(route('admin.puroks.store')) }};
  document.getElementById('purokMethod').value = 'POST';
  document.getElementById('purokModalTitle').textContent = 'Add Purok';
});
</script>
@endsection
