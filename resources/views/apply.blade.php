@extends('layouts.auth', ['video' => true, 'cardStyle' => 'max-width:680px;'])

@section('title', 'Apply for Membership')

@push('styles')
<style>
  .apply-section{margin:22px 0 6px;padding-top:16px;border-top:1px solid var(--border);}
  .apply-section:first-of-type{border-top:0;margin-top:6px;padding-top:0;}
  .apply-section h2{font-size:15px;color:var(--primary-dark);margin-bottom:10px;}
  .hint{font-size:12px;color:var(--text-muted);margin-top:4px;}
  .cam-stage{margin-top:10px;text-align:center;}
  .cam-stage video,.cam-stage img{max-width:100%;max-height:280px;border-radius:10px;background:#0b1a22;}
  .cam-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
  .cam-status{font-size:12px;color:var(--text-muted);margin-top:6px;}
  .apply-note{background:#f0fbff;border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12.5px;margin-bottom:10px;}
  .liveness-stage{position:relative;max-width:360px;margin:10px auto 0;border-radius:12px;overflow:hidden;background:#0b1a22;}
  .liveness-stage video{width:100%;display:block;transform:scaleX(-1);}
  .liveness-guide{position:absolute;left:50%;top:50%;width:58%;height:78%;transform:translate(-50%,-50%);
    border:3px dashed rgba(255,255,255,.85);border-radius:50%;box-shadow:0 0 0 999px rgba(0,0,0,.35);transition:border-color .2s;}
  .liveness-guide.ok{border-color:#2ecc71;border-style:solid;}
  .liveness-prompt{text-align:center;font-weight:600;margin-top:8px;min-height:22px;}
  .liveness-prompt.good{color:#1a7a68;} .liveness-prompt.bad{color:#b8382a;}
  #livenessResult{display:block;max-width:200px;margin:8px auto 0;border-radius:10px;border:2px solid #2ecc71;}
</style>
@endpush

@section('content')
  <h1>Apply for Membership</h1>
  <p class="subtitle">Don't have a water account yet? Fill in the form below. We'll email you a code to verify your email, then the barangay water office will review your application.</p>

  @if ($errors->any())
    <div class="alert alert-danger">
      @foreach ($errors->all() as $err){{ $err }}<br>@endforeach
      @if (session('filesDiscarded'))<span style="font-size:12px;">Your valid ID photo and face check were not kept — please attach the ID and do the face check again.</span>@endif
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
      <div class="row g-3">
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
      <div class="row g-3">
        <div class="col-md-6 mb-3">
          <label for="contact_number" class="form-label">Contact Number *</label>
          <input type="text" id="contact_number" name="contact_number" class="form-control" required maxlength="20" inputmode="tel" value="{{ old('contact_number') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="email" class="form-label">Email Address *</label>
          <input type="email" id="email" name="email" class="form-control" required maxlength="150" value="{{ old('email') }}">
        </div>
      </div>
      <div class="mb-3">
        <label for="address" class="form-label">Complete Address (house no., street, subdivision) *</label>
        <input type="text" id="address" name="address" class="form-control" required maxlength="255" value="{{ old('address') }}">
      </div>
      <div class="row g-3">
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
          <label for="barangay" class="form-label">Barangay *</label>
          <input type="text" id="barangay" name="barangay" class="form-control" required maxlength="100" value="{{ old('barangay', $defaultBarangay) }}">
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-6 mb-3">
          <label for="municipality" class="form-label">Municipality *</label>
          <input type="text" id="municipality" name="municipality" class="form-control" required maxlength="100" value="{{ old('municipality') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="province" class="form-label">Province *</label>
          <input type="text" id="province" name="province" class="form-control" required maxlength="100" value="{{ old('province') }}">
        </div>
      </div>
    </div>

    <div class="apply-section mb-4">
      <h2>2. Account Information</h2>
      <div class="apply-note">Your email address is your login.</div>
      <div class="row g-3">
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
      <div class="row g-3">
        <div class="col-md-6 mb-3">
          <label for="household_number" class="form-label">Household Number (if applicable)</label>
          <input type="text" id="household_number" name="household_number" class="form-control" maxlength="30" value="{{ old('household_number') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="household_members" class="form-label">Number of Household Members *</label>
          <input type="number" id="household_members" name="household_members" class="form-control" required min="1" max="50" value="{{ old('household_members') }}">
        </div>
      </div>
      <div class="row g-3">
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
      <h2>4. Valid ID Photo</h2>
      <div class="apply-note">Upload (or take) one clear photo of a valid government-issued ID. Make sure all important information is readable.</div>
      <div class="mb-3">
        <label for="id_type" class="form-label">Type of ID *</label>
        <select id="id_type" name="id_type" class="form-select" required>
          <option value="">Select the government-issued ID you are submitting</option>
          @foreach (\App\Models\MembershipApplication::ID_TYPES as $t)
            <option value="{{ $t }}" @selected(old('id_type') === $t)>{{ $t }}</option>
          @endforeach
        </select>
      </div>
      <div class="cam-widget" data-facing="environment">
        <label for="id_file">Valid ID Photo *</label>
        <input type="file" id="id_file" name="id_file" class="form-control" accept="image/jpeg,image/png,image/webp" data-role="file">
        <div class="hint">JPG, PNG or WEBP, up to 5 MB. Your ID is stored privately and only seen by barangay water office administrators.</div>
        <input type="hidden" name="id_capture" data-role="data">
        <div class="cam-actions">
          <button type="button" class="btn btn-outline btn-sm" data-role="open">📷 Use camera instead</button>
          <button type="button" class="btn btn-success btn-sm" data-role="snap" hidden>Capture photo</button>
          <button type="button" class="btn btn-secondary btn-sm" data-role="retake" hidden>Retake</button>
          <button type="button" class="btn btn-secondary btn-sm" data-role="close" hidden>Cancel</button>
        </div>
        <div class="cam-stage" data-role="stage" hidden>
          <video data-role="video" playsinline muted hidden></video>
          <img data-role="preview" alt="Preview of your ID photo" hidden>
        </div>
        <div class="cam-status" data-role="status" aria-live="polite"></div>
      </div>
    </div>

    <div class="apply-section mb-4">
      <h2>5. Face Verification</h2>
      <div class="apply-note">A quick live check that a real person is applying: position your face inside the guide, then blink when asked. Use good lighting and remove sunglasses or masks.</div>
      <div id="liveness" class="liveness">
        <input type="hidden" name="selfie_capture" id="selfie_capture">
        <input type="hidden" name="liveness_passed" id="liveness_passed" value="0">
        <div class="liveness-stage" id="livenessStage" hidden>
          <video id="livenessVideo" playsinline muted></video>
          <div class="liveness-guide" id="livenessGuide"></div>
        </div>
        <div class="liveness-prompt" id="livenessPrompt" aria-live="polite"></div>
        <img id="livenessResult" alt="Face captured during the blink check" hidden>
        <div class="cam-actions">
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
<script src="{{ asset('assets/js/camera_capture.js') }}"></script>
<script type="module" src="{{ asset('assets/js/liveness.js') }}?v={{ @filemtime(public_path('assets/js/liveness.js')) ?: '1' }}"></script>
<script>
// Friendly client-side checks so nobody uploads a large file only to be
// told it was rejected. The server re-validates everything.
document.getElementById('applyForm').addEventListener('submit', function (ev) {
  var msgs = [];
  var idFile = document.getElementById('id_file');
  var hasIdCapture = this.querySelector('[name="id_capture"]').value !== '';
  var livenessPassed = document.getElementById('liveness_passed').value === '1';
  var inPerson = document.getElementById('verify_in_person').checked;
  var allowed = ['image/jpeg', 'image/png', 'image/webp'];

  if (idFile.files.length) {
    if (allowed.indexOf(idFile.files[0].type) === -1) msgs.push('The Valid ID must be a JPG, PNG or WEBP image.');
    else if (idFile.files[0].size > 5 * 1024 * 1024) msgs.push('The Valid ID photo is larger than 5 MB.');
  } else if (!hasIdCapture) {
    msgs.push('Please upload or capture a photo of your Valid ID.');
  }
  if (!livenessPassed && !inPerson) {
    msgs.push('Please complete the face verification (blink when asked), or tick "I will verify my face in person".');
  }
  var pw = this.querySelector('[name="password"]').value;
  if (!(pw.length >= 8 && /[A-Za-z]/.test(pw) && /\d/.test(pw) && /[^A-Za-z0-9]/.test(pw))) {
    msgs.push('Password must contain at least 8 characters, including letters, numbers, and symbols.');
  } else if (pw !== this.querySelector('[name="confirm_password"]').value) msgs.push('Passwords do not match.');

  if (msgs.length) {
    ev.preventDefault();
    alert(msgs.join('\n'));
  }
});
</script>
@endpush
