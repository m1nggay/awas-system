{{-- Meter Reader: which puroks they cover (or a warning when none are assigned yet). --}}
@if (auth()->user()->isMeterReader())
  @php
    $myPuroks = \Illuminate\Support\Facades\DB::table('meter_reader_puroks as a')->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
        ->where('a.user_id', auth()->user()->user_id)->orderBy('p.purok_name')->pluck('p.purok_name');
  @endphp
  @if ($myPuroks->isEmpty())
    <div class="alert alert-warning">No puroks are assigned to your account yet, so there are no consumers to read. Please ask the administrator to assign your puroks.</div>
  @else
    <div class="alert alert-info py-2" style="font-size:13.5px;">📍 Assigned puroks: <strong>{{ $myPuroks->implode(', ') }}</strong></div>
  @endif
@endif
