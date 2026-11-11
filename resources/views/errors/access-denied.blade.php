<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
<meta charset="UTF-8">
<title>{{ __('Access Denied · :value', ['value' => config('agas.short_name')]) }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body class="auth-body">
  <div class="auth-card" style="text-align:center;">
    <h1 style="color:#d9534f;"><i class="ph ph-prohibit" aria-hidden="true"></i> {{ __('Access Denied') }}</h1>
    <p>{{ __('You do not have permission to view this page.') }}</p>
    <a class="btn btn-primary" href="javascript:history.back()">{{ __('Go Back') }}</a>
  </div>
</body>
</html>
