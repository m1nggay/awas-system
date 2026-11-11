{{-- English / Tagalog switch for the public pages (styles: .lang-switch in style.css, and in home.blade.php).
     $light: dark text, for pages with a white header. --}}
@php($lang = app()->getLocale())
<div @class(['lang-switch', 'on-light' => $light ?? false]) role="group" aria-label="Language / Wika">
  <a href="{{ route('locale', 'en') }}" lang="en" hreflang="en" title="English" @class(['on' => $lang === 'en']) @if ($lang === 'en') aria-current="true" @endif>EN</a>
  <a href="{{ route('locale', 'tl') }}" lang="tl" hreflang="tl" title="Tagalog" @class(['on' => $lang === 'tl']) @if ($lang === 'tl') aria-current="true" @endif>TL</a>
</div>
