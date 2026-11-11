@extends('layouts.auth', ['video' => true, 'cardStyle' => 'max-width:680px;'])

@section('title', __('Apply for Membership'))

@push('styles')
<style>
  .apply-section{margin:22px 0 6px;padding-top:16px;border-top:1px solid var(--border);}
  .apply-section:first-of-type{border-top:0;margin-top:6px;padding-top:0;}
  .apply-section h2{font-size:15px;color:var(--primary-dark);margin-bottom:10px;}
  .apply-section .step{display:block;font-size:11.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2px;}
  .hint{font-size:12px;color:#5b6d77;margin-top:4px;}
  .field-help{font-size:12px;color:#5b6d77;margin-top:4px;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;}
  .field-status{font-weight:600;}
  .field-status.ok{color:#1a7a68;} .field-status.bad{color:#c62828;}
  .cam-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:8px;}
  .cam-status{font-size:12.5px;color:var(--text-muted);margin-top:6px;}
  .cam-status.good{color:#1a7a68;font-weight:600;} .cam-status.warn{color:#9a6a00;font-weight:600;} .cam-status.error{color:#b8382a;font-weight:600;}
  .apply-note{background:#f0fbff;border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12.5px;margin-bottom:10px;}
  .address-line{display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-dark);background:#f4f8fb;border:1px solid var(--border);border-radius:8px;padding:8px 12px;margin-bottom:12px;}
  .address-line i{color:var(--primary);font-size:17px;}
  .is-invalid-block{border-color:#e5533d !important;box-shadow:0 0 0 3px rgba(229,83,61,.12);}

  /* Valid ID — landscape card guide */
  .id-widget{border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:12px;}
  .id-widget > label{font-weight:600;font-size:14px;}
  .id-stage{position:relative;margin:10px auto 0;border-radius:10px;overflow:hidden;background:#0b1a22;}
  .id-stage video{display:block;width:100%;height:auto;}
  .id-guide{position:absolute;border:3px dashed rgba(255,255,255,.9);border-radius:12px;box-shadow:0 0 0 999px rgba(0,0,0,.45);pointer-events:none;}
  .id-preview{display:block;width:100%;max-width:420px;margin:10px auto 0;border-radius:10px;border:3px solid var(--border);}
  .id-preview.good{border-color:#2ecc71;} .id-preview.warn{border-color:#e0a93b;} .id-preview.error{border-color:#e5533d;opacity:.6;}
  .file-pick{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;}   /* the styled button below opens it */
  .file-pick:focus-visible + .btn{outline:2px solid var(--primary-light);outline-offset:2px;}

  /* Face verification — portrait only */
  .liveness-stage{position:relative;width:min(300px,100%);aspect-ratio:3/4;margin:10px auto 0;border-radius:12px;overflow:hidden;background:#0b1a22;}
  .liveness-stage video{width:100%;height:100%;object-fit:cover;display:block;transform:scaleX(-1);}
  .liveness-guide{position:absolute;left:50%;top:50%;width:68%;height:70%;transform:translate(-50%,-50%);
    border:3px dashed rgba(255,255,255,.85);border-radius:50%;box-shadow:0 0 0 999px rgba(0,0,0,.35);transition:border-color .2s;}
  .liveness-guide.ok{border-color:#2ecc71;border-style:solid;}
  .liveness-prompt{text-align:center;font-weight:600;margin-top:8px;min-height:22px;}
  .liveness-prompt.good{color:#1a7a68;} .liveness-prompt.bad{color:#b8382a;}
  #livenessResult{display:block;width:120px;aspect-ratio:3/4;object-fit:cover;margin:8px auto 0;border-radius:10px;border:2px solid #2ecc71;}

  .consent{background:#f4f8fb;border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-top:16px;font-size:13px;}
  .consent .form-check-label{color:var(--text-dark);line-height:1.5;}
  .consent .form-check-input{border:1.5px solid #8aa2b0;}
  .consent .form-check-input:disabled{opacity:.55;}
  .consent .form-check-input:disabled ~ .form-check-label{opacity:1;}   /* the sentence and links stay readable */
  .consent a.doc-link{color:var(--primary);font-weight:600;text-decoration:underline;text-underline-offset:2px;}
  .consent a.doc-link:hover{color:var(--primary-dark);}
  .consent-read{display:flex;flex-wrap:wrap;gap:6px 14px;margin-top:8px;font-size:12px;color:#5b6d77;}
  .consent-read .done{color:#1a7a68;font-weight:600;}
  .doc-body{max-height:60vh;overflow-y:auto;font-size:14px;line-height:1.65;padding-right:4px;}
  .doc-body h1{font-size:20px;color:var(--primary-dark);} .doc-body h2{font-size:15.5px;color:var(--primary-dark);margin:18px 0 6px;}
  .doc-body ul{padding-left:20px;list-style:disc;}
  .doc-progress{font-size:12.5px;color:#5b6d77;}
  .doc-progress.done{color:#1a7a68;font-weight:600;}
</style>
@endpush

@section('content')
  @php
    // Errors keyed by field (MembershipApplicationController): highlight that field.
    $bad = fn (string $field) => $errors->has($field) ? ' is-invalid' : '';
  @endphp
  <h1>{{ __('Apply for Membership') }}</h1>
  <p class="subtitle">{{ __('Don\'t have a water account yet? Fill in the form below. We\'ll email you a code to verify your email, then the barangay water office will review your application.') }}</p>

  @if ($errors->any())
    <div class="alert alert-danger" id="applyErrors" tabindex="-1">
      @foreach ($errors->all() as $err){{ $err }}<br>@endforeach
      @if (session('filesDiscarded'))<span style="font-size:12px;" data-photos-lost>{{ __('Your ID photos and face check were not kept — please capture the front and back of your ID and do the face check again.') }}</span>@endif
      <span style="font-size:12px;" data-photos-kept hidden>{{ __('Your ID photos and face check are still here — you don\'t need to take them again.') }}</span>
    </div>
  @endif

  <form method="POST" action="{{ route('apply.store') }}" enctype="multipart/form-data" id="applyForm" data-loading-form
        @if ($errors->any()) data-has-errors @endif>
    @csrf

    <div class="apply-section mb-4">
      <span class="step">{{ __('Step :n of :total', ['n' => 1, 'total' => 5]) }}</span>
      <h2>{{ __('Personal Information') }}</h2>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="first_name" class="form-label">{{ __('First Name *') }}</label>
          <input type="text" id="first_name" name="first_name" class="form-control{{ $bad('first_name') }}" required maxlength="60"
                 autocomplete="given-name" placeholder="{{ __('e.g. Juan') }}" value="{{ old('first_name') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="middle_name" class="form-label">{{ __('Middle Name') }}</label>
          <input type="text" id="middle_name" name="middle_name" class="form-control{{ $bad('middle_name') }}" maxlength="60"
                 autocomplete="additional-name" placeholder="{{ __('e.g. Dizon') }}" value="{{ old('middle_name') }}">
        </div>
      </div>
      <div class="row gx-3">
        <div class="col-md-8 mb-3">
          <label for="last_name" class="form-label">{{ __('Last Name *') }}</label>
          <input type="text" id="last_name" name="last_name" class="form-control{{ $bad('last_name') }}" required maxlength="60"
                 autocomplete="family-name" placeholder="{{ __('e.g. Dela Cruz') }}" value="{{ old('last_name') }}">
        </div>
        <div class="col-md-4 mb-3">
          <label for="suffix" class="form-label">{{ __('Suffix') }}</label>
          <select id="suffix" name="suffix" class="form-select{{ $bad('suffix') }}">
            <option value="">{{ __('None') }}</option>
            @foreach (\App\Http\Controllers\MembershipApplicationController::SUFFIXES as $s)
              <option value="{{ $s }}" @selected(old('suffix') === $s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="birth_date" class="form-label">{{ __('Date of Birth *') }}</label>
          <input type="date" id="birth_date" name="birth_date" class="form-control{{ $bad('birth_date') }}" required max="{{ date('Y-m-d') }}" value="{{ old('birth_date') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="sex" class="form-label">{{ __('Sex *') }}</label>
          <select id="sex" name="sex" class="form-select{{ $bad('sex') }}" required>
            <option value="">{{ __('Select') }}</option>
            <option value="male" @selected(old('sex') === 'male')>{{ __('Male') }}</option>
            <option value="female" @selected(old('sex') === 'female')>{{ __('Female') }}</option>
          </select>
        </div>
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="contact_number" class="form-label">{{ __('Contact Number *') }}</label>
          <input type="tel" id="contact_number" name="contact_number" class="form-control{{ $bad('contact_number') }}" required maxlength="20"
                 inputmode="tel" autocomplete="tel" placeholder="09XX XXX XXXX" value="{{ old('contact_number') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="email" class="form-label">{{ __('Email Address *') }}</label>
          <input type="email" id="email" name="email" class="form-control{{ $bad('email') }}" required maxlength="150"
                 autocomplete="email" placeholder="{{ __('you@email.com') }}" value="{{ old('email') }}">
          <div class="hint">{{ __('We\'ll send a verification code here.') }}</div>
        </div>
      </div>

      <label class="form-label" style="font-weight:600;">{{ __('Complete Address *') }}</label>
      <div class="address-line"><i class="ph ph-map-pin" aria-hidden="true"></i> {{ __('Barangay :barangay, :municipality, :province', ['barangay' => $barangay, 'municipality' => $municipality, 'province' => $province]) }}</div>
      <div class="row gx-3">
        <div class="col-md-5 mb-3">
          <label for="purok_id" class="form-label">{{ __('Purok *') }}</label>
          <select id="purok_id" name="purok_id" class="form-select{{ $bad('purok_id') }}" required>
            <option value="">{{ __('Select purok') }}</option>
            @foreach ($puroks as $pk)
              <option value="{{ $pk->purok_id }}" @selected((string)old('purok_id') === (string)$pk->purok_id)>{{ $pk->purok_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-7 mb-3">
          <label for="landmark" class="form-label">{{ __('Landmark') }}</label>
          <input type="text" id="landmark" name="landmark" class="form-control{{ $bad('landmark') }}" maxlength="150"
                 placeholder="{{ __('e.g. near the chapel, blue gate') }}" value="{{ old('landmark') }}">
          <div class="hint">{{ __('Helps the meter reader find your house.') }}</div>
        </div>
      </div>
    </div>

    <div class="apply-section mb-4">
      <span class="step">{{ __('Step :n of :total', ['n' => 2, 'total' => 5]) }}</span>
      <h2>{{ __('Account Information') }}</h2>
      <div class="mb-3">
        <label for="username" class="form-label">{{ __('Username *') }}</label>
        <input type="text" id="username" name="username" class="form-control{{ $bad('username') }}" required maxlength="50"
               autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="username_help"
               data-username-check="{{ route('activate.username') }}" value="{{ old('username') }}">
        <div class="field-help" id="username_help">
          <span>{{ __('You\'ll use this to log in. Save it somewhere safe.') }}</span>
          <span class="field-status" data-username-status aria-live="polite"></span>
        </div>
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="password" class="form-label">{{ __('Password *') }}</label>
          <input type="password" id="password" name="password" class="form-control{{ $bad('password') }}" required minlength="8" autocomplete="new-password">
        </div>
        <div class="col-md-6 mb-3">
          <label for="confirm_password" class="form-label">{{ __('Confirm Password *') }}</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control{{ $bad('confirm_password') }}" required minlength="8"
                 autocomplete="new-password" data-match="password">
          <div class="field-help"><span class="field-status" data-match-status="confirm_password" aria-live="polite"></span></div>
        </div>
      </div>
      @include('partials.password-hint', ['for' => 'password'])
    </div>

    <div class="apply-section mb-4">
      <span class="step">{{ __('Step :n of :total', ['n' => 3, 'total' => 5]) }}</span>
      <h2>{{ __('Household Information') }}</h2>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="household_number" class="form-label">{{ __('Household Number (if applicable)') }}</label>
          <input type="text" id="household_number" name="household_number" class="form-control{{ $bad('household_number') }}" maxlength="30" value="{{ old('household_number') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="household_members" class="form-label">{{ __('Number of Household Members *') }}</label>
          <select id="household_members" name="household_members" class="form-select{{ $bad('household_members') }}" required>
            <option value="">{{ __('Select') }}</option>
            @for ($n = 1; $n <= 30; $n++)
              <option value="{{ $n }}" @selected((string)old('household_members') === (string)$n)>{{ $n === 1 ? __('1 person') : __(':n persons', ['n' => $n]) }}</option>
            @endfor
          </select>
        </div>
      </div>
      <div class="row gx-3">
        <div class="col-md-6 mb-3">
          <label for="residence_type" class="form-label d-flex align-items-center gap-1">
            {{ __('Type of Residence *') }}
            <button type="button" class="btn btn-link p-0 ms-auto" style="font-size:14px;line-height:1;text-decoration:none;"
                    data-bs-toggle="collapse" data-bs-target="#residenceHelp" aria-expanded="false" aria-controls="residenceHelp"
                    title="{{ __('What do these mean?') }}">ⓘ</button>
          </label>
          <select id="residence_type" name="residence_type" class="form-select{{ $bad('residence_type') }}" required>
            <option value="">{{ __('Select') }}</option>
            @foreach (\App\Models\MembershipApplication::RESIDENCE_TYPES as $value => $label)
              <option value="{{ $value }}" @selected(old('residence_type') === $value)>{{ __($label) }}</option>
            @endforeach
          </select>
          <div class="collapse" id="residenceHelp">
            <div class="apply-note mt-2" style="font-size:12px;">
              @foreach (\App\Models\MembershipApplication::RESIDENCE_HELP as $value => $text)
                <div><strong>{{ __(\App\Models\MembershipApplication::RESIDENCE_TYPES[$value]) }}</strong> – {{ __($text) }}</div>
              @endforeach
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          {{-- Starts blank so the type is a real choice, not a skipped default. --}}
          @include('partials.consumer-type-select', ['selected' => old('consumer_type', ''), 'blank' => true])
        </div>
      </div>
    </div>

    <div class="apply-section mb-4">
      <span class="step">{{ __('Step :n of :total', ['n' => 4, 'total' => 5]) }}</span>
      <h2>{{ __('Valid ID (Front and Back)') }}</h2>
      <div class="apply-note">
        {{ __('Take a clear') }} <strong>{{ __('landscape (horizontal)') }}</strong> {{ __('photo of the') }} <strong>{{ __('front') }}</strong> {{ __('and the') }} <strong>{{ __('back') }}</strong> {{ __('of one valid government-issued ID. Place the whole card inside the frame in good light so all details are readable. Your ID is stored privately and only seen by barangay water office administrators.') }}
        {{ __('Your ID and face photos are deleted :days days after your application is approved or rejected.', ['days' => config('agas.application_photo_days')]) }}
      </div>
      <div class="mb-3">
        <label for="id_type" class="form-label">{{ __('Type of ID *') }}</label>
        <select id="id_type" name="id_type" class="form-select{{ $bad('id_type') }}" required>
          <option value="">{{ __('Select the government-issued ID you are submitting') }}</option>
          @foreach (\App\Models\MembershipApplication::ID_TYPES as $t)
            <option value="{{ $t }}" @selected(old('id_type') === $t)>{{ __($t) }}</option>
          @endforeach
        </select>
      </div>

      @foreach (['front' => ['Front of ID', 'Capture front of ID', 'id_file', 'id_capture'], 'back' => ['Back of ID', 'Capture back of ID', 'id_back_file', 'id_back_capture']] as $side => [$title, $captureLabel, $fileName, $captureName])
        <div class="id-widget" data-id-widget data-side="{{ $side }}">
          <label for="{{ $fileName }}">{{ __($title) }} *</label>
          <div class="cam-actions">
            <button type="button" class="btn btn-primary btn-sm" data-role="open"><i class="ph ph-camera" aria-hidden="true"></i> {{ __($captureLabel) }}</button>
            <button type="button" class="btn btn-success btn-sm" data-role="snap" hidden>{{ __('Capture') }}</button>
            <button type="button" class="btn btn-secondary btn-sm" data-role="retake" hidden><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i> {{ __('Retake') }}</button>
            <button type="button" class="btn btn-secondary btn-sm" data-role="close" hidden>{{ __('Cancel') }}</button>
            {{-- The real file input is hidden; this button (a label) opens it, so it matches the form and translates. --}}
            <input type="file" id="{{ $fileName }}" name="{{ $fileName }}" class="file-pick" accept="image/jpeg,image/png,image/webp" data-role="file">
            <label for="{{ $fileName }}" class="btn btn-outline btn-sm mb-0" data-role="pick"><i class="ph ph-upload-simple" aria-hidden="true"></i> {{ __('Upload a photo') }}</label>
          </div>
          <div class="id-stage" data-role="stage" hidden>
            <video data-role="video" playsinline muted></video>
            <div class="id-guide" data-role="guide"></div>
          </div>
          <img class="id-preview" data-role="preview" alt="{{ __('Preview of the :side', ['side' => mb_strtolower(__($title))]) }}" hidden>
          <div class="cam-status" data-role="status" aria-live="polite"></div>
          <div class="hint mt-1" data-role="upload-hint">{{ __('Landscape photo, JPG, PNG or WEBP. Large phone photos are made smaller automatically.') }}</div>
          <input type="hidden" name="{{ $captureName }}" data-role="data">
        </div>
      @endforeach
    </div>

    <div class="apply-section mb-4" id="livenessSection">
      <span class="step">{{ __('Step :n of :total', ['n' => 5, 'total' => 5]) }}</span>
      <h2>{{ __('Face Verification') }}</h2>
      <div class="apply-note">
        {{ __('A quick live check that a real person is applying: position your face inside the oval, then blink when asked. The photo is taken in') }} <strong>{{ __('portrait') }}</strong>{{ __('. Use good lighting and remove sunglasses or masks.') }}
        {{ __('This only checks that a live person is in front of the camera; it does not match your face to your ID automatically. The water office compares your photo with your ID when it reviews your application.') }}
      </div>
      <div id="liveness" class="liveness">
        <input type="hidden" name="selfie_capture" id="selfie_capture">
        <input type="hidden" name="liveness_passed" id="liveness_passed" value="0">
        <div class="liveness-stage" id="livenessStage" hidden>
          <video id="livenessVideo" playsinline muted></video>
          <div class="liveness-guide" id="livenessGuide"></div>
        </div>
        <div class="liveness-prompt" id="livenessPrompt" aria-live="polite"></div>
        <img id="livenessResult" alt="{{ __('Face captured during the blink check') }}" hidden>
        <div class="cam-actions" style="justify-content:center;">
          <button type="button" class="btn btn-outline btn-sm" id="livenessStart"><i class="ph ph-camera" aria-hidden="true"></i> {{ __('Start face verification') }}</button>
          <button type="button" class="btn btn-secondary btn-sm" id="livenessRetry" hidden>{{ __('Try again') }}</button>
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="verify_in_person" name="verify_in_person" value="1" @checked(old('verify_in_person'))>
          <label class="form-check-label" for="verify_in_person" style="font-size:12.5px;">{{ __('I can\'t use a camera — I will verify my face in person at the barangay water office.') }}</label>
        </div>
      </div>
    </div>

    {{-- Consent: the box can only be ticked after both documents were opened and scrolled to the end (JS below).
         Without JavaScript the links open the pages and the box works as usual; the server requires it either way. --}}
    <div class="consent{{ $errors->has('consent') ? ' is-invalid-block' : '' }}" id="consentBox">
      <div class="form-check mb-0">
        <input class="form-check-input{{ $bad('consent') }}" type="checkbox" id="consent" name="consent" value="1" required @checked(old('consent'))>
        <label class="form-check-label" for="consent">
          {{ __('I have read and agree to the') }} <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="doc-link" data-doc="terms">{{ __('Terms of Service') }}</a>
          {{ __('and') }} <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="doc-link" data-doc="privacy">{{ __('Privacy Policy') }}</a>{{ __(', and I consent to AWAS processing my ID and photo to verify my application.') }}
        </label>
      </div>
      <div class="consent-read" id="consentRead" hidden>
        <span>{{ __('Open and read both to the end to tick the box:') }}</span>
        <span data-read="terms">○ {{ __('Terms of Service') }}</span>
        <span data-read="privacy">○ {{ __('Privacy Policy') }}</span>
      </div>
    </div>

    <div class="modal fade" id="docModal" tabindex="-1" aria-labelledby="docModalTitle" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h3 class="modal-title h6 mb-0" id="docModalTitle"></h3>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
          </div>
          <div class="modal-body doc-body" id="docBody" tabindex="0"></div>
          <div class="modal-footer justify-content-between">
            <span class="doc-progress" id="docProgress" aria-live="polite"></span>
            <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" id="docDone">{{ __('Close') }}</button>
          </div>
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary w-100" style="margin-top:14px;" data-loading-text="{{ __('Submitting…') }}">{{ __('Submit & Verify Email') }}</button>
  </form>

  <div class="auth-footer-link">
    {{ __('Already have a water account?') }} <a href="{{ route('activate') }}">{{ __('Activate your account') }}</a>
  </div>
  <div class="auth-footer-link">
    {{ __('Already applied?') }} <a href="{{ route('login') }}">{{ __('Log in to check your status') }}</a>
  </div>
  <div class="auth-footer-link">
    <a href="{{ route('home') }}">{{ __('← Back to Home') }}</a>
  </div>
@endsection

@push('scripts')
<script type="module" src="{{ asset('assets/js/id_capture.js') }}?v={{ @filemtime(public_path('assets/js/id_capture.js')) ?: '1' }}"></script>
<script type="module" src="{{ asset('assets/js/liveness.js') }}?v={{ @filemtime(public_path('assets/js/liveness.js')) ?: '1' }}"></script>
<script>
(function () {
  var form = document.getElementById('applyForm');
  var KEY = 'awas.apply.photos';   // photos kept in this tab only, for one retry after a server error

  // Friendly client-side checks so nobody waits for an upload only to be told
  // something is missing. The server re-validates everything.
  form.addEventListener('submit', function (ev) {
    var msgs = [], warnings = [], first = null;
    var flag = function (el, msg) { msgs.push(msg); if (!first) first = el; };

    form.querySelectorAll('[data-id-widget]').forEach(function (w) {
      var side = w.getAttribute('data-side') === 'back' ? 'BACK' : 'FRONT';
      var hasPhoto = w.querySelector('[data-role="data"]').value !== '' || w.querySelector('[data-role="file"]').files.length > 0;
      if (!hasPhoto) flag(w, side === 'BACK' ? {{ Js::from(__('Please capture or upload a valid photo of the BACK of your ID.')) }} : {{ Js::from(__('Please capture or upload a valid photo of the FRONT of your ID.')) }});
      else if (w.dataset.warn === '1') warnings.push(side === 'BACK' ? {{ Js::from(__('The BACK of your ID may not show a valid ID.')) }} : {{ Js::from(__('The FRONT of your ID may not show a valid ID.')) }});
    });

    var livenessPassed = document.getElementById('liveness_passed').value === '1';
    if (!livenessPassed && !document.getElementById('verify_in_person').checked) {
      flag(document.getElementById('livenessSection'), {{ Js::from(__('Please complete the face verification (blink when asked), or tick "I will verify my face in person".')) }});
    }

    var consent = document.getElementById('consent');
    if (!consent.checked) {
      flag(document.getElementById('consentBox'), consent.disabled
        ? {{ Js::from(__('Open and read the Terms of Service and the Privacy Policy to the end first.')) }}
        : {{ Js::from(__('Please tick the box to agree to the Terms of Service and Privacy Policy and to the checking of your ID and photo.')) }});
    }

    if (msgs.length) {
      ev.preventDefault();
      first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      alert(msgs.join('\n'));
      return;
    }
    if (warnings.length && !confirm(warnings.join('\n') + '\n\n' + {{ Js::from(__('Please use a valid government-issued ID. Submit anyway?')) }})) {
      ev.preventDefault();
      return;
    }
    // Keep the photos in this tab, so a server error (e.g. username taken) doesn't make them retake everything.
    try {
      sessionStorage.setItem(KEY, JSON.stringify({
        at: Date.now(),
        id_capture: form.querySelector('[name="id_capture"]').value,
        id_back_capture: form.querySelector('[name="id_back_capture"]').value,
        selfie: livenessPassed ? document.getElementById('selfie_capture').value : '',
      }));
    } catch (e) {}
  });

  // Back after a server error: put the photos back, and go to the first problem.
  window.addEventListener('load', function () {
    if (!form.hasAttribute('data-has-errors')) { try { sessionStorage.removeItem(KEY); } catch (e) {} return; }
    var saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch (e) {}
    if (saved && Date.now() - saved.at < 30 * 60 * 1000) {
      var restored = false;
      [['id_capture', 'front'], ['id_back_capture', 'back']].forEach(function (pair) {
        var url = saved[pair[0]], w = form.querySelector('[data-id-widget][data-side="' + pair[1] + '"]');
        if (!url || !w) return;
        w.querySelector('[data-role="data"]').value = url;
        var img = w.querySelector('[data-role="preview"]');
        img.src = url; img.hidden = false; img.className = 'id-preview good';
        w.querySelector('[data-role="retake"]').hidden = false;
        var st = w.querySelector('[data-role="status"]');
        st.textContent = '✓ ' + {{ Js::from(__('Photo kept from your last try.')) }}; st.className = 'cam-status good';
        restored = true;
      });
      if (saved.selfie) {
        document.getElementById('selfie_capture').value = saved.selfie;
        document.getElementById('liveness_passed').value = '1';
        var face = document.getElementById('livenessResult');
        face.src = saved.selfie; face.hidden = false;
        var prompt = document.getElementById('livenessPrompt');
        prompt.textContent = '✓ ' + {{ Js::from(__('Face verified')) }}; prompt.className = 'liveness-prompt good';
        document.getElementById('livenessStart').hidden = true;
        var retry = document.getElementById('livenessRetry');
        retry.hidden = false; retry.textContent = {{ Js::from(__('Redo face verification')) }};
        restored = true;
      }
      if (restored) {
        var lost = document.querySelector('[data-photos-lost]'), kept = document.querySelector('[data-photos-kept]');
        if (lost) lost.hidden = true;
        if (kept) kept.hidden = false;
      }
    }
    var target = form.querySelector('.is-invalid') || document.getElementById('consentBox').classList.contains('is-invalid-block') && document.getElementById('consentBox');
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (target.focus) setTimeout(function () { target.focus({ preventScroll: true }); }, 400);
    } else {
      var box = document.getElementById('applyErrors');
      if (box) { box.scrollIntoView({ behavior: 'smooth', block: 'start' }); box.focus({ preventScroll: true }); }
    }
  });

  // A highlighted field stops being red once it is changed.
  form.addEventListener('input', function (e) { if (e.target.classList) e.target.classList.remove('is-invalid'); });
  form.addEventListener('change', function (e) {
    if (e.target.classList) e.target.classList.remove('is-invalid');
    if (e.target.id === 'consent') document.getElementById('consentBox').classList.remove('is-invalid-block');
  });

  // Consent: open Terms / Privacy in a reading window; the box unlocks once both were scrolled to the end.
  (function () {
    var box = document.getElementById('consent'), list = document.getElementById('consentRead');
    var modalEl = document.getElementById('docModal'), body = document.getElementById('docBody');
    var title = document.getElementById('docModalTitle'), progress = document.getElementById('docProgress');
    if (!box || !modalEl || !window.bootstrap || !window.fetch) return;
    // Inside the card the window would sit under Bootstrap's dark backdrop (the card has its own z-index): move it to <body>.
    document.body.appendChild(modalEl);
    var modal = new bootstrap.Modal(modalEl), read = { terms: false, privacy: false }, current = null, cache = {};
    var KEY = 'awas.apply.read';
    try { Object.assign(read, JSON.parse(sessionStorage.getItem(KEY) || '{}')); } catch (e) {}
    if (box.checked) read.terms = read.privacy = true;   // ticked before (back after an error)

    function refresh() {
      var all = read.terms && read.privacy;
      box.disabled = !all;
      box.title = all ? '' : {{ Js::from(__('Open and read the Terms of Service and the Privacy Policy to the end first.')) }};
      list.hidden = all && box.checked;
      list.querySelectorAll('[data-read]').forEach(function (s) {
        var done = read[s.getAttribute('data-read')];
        s.className = done ? 'done' : '';
        s.textContent = (done ? '✓ ' : '○ ') + s.textContent.replace(/^[✓○] /, '');
      });
      try { sessionStorage.setItem(KEY, JSON.stringify(read)); } catch (e) {}
    }
    function checkEnd() {
      if (!current || read[current]) return;
      // Only while the window is fully open and the text is loaded (a hidden window has height 0).
      if (!modalEl.classList.contains('show') || body.clientHeight === 0 || !body.querySelector('h1, h2, p')) return;
      if (body.scrollTop + body.clientHeight >= body.scrollHeight - 24) {
        read[current] = true;
        progress.textContent = '✓ ' + {{ Js::from(__('Read to the end')) }};
        progress.className = 'doc-progress done';
        refresh();
      }
    }
    document.querySelectorAll('#consentBox .doc-link').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        current = link.getAttribute('data-doc');
        title.textContent = link.textContent;
        progress.className = 'doc-progress' + (read[current] ? ' done' : '');
        progress.textContent = read[current] ? '✓ ' + {{ Js::from(__('Read to the end')) }} : {{ Js::from(__('Scroll down to the end to continue.')) }};
        body.innerHTML = '<div class="text-center text-muted p-4">' + {{ Js::from(__('Loading…')) }} + '</div>';
        modal.show();
        var url = link.href;
        (cache[url] ? Promise.resolve(cache[url]) : fetch(url).then(function (r) { return r.text(); }).then(function (html) {
          var main = new DOMParser().parseFromString(html, 'text/html').querySelector('main.legal');
          return (cache[url] = main ? main.innerHTML : null);
        })).then(function (content) {
          if (!content) { window.open(url, '_blank', 'noopener'); modal.hide(); return; }
          body.innerHTML = content;
          body.scrollTop = 0;
          setTimeout(checkEnd, 50);   // short enough to fit without scrolling
        }).catch(function () { window.open(url, '_blank', 'noopener'); modal.hide(); });
      });
    });
    body.addEventListener('scroll', checkEnd);
    modalEl.addEventListener('shown.bs.modal', function () { body.focus({ preventScroll: true }); checkEnd(); });
    box.addEventListener('change', refresh);
    list.hidden = false;
    refresh();
  })();

  // Live "✓ Available / ✗ Taken" while typing the username (the server checks it again on submit).
  var input = document.querySelector('[data-username-check]'), out = document.querySelector('[data-username-status]');
  if (input && out && window.fetch) {
    var timer = null, last = '';
    var show = function (text, cls) { out.textContent = text; out.className = 'field-status' + (cls ? ' ' + cls : ''); };
    var check = function () {
      var value = input.value.trim();
      if (value === last) return;
      last = value;
      if (!value) { show(''); input.setCustomValidity(''); return; }
      show(__t('Checking…'));
      fetch(input.getAttribute('data-username-check') + '?username=' + encodeURIComponent(value), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || input.value.trim() !== value) return;
          if (d.available) { show('✓ ' + __t('Available'), 'ok'); input.setCustomValidity(''); }
          else { show('✗ ' + __t('Taken — please choose another'), 'bad'); input.setCustomValidity(__t('That username is already taken. Please choose another.')); }
        })
        .catch(function () { show(''); });
    };
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(check, 400); });
    if (input.value) check();
  }
})();
</script>
@endpush
