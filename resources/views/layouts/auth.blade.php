{{-- Centered card layout for login/registration/verification pages.
     Options: $video (drone background), $cardStyle (inline style for the card). --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title') · {{ config('agas.short_name') }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<link rel="icon" type="image/png" href="{{ asset('assets/img/logo.png') }}">
@stack('styles')
</head>
<body class="auth-body">
  <div id="pageTransitionOverlay" aria-hidden="true"></div>
  @if ($video ?? false)
    <video class="bg-video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
      <source src="{{ asset('assets/drone.mp4') }}" type="video/mp4">
    </video>
    <div class="bg-video-tint" aria-hidden="true"></div>
  @endif

  <div class="auth-card" @isset($cardStyle) style="{{ $cardStyle }}" @endisset>
    <div class="auth-logo"><img src="{{ asset('assets/img/logo.png') }}" alt="AWAS logo"></div>
    @include('partials.flash')
    @yield('content')
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/forms.js') }}?v={{ @filemtime(public_path('assets/js/forms.js')) ?: '1' }}"></script>
@stack('scripts')
<script src="{{ asset('assets/js/transitions.js') }}"></script>
</body>
</html>
