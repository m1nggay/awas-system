@extends('layouts.app')

@section('title', !$isAdmin ? 'My Activity Logs' : ($user ? 'Activity Logs — ' . $user->full_name : 'Activity Logs'))

@section('content')
@if ($isAdmin)
  <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm mb-3">&larr; Back to Manage Users</a>
@endif

<div class="card">
  <div class="card-header">
    <h3>
      @if (!$isAdmin)
        My Activity
      @elseif ($user)
        Activity of {{ $user->full_name }} <span class="text-muted" style="font-weight:400;">({{ $user->username }} · {{ roleLabel($user->role) }})</span>
      @else
        All User Activity
      @endif
    </h3>
    <span class="text-muted small">{{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}</span>
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('admin.users.logs') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3">
      @if ($isAdmin)
        <select name="user_id" class="form-select w-auto" onchange="this.form.submit()">
          <option value="0">All users</option>
          @foreach ($users as $u)
            <option value="{{ $u->user_id }}" @selected($userId === (int)$u->user_id)>{{ $u->full_name }} ({{ $u->username }})</option>
          @endforeach
        </select>
        <select name="role" class="form-select w-auto" onchange="this.form.submit()">
          <option value="">All roles</option>
          @foreach (['admin', 'staff', 'resident'] as $r)
            <option value="{{ $r }}" @selected($roleFil === $r)>{{ roleLabel($r) }}</option>
          @endforeach
        </select>
      @endif
      <select name="action" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All actions</option>
        @foreach ($actions as $a)
          <option value="{{ $a }}" @selected($action === $a)>{{ ucfirst(str_replace('_', ' ', $a)) }}</option>
        @endforeach
      </select>
      <input type="date" name="from" class="form-control w-auto" value="{{ $from }}" title="From date">
      <input type="date" name="to" class="form-control w-auto" value="{{ $to }}" title="To date">
      <div class="input-group flex-grow-1" style="min-width:180px;">
        <span class="input-group-text">🔍</span>
        <input type="text" name="q" class="form-control" placeholder="Search description or IP..." value="{{ $search }}">
      </div>
      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
      @if (($isAdmin && ($userId || $roleFil !== '')) || $action !== '' || $search !== '' || $from !== '' || $to !== '')
        <a href="{{ route('admin.users.logs') }}" class="btn btn-outline btn-sm">Clear</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>Date</th><th>Time</th><th>IP Address</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
          @php
            $badge = match (true) {
                in_array($log->action, ['login_failed', 'login_blocked', 'consumer_delete', 'faq_delete', 'application_reject'], true) => 'badge-danger',
                str_starts_with($log->action, 'login') || $log->action === 'logout' => 'badge-secondary',
                str_starts_with($log->action, 'payment') => 'badge-success',
                str_contains($log->action, 'password') => 'badge-warning',
                default => 'badge-info',
            };
          @endphp
          <tr>
            <td>
              @if ($log->user_id)
                @if ($isAdmin)
                  <a href="{{ route('admin.users.logs', ['user_id' => $log->user_id]) }}">{{ $log->full_name ?? 'Deleted user' }}</a>
                @else
                  {{ $log->full_name }}
                @endif
                <div class="text-muted" style="font-size:11.5px;">{{ $log->username }}</div>
              @else
                <span class="text-muted">System / not signed in</span>
              @endif
            </td>
            <td>{{ $log->user_id ? roleLabel($log->role) : '—' }}</td>
            <td><span class="badge {{ $badge }}">{{ str_replace('_', ' ', $log->action) }}</span></td>
            <td style="max-width:380px;">{{ $log->details ?: '—' }}</td>
            <td style="white-space:nowrap;">{{ formatDate($log->created_at) }}</td>
            <td style="white-space:nowrap;">{{ formatDateTime($log->created_at, 'g:i:s A') }}</td>
            <td class="text-muted" style="font-size:12px;">{{ $log->ip_address ?: '—' }}</td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="7">No activity found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $logs])
  </div>
</div>
@endsection
