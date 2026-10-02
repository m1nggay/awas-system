@extends('layouts.app')

@section('title', 'Manage Users')

@section('content')
<div class="card">
  <div class="card-header">
    <h3>System Users</h3>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.users.logs') }}" class="btn btn-outline btn-sm">📋 Activity Logs</a>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal">+ Add User</button>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <select name="role" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All Roles</option>
        <option value="admin" @selected($roleFil === 'admin')>Administrator</option>
        <option value="staff" @selected($roleFil === 'staff')>Meter Reader</option>
        <option value="resident" @selected($roleFil === 'resident')>Consumer</option>
      </select>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Email</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($users as $u)
          <tr>
            <td>{{ $u->username }}</td>
            <td>{{ $u->full_name }}</td>
            <td><span class="badge badge-info">{{ roleLabel($u->role) }}</span></td>
            <td>{{ $u->email ?: '—' }}</td>
            <td><span class="badge {{ $u->status === 'active' ? 'badge-success' : ($u->status === 'pending' ? 'badge-warning' : 'badge-secondary') }}">{{ $u->status }}</span></td>
            <td>{{ formatDateTime($u->last_login) }}</td>
            <td class="actions">
              <a class="btn btn-outline btn-sm" href="{{ route('admin.users.logs', ['user_id' => $u->user_id]) }}">Logs</a>
              <button class="btn btn-secondary btn-sm" onclick="openResetPw('{{ route('admin.users.reset-password', $u) }}', {{ json_encode($u->username) }})">Reset PW</button>
              @if ((int)$u->user_id !== (int)auth()->user()->user_id)
                <form method="POST" action="{{ route('admin.users.toggle', $u) }}" class="d-inline" data-confirm="Toggle active status for this user?">
                  @csrf
                  <button class="btn {{ $u->status === 'active' ? 'btn-danger' : 'btn-success' }} btn-sm" type="submit">{{ $u->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="7">No users found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.users.store') }}">
        <div class="modal-header"><h3 class="h6 mb-0">Add System User</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Role *</label>
              <select name="role" class="form-select" required>
                <option value="staff">Meter Reader</option>
                <option value="admin">Administrator</option>
                <option value="resident">Consumer</option>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Contact Number</label><input type="text" name="contact_number" class="form-control"></div>
            <div class="col-12">
              <label class="form-label" for="new_user_password">Temporary Password *</label>
              <input type="password" id="new_user_password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
              @include('partials.password-hint', ['for' => 'new_user_password'])
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="resetPwModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="" id="resetPwForm">
        <div class="modal-header"><h3 class="h6 mb-0">Reset Password for <span id="resetPwUsername"></span></h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="reset_user_password">New Password *</label>
            <input type="password" id="reset_user_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
            @include('partials.password-hint', ['for' => 'reset_user_password'])
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openResetPw(action, username) {
  document.getElementById('resetPwForm').action = action;
  document.getElementById('resetPwUsername').textContent = username;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('resetPwModal')).show();
}
</script>
@endsection
