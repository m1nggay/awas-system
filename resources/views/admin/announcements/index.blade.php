@extends('layouts.app')

@section('title', 'Announcements')

@section('content')
<div class="row g-3 align-items-start">
  <div class="col-lg-5">
    <div class="card mb-0">
      <div class="card-header"><h3>New Announcement</h3></div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.announcements.store') }}" id="announceForm">
          @csrf
          <div class="mb-3">
            <label for="title" class="form-label">Title *</label>
            <input type="text" id="title" name="title" class="form-control" maxlength="150" required value="{{ old('title') }}" placeholder="e.g. Water interruption on Oct 15">
          </div>
          <div class="mb-3">
            <label for="message" class="form-label">Message *</label>
            <textarea id="message" name="message" class="form-control" rows="4" maxlength="500" required placeholder="What consumers need to know">{{ old('message') }}</textarea>
            <div class="text-muted small text-end"><span id="msgCount">0</span>/500</div>
          </div>

          <label class="form-label">Send to</label>
          <div class="mb-2">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="audience" id="audAll" value="all" @checked(old('audience', 'all') === 'all')>
              <label class="form-check-label" for="audAll">All consumers <span class="text-muted">({{ $accountCount }} with an online account)</span></label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="audience" id="audPuroks" value="puroks" @checked(old('audience') === 'puroks')>
              <label class="form-check-label" for="audPuroks">Only selected puroks</label>
            </div>
          </div>
          <div id="purokChoices" class="d-flex flex-wrap gap-2 mb-3 ps-4" @if (old('audience') !== 'puroks') hidden @endif>
            @foreach ($puroks as $pk)
              <label class="btn btn-outline btn-sm mb-0">
                <input type="checkbox" name="puroks[]" value="{{ $pk->purok_id }}" class="form-check-input me-1" @checked(in_array($pk->purok_id, old('puroks', [])))>{{ $pk->purok_name }}
              </label>
            @endforeach
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="email" id="email" value="1" @checked(old('email'))>
            <label class="form-check-label" for="email">Also send by email</label>
          </div>
          <button type="submit" class="btn btn-primary w-100" id="announceBtn">📣 Send Announcement</button>
          <p class="text-muted small mt-2 mb-0">It appears in each consumer's notification bell. Consumers without an online account won't receive it.</p>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card mb-0">
      <div class="card-header"><h3>Sent Announcements</h3></div>
      <div class="card-body no-pad">
        @forelse ($announcements as $a)
          <div class="p-3 border-bottom">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
              <strong>{{ $a->title }}</strong>
              <span class="text-muted small">{{ formatDateTime($a->created_at) }}</span>
            </div>
            <div class="text-muted small mt-1" style="white-space:pre-line;">{{ $a->message }}</div>
            <div class="small mt-2">
              <span class="badge badge-info">{{ $a->purok_ids ? collect(explode(',', $a->purok_ids))->map(fn ($id) => $purokNames[$id] ?? "Purok #$id")->join(', ') : 'All consumers' }}</span>
              <span class="text-muted">· {{ $a->recipient_count }} account(s){{ $a->emailed ? ' · emailed' : '' }}{{ $a->sender ? ' · by ' . $a->sender : '' }}</span>
            </div>
          </div>
        @empty
          <div class="text-center text-muted p-5">No announcements sent yet.</div>
        @endforelse
      </div>
    </div>
    @include('partials.pagination', ['paginator' => $announcements])
  </div>
</div>

<script>
(function () {
  const msg = document.getElementById('message'), count = document.getElementById('msgCount');
  const showCount = () => { count.textContent = msg.value.length; };
  msg.addEventListener('input', showCount); showCount();
  document.querySelectorAll('input[name="audience"]').forEach(r => r.addEventListener('change', () => {
    document.getElementById('purokChoices').hidden = !document.getElementById('audPuroks').checked;
  }));
  document.getElementById('announceForm').addEventListener('submit', function (e) {
    if (!confirm('Send this announcement now?')) { e.preventDefault(); return; }
    document.getElementById('announceBtn').disabled = true;
  });
})();
</script>
@endsection
