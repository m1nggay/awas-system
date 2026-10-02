@php $prefix = $isEdit ? 'edit_' : ''; @endphp
<div class="row g-3">
  <div class="col-md-6">
    <label for="{{ $prefix }}meter_number" class="form-label">Meter Number *</label>
    <input type="text" id="{{ $prefix }}meter_number" name="meter_number" class="form-control" required
           inputmode="numeric" pattern="\d+" maxlength="20" data-digits-only placeholder="e.g. 1001"
           title="Numbers only — no letters or prefix">
    <div class="text-muted" style="font-size:11.5px;">Numbers only (e.g. 1001).</div>
  </div>
  <div class="col-md-6">
    <label for="{{ $prefix }}consumer_type" class="form-label">Type of Consumer *</label>
    <select id="{{ $prefix }}consumer_type" name="consumer_type" class="form-select" required>
      @foreach (\App\Models\Consumer::TYPES as $v => $l)
        <option value="{{ $v }}">{{ $l }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-6">
    <label for="{{ $prefix }}full_name" class="form-label">Name *</label>
    <input type="text" id="{{ $prefix }}full_name" name="full_name" class="form-control" required>
  </div>
  <div class="col-md-6">
    <label for="{{ $prefix }}purok_id" class="form-label">Purok *</label>
    <select id="{{ $prefix }}purok_id" name="purok_id" class="form-select" required>
      <option value="">Select purok</option>
      @foreach ($puroks as $pk)
        <option value="{{ $pk->purok_id }}">{{ $pk->purok_name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-12">
    <label for="{{ $prefix }}address" class="form-label">Address *</label>
    <input type="text" id="{{ $prefix }}address" name="address" class="form-control" required>
  </div>
  <div class="col-md-6">
    <label for="{{ $prefix }}contact_number" class="form-label">Contact Number</label>
    <input type="text" id="{{ $prefix }}contact_number" name="contact_number" class="form-control">
  </div>
  <div class="col-md-6">
    <label for="{{ $prefix }}email" class="form-label">Email</label>
    <input type="email" id="{{ $prefix }}email" name="email" class="form-control">
  </div>
  <div class="col-md-6">
    <label for="{{ $prefix }}connection_date" class="form-label">Connection Date</label>
    <input type="date" id="{{ $prefix }}connection_date" name="connection_date" class="form-control">
  </div>
  <div class="col-md-6 d-flex align-items-end">
    <div class="form-check">
      <input type="checkbox" id="{{ $prefix }}is_senior" name="is_senior" value="1" class="form-check-input">
      <label for="{{ $prefix }}is_senior" class="form-check-label">Senior citizen ({{ rtrim(rtrim((string)setting('senior_discount_percent', 20), '0'), '.') }}% discount)</label>
    </div>
  </div>
  @if ($isEdit)
  <div class="col-12">
    <label for="edit_status" class="form-label">Status</label>
    <select id="edit_status" name="status" class="form-select">
      <option value="active">Active</option>
      <option value="disconnected">Disconnected</option>
      <option value="inactive">Inactive</option>
    </select>
  </div>
  @endif
</div>
