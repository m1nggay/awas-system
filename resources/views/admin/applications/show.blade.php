@extends('layouts.app')

@section('title', 'Review Application')

@php
  $canEditVerification = in_array($app->status, ['pending_review', 'approved'], true);
  $hasFiles = !empty($app->id_file) || !empty($app->face_file);
  $detail = fn (string $label, ?string $value) => ['label' => $label, 'value' => ($value !== null && $value !== '') ? $value : '—'];
  $applicant = [
      $detail('Name', $app->full_name),
      $detail('Birth date', $app->birth_date ? formatDate($app->birth_date) . ' (age ' . ageFromBirthDate($app->birth_date) . ')' : null),
      $detail('Sex', $app->sex ? ucfirst($app->sex) : null),
      $detail('Contact number', $app->contact_number),
      $detail('Email', $app->email),
      $detail('Complete address', $app->address),
      $detail('Purok', $app->purok_name),
      $detail('Barangay', $app->barangay),
      $detail('Municipality', $app->municipality),
      $detail('Province', $app->province),
      $detail('Date applied', formatDateTime($app->created_at)),
      $detail('Email verified', $app->email_verified_at ? formatDateTime($app->email_verified_at) : 'Not yet'),
  ];
  $household = [
      $detail('Household number', $app->household_number),
      $detail('Household members', $app->household_members !== null ? (string)$app->household_members : null),
      $detail('Type of residence', $app->residence_type ? (\App\Models\MembershipApplication::RESIDENCE_TYPES[$app->residence_type] ?? $app->residence_type) : null),
      $detail('Type of Consumer', consumerTypeLabel($app->consumer_type)),
  ];
@endphp

@section('content')
<a href="{{ route('admin.applications.index') }}" class="btn btn-secondary btn-sm mb-3">&larr; Back to Applications</a>

<div class="card">
  <div class="card-header">
    <h3>{{ $app->reference_code }} — {{ $app->full_name }}</h3>
    <span class="badge {{ applicationStatusBadgeClass($app->status) }}">{{ applicationStatusLabel($app->status) }}</span>
  </div>
  <div class="card-body">
    @if ($app->status === 'pending_verification')
      <div class="alert alert-warning">The applicant has not verified their email yet, so this application cannot be reviewed.</div>
    @endif

    <div class="row g-3">
      <div class="col-lg-6">
        <h4 style="margin-bottom:12px;font-size:14px;">Applicant Information</h4>
        @foreach ($applicant as $row)
          <div class="d-flex justify-content-between gap-2 mb-2" style="font-size:13.5px;"><span class="text-muted">{{ $row['label'] }}</span><strong class="text-end">{{ $row['value'] }}</strong></div>
        @endforeach
      </div>
      <div class="col-lg-6">
        <h4 style="margin-bottom:12px;font-size:14px;">Household Information</h4>
        @foreach ($household as $row)
          <div class="d-flex justify-content-between gap-2 mb-2" style="font-size:13.5px;"><span class="text-muted">{{ $row['label'] }}</span><strong class="text-end">{{ $row['value'] }}</strong></div>
        @endforeach
        @if ($app->status === 'rejected')
          <h4 style="margin:18px 0 8px;font-size:14px;">Rejection Reason</h4>
          <div class="alert alert-danger">{{ $app->review_notes ?: 'No reason recorded.' }}</div>
        @endif
        @if ($app->status === 'active' && $app->consumer_id)
          <h4 style="margin:18px 0 8px;font-size:14px;">Consumer Account</h4>
          <a class="btn btn-outline btn-sm" href="{{ route('admin.consumers.history', $app->consumer_id) }}">View consumer account</a>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Identity Verification</h3></div>
  <div class="card-body">
    @if (!$hasFiles)
      <p class="text-muted">No valid ID photo or face check was submitted with this application.</p>
    @else
    <div class="row g-3">
      <div class="col-lg-6">
        <h4 style="margin-bottom:8px;font-size:14px;">Valid ID <span class="text-muted" style="font-weight:400;">— {{ $app->id_type ?: 'type not given' }}</span></h4>
        @if ($app->id_file)
          @php $idUrl = route('admin.applications.file', [$app->application_id, 'id']); @endphp
          <a href="{{ $idUrl }}" target="_blank" rel="noopener"><img src="{{ $idUrl }}" alt="Applicant's valid ID" style="max-width:100%;border:1px solid var(--border);border-radius:8px;"></a>
        @else<p class="text-muted">No ID uploaded.</p>@endif
      </div>
      <div class="col-lg-6">
        <h4 style="margin-bottom:8px;font-size:14px;">Face Verification
          @if ($app->liveness_status === 'passed')
            <span class="badge badge-success">Blink check passed</span>
          @else
            <span class="badge badge-secondary">Blink check not done — verify in person</span>
          @endif
        </h4>
        @if ($app->face_file)
          @php $faceUrl = route('admin.applications.file', [$app->application_id, 'face']); @endphp
          <a href="{{ $faceUrl }}" target="_blank" rel="noopener"><img src="{{ $faceUrl }}" alt="Face captured during the blink check" style="max-width:100%;border:1px solid var(--border);border-radius:8px;"></a>
          <div class="text-muted" style="font-size:11.5px;">Captured automatically during the live blink check.</div>
        @else<p class="text-muted">No face capture — the applicant chose to verify in person.</p>@endif
      </div>
    </div>
    @endif

    <p class="text-muted" style="font-size:12px;margin:14px 0;">The blink check only proves a live person was in front of the camera. Compare the captured face with the ID photo yourself and record the result — the system does not match faces automatically.</p>

    <form method="POST" action="{{ route('admin.applications.verification', $app->application_id) }}" class="row g-3 align-items-end">
      @csrf
      <div class="col-md-4">
        <label for="id_status" class="form-label">ID status</label>
        <select id="id_status" name="id_status" class="form-select" @disabled(!$canEditVerification)>
          @foreach (['submitted', 'verified', 'failed'] as $s)
            <option value="{{ $s }}" @selected($app->id_status === $s)>{{ verificationStatusLabel($s) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label for="face_status" class="form-label">Face verification status</label>
        <select id="face_status" name="face_status" class="form-select" @disabled(!$canEditVerification)>
          @foreach (['for_review', 'verified', 'failed'] as $s)
            <option value="{{ $s }}" @selected($app->face_status === $s)>{{ verificationStatusLabel($s) }}</option>
          @endforeach
        </select>
      </div>
      @if ($canEditVerification)
        <div class="col-md-4"><button type="submit" class="btn btn-secondary">Save statuses</button></div>
      @endif
    </form>
    <div style="font-size:13px;">
      Current: ID <span class="badge {{ verificationStatusBadgeClass($app->id_status) }}">{{ verificationStatusLabel($app->id_status) }}</span>
      Face <span class="badge {{ verificationStatusBadgeClass($app->face_status) }}">{{ verificationStatusLabel($app->face_status) }}</span>
    </div>
  </div>
</div>

@if ($app->status === 'pending_review')
<div class="card">
  <div class="card-header"><h3>Decision</h3></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-6">
        <h4 style="margin-bottom:8px;font-size:14px;">Approve</h4>
        <p class="text-muted" style="font-size:13px;margin-bottom:12px;">Approving moves the application to meter assignment. The applicant's account is activated once a meter is assigned.</p>
        <form method="POST" action="{{ route('admin.applications.approve', $app->application_id) }}" onsubmit="return confirm('Approve this application?');">
          @csrf
          <button type="submit" class="btn btn-success">APPROVE APPLICATION</button>
        </form>
      </div>
      <div class="col-lg-6">
        <h4 style="margin-bottom:8px;font-size:14px;">Reject</h4>
        <form method="POST" action="{{ route('admin.applications.reject', $app->application_id) }}" onsubmit="return confirm('Reject this application? The applicant will be emailed the reason.');">
          @csrf
          <div class="mb-3">
            <label for="rejection_reason" class="form-label">Please provide the reason for rejecting this application. *</label>
            <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="3" maxlength="500" required></textarea>
          </div>
          <button type="submit" class="btn btn-danger">REJECT APPLICATION</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endif

@if ($app->status === 'approved')
<div class="card">
  <div class="card-header"><h3>Assign Water Meter</h3></div>
  <div class="card-body">
    @include('partials.errors', ['bag' => 'meter'])
    <p class="text-muted" style="font-size:13px;margin-bottom:14px;">Assigning a meter creates the consumer account and activates the applicant's login. Only an administrator can assign the official meter number.</p>
    <form method="POST" action="{{ route('admin.applications.assign-meter', $app->application_id) }}">
      @csrf
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" value="{{ $app->full_name }}" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label">Type of Consumer</label>
          <input type="text" class="form-control" value="{{ consumerTypeLabel($app->consumer_type) }}" readonly>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label for="meter_number" class="form-label">Meter Number *</label>
          <input type="text" id="meter_number" name="meter_number" class="form-control" required maxlength="20"
                 inputmode="numeric" pattern="\d+" data-digits-only placeholder="e.g. 1005" value="{{ old('meter_number') }}">
          <div class="text-muted" style="font-size:11.5px;">Numbers only.</div>
        </div>
        <div class="col-md-6">
          <label for="installation_date" class="form-label">Installation Date *</label>
          <input type="date" id="installation_date" name="installation_date" class="form-control" required value="{{ old('installation_date', date('Y-m-d')) }}">
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label for="initial_reading" class="form-label">Initial Meter Reading (m³) *</label>
          <input type="number" step="0.01" min="0" id="initial_reading" name="initial_reading" class="form-control" required value="{{ old('initial_reading', '0') }}">
        </div>
        <div class="col-md-6">
          <label for="meter_status" class="form-label">Meter Status *</label>
          <select id="meter_status" name="meter_status" class="form-select" required>
            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance'] as $v => $l)
              <option value="{{ $v }}" @selected(old('meter_status', 'active') === $v)>{{ $l }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label for="household_number" class="form-label">Household Number (if applicable)</label>
        <input type="text" id="household_number" name="household_number" class="form-control" maxlength="30" value="{{ old('household_number', $app->household_number) }}">
      </div>
      <button type="submit" class="btn btn-primary">Assign Meter &amp; Activate Account</button>
    </form>
  </div>
</div>
@endif
@endsection
