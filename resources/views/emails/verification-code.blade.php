<p>{{ __('Hi :name,', ['name' => $name]) }}</p>
<p>{{ $intro }}</p>
<p style="font-size:28px;font-weight:700;letter-spacing:6px;">{{ $code }}</p>
<p>{{ __('This code expires in :ttl minutes.', ['ttl' => $ttl]) }}</p>
@if ($next)
<p><strong>{{ __('What to do next:') }}</strong> {{ $next }}</p>
@endif
<p>{{ __('If you did not request this, you can safely ignore this email.') }}</p>
