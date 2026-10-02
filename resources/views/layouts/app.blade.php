@php
    $user = auth()->user();
    $role = $user->role;
    $nav = [];
    if ($user->isAdmin()) {
        $nav = [
            'Main' => [['admin.dashboard', 'admin.dashboard', '📊', 'Dashboard']],
            'Water Management' => [
                ['admin.consumers.index', 'admin.consumers.*', '👤', 'Consumers'],
                ['admin.applications.index', 'admin.applications.*', '📝', 'Applications', $pendingApplicationCount ?? 0],
                ['admin.readings.index', 'admin.readings.*', '🧮', 'Meter Readings'],
                ['admin.bills.index', 'admin.bills.*', '🧾', 'Water Bills'],
                ['admin.payments.index', 'admin.payments.*', '💳', 'Payments'],
            ],
            'Reports' => [['admin.reports.index', 'admin.reports.*', '📈', 'Reports']],
            'Administration' => [
                ['admin.rates.index', 'admin.rates.*', '⚙️', 'Billing Rates'],
                ['admin.puroks.index', 'admin.puroks.*', '📍', 'Puroks'],
                ['admin.users.index', 'admin.users.index', '🔐', 'Manage Users'],
                ['admin.users.logs', 'admin.users.logs', '📋', 'Activity Logs'],
                ['admin.faqs.index', 'admin.faqs.*', '🤖', 'Chatbot FAQs'],
                ['admin.settings.edit', 'admin.settings.*', '🛠️', 'System Settings'],
            ],
            'Account' => [['account.password', 'account.*', '🔑', 'Change Password']],
        ];
    } elseif ($user->isMeterReader()) {
        // The Meter Reader's simpler menu: readings are their job.
        $nav = [
            'Main' => [
                ['admin.dashboard', 'admin.dashboard', '📊', 'Dashboard'],
                ['admin.consumers.index', 'admin.consumers.*', '👤', 'Consumer List'],
                ['admin.readings.index', 'admin.readings.*', '🧮', 'Meter Readings'],
                ['admin.reports.index', 'admin.reports.*', '📈', 'Reports'],
                ['admin.users.logs', 'admin.users.logs', '📋', 'My Activity Logs'],
            ],
            'Account' => [['account.password', 'account.*', '🔑', 'Change Password']],
        ];
    } elseif ($role === 'resident') {
        $nav = [
            'Main' => [
                ['resident.dashboard', 'resident.dashboard', '📊', 'Dashboard'],
                ['resident.bill', 'resident.bill*', '🧾', 'Current Bills'],
                ['resident.billing-history', 'resident.billing-history', '📜', 'Billing History'],
                ['resident.consumption', 'resident.consumption', '💧', 'My Consumption'],
                ['resident.payment-history', 'resident.payment-history', '💳', 'Payment History'],
            ],
            'Account' => [
                ['resident.profile', 'resident.profile*', '👤', 'My Profile'],
                ['account.password', 'account.*', '🔑', 'Change Password'],
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
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<link rel="icon" type="image/png" href="{{ asset('assets/img/logo.png') }}">
@stack('styles')
</head>
<body>
<div id="pageTransitionOverlay" aria-hidden="true"></div>
<div class="app-shell">

  <div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header d-lg-none">
      <span id="sidebarLabel" class="visually-hidden">Navigation</span>
      <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
      <div class="sidebar-brand">
        <img src="{{ asset('assets/img/logo.png') }}" alt="AWAS logo">
        <div>
          <div class="name">AGAS</div>
          <div class="sub">BARANGAY ADLAY</div>
        </div>
      </div>
      <div class="sidebar-user">
        <div class="u-name">{{ $user->full_name }}</div>
        <div class="u-role">{{ roleLabel($role) }}</div>
      </div>
      <nav class="sidebar-nav">
        @foreach ($nav as $section => $items)
          <div class="nav-label">{{ $section }}</div>
          @foreach ($items as $item)
            @php [$route, $pattern, $icon, $label] = $item; $badge = $item[4] ?? 0; @endphp
            <div class="position-relative">
              <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}"><span class="ic">{{ $icon }}</span>{{ $label }}</a>
              @if ($badge > 0)
                <span class="badge text-bg-warning position-absolute" style="right:12px;top:9px;font-size:10px;padding:1px 6px;">{{ $badge > 9 ? '9+' : $badge }}</span>
              @endif
            </div>
          @endforeach
        @endforeach
        <form method="POST" action="{{ route('logout') }}" id="logoutForm">
          @csrf
          <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logoutForm').submit();"><span class="ic">🚪</span>Logout</a>
        </form>
      </nav>
    </div>
  </div>

  <div class="main-content">
    <header class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="menu-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">☰</button>
        <div class="topbar-title">{{ $pageTitle }}</div>
      </div>
      <div class="topbar-right">
        <div class="dropdown">
          <button class="notif-btn" id="notifBtn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
            🔔
            @if (($unreadCount ?? 0) > 0)<span class="notif-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>@endif
          </button>
          <div class="dropdown-menu dropdown-menu-end notif-panel" aria-labelledby="notifBtn">
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
          </div>
        </div>
      </div>
    </header>

    <main class="page-body">
      @include('partials.flash')
      @yield('content')
    </main>

    <footer style="padding:16px 26px;text-align:center;font-size:12px;color:var(--text-muted);">
      &copy; {{ date('Y') }} Barangay Adlay — AGAS Smart Water Management and Billing System
    </footer>
  </div>
</div>

@if ($role === 'resident')
  @include('partials.chatbot-widget')
  <script src="{{ asset('assets/js/chatbot.js') }}"></script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/main.js') }}"></script>
<script src="{{ asset('assets/js/forms.js') }}?v={{ @filemtime(public_path('assets/js/forms.js')) ?: '1' }}"></script>
<script src="{{ asset('assets/js/transitions.js') }}"></script>
@stack('scripts')
</body>
</html>
