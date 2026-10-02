@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
<div class="card">
  <div class="card-header"><h3>System Settings</h3></div>
  <div class="card-body">
    <form method="POST" action="{{ route('admin.settings.update') }}">
      @csrf
      @foreach ($fields as $key => $label)
        <div class="mb-3">
          <label for="{{ $key }}" class="form-label">{{ $label }}</label>
          <input type="text" id="{{ $key }}" name="{{ $key }}" class="form-control" value="{{ $values[$key] }}">
        </div>
      @endforeach
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>📱 GCash QR Payment</h3></div>
  <div class="card-body">
    <p class="text-muted" style="font-size:13px;">Upload the barangay's <strong>official GCash QR code</strong> (from the GCash app: Profile → My QR Code → Download).
      Consumers see it when they click <strong>Pay Bill</strong>, and staff show it for GCash payments at the barangay.</p>
    <div class="row g-4">
      <div class="col-md-4 text-center">
        @if ($qr->isConfigured())
          <img src="{{ route('gcash.qr') }}?v={{ $qr->fileName() }}" alt="Current GCash QR code" style="width:100%;max-width:220px;border:2px solid #007dfe;border-radius:12px;padding:6px;background:#fff;">
          <div class="text-success small mt-1">✓ QR code uploaded</div>
        @else
          <div class="alert alert-warning small mb-0">No QR code yet — online payment is unavailable until you upload one.</div>
        @endif
      </div>
      <div class="col-md-8">
        <form method="POST" action="{{ route('admin.settings.gcash') }}" enctype="multipart/form-data">
          @csrf
          <div class="mb-3">
            <label for="gcash_qr" class="form-label">GCash QR Code Image {{ $qr->isConfigured() ? '(upload to replace)' : '*' }}</label>
            <input type="file" id="gcash_qr" name="gcash_qr" class="form-control" accept="image/jpeg,image/png,image/webp" @required(!$qr->isConfigured())>
            <div class="text-muted" style="font-size:12px;">JPG, PNG or WEBP, up to 5 MB.</div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="gcash_account_name" class="form-label">GCash Account Name</label>
              <input type="text" id="gcash_account_name" name="gcash_account_name" class="form-control" maxlength="100" value="{{ $qr->accountName() }}" placeholder="e.g. Barangay Adlay Water">
            </div>
            <div class="col-md-6">
              <label for="gcash_number" class="form-label">GCash Number</label>
              <input type="text" id="gcash_number" name="gcash_number" class="form-control" maxlength="13" inputmode="tel" value="{{ $qr->number() }}" placeholder="09XXXXXXXXX">
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-3">Save GCash Settings</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>About AGAS</h3></div>
  <div class="card-body">
    <p class="text-muted">AGAS: Smart Water Management and Billing System with Online Payment — developed for Barangay Adlay to improve the efficiency, accuracy, and transparency of water billing and payment monitoring.</p>
  </div>
</div>
@endsection
