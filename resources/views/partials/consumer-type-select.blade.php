{{-- "Type of Consumer" select with a small (i) help button. Usage: @include('partials.consumer-type-select', ['selected' => ...]) --}}
<label class="form-label d-flex align-items-center gap-1" for="consumer_type">
  Type of Consumer *
  <button type="button" class="btn btn-link p-0 ms-auto" style="font-size:14px;line-height:1;text-decoration:none;"
          data-bs-toggle="collapse" data-bs-target="#consumerTypeHelp" aria-expanded="false" aria-controls="consumerTypeHelp"
          title="What do these mean?">ⓘ</button>
</label>
<select id="consumer_type" name="consumer_type" class="form-select" required>
  @foreach (\App\Models\Consumer::TYPES as $v => $l)
    <option value="{{ $v }}" @selected(($selected ?? 'residential') === $v)>{{ $l }}</option>
  @endforeach
</select>
<div class="collapse" id="consumerTypeHelp">
  <div class="apply-note mt-2" style="font-size:12px;background:#f0fbff;border:1px solid var(--border);border-radius:8px;padding:8px 10px;">
    @foreach (\App\Models\Consumer::TYPE_HELP as $v => $text)
      <div><strong>{{ \App\Models\Consumer::TYPES[$v] }}</strong> – {{ $text }}</div>
    @endforeach
  </div>
</div>
