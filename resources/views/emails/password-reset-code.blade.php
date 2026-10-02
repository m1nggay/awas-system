<p>Hi {{ $name }},</p>
<p>{{ $purpose ?? 'Your AGAS password reset code is:' }}</p>
<p style="font-size:28px;font-weight:700;letter-spacing:6px;">{{ $code }}</p>
<p>This code expires in {{ $ttl }} minutes. Never share it with anyone — AGAS staff will never ask for it.
If you did not request this, you can safely ignore this email; your password stays the same.</p>
<p>— Barangay Adlay AGAS</p>
