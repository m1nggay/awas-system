@extends('layouts.app')

@section('title', __('Manage Users'))

@section('content')
<div class="card">
  <div class="card-header">
    <h3>{{ __('System Users') }}</h3>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.users.logs') }}" class="btn btn-outline btn-sm"><i class="ph ph-scroll" aria-hidden="true"></i> {{ __('Activity Logs') }}</a>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal">{{ __('+ Add User') }}</button>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <select name="role" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">{{ __('All Roles') }}</option>
        <option value="admin" @selected($roleFil === 'admin')>{{ __('AWAS Administrator') }}</option>
        <option value="staff" @selected($roleFil === 'staff')>{{ __('Meter Reader') }}</option>
        <option value="resident" @selected($roleFil === 'resident')>{{ __('Consumer') }}</option>
      </select>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>{{ __('Username') }}</th><th>{{ __('Full Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Assigned Puroks') }}</th><th>{{ __('Email') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last Login') }}</th><th>{{ __('Actions') }}</th></tr></thead>
        <tbody>
        @forelse ($users as $u)
          <tr>
            <td class="text-nowrap">{{ $u->username }}</td>
            <td class="text-nowrap">{{ $u->full_name }}</td>
            <td class="text-nowrap"><span class="badge badge-info">{{ roleLabel($u->role) }}</span></td>
            <td style="min-width:150px;">
              @if ($u->role === 'staff')
                @php $mine = $userPuroks[$u->user_id] ?? collect(); @endphp
                @if ($mine->isEmpty())<span class="badge badge-warning">{{ __('None assigned') }}</span>
                @else
                  <div class="d-flex flex-wrap gap-1">
                    @foreach ($mine as $pk)<span class="badge badge-secondary">{{ $pk->purok_name }}</span>@endforeach
                  </div>
                @endif
              @else<span class="text-muted">—</span>@endif
            </td>
            <td class="text-nowrap">{{ $u->email ?: '—' }}</td>
            <td class="text-nowrap">
              @if ($u->locked_at)
                {{-- Locked after too many failed logins --}}
                <span class="badge badge-danger" title="Locked {{ formatDateTime($u->locked_at) }} after {{ \App\Models\User::MAX_LOGIN_ATTEMPTS }} failed logins"><i class="ph ph-lock-simple" aria-hidden="true"></i> {{ __('Locked') }}</span>
                <form method="POST" action="{{ route('admin.users.unlock', $u) }}" class="mt-1" data-confirm="{{ __('Unlock :name\'s account?', ['name' => $u->full_name]) }}">
                  @csrf @method('PATCH')
                  <button type="submit" class="btn btn-outline btn-sm">{{ __('Unlock') }}</button>
                </form>
              @else
                <span class="badge {{ $u->status === 'active' ? 'badge-success' : ($u->status === 'pending' ? 'badge-warning' : 'badge-inactive') }}">{{ ucfirst($u->status) }}</span>
              @endif
            </td>
            <td class="text-nowrap small">
              @if ($u->last_login)
                {{ formatDateTime($u->last_login, 'M j, Y') }}<br><span class="text-muted">{{ formatDateTime($u->last_login, 'g:i A') }}</span>
              @else<span class="text-muted">{{ __('Never') }}</span>@endif
            </td>
            <td>
              {{-- Fixed slots so every row's buttons line up, even when a slot is empty. --}}
              <div class="user-actions">
                @if ($u->role === 'staff')
                  <button class="btn btn-outline btn-sm" onclick="openPuroks('{{ route('admin.users.puroks', $u) }}', {{ json_encode($u->username) }}, {{ json_encode(($userPuroks[$u->user_id] ?? collect())->pluck('purok_id')->map(fn ($id) => (int)$id)->values()) }}, {{ (int)$u->user_id }})">{{ __('Puroks') }}</button>
                @else
                  <span></span>
                @endif
                @if ((int)$u->user_id !== (int)auth()->user()->user_id)
                  <form method="POST" action="{{ route('admin.users.toggle', $u) }}" data-confirm="{{ __('Toggle active status for this user?') }}">
                    @csrf
                    <button class="btn {{ $u->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }} btn-sm w-100" type="submit">{{ $u->status === 'active' ? __('Deactivate') : __('Activate') }}</button>
                  </form>
                @else
                  <span></span>
                @endif
                {{-- Consumers only; refused when they already have readings, bills or payments. --}}
                @if ($u->role === 'resident')
                  <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                        data-confirm="{{ __('Delete :name\'s account?', ['name' => $u->full_name]) }}&#10;&#10;{{ __('Their login and water account are removed. This only works if they have no meter readings, bills or payments yet. This cannot be undone.') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm w-100" type="submit">{{ __('Delete') }}</button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="8">{{ __('No users found.') }}</td></tr>
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
        <div class="modal-header"><h3 class="h6 mb-0">{{ __('Add System User') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">{{ __('Username *') }}</label><input type="text" name="username" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">{{ __('Role *') }}</label>
              <select name="role" class="form-select" required id="newUserRole">
                <option value="staff">{{ __('Meter Reader') }}</option>
                <option value="admin">{{ __('AWAS Administrator') }}</option>
                <option value="resident">{{ __('Consumer') }}</option>
              </select>
            </div>
            <div class="col-12"><label class="form-label">{{ __('Full Name *') }}</label><input type="text" name="full_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Contact Number') }}</label><input type="text" name="contact_number" class="form-control"></div>
            <div class="col-12" id="newUserPuroks">
              <label class="form-label">{{ __('Assigned Puroks') }} <span class="text-muted" style="font-weight:400;">{{ __('(the meter reader can only read these)') }}</span></label>
              @include('admin.users._purok-checkboxes', ['prefix' => 'new'])
            </div>
            <div class="col-12">
              <label class="form-label" for="new_user_password">{{ __('Temporary Password *') }}</label>
              <input type="password" id="new_user_password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
              @include('partials.password-hint', ['for' => 'new_user_password'])
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Create User') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="puroksModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="" id="puroksForm">
        <div class="modal-header"><h3 class="h6 mb-0">{{ __('Puroks for') }} <span id="puroksUsername"></span></h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          <p class="text-muted small mb-2">{{ __('This meter reader will only see and read consumers in the puroks you tick. A purok already assigned to another meter reader moves to this one.') }}</p>
          @include('admin.users._purok-checkboxes', ['prefix' => 'edit'])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save Puroks') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openPuroks(action, username, assigned, userId) {
  document.getElementById('puroksForm').action = action;
  document.getElementById('puroksUsername').textContent = username;
  document.querySelectorAll('#puroksForm [data-purok]').forEach(function (box) {
    box.checked = assigned.indexOf(parseInt(box.value, 10)) !== -1;
    var note = box.parentElement.querySelector('[data-owner]');
    if (note) note.hidden = parseInt(note.getAttribute('data-owner'), 10) === userId;
  });
  bootstrap.Modal.getOrCreateInstance(document.getElementById('puroksModal')).show();
}
document.getElementById('newUserRole').addEventListener('change', function () {
  document.getElementById('newUserPuroks').hidden = this.value !== 'staff';
});
</script>
@endsection
