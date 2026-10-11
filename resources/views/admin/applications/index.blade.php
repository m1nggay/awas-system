@extends('layouts.app')

@section('title', 'Membership Applications')

@section('content')
<div class="card">
  <div class="card-body">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text">🔍</span>
        <input type="text" name="q" class="form-control" value="{{ $search }}"
               placeholder="{{ isset($accounts) ? 'Search by name, username, email, or meter number...' : 'Search by applicant name, email, or application ID...' }}">
      </div>
      <select name="status" class="form-select w-auto" onchange="this.form.submit()">
        @foreach ($statusOptions as $value => $label)
          <option value="{{ $value }}" @selected($statusFil === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
    </form>

    @isset($accounts)
    <p class="text-muted small mb-2">Existing consumers who created their own login on the Register page (with their Meter Number). These are not applications — nothing to review.</p>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead>
          <tr><th>Username</th><th>Name</th><th>Meter Number</th><th>Purok</th><th>Email</th><th>Date Registered</th><th>Email</th><th>Last Login</th><th>Action</th></tr>
        </thead>
        <tbody>
        @forelse ($accounts as $u)
          <tr>
            <td><strong>{{ $u->username }}</strong></td>
            <td>{{ $u->full_name }}</td>
            <td>{{ $u->meter_number ?: '—' }}</td>
            <td>{{ $u->purok_name ?: '—' }}</td>
            <td>{{ $u->email ?: '—' }}</td>
            <td>{{ formatDateTime($u->created_at) }}</td>
            <td>
              @if ($u->status === 'pending')<span class="badge badge-secondary">Not verified</span>
              @elseif ($u->status === 'inactive')<span class="badge badge-danger">Deactivated</span>
              @else<span class="badge badge-success">Verified</span>@endif
            </td>
            <td>{{ $u->last_login ? formatDateTime($u->last_login) : 'Never' }}</td>
            <td class="actions">
              <form method="POST" action="{{ route('admin.applications.accounts.destroy', $u->user_id) }}" class="d-inline"
                    data-confirm="Delete the account {{ $u->username }}?&#10;&#10;They can no longer sign in. Their water account, bills and payments are kept, and they can register again.">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="9">No self-registered accounts found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $accounts])
    @else
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead>
          <tr>
            <th>Application ID</th><th>Applicant</th><th>Email</th><th>Purok</th><th>Date Applied</th>
            <th>Email</th><th>ID</th><th>Face</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
        @forelse ($applications as $a)
          <tr>
            <td>{{ $a->reference_code }}</td>
            <td>{{ $a->full_name }}</td>
            <td>{{ $a->email ?: '—' }}</td>
            <td>{{ $a->purok_name }}</td>
            <td>{{ formatDateTime($a->created_at) }}</td>
            <td><span class="badge {{ $a->email_verified_at ? 'badge-success' : 'badge-secondary' }}">{{ $a->email_verified_at ? 'Verified' : 'Not verified' }}</span></td>
            <td><span class="badge {{ verificationStatusBadgeClass($a->id_status) }}">{{ verificationStatusLabel($a->id_status) }}</span></td>
            <td><span class="badge {{ verificationStatusBadgeClass($a->face_status) }}">{{ verificationStatusLabel($a->face_status) }}</span></td>
            <td><span class="badge {{ applicationStatusBadgeClass($a->status) }}">{{ applicationStatusLabel($a->status) }}</span></td>
            <td class="actions">
              @if ($a->status === 'pending_verification')
                <span class="text-muted small">Not submitted yet</span>
              @else
                <a class="btn btn-primary btn-sm" href="{{ route('admin.applications.show', $a->application_id) }}">Review</a>
              @endif
              <form method="POST" action="{{ route('admin.applications.destroy', ['application' => $a->application_id, 'status' => $statusFil, 'q' => $search ?: null]) }}" class="d-inline"
                    data-confirm="Delete application {{ $a->reference_code }} ({{ $a->full_name }})?&#10;&#10;{{ $a->status === 'active' ? 'The application record and its ID/face photos are removed. The consumer, their login, bills and payments are kept.' : 'The application, its ID/face photos and the applicant\'s login are removed. This cannot be undone.' }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="10">No applications found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $applications])
    @endisset
  </div>
</div>
@endsection
