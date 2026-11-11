{{-- "▲ 12% vs September" under a dashboard number. Needs $pct (null = nothing to compare) and $vs; $unit defaults to %. --}}
<div class="change-note">
  @if ($pct === null)
    {{ __('No earlier month to compare') }}
  @elseif (abs($pct) < 0.05)
    {{ __('— Same as :vs', ['vs' => $vs]) }}
  @else
    <span aria-hidden="true">{{ $pct > 0 ? '▲' : '▼' }}</span>
    <span class="visually-hidden">{{ $pct > 0 ? __('Up') : __('Down') }}</span>
    {{ __(':value:value2 vs :vs', ['value' => rtrim(rtrim(number_format(abs($pct), 1), '0'), '.'), 'value2' => $unit ?? '%', 'vs' => $vs]) }}
  @endif
</div>
