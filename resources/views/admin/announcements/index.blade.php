@extends('layouts.app')

@section('title', __('Announcements'))

@section('content')
<div class="row g-3 align-items-start">
  <div class="col-lg-5">
    <div class="card mb-0">
      <div class="card-header"><h3>{{ __('New Announcement') }}</h3></div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.announcements.store') }}" id="announceForm">
          @csrf
          {{-- Ready-made announcements: pick one, fill in the [bracketed] parts, choose who gets it. --}}
          <div class="mb-3">
            <label for="template" class="form-label">{{ __('Use a ready-made announcement') }}</label>
            <select id="template" class="form-select">
              <option value="">{{ __('— Write my own —') }}</option>
              @foreach ($templates as $group => $items)
                <optgroup label="{{ $group }}">
                  @foreach ($items as $name => $t)
                    <option value="{{ $group }}|{{ $name }}">{{ $name }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
            <div class="text-muted small mt-1" id="templateHint" hidden>
              <i class="ph ph-info" aria-hidden="true"></i> {{ __('Replace the parts in') }} <strong>{{ __('[brackets]') }}</strong>{{ __(', then choose who gets it.') }}
            </div>
          </div>
          <div class="mb-3">
            <label for="title" class="form-label">{{ __('Title *') }}</label>
            <input type="text" id="title" name="title" class="form-control" maxlength="150" required value="{{ old('title') }}" placeholder="{{ __('e.g. Water interruption on Oct 15') }}">
          </div>
          <div class="mb-3">
            <label for="message" class="form-label">{{ __('Message *') }}</label>
            <textarea id="message" name="message" class="form-control" rows="4" maxlength="500" required placeholder="{{ __('What consumers need to know') }}">{{ old('message') }}</textarea>
            <div class="text-muted small text-end"><span id="msgCount">0</span>/500</div>
          </div>

          <label class="form-label">{{ __('Send to') }}</label>
          <div class="mb-2">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="audience" id="audAll" value="all" @checked(old('audience', 'all') === 'all')>
              <label class="form-check-label" for="audAll">{{ __('All consumers') }} <span class="text-muted">{{ __('(:accountcount with an online account)', ['accountcount' => $accountCount]) }}</span></label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="audience" id="audPuroks" value="puroks" @checked(old('audience') === 'puroks')>
              <label class="form-check-label" for="audPuroks">{{ __('Only selected puroks') }}</label>
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
            <label class="form-check-label" for="email">{{ __('Also send by email') }}</label>
          </div>
          <button type="submit" class="btn btn-primary w-100" id="announceBtn"><i class="ph ph-megaphone-simple" aria-hidden="true"></i> {{ __('Send Announcement') }}</button>
          <p class="text-muted small mt-2 mb-0">{{ __('It appears in each consumer\'s notification bell. Consumers without an online account won\'t receive it.') }}</p>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card mb-0">
      <div class="card-header"><h3>{{ __('Sent Announcements') }}</h3></div>
      <div class="card-body no-pad">
        @forelse ($announcements as $a)
          <div class="p-3 border-bottom">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
              <strong>{{ $a->title }}</strong>
              <span class="text-muted small">{{ formatDateTime($a->created_at) }}</span>
            </div>
            <div class="text-muted small mt-1" style="white-space:pre-line;">{{ $a->message }}</div>
            <div class="small mt-2">
              <span class="badge badge-info">{{ $a->purok_ids ? collect(explode(',', $a->purok_ids))->map(fn ($id) => $purokNames[$id] ?? "Purok #$id")->join(', ') : __('All consumers') }}</span>
              <span class="text-muted">{{ __('· :recipientcount account(s):value:value2', ['recipientcount' => $a->recipient_count, 'value' => $a->emailed ? __(' · emailed') : '', 'value2' => $a->sender ? __(' · by ') . $a->sender : '']) }}</span>
            </div>
          </div>
        @empty
          <div class="text-center text-muted p-5">{{ __('No announcements sent yet.') }}</div>
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
  // Ready-made announcements fill in the title and message.
  const templates = {{ Js::from($templates) }};
  const title = document.getElementById('title'), hint = document.getElementById('templateHint');
  const blank = /\[[^\]]+\]/;
  document.getElementById('template').addEventListener('change', function () {
    if (!this.value) { hint.hidden = true; return; }
    const [group, name] = this.value.split('|');
    const t = templates[group][name];
    if ((title.value.trim() || msg.value.trim()) && !confirm({{ Js::from(__('Replace what you have typed with ":name"?')) }}.replace(':name', name))) {
      this.value = ''; return;
    }
    title.value = t.title;
    msg.value = t.message;
    showCount();
    hint.hidden = false;
    // Jump to the first blank to fill in.
    const field = blank.test(title.value) ? title : msg;
    const m = field.value.match(blank);
    field.focus();
    if (m) field.setSelectionRange(m.index, m.index + m[0].length);
  });

  document.getElementById('announceForm').addEventListener('submit', function (e) {
    // Don't send an announcement that still has [blanks] in it.
    const left = (title.value + ' ' + msg.value).match(blank);
    if (left) {
      e.preventDefault();
      alert({{ Js::from(__('Please fill in :blank before sending.')) }}.replace(':blank', left[0]));
      const field = blank.test(title.value) ? title : msg;
      const m = field.value.match(blank);
      field.focus();
      field.setSelectionRange(m.index, m.index + m[0].length);
      return;
    }
    if (!confirm('{{ addslashes(__('Send this announcement now?')) }}')) { e.preventDefault(); return; }
    document.getElementById('announceBtn').disabled = true;
  });
})();
</script>
@endsection
