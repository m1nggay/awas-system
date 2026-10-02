{{-- Validation messages as one red box, one message per line. Optional $bag. --}}
@php $bagErrors = $errors->getBag($bag ?? 'default'); @endphp
@if ($bagErrors->any())
  <div class="alert alert-danger">
    @foreach ($bagErrors->all() as $err){{ $err }}<br>@endforeach
  </div>
@endif
