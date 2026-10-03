{{-- Purok checkboxes for assigning a Meter Reader. Shows who currently has each purok.
     Usage: @include('admin.users._purok-checkboxes', ['prefix' => 'new']) --}}
<div class="row g-1">
  @foreach ($puroks as $pk)
    @php $owner = $purokOwner[$pk->purok_id] ?? null; @endphp
    <div class="col-6">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="puroks[]" value="{{ $pk->purok_id }}" id="{{ $prefix }}_purok_{{ $pk->purok_id }}" data-purok>
        <label class="form-check-label" for="{{ $prefix }}_purok_{{ $pk->purok_id }}">
          {{ $pk->purok_name }}
          @if ($owner)<span class="text-muted" style="font-size:11px;" data-owner="{{ $owner }}">({{ $readerNames[$owner] ?? 'another reader' }})</span>@endif
        </label>
      </div>
    </div>
  @endforeach
</div>
