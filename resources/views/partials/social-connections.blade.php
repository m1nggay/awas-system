{{-- Profile → Security: connect / disconnect Google and Facebook sign-in. Needs $user. Shown only for providers set up in .env. --}}
@php $socials = \App\Http\Controllers\Auth\SocialLoginController::enabled(); @endphp
@if ($socials)
  <div class="social-links">
    <div class="pf-label mb-2">Sign in with</div>
    @foreach ($socials as $key => $name)
      @php $linked = !empty($user->{$key . '_id'}); @endphp
      <div class="social-link-row">
        <span class="d-flex align-items-center gap-2">
          <i class="{{ $key === 'facebook' ? 'ph-fill ph-facebook-logo' : 'ph ph-google-logo' }}" style="font-size:20px;color:{{ $key === 'facebook' ? '#1877f2' : '#4285f4' }};" aria-hidden="true"></i>
          <strong>{{ $name }}</strong>
          @if ($linked)<span class="pf-pill">Connected</span>@endif
        </span>
        @if ($linked)
          <form method="POST" action="{{ route('social.unlink', $key) }}" data-confirm="Disconnect {{ $name }}? You will sign in with your username and password.">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">Disconnect</button>
          </form>
        @else
          <a href="{{ route('social.redirect', [$key, 'link' => 1]) }}" class="btn btn-outline btn-sm">Connect</a>
        @endif
      </div>
    @endforeach
  </div>
  <style>
    .social-links{margin-top:18px;padding-top:16px;border-top:1px solid var(--pf-border,#e3edf3);}
    .social-link-row{display:flex;justify-content:space-between;align-items:center;gap:10px;min-height:44px;}
    .social-link-row + .social-link-row{border-top:1px dashed var(--pf-border,#e3edf3);}
  </style>
@endif
