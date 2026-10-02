<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Access Denied · {{ config('agas.short_name') }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body class="auth-body">
  <div class="auth-card" style="text-align:center;">
    <h1 style="color:#d9534f;">🚫 Access Denied</h1>
    <p>You do not have permission to view this page.</p>
    <a class="btn btn-primary" href="javascript:history.back()">Go Back</a>
  </div>
</body>
</html>
