{{-- "▲ 12% vs September" under a dashboard number. Needs $pct (null = nothing to compare) and $vs; $unit defaults to %. --}}
<div class="change-note">
  @if ($pct === null)
    No earlier month to compare
  @elseif (abs($pct) < 0.05)
    — Same as {{ $vs }}
  @else
    <span aria-hidden="true">{{ $pct > 0 ? '▲' : '▼' }}</span>
    <span class="visually-hidden">{{ $pct > 0 ? 'Up' : 'Down' }}</span>
    {{ rtrim(rtrim(number_format(abs($pct), 1), '0'), '.') }}{{ $unit ?? '%' }} vs {{ $vs }}
  @endif
</div>
