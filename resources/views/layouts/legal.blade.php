<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title') · {{ config('agas.short_name') }}</title>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<link rel="icon" type="image/png" href="{{ asset('assets/img/logo-round.png') }}">
<style>
  body{background:var(--bg);}
  .legal-top{background:#fff;border-bottom:1px solid var(--border);}
  .legal-top .wrap{max-width:820px;margin:0 auto;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;}
  .legal-brand{display:flex;align-items:center;gap:10px;color:var(--primary-dark);font-weight:700;text-decoration:none;}
  .legal-brand img{width:40px;height:40px;object-fit:contain;}
  .legal{max-width:820px;margin:24px auto 40px;padding:32px 36px;background:#fff;border:1px solid var(--border);border-radius:14px;line-height:1.7;color:var(--text-dark);font-size:15px;}
  .legal h1{font-size:26px;color:var(--primary-dark);margin-bottom:2px;}
  .legal h2{font-size:17px;color:var(--primary-dark);margin:26px 0 8px;}
  .legal-date{color:var(--text-muted);font-size:13px;margin-bottom:18px;}
  .legal ul{padding-left:22px;margin-bottom:10px;list-style:disc;} .legal li{margin-bottom:6px;}
  .legal-foot{text-align:center;color:var(--text-muted);font-size:13px;margin-bottom:30px;}
  .legal-foot a{margin:0 8px;}
  @media (max-width:576px){ .legal{margin:12px;padding:22px 18px;font-size:14.5px;} .legal h1{font-size:22px;} }
</style>
</head>
<body>
  <header class="legal-top">
    <div class="wrap">
      <a href="{{ url('/') }}" class="legal-brand"><img src="{{ asset('assets/img/logo-round.png') }}" alt="">AWAS</a>
      <a href="{{ url('/') }}" class="small"><i class="ph ph-arrow-left" aria-hidden="true"></i> Back to Home</a>
    </div>
  </header>
  <main class="legal">
    @yield('content')
  </main>
  <div class="legal-foot">
    <a href="{{ route('privacy') }}">Privacy Policy</a>·<a href="{{ route('terms') }}">Terms of Service</a>·<a href="{{ route('login') }}">Login</a>
  </div>
</body>
</html>
