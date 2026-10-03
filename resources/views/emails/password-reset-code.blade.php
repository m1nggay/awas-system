<p>Hi {{ $name }},</p>
<p>{{ $purpose ?? 'Your AWAS password reset code is:' }}</p>
<p style="font-size:28px;font-weight:700;letter-spacing:6px;">{{ $code }}</p>
<p>This code expires in {{ $ttl }} minutes. Never share it with anyone — AWAS staff will never ask for it.
If you did not request this, you can safely ignore this email; your password stays the same.</p>
<p>— Barangay Adlay AWAS</p>
