@extends('layouts.auth', ['video' => true, 'cardStyle' => 'max-width:680px;'])

@section('title', 'Apply for Membership')

@push('styles')
<style>
  .apply-section{margin:22px 0 6px;padding-top:16px;border-top:1px solid var(--border);}
  .apply-section:first-of-type{border-top:0;margin-top:6px;padding-top:0;}
  .apply-section h2{font-size:15px;color:var(--primary-dark);margin-bottom:10px;}
  .hint{font-size:12px;color:var(--text-muted);margin-top:4px;}
  .cam-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
  .cam-status{font-size:12.5px;color:var(--text-muted);margin-top:6px;}
  .cam-status.good{color:#1a7a68;font-weight:600;} .cam-status.warn{color:#9a6a00;font-weight:600;} .cam-status.error{color:#b8382a;font-weight:600;}
  .apply-note{background:#f0fbff;border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12.5px;margin-bottom:10px;}
  .form-control[readonly]{background:#eef4f7;color:var(--text-muted);cursor:not-allowed;}

  /* Valid ID — landscape card guide */
  .id-widget{border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:12px;}
  .id-widget > label{font-weight:600;font-size:14px;}
  .id-stage{position:relative;margin:10px auto 0;border-radius:10px;overflow:hidden;background:#0b1a22;}
  .id-stage video{display:block;width:100%;height:auto;}
  .id-guide{position:absolute;border:3px dashed rgba(255,255,255,.9);border-radius:12px;box-shadow:0 0 0 999px rgba(0,0,0,.45);pointer-events:none;}
  .id-preview{display:block;width:100%;max-width:420px;margin:10px auto 0;border-radius:10px;border:3px solid var(--border);}
  .id-preview.good{border-color:#2ecc71;} .id-preview.warn{border-color:#e0a93b;} .id-preview.error{border-color:#e5533d;opacity:.6;}

  /* Face verification — portrait only */
  .liveness-stage{position:relative;width:min(300px,100%);aspect-ratio:3/4;margin:10px auto 0;border-radius:12px;overflow:hidden;background:#0b1a22;}
  .liveness-stage video{width:100%;height:100%;object-fit:cover;display:block;transform:scaleX(-1);}
  .liveness-guide{position:absolute;left:50%;top:50%;width:68%;height:70%;transform:translate(-50%,-50%);
    border:3px dashed rgba(255,255,255,.85);border-radius:50%;box-shadow:0 0 0 999px rgba(0,0,0,.35);transition:border-color .2s;}
  .liveness-guide.ok{border-color:#2ecc71;border-style:solid;}
  .liveness-prompt{text-align:center;font-weight:600;margin-top:8px;min-height:22px;}
  .liveness-prompt.good{color:#1a7a68;} .liveness-prompt.bad{color:#b8382a;}
  #livenessResult{display:block;width:150px;aspect-ratio:3/4;object-fit:cover;margin:8px auto 0;border-radius:10px;border:2px solid #2ecc71;}
</style>
@endpush

@section('content')
  <h1>Apply for Membership</h1>
  <p class="subtitle">Don't have a water account yet? Fill in the form below. We'll email you a code to verify your email, then the barangay water office will review your application.</p>

  @if ($errors->any())
    <div class="alert alert-danger">
      @foreach ($errors->all() as $err){{ $err }}<br>@endforeach
      @if (session('filesDiscarded'))<span style="font-size:12px;">Your ID photos and face check were not kept — please capture the front and back of your ID and do the face check again.</span>@endif
    </div>
  @endif

  <form method="POST" action="{{ route('apply.store') }}" enctype="multipart/form-data" id="applyForm">
    @csrf

    <div class="apply-section mb-4">
      <h2>1. Personal Information</h2>
      <div class="mb-3">
        <label for="full_name" class="form-label">Full Name *</label>
        <input type="text" id="full_name" name="full_name" class="form-control" required maxlength="150" value="{{ old('full_name') }}">
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="birth_date" class="form-label">Date of Birth *</label>
          <input type="date" id="birth_date" name="birth_date" class="form-control" required max="{{ date('Y-m-d') }}" value="{{ old('birth_date') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="sex" class="form-label">Sex *</label>
          <select id="sex" name="sex" class="form-select" required>
            <option value="">Select</option>
            <option value="male" @selected(old('sex') === 'male')>Male</option>
            <option value="female" @selected(old('sex') === 'female')>Female</option>
          </select>
        </div>
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="contact_number" class="form-label">Contact Number *</label>
          <input type="text" id="contact_number" name="contact_number" class="form-control" required maxlength="20" inputmode="tel" value="{{ old('contact_number') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="email" class="form-label">Email Address *</label>
          <input type="email" id="email" name="email" class="form-control" required maxlength="150" value="{{ old('email') }}">
          <div class="hint">We'll send a verification code here.</div>
        </div>
      </div>

      <label class="form-label" style="font-weight:600;">Complete Address *</label>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="purok_id" class="form-label">Purok *</label>
          <select id="purok_id" name="purok_id" class="form-select" required>
            <option value="">Select purok</option>
            @foreach ($puroks as $pk)
              <option value="{{ $pk->purok_id }}" @selected((string)old('purok_id') === (string)$pk->purok_id)>{{ $pk->purok_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label for="barangay" class="form-label">Barangay</label>
          <input type="text" id="barangay" class="form-control" value="{{ $barangay }}" readonly tabindex="-1">
        </div>
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="municipality" class="form-label">Municipality</label>
          <input type="text" id="municipality" class="form-control" value="{{ $municipality }}" readonly tabindex="-1">
        </div>
        <div class="col-md-6 mb-3">
          <label for="province" class="form-label">Province</label>
          <input type="text" id="province" class="form-control" value="{{ $province }}" readonly tabindex="-1">
        </div>
      </div>
    </div>

    <div class="apply-section mb-4">
      <h2>2. Account Information</h2>
      <div class="apply-note">You will log in with your <strong>username</strong> and password.</div>
      <div class="mb-3">
        <label for="username" class="form-label">Username *</label>
        <input type="text" id="username" name="username" class="form-control" required minlength="4" maxlength="30"
               pattern="[A-Za-z0-9._\-]{4,30}" autocomplete="username" autocapitalize="none" spellcheck="false" value="{{ old('username') }}"
               title="4–30 characters: letters, numbers, dots, dashes or underscores — no spaces">
        <div class="hint">4–30 characters: letters, numbers, dots (.), dashes (-) or underscores (_). No spaces.</div>
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="password" class="form-label">Password *</label>
          <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <div class="col-md-6 mb-3">
          <label for="confirm_password" class="form-label">Confirm Password *</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
      </div>
      @include('partials.password-hint', ['for' => 'password'])
    </div>

    <div class="apply-section mb-4">
      <h2>3. Household Information</h2>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="household_number" class="form-label">Household Number (if applicable)</label>
          <input type="text" id="household_number" name="household_number" class="form-control" maxlength="30" value="{{ old('household_number') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="household_members" class="form-label">Number of Household Members *</label>
          <select id="household_members" name="household_members" class="form-select" required>
            <option value="">Select</option>
            @for ($n = 1; $n <= 30; $n++)
              <option value="{{ $n }}" @selected((string)old('household_members') === (string)$n)>{{ $n }} {{ $n === 1 ? 'person' : 'persons' }}</option>
            @endfor
          </select>
        </div>
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="residence_type" class="form-label d-flex align-items-center gap-1">
            Type of Residence *
            <button type="button" class="btn btn-link p-0 ms-auto" style="font-size:14px;line-height:1;text-decoration:none;"
                    data-bs-toggle="collapse" data-bs-target="#residenceHelp" aria-expanded="false" aria-controls="residenceHelp"
                    title="What do these mean?">ⓘ</button>
          </label>
          <select id="residence_type" name="residence_type" class="form-select" required>
            <option value="">Select</option>
            @foreach (\App\Models\MembershipApplication::RESIDENCE_TYPES as $value => $label)
              <option value="{{ $value }}" @selected(old('residence_type') === $value)>{{ $label }}</option>
            @endforeach
          </select>
          <div class="collapse" id="residenceHelp">
            <div class="apply-note mt-2" style="font-size:12px;">
              @foreach (\App\Models\MembershipApplication::RESIDENCE_HELP as $value => $text)
                <div><strong>{{ \App\Models\MembershipApplication::RESIDENCE_TYPES[$value] }}</strong> – {{ $text }}</div>
              @endforeach
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          @include('partials.consumer-type-select', ['selected' => old('consumer_type', 'residential')])
        </div>
      </div>
    </div>

    <div class="apply-section mb-4">
      <h2>4. Valid ID (Front and Back)</h2>
      <div class="apply-note">Take a clear <strong>landscape (horizontal)</strong> photo of the <strong>front</strong> and the <strong>back</strong> of one valid government-issued ID. Place the whole card inside the frame in good light so all details are readable. Your ID is stored privately and only seen by barangay water office administrators.</div>
      <div class="mb-3">
        <label for="id_type" class="form-label">Type of ID *</label>
        <select id="id_type" name="id_type" class="form-select" required>
          <option value="">Select the government-issued ID you are submitting</option>
          @foreach (\App\Models\MembershipApplication::ID_TYPES as $t)
            <option value="{{ $t }}" @selected(old('id_type') === $t)>{{ $t }}</option>
          @endforeach
        </select>
      </div>

      @foreach (['front' => ['Front of ID', 'id_file', 'id_capture'], 'back' => ['Back of ID', 'id_back_file', 'id_back_capture']] as $side => [$title, $fileName, $captureName])
        <div class="id-widget" data-id-widget data-side="{{ $side }}">
          <label for="{{ $fileName }}">{{ $title }} *</label>
          <div class="cam-actions">
            <button type="button" class="btn btn-primary btn-sm" data-role="open">📷 Capture {{ strtolower($title) }}</button>
            <button type="button" class="btn btn-success btn-sm" data-role="snap" hidden>Capture</button>
            <button type="button" class="btn btn-secondary btn-sm" data-role="retake" hidden>Retake</button>
            <button type="button" class="btn btn-secondary btn-sm" data-role="close" hidden>Cancel</button>
          </div>
          <div class="id-stage" data-role="stage" hidden>
            <video data-role="video" playsinline muted></video>
            <div class="id-guide" data-role="guide"></div>
          </div>
          <img class="id-preview" data-role="preview" alt="Preview of the {{ strtolower($title) }}" hidden>
          <div class="cam-status" data-role="status" aria-live="polite"></div>
          <div class="hint mt-2">Or upload a landscape photo (JPG, PNG or WEBP, up to 5 MB):</div>
          <input type="file" id="{{ $fileName }}" name="{{ $fileName }}" class="form-control form-control-sm mt-1" accept="image/jpeg,image/png,image/webp" data-role="file">
          <input type="hidden" name="{{ $captureName }}" data-role="data">
        </div>
      @endforeach
    </div>

    <div class="apply-section mb-4">
      <h2>5. Face Verification</h2>
      <div class="apply-note">A quick live check that a real person is applying: position your face inside the oval, then blink when asked. The photo is taken in <strong>portrait</strong>. Use good lighting and remove sunglasses or masks.</div>
      <div id="liveness" class="liveness">
        <input type="hidden" name="selfie_capture" id="selfie_capture">
        <input type="hidden" name="liveness_passed" id="liveness_passed" value="0">
        <div class="liveness-stage" id="livenessStage" hidden>
          <video id="livenessVideo" playsinline muted></video>
          <div class="liveness-guide" id="livenessGuide"></div>
        </div>
        <div class="liveness-prompt" id="livenessPrompt" aria-live="polite"></div>
        <img id="livenessResult" alt="Face captured during the blink check" hidden>
        <div class="cam-actions" style="justify-content:center;">
          <button type="button" class="btn btn-outline btn-sm" id="livenessStart">📷 Start face verification</button>
          <button type="button" class="btn btn-secondary btn-sm" id="livenessRetry" hidden>Try again</button>
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="verify_in_person" name="verify_in_person" value="1" @checked(old('verify_in_person'))>
          <label class="form-check-label" for="verify_in_person" style="font-size:12.5px;">I can't use a camera — I will verify my face in person at the barangay water office.</label>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100" style="margin-top:18px;">Submit &amp; Verify Email</button>
  </form>

  <div class="auth-footer-link">
    Already have a water account? <a href="{{ route('register') }}">Create your online login</a>
  </div>
  <div class="auth-footer-link">
    Already applied? <a href="{{ route('login') }}">Log in to check your status</a>
  </div>
  <div class="auth-footer-link">
    <a href="{{ route('home') }}">&larr; Back to Home</a>
  </div>
@endsection

@push('scripts')
<script type="module" src="{{ asset('assets/js/id_capture.js') }}?v={{ @filemtime(public_path('assets/js/id_capture.js')) ?: '1' }}"></script>
<script type="module" src="{{ asset('assets/js/liveness.js') }}?v={{ @filemtime(public_path('assets/js/liveness.js')) ?: '1' }}"></script>
<script>
// Friendly client-side checks so nobody waits for an upload only to be told
// something is missing. The server re-validates everything.
document.getElementById('applyForm').addEventListener('submit', function (ev) {
  var form = this, msgs = [], warnings = [];

  form.querySelectorAll('[data-id-widget]').forEach(function (w) {
    var side = w.getAttribute('data-side') === 'back' ? 'BACK' : 'FRONT';
    var hasPhoto = w.querySelector('[data-role="data"]').value !== '' || w.querySelector('[data-role="file"]').files.length > 0;
    if (!hasPhoto) msgs.push('Please capture or upload a valid photo of the ' + side + ' of your ID.');
    else if (w.dataset.warn === '1') warnings.push('The ' + side + ' of your ID may not show a valid ID.');
  });

  var livenessPassed = document.getElementById('liveness_passed').value === '1';
  if (!livenessPassed && !document.getElementById('verify_in_person').checked) {
    msgs.push('Please complete the face verification (blink when asked), or tick "I will verify my face in person".');
  }
  if (!/^[A-Za-z0-9._-]{4,30}$/.test(form.querySelector('[name="username"]').value)) {
    msgs.push('Username must be 4–30 characters: letters, numbers, dots, dashes or underscores, with no spaces.');
  }
  var pw = form.querySelector('[name="password"]').value;
  if (!(pw.length >= 8 && /[A-Z]/.test(pw) && /\d/.test(pw) && /[^A-Za-z0-9]/.test(pw))) {
    msgs.push('Password must contain at least 8 characters, including an uppercase letter, a number, and a symbol.');
  } else if (pw !== form.querySelector('[name="confirm_password"]').value) msgs.push('Passwords do not match.');

  if (msgs.length) {
    ev.preventDefault();
    alert(msgs.join('\n'));
    return;
  }
  if (warnings.length && !confirm(warnings.join('\n') + '\n\nPlease use a valid government-issued ID. Submit anyway?')) {
    ev.preventDefault();
  }
});
</script>
@endpush
