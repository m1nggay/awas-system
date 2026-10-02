@extends('layouts.auth', ['video' => true, 'cardStyle' => 'max-width:520px;'])

@section('title', 'Application Status')

@push('styles')
<style>
  .steps{list-style:none;margin:18px 0 6px;padding:0;}
  .steps li{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border);font-size:14px;}
  .steps li:last-child{border-bottom:0;}
  .steps .mark{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;background:#eef1f2;color:#5c6b73;flex:none;}
  .steps .done .mark{background:#e0f5f0;color:#1a7a68;}
  .steps .pending .mark{background:#fff2dc;color:#946313;}
  .steps .failed .mark{background:#fde5e2;color:#b8382a;}
  .steps .state{margin-left:auto;font-size:12.5px;color:var(--text-muted);}
  .steps .done .state{color:#1a7a68;}
  .steps .failed .state{color:#b8382a;font-weight:600;}
</style>
@endpush

@section('content')
  @php
    $stepIcons = ['done' => '✓', 'pending' => '⏳', 'failed' => '✕', 'waiting' => '—'];
    $stepText  = ['done' => 'Completed', 'pending' => 'Pending', 'failed' => 'Rejected', 'waiting' => ''];
  @endphp

  <h1>Application Status</h1>

  @if (!$app)
    <p class="subtitle">We couldn't find a membership application for this account.</p>
  @else
    <p class="subtitle">Hi {{ $app->full_name }} — here is where your AWAS membership application stands.</p>

    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Application ID</span><strong>{{ $app->reference_code }}</strong></div>
    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Date applied</span><strong>{{ formatDateTime($app->created_at) }}</strong></div>
    <div class="d-flex justify-content-between mb-2">
      <span class="text-muted">Application Status</span>
      <span class="badge {{ applicationStatusBadgeClass($app->status) }}">{{ $app->status === 'rejected' ? 'REJECTED' : applicationStatusLabel($app->status) }}</span>
    </div>

    @if ($app->status === 'rejected')
      <div class="alert alert-danger" style="margin-top:14px;">
        <strong>Your application was not approved.</strong><br>
        Reason from the administrator: {{ $app->review_notes ?: 'No reason was recorded.' }}<br>
        <span style="font-size:12.5px;">If you have questions, please contact the barangay water office.</span>
      </div>
    @elseif ($app->status === 'pending_review')
      <div class="alert alert-info" style="margin-top:14px;">
        Your application is waiting for administrator review. You will receive an email once it has been approved or rejected.
      </div>
    @elseif ($app->status === 'approved')
      <div class="alert alert-success" style="margin-top:14px;">
        Your application was approved! The water office is now assigning your water meter — your account will be activated right after.
      </div>
    @endif

    <ul class="steps">
      @foreach ($app->progress() as $step)
        <li class="{{ $step['state'] }}">
          <span class="mark">{{ $stepIcons[$step['state']] }}</span>
          <span>{{ $step['label'] }}</span>
          <span class="state">{{ $stepText[$step['state']] }}</span>
        </li>
      @endforeach
    </ul>
  @endif

  <div class="auth-footer-link">
    <form method="POST" action="{{ route('logout') }}" class="d-inline">
      @csrf
      <button type="submit" class="btn btn-link p-0">Log out</button>
    </form>
  </div>
@endsection
