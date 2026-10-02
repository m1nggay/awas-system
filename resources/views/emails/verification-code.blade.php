<p>Hi {{ $name }},</p>
<p>{{ $intro }}</p>
<p style="font-size:28px;font-weight:700;letter-spacing:6px;">{{ $code }}</p>
<p>This code expires in {{ $ttl }} minutes.</p>
@if ($next)
<p><strong>What to do next:</strong> {{ $next }}</p>
@endif
<p>If you did not request this, you can safely ignore this email.</p>
<p>— Barangay Adlay AGAS</p>
