@extends('layouts.app')

@section('title', 'Membership Applications')

@section('content')
<div class="card">
  <div class="card-header">
    <h3>Membership Applications</h3>
  </div>
  <div class="card-body">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text">🔍</span>
        <input type="text" name="q" class="form-control" placeholder="Search by applicant name, email, or application ID..." value="{{ $search }}">
      </div>
      <select name="status" class="form-select w-auto" onchange="this.form.submit()">
        @foreach ($statusOptions as $value => $label)
          <option value="{{ $value }}" @selected($statusFil === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
    </form>

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
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="10">No applications found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $applications])
  </div>
</div>
@endsection
