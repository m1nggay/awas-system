@extends('layouts.app')

@section('title', 'Notifications')

@push('styles')
<style>
  .notif-list-item{display:flex;gap:14px;align-items:flex-start;padding:14px 20px;border-bottom:1px solid var(--border);}
  .notif-list-item:last-child{border-bottom:0;}
  .notif-list-item.unread{background:#f0fbff;box-shadow:inset 3px 0 0 var(--primary);}
  .notif-list-icon{flex:none;width:36px;height:36px;border-radius:50%;background:#eef7fb;display:flex;align-items:center;justify-content:center;font-size:17px;}
  .notif-list-body{flex:1;min-width:0;}
  .notif-list-title{font-weight:600;font-size:14px;}
  .notif-list-msg{font-size:13px;color:var(--text-muted);margin-top:2px;}
  .notif-list-time{font-size:11.5px;color:#a3b3ba;margin-top:4px;}
  @media (max-width:640px){ .notif-list-item{padding:12px 14px;} .notif-list-item .btn{margin-top:6px;} .notif-list-item{flex-wrap:wrap;} }
</style>
@endpush

@section('content')
<div class="card">
  <div class="card-body no-pad">
    @forelse ($items as $n)
      <div class="notif-list-item {{ $n->is_read ? '' : 'unread' }}">
        <div class="notif-list-icon" aria-hidden="true">{{ match ($n->type) {
          'bill_due' => '🧾', 'bill_overdue' => '⚠️', 'disconnection' => '🚱', 'payment_received' => '✅', 'announcement' => '📣', default => '🔔',
        } }}</div>
        <div class="notif-list-body">
          <div class="notif-list-title">{{ $n->title }} @unless ($n->is_read)<span class="badge badge-info ms-1">New</span>@endunless</div>
          <div class="notif-list-msg">{{ $n->message }}</div>
          <div class="notif-list-time">{{ formatDateTime($n->created_at) }}</div>
        </div>
        @if ($n->link)
          <a href="{{ $n->link[0] }}" class="btn btn-outline btn-sm flex-shrink-0">{{ $n->link[1] }}</a>
        @endif
      </div>
    @empty
      <div class="text-center text-muted p-5">No notifications yet.</div>
    @endforelse
  </div>
</div>
@include('partials.pagination', ['paginator' => $items])
@endsection
