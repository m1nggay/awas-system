{{-- Password requirement reminder with a live checklist.
     Usage: @include('partials.password-hint', ['for' => 'password'])  — the id of the password input. --}}
<div class="password-hint" data-password-hint="{{ $for }}" style="font-size:12px;margin-top:6px;">
  <ul class="list-unstyled mb-0" style="display:flex;flex-wrap:wrap;gap:4px 14px;">
    <li data-rule="length">{{ __('○ 8+ characters') }}</li>
    <li data-rule="capital">{{ __('○ an uppercase letter (A–Z)') }}</li>
    <li data-rule="number">{{ __('○ a number') }}</li>
    <li data-rule="symbol">{{ __('○ a symbol (e.g. ! @ # ?)') }}</li>
  </ul>
</div>
