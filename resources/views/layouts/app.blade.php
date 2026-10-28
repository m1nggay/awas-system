@php
    $user = auth()->user();
    $role = $user->role;
    $nav = [];
    if ($user->isAdmin()) {
        $nav = [
            'Main' => [['admin.dashboard', 'admin.dashboard', 'ph-squares-four', 'Dashboard']],
            'Water Management' => [
                ['admin.consumers.index', 'admin.consumers.*', 'ph-users-three', 'Consumers'],
                ['admin.applications.index', 'admin.applications.*', 'ph-clipboard-text', 'Applications', $pendingApplicationCount ?? 0],
                ['admin.readings.index', 'admin.readings.*', 'ph-gauge', 'Recorded Readings'],
                ['admin.bills.index', 'admin.bills.*', 'ph-invoice', 'Water Bills'],
                ['admin.payments.index', 'admin.payments.*', 'ph-hand-coins', 'Payments'],
            ],
            'Administration' => [
                // Most used first: talking to consumers, monitoring, then setup.
                ['admin.announcements.index', 'admin.announcements.*', 'ph-megaphone-simple', 'Announcements'],
                ['admin.reports.index', 'admin.reports.*', 'ph-chart-bar', 'Reports'],
                ['admin.users.index', 'admin.users.index', 'ph-user-gear', 'Manage Users'],
                ['admin.settings.edit', ['admin.settings.*', 'admin.faqs.*', 'admin.puroks.*', 'admin.backups.*'], 'ph-gear-six', 'System Settings'],            ],
            'Account' => [
                ['admin.profile', ['admin.profile*', 'account.*'], 'ph-user', 'Profile'],
                ['admin.users.logs', 'admin.users.logs', 'ph-scroll', 'Activity Logs'],
            ],
        ];
    } elseif ($user->isMeterReader()) {
        // The Meter Reader's simpler menu: readings are their job.
        $nav = [
            'Main' => [
                ['admin.dashboard', 'admin.dashboard', 'ph-squares-four', 'Dashboard'],
                ['admin.consumers.index', 'admin.consumers.*', 'ph-users-three', 'Consumer List'],
                ['admin.readings.index', 'admin.readings.index', 'ph-gauge', 'Meter Readings'],
                ['admin.readings.history', 'admin.readings.history', 'ph-clock-counter-clockwise', 'Recorded Readings'],
                ['admin.reports.index', 'admin.reports.*', 'ph-chart-bar', 'Reports'],
                ['admin.users.logs', 'admin.users.logs', 'ph-scroll', 'Activity Logs'],
            ],
            // Change Password is opened from Profile.
            'Account' => [['admin.profile', ['admin.profile*', 'account.*'], 'ph-user', 'Profile']],
        ];
    } elseif ($role === 'resident') {
        $nav = [
            'Main' => [
                ['resident.dashboard', 'resident.dashboard', 'ph-squares-four', 'Dashboard'],
                ['resident.bill', 'resident.bill*', 'ph-drop-half-bottom', 'Current Bills'],
                ['resident.billing-history', 'resident.billing-history', 'ph-clock-counter-clockwise', 'Billing History'],
                ['resident.payment-history', 'resident.payment-history', 'ph-wallet', 'Payment History'],
            ],
            'Account' => [
                ['notifications.index', 'notifications.*', 'ph-bell-simple', 'Notifications', $unreadCount ?? 0],
                // Change Password is opened from My Profile.
                ['resident.profile', ['resident.profile*', 'account.*'], 'ph-user', 'My Profile'],
            ],
        ];
    }
    $pageTitle = trim($__env->yieldContent('title', config('agas.short_name')));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }} · {{ config('agas.short_name') }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<link rel="icon" type="image/png" href="{{ asset('assets/img/logo-round.png') }}">
@stack('styles')
</head>
<body @if (!empty($liveStart)) data-live-url="{{ route('admin.live') }}" data-live-reading="{{ $liveStart['reading'] }}" data-live-notif="{{ $liveStart['notif'] }}" @endif>
<div id="pageTransitionOverlay" aria-hidden="true"></div>
<div class="app-shell">

  <div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header d-lg-none">
      <span id="sidebarLabel" class="visually-hidden">Navigation</span>
      <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
      <div class="sidebar-brand">
        <img src="{{ asset('assets/img/logo-round.png') }}" alt="AWAS logo">
        <div>
          <div class="name">AWAS</div>
          <div class="sub">BARANGAY ADLAY</div>
        </div>
      </div>
      <div class="sidebar-user d-flex align-items-center gap-2">
        @if ($user->profile_photo)
          <img src="{{ route('account.photo', ['v' => $user->profile_photo]) }}" alt="" class="u-photo">
        @endif
        <div>
          <div class="u-name">{{ $user->full_name }}</div>
          <div class="u-role">{{ roleLabel($role) }}</div>
        </div>
      </div>
      <nav class="sidebar-nav">
        @foreach ($nav as $section => $items)
          <div class="nav-label">{{ $section }}</div>
          @foreach ($items as $item)
            @php [$route, $pattern, $icon, $label] = $item; $badge = $item[4] ?? 0; @endphp
            <div class="position-relative" data-nav="{{ $route }}">
              @php $active = request()->routeIs($pattern); @endphp
              {{-- Outline icon; the filled one for the page you are on. --}}
              <a href="{{ route($route) }}" class="{{ $active ? 'active' : '' }}"><i class="ic {{ $active ? 'ph-fill' : 'ph' }} {{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>
              @if ($badge > 0)
                <span class="badge text-bg-warning position-absolute" style="right:12px;top:9px;font-size:10px;padding:1px 6px;">{{ $badge > 9 ? '9+' : $badge }}</span>
              @endif
            </div>
          @endforeach
        @endforeach
        <form method="POST" action="{{ route('logout') }}" id="logoutForm">
          @csrf
          <a href="{{ route('logout') }}" onclick="event.preventDefault(); if (!window.awasOutboxCanLeave || awasOutboxCanLeave()) document.getElementById('logoutForm').submit();"><i class="ic ph ph-sign-out" aria-hidden="true"></i>Logout</a>
        </form>
      </nav>
    </div>
  </div>

  <div class="main-content">
    <header class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="menu-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar"><i class="ph ph-list" aria-hidden="true"></i></button>
        <div class="topbar-title">{{ $pageTitle }}</div>
      </div>
      <div class="topbar-right">
        @if ($user->isMeterReader())
          {{-- Readings saved on the phone, not sent yet (reading-outbox.js). --}}
          <a href="{{ route('admin.readings.index') }}" id="outboxBadge" class="outbox-badge" hidden><i class="ph ph-cloud-arrow-up" aria-hidden="true"></i><span></span></a>
        @endif
        <div class="dropdown">
          <button class="notif-btn" id="notifBtn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" data-read-url="{{ route('notifications.read') }}">
            <i class="ph ph-bell-simple" aria-hidden="true"></i>
            @if (($unreadCount ?? 0) > 0)<span class="notif-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>@endif
          </button>
          <div class="dropdown-menu dropdown-menu-end notif-panel" aria-labelledby="notifBtn" id="notifPanel">
            <div class="notif-panel-header">Notifications</div>
            @forelse ($notifications ?? [] as $n)
              <div class="notif-panel-item {{ $n->is_read ? '' : 'unread' }}">
                <div class="notif-panel-title">{{ $n->title }}</div>
                <div class="notif-panel-msg">{{ $n->message }}</div>
                <div class="notif-panel-time">{{ formatDateTime($n->created_at) }}</div>
              </div>
            @empty
              <div class="notif-panel-empty">No notifications yet.</div>
            @endforelse
            <a href="{{ route('notifications.index') }}" class="notif-panel-footer">View all notifications</a>
          </div>
        </div>
      </div>
    </header>

    <main class="page-body">
      @if ($user->isAdmin() && setting('maintenance_mode', '0') === '1')
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
          <span><i class="ph ph-wrench" aria-hidden="true"></i> <strong>Maintenance mode is ON</strong> — consumers and meter readers can't use AWAS right now.</span>
          <a href="{{ route('admin.settings.edit') }}#maintenance" class="btn btn-sm btn-outline">Turn off</a>
        </div>
      @endif
      @include('partials.flash')
      @yield('content')
    </main>

    <footer style="padding:16px 26px;text-align:center;font-size:12px;color:var(--text-muted);">
      &copy; {{ date('Y') }} Barangay Adlay — AWAS: Adlay Water Augmentation System
    </footer>
  </div>
</div>

@if ($role === 'resident')
  @include('partials.chatbot-widget')
  <script src="{{ asset('assets/js/chatbot.js') }}"></script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/main.js') }}?v={{ @filemtime(public_path('assets/js/main.js')) ?: '1' }}"></script>
<script src="{{ asset('assets/js/forms.js') }}?v={{ @filemtime(public_path('assets/js/forms.js')) ?: '1' }}"></script>
<script src="{{ asset('assets/js/transitions.js') }}"></script>
@if ($user->isMeterReader())
  <script>
    window.AWAS_OUTBOX = {{ Js::from([
        'userId' => $user->user_id, 'csrf' => csrf_token(), 'loginUrl' => route('login'),
        'syncUrl' => route('admin.readings.sync'), 'offlineUrl' => route('admin.readings.offline-data'),
    ]) }};
  </script>
  <script src="{{ asset('assets/js/reading-outbox.js') }}?v={{ @filemtime(public_path('assets/js/reading-outbox.js')) ?: '1' }}"></script>
@endif
@if (!empty($liveStart))
<script src="{{ asset('assets/js/live.js') }}?v={{ @filemtime(public_path('assets/js/live.js')) ?: '1' }}"></script>
@endif
<script>window.AWAS_SESSION = {{ Js::from([
    'timeout' => config('agas.idle_timeout_minutes') * 60,
    'statusUrl' => route('session.status'), 'keepAliveUrl' => route('session.keep-alive'),
    'loginUrl' => route('login', ['timeout' => 1]),
]) }};</script>
<script src="{{ asset('assets/js/session-timeout.js') }}?v={{ @filemtime(public_path('assets/js/session-timeout.js')) ?: '1' }}"></script>
@stack('scripts')
</body>
</html>
