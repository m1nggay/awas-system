{{-- Profile picture + name, with Change / Remove photo (every role's profile page). Needs $user. --}}
<style>
  .profile-head{display:flex;align-items:center;gap:18px;flex-wrap:wrap;}
  .profile-avatar{width:96px;height:96px;border-radius:50%;object-fit:cover;flex:none;border:3px solid #fff;box-shadow:0 0 0 2px var(--border);background:#e8f3f8;}
  .profile-initials{display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:700;color:var(--primary-dark);}
  .profile-name{font-size:18px;font-weight:700;margin:0;}
</style>
<div class="card mb-3">
  <div class="card-body profile-head">
    @if ($user->profile_photo)
      <img src="{{ route('account.photo', ['v' => $user->profile_photo]) }}" alt="Your profile picture" class="profile-avatar">
    @else
      <div class="profile-avatar profile-initials" aria-hidden="true">{{ mb_strtoupper(collect(preg_split('/\s+/', trim($user->full_name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('')) }}</div>
    @endif
    <div class="flex-grow-1">
      @php $editingName = $errors->any() && old('full_name') !== null; @endphp
      <div class="d-flex align-items-center gap-2 flex-wrap" id="nameView" @if ($editingName) hidden @endif>
        <p class="profile-name">{{ $user->full_name }}</p>
        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="editNameBtn">✏️ Edit name</button>
      </div>
      <form method="POST" action="{{ route('account.name') }}" class="d-flex flex-wrap gap-2 align-items-center" id="nameForm" @unless ($editingName) hidden @endunless>
        @csrf
        <label for="full_name" class="visually-hidden">Full name</label>
        <input type="text" id="full_name" name="full_name" class="form-control form-control-sm" style="max-width:280px;" maxlength="150" required
               value="{{ old('full_name', $user->full_name) }}" autocomplete="name">
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
        <button type="button" class="btn btn-outline btn-sm" id="cancelNameBtn">Cancel</button>
      </form>
      @if ($user->role === 'resident')
        <div class="text-muted" style="font-size:11.5px;" id="nameNote" @unless ($editingName) hidden @endunless>This is also the name on your water account, bills and receipts.</div>
      @endif
      <div class="d-flex flex-wrap gap-2 mt-2">
        <form method="POST" action="{{ route('account.photo.update') }}" enctype="multipart/form-data" id="photoForm">
          @csrf
          <label class="btn btn-primary btn-sm mb-0" for="photoInput">📷 {{ $user->profile_photo ? 'Change photo' : 'Add profile picture' }}</label>
          <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
        </form>
        @if ($user->profile_photo)
          <form method="POST" action="{{ route('account.photo.remove') }}" data-confirm="Remove your profile picture?">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline btn-sm">Remove</button>
          </form>
        @endif
      </div>
      <div class="text-muted" style="font-size:11.5px;margin-top:4px;">JPG, PNG or WEBP, up to 5 MB.</div>
    </div>
  </div>
</div>
<script>
// Edit name: swap the name for a small form, and back.
(function () {
  const view = document.getElementById('nameView'), form = document.getElementById('nameForm'), note = document.getElementById('nameNote');
  const input = document.getElementById('full_name');
  const editing = on => { view.hidden = on; form.hidden = !on; if (note) note.hidden = !on; if (on) { input.focus(); input.select(); } };
  document.getElementById('editNameBtn').addEventListener('click', () => editing(true));
  document.getElementById('cancelNameBtn').addEventListener('click', () => { input.value = input.defaultValue; editing(false); });
})();
// Uploads as soon as a picture is chosen.
document.getElementById('photoInput').addEventListener('change', function () {
  const f = this.files && this.files[0];
  if (!f) return;
  if (f.size > 5 * 1024 * 1024) { alert('The picture is larger than 5 MB. Please choose a smaller one.'); this.value = ''; return; }
  document.querySelector('label[for="photoInput"]').textContent = 'Uploading…';
  document.getElementById('photoForm').submit();
});
</script>
