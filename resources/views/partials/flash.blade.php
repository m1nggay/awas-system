@if ($flash = session('flash'))
  <div class="alert alert-{{ $flash['type'] }}" data-autohide>{{ $flash['message'] }}</div>
@endif
