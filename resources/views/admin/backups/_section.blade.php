{{-- System Settings → Backup & Restore. Needs $backups, $keepAuto, $maxUpload (BackupController::sectionData). --}}
@php
  $kinds = [
      'manual'         => ['Manual', 'badge-info'],
      'automatic'      => ['Automatic', 'badge-secondary'],
      'before-restore' => ['Before restore', 'badge-warning'],
      'uploaded'       => ['Uploaded', 'badge-secondary'],
  ];
  $size = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format(max(1, $b / 1024)) . ' KB';
  $latest = $backups[0] ?? null;
@endphp
<div class="card" id="backup" style="scroll-margin-top:80px;">
  <div class="card-header">
    <h3><i class="ph ph-database" aria-hidden="true"></i> Backup &amp; Restore</h3>
    <span class="small text-muted">
      @if ($latest) Last backup: {{ $latest['created_at']->format('M j, Y g:i A') }} ({{ $latest['created_at']->diffForHumans() }})
      @else No backups yet @endif
    </span>
  </div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-lg-7">
        <p class="mb-2" style="font-size:13.5px;">A backup is a full copy of AWAS's data: consumers, accounts, meter readings, bills, payments, applications,
          settings, activity logs and every uploaded file (GCash receipts, IDs and photos).</p>
        <ul class="small text-muted mb-3 ps-3">
          <li>An <strong>automatic backup</strong> is made every night at 2:00 AM; the newest {{ $keepAuto }} are kept.</li>
          <li>Backups are saved privately on the server. <strong>Download</strong> important ones to keep a copy elsewhere (e.g. Google Drive or a USB drive).</li>
          <li>Backup files contain personal information and password hashes — keep downloaded copies private.</li>
        </ul>
        <form method="POST" action="{{ route('admin.backups.store') }}" data-busy="Creating backup…">
          @csrf
          <button type="submit" class="btn btn-primary"><i class="ph ph-database" aria-hidden="true"></i> Create Backup Now</button>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="backup-upload">
          <strong class="d-block mb-1" style="font-size:14px;">Restore from a file</strong>
          <p class="small text-muted mb-2">Upload an AWAS backup file (<code>.json.gz</code>) downloaded earlier. Largest file the server accepts: <strong>{{ $size($maxUpload) }}</strong>.</p>
          <form method="POST" action="{{ route('admin.backups.upload') }}" enctype="multipart/form-data" class="restore-form">
            @csrf
            <input type="file" name="backup_file" accept=".gz,application/gzip" class="form-control form-control-sm mb-2" required>
            <button type="button" class="btn btn-outline btn-sm" data-restore-open data-label="the uploaded file"><i class="ph ph-upload-simple" aria-hidden="true"></i> Upload &amp; Restore…</button>
          </form>
        </div>
      </div>
    </div>

    <div class="alert alert-warning small d-flex gap-2 align-items-start mt-4 mb-3">
      <i class="ph ph-warning" style="font-size:18px;" aria-hidden="true"></i>
      <div><strong>Restoring replaces all current data</strong> with the backup's. Anything recorded after the backup was made (readings, bills, payments, accounts) is removed.
        To be safe, AWAS first saves the current data as a <strong>"Before restore"</strong> backup — restore that one to undo.</div>
    </div>

    <strong class="d-block mb-2" style="font-size:14px;">Saved Backups <span class="text-muted fw-normal">({{ count($backups) }})</span></strong>
    <div class="table-responsive backup-table">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Date</th><th>Type</th><th>Made by</th><th class="text-end">Size</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($backups as $b)
          @php [$kindLabel, $kindClass] = $kinds[$b['kind']] ?? [ucfirst($b['kind']), 'badge-secondary']; @endphp
          <tr>
            <td><strong>{{ $b['created_at']->format('M j, Y g:i A') }}</strong><div class="text-muted small">{{ $b['name'] }}</div></td>
            <td><span class="badge {{ $kindClass }}">{{ $kindLabel }}</span></td>
            <td>{{ $b['created_by'] ?? '—' }}</td>
            <td class="text-end text-nowrap">{{ $size($b['size']) }}</td>
            <td class="text-end">
              <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                <a href="{{ route('admin.backups.download', $b['name']) }}" class="btn btn-outline btn-sm"><i class="ph ph-download-simple" aria-hidden="true"></i> Download</a>
                <form method="POST" action="{{ route('admin.backups.restore', $b['name']) }}" class="restore-form">
                  @csrf
                  <button type="button" class="btn btn-secondary btn-sm" data-restore-open data-label="the backup from {{ $b['created_at']->format('M j, Y g:i A') }}">Restore…</button>
                </form>
                <form method="POST" action="{{ route('admin.backups.destroy', $b['name']) }}" data-confirm="Delete the backup from {{ $b['created_at']->format('M j, Y g:i A') }}? This cannot be undone.">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete backup"><i class="ph ph-trash" aria-hidden="true"></i></button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="5">No backups yet. Click <strong>Create Backup Now</strong> to make the first one.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Confirm a restore: password + typing RESTORE. The fields are copied into the form that opened it. --}}
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreTitle" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h3 class="h6 mb-0" id="restoreTitle">Restore Database</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <p class="mb-2">You are about to restore <strong id="restoreLabel"></strong>.</p>
        <p class="small text-danger mb-3">All current records will be replaced by the backup's. A "Before restore" backup of the current data is saved first so you can undo.</p>
        <label class="form-label" for="restorePassword">Your password *</label>
        <input type="password" id="restorePassword" class="form-control mb-2" autocomplete="current-password">
        <label class="form-label" for="restoreConfirm">Type <strong>RESTORE</strong> to confirm *</label>
        <input type="text" id="restoreConfirm" class="form-control" autocomplete="off" spellcheck="false">
        <div class="text-danger small mt-2" id="restoreError" hidden></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="restoreGo">Restore</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  const modalEl = document.getElementById('restoreModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const $ = id => document.getElementById(id);
  let form = null;

  document.querySelectorAll('#backup [data-restore-open]').forEach(btn => btn.addEventListener('click', () => {
    form = btn.closest('form');
    const file = form.querySelector('input[type=file]');
    if (file && !file.files.length) { file.reportValidity(); return; }
    $('restoreLabel').textContent = btn.dataset.label;
    $('restorePassword').value = ''; $('restoreConfirm').value = ''; $('restoreError').hidden = true;
    modal.show();
  }));
  modalEl.addEventListener('shown.bs.modal', () => $('restorePassword').focus());

  $('restoreGo').addEventListener('click', () => {
    const pw = $('restorePassword').value, text = $('restoreConfirm').value.trim().toUpperCase();
    if (!pw || text !== 'RESTORE') {
      $('restoreError').textContent = !pw ? 'Enter your password.' : 'Type RESTORE to confirm.';
      $('restoreError').hidden = false;
      return;
    }
    [['password', pw], ['confirm_text', text]].forEach(([name, value]) => {
      let input = form.querySelector('input[name="' + name + '"]');
      if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = name; form.appendChild(input); }
      input.value = value;
    });
    $('restoreGo').disabled = true; $('restoreGo').textContent = 'Restoring… do not close this page';
    form.submit();
  });

  // Creating a backup can take a moment: show it and stop double clicks.
  document.querySelectorAll('#backup form[data-busy]').forEach(f => f.addEventListener('submit', () => {
    const b = f.querySelector('button[type=submit]');
    if (b) { b.disabled = true; b.textContent = f.dataset.busy; }
  }));
})();
</script>
@endpush
