{{-- Password requirement reminder with a live checklist.
     Usage: @include('partials.password-hint', ['for' => 'password'])  — the id of the password input. --}}
<div class="password-hint" data-password-hint="{{ $for }}" style="font-size:12px;margin-top:6px;">
  <div class="text-muted mb-1">Password must contain at least 8 characters, including an uppercase letter, a number, and a symbol.</div>
  <ul class="list-unstyled mb-0" style="display:flex;flex-wrap:wrap;gap:4px 14px;">
    <li data-rule="length">○ 8+ characters</li>
    <li data-rule="capital">○ an uppercase letter (A–Z)</li>
    <li data-rule="number">○ a number</li>
    <li data-rule="symbol">○ a symbol (e.g. ! @ # ?)</li>
  </ul>
</div>
