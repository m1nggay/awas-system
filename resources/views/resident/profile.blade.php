@extends('layouts.app')

@section('title', 'My Profile')

@push('styles')
@include('partials.profile-styles')
@endpush

@section('content')
@php
  $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($user->full_name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join(''));
  $active = $consumer && $consumer->status === 'active';
  // One address line with the purok first, e.g. "Purok 1, Adlay, Carrascal, Surigao del Sur".
  $address = '';
  if ($consumer) {
      $addr = preg_replace('/\bDel\b/', 'del', trim((string)$consumer->address));
      if ($addr !== '' && !str_contains($addr, ',')) {
          $addr = preg_replace('/^(.*?)\s+(Carrascal)\s+(Surigao del Sur)$/i', '$1, $2, $3', $addr);
      }
      $address = collect([stripos($addr, 'purok') === false ? $consumer->purok_name : null, $addr])->filter()->join(', ');
  }
  // The contact number as typed after "+63", e.g. 985 588 5588.
  $mobile = preg_replace('/^0/', '', (string)$user->contact_number);
  $mobile = preg_match('/^9\d{9}$/', $mobile) ? substr($mobile, 0, 3) . ' ' . substr($mobile, 3, 3) . ' ' . substr($mobile, 6) : $mobile;
  $editingName = $errors->any() && old('full_name') !== null;
  $maskedEmail = $user->email ? preg_replace('/(?<=^.).*(?=@)/', '•••', $user->email) : '';
@endphp

<div class="pf">
@include('partials.errors')

{{-- 1. Banner --}}
<div class="pf-card pf-banner">
  <div class="pf-avatar">
    @if ($user->profile_photo)
      <img src="{{ route('account.photo', ['v' => $user->profile_photo]) }}" alt="Your profile picture" class="pic">
    @else
      <div class="pic" aria-hidden="true">{{ $initials }}</div>
    @endif
    <div class="dropdown">
      <button type="button" class="pf-cam" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Change or remove your photo">
        <i class="ph ph-camera" aria-hidden="true"></i>
      </button>
      <div class="dropdown-menu pf-photo-menu">
        <form method="POST" action="{{ route('account.photo.update') }}" enctype="multipart/form-data" id="photoForm">
          @csrf
          <label class="dropdown-item mb-0" for="photoInput" style="cursor:pointer;"><i class="ph ph-upload-simple me-2" aria-hidden="true"></i>Change photo</label>
          <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
          <div class="hint">JPG, PNG or WEBP, up to 5 MB</div>
        </form>
        @if ($user->profile_photo)
          <div class="dropdown-divider"></div>
          <form method="POST" action="{{ route('account.photo.remove') }}" data-confirm="Remove your profile picture?">
            @csrf @method('DELETE')
            <button type="submit" class="dropdown-item text-danger"><i class="ph ph-trash me-2" aria-hidden="true"></i>Remove photo</button>
          </form>
        @endif
      </div>
    </div>
  </div>

  <div class="pf-who">
    <div id="nameView" class="{{ $editingName ? 'd-none' : '' }}">
      <h2 class="pf-name">{{ $user->full_name }}</h2>
      <button type="button" class="pf-edit-link mt-1" id="editNameBtn"><i class="ph ph-pencil-simple" aria-hidden="true"></i> Edit name</button>
    </div>
    <form method="POST" action="{{ route('account.name') }}" class="pf-name-form {{ $editingName ? '' : 'd-none' }}" id="nameForm">
      @csrf
      <label for="full_name" class="visually-hidden">Full name</label>
      <input type="text" id="full_name" name="full_name" class="form-control" style="max-width:300px;" maxlength="150" required
             value="{{ old('full_name', $user->full_name) }}" autocomplete="name">
      <button type="submit" class="btn btn-primary btn-sm">Save name</button>
      <button type="button" class="btn btn-outline btn-sm" id="cancelNameBtn">Cancel</button>
      <div class="w-100 text-muted" style="font-size:11.5px;">This is also the name on your water account, bills and receipts.</div>
    </form>
  </div>

  @if ($consumer)
    <div class="pf-summary">
      <div><div class="pf-label">Meter Number</div><div class="pf-value">{{ $consumer->meter_number ?: '—' }}</div></div>
      <div><div class="pf-label">Type of Consumer</div><div class="pf-value">{{ consumerTypeLabel($consumer->consumer_type) }}</div></div>
      <div><div class="pf-label">Status</div><span class="pf-pill {{ $active ? '' : 'off' }}">{{ ucfirst($consumer->status) }}</span></div>
    </div>
  @endif
</div>

{{-- 2. Two columns --}}
<div class="pf-grid">
  <div class="pf-col">
    <div class="pf-card">
      <h3 class="pf-title"><i class="ph ph-identification-card" aria-hidden="true"></i>Account Information</h3>
      @if ($consumer)
        {{-- Meter number, type and status are in the banner; these are the rest of the water account. --}}
        <div class="pf-info">
          <div class="wide"><div class="pf-label">Address</div><div class="pf-value">{{ $address ?: '—' }}</div></div>
          <div><div class="pf-label">Connected Since</div><div class="pf-value">{{ $consumer->connection_date ? formatDate($consumer->connection_date) : '—' }}</div></div>
          <div><div class="pf-label">Senior Citizen</div><div class="pf-value">{{ $consumer->is_senior ? 'Yes — ' . rtrim(rtrim((string)setting('senior_discount_percent', 20), '0'), '.') . '% discount' : 'No' }}</div></div>
          @if ($consumer->household_number)
            <div><div class="pf-label">Household No.</div><div class="pf-value">{{ $consumer->household_number }}</div></div>
          @endif
          <div><div class="pf-label">Account Holder Since</div><div class="pf-value">{{ formatDate($user->created_at) }}</div></div>
        </div>
      @else
        <p class="text-muted mb-0">Your login is not linked to a water account yet.</p>
      @endif
    </div>
  </div>

  <div class="pf-col">
    <div class="pf-card">
      <h3 class="pf-title"><i class="ph ph-phone" aria-hidden="true"></i>Contact Info</h3>
      @if ($pending)
        {{-- A new email waits for the code sent to the current one. --}}
        <p class="mb-2" style="font-size:14px;">We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>. Enter it to change your email to <strong>{{ $pending['email'] }}</strong>.</p>
        <form method="POST" action="{{ route('resident.profile.contact.confirm') }}" class="d-flex flex-wrap gap-2 align-items-center">
          @csrf
          <label for="otp" class="visually-hidden">6-digit code</label>
          <input type="text" id="otp" name="otp" class="form-control pf-code" inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" required autofocus>
          <button type="submit" class="btn btn-primary">Confirm</button>
        </form>
        <div class="d-flex flex-wrap gap-3 mt-3 align-items-center">
          <form method="POST" action="{{ route('resident.profile.contact.resend') }}">
            @csrf
            <button type="submit" class="pf-edit-link" @disabled($resendIn > 0) data-resend-in="{{ $resendIn }}">Resend code{{ $resendIn > 0 ? " ({$resendIn}s)" : '' }}</button>
          </form>
          <form method="POST" action="{{ route('resident.profile.contact.cancel') }}">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">Cancel change</button>
          </form>
        </div>
      @else
        <form method="POST" action="{{ route('resident.profile.contact') }}" id="contactForm">
          @csrf
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" autocomplete="email">
            @if ($user->email)<div class="text-muted" style="font-size:11.5px;margin-top:4px;">Changing it needs a code sent to your current email.</div>@endif
          </div>
          <div class="mb-3">
            <label class="form-label" for="contact_number">Contact Number</label>
            <div class="input-group">
              <span class="input-group-text">+63</span>
              <input type="tel" id="contact_number" name="contact_number" class="form-control" inputmode="numeric" maxlength="13"
                     placeholder="9XX XXX XXXX" value="{{ old('contact_number', $mobile) }}" autocomplete="tel-national">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" id="contactSave" disabled>Save Changes</button>
        </form>
      @endif
    </div>

    <div class="pf-card" id="security" style="scroll-margin-top:80px;">
      <h3 class="pf-title"><i class="ph ph-lock-key" aria-hidden="true"></i>Security</h3>
      @if (session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
      @if (session('success') && $pwStep !== 'request')<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

      @if ($pwStep === 'code')
        {{-- Step 2: the code from the email --}}
        <p class="mb-2" style="font-size:14px;">Enter the 6-digit code we sent to <strong>{{ $maskedEmail }}</strong>. It expires in {{ $pwTtl }} minutes.</p>
        <form method="POST" action="{{ route('account.password.verify') }}" class="d-flex flex-wrap gap-2 align-items-center">
          @csrf
          <input type="hidden" name="from" value="profile">
          <label for="pw_otp" class="visually-hidden">6-digit code</label>
          <input type="text" id="pw_otp" name="otp" class="form-control pf-code" inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" required autofocus>
          <button type="submit" class="btn btn-primary">Verify</button>
        </form>
        <form method="POST" action="{{ route('account.password.send-code') }}" class="mt-3">
          @csrf
          <input type="hidden" name="from" value="profile">
          <button type="submit" class="pf-edit-link" data-resend-in="{{ $pwResendIn }}">Resend code</button>
        </form>

      @elseif ($pwStep === 'new')
        {{-- Step 3: the new password --}}
        <form method="POST" action="{{ route('account.password.update') }}">
          @csrf
          <input type="hidden" name="from" value="profile">
          <div class="mb-3">
            <label class="form-label" for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password" autofocus>
            @include('partials.password-hint', ['for' => 'new_password'])
          </div>
          <div class="mb-3">
            <label class="form-label" for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-primary">Save password</button>
        </form>

      @else
        {{-- Step 1: send the code (opens right here) --}}
        <p class="text-muted mb-3" style="font-size:14px;">Changing your password requires a 6-digit code sent to your registered email.</p>
        <button type="button" class="btn btn-outline pf-link-btn" id="pwStart">Change password <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
        <div class="d-none" id="pwSend">
          @if ($user->email)
            <p class="mb-3" style="font-size:14px;">We'll email a 6-digit code to <strong>{{ $maskedEmail }}</strong>. After you enter it, you can choose a new password.</p>
            <div class="d-flex flex-wrap gap-2">
              <form method="POST" action="{{ route('account.password.send-code') }}">
                @csrf
                <input type="hidden" name="from" value="profile">
                <button type="submit" class="btn btn-primary">Send code</button>
              </form>
              <button type="button" class="btn btn-outline" id="pwCancel">Cancel</button>
            </div>
          @else
            <p class="text-danger mb-0" style="font-size:13px;">Add an email address in Contact Info first — the code is sent there.</p>
          @endif
        </div>
      @endif
      @include('partials.social-connections', ['user' => $user])
    </div>
  </div>
</div>
</div>

<script>
(function () {
  // Edit name: the form shows only after "Edit name".
  const view = document.getElementById('nameView'), form = document.getElementById('nameForm'), input = document.getElementById('full_name');
  const editing = on => { view.classList.toggle('d-none', on); form.classList.toggle('d-none', !on); if (on) { input.focus(); input.select(); } };
  document.getElementById('editNameBtn').addEventListener('click', () => editing(true));
  document.getElementById('cancelNameBtn').addEventListener('click', () => { input.value = input.defaultValue; editing(false); });

  // Photo: uploads as soon as one is chosen.
  document.getElementById('photoInput').addEventListener('change', function () {
    const f = this.files && this.files[0];
    if (!f) return;
    if (f.size > 5 * 1024 * 1024) { alert('The picture is larger than 5 MB. Please choose a smaller one.'); this.value = ''; return; }
    document.querySelector('label[for="photoInput"]').textContent = 'Uploading…';
    document.getElementById('photoForm').submit();
  });

  // Contact: "985 588 5588" as it is typed; Save only once something changed.
  const contact = document.getElementById('contactForm');
  if (contact) {
    const email = document.getElementById('email'), phone = document.getElementById('contact_number'), save = document.getElementById('contactSave');
    const fmt = v => {
      const d = v.replace(/\D/g, '').replace(/^(63|0)(?=9)/, '').slice(0, 10);
      return [d.slice(0, 3), d.slice(3, 6), d.slice(6)].filter(Boolean).join(' ');
    };
    const saved = { email: {{ Js::from(mb_strtolower((string)$user->email)) }}, phone: fmt({{ Js::from($mobile) }}) };
    phone.value = fmt(phone.value);
    const check = () => { save.disabled = email.value.trim().toLowerCase() === saved.email && fmt(phone.value) === saved.phone; };
    phone.addEventListener('input', () => { phone.value = fmt(phone.value); check(); });
    email.addEventListener('input', check);
    contact.addEventListener('submit', () => { save.disabled = true; save.textContent = 'Saving…'; });
    check();
  }

  // Resend countdowns (email change and password codes).
  document.querySelectorAll('[data-resend-in]').forEach(resend => {
    let left = parseInt(resend.dataset.resendIn, 10) || 0;
    const tick = () => {
      resend.disabled = left > 0;
      resend.textContent = left > 0 ? 'Resend code (' + left + 's)' : 'Resend code';
      if (left-- > 0) setTimeout(tick, 1000);
    };
    tick();
  });

  // Change password: "Change password" opens step 1 right in the Security card.
  const pwStart = document.getElementById('pwStart'), pwSend = document.getElementById('pwSend');
  if (pwStart && pwSend) {
    const open = on => { pwStart.classList.toggle('d-none', on); pwSend.classList.toggle('d-none', !on); };
    pwStart.addEventListener('click', () => open(true));
    document.getElementById('pwCancel')?.addEventListener('click', () => open(false));
  }
})();
</script>
@endsection
