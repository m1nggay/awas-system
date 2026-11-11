{{-- Tagalog for the scripts in public/assets/js (lang/js/tl.json): __t('English text', {name: value}) returns
     the text in the page's language, with :name placeholders filled in. English pages get an empty list. --}}
@php($jsLang = is_file($file = lang_path('js/' . app()->getLocale() . '.json')) ? json_decode(file_get_contents($file), true) : [])
<script>
  window.AWAS_LANG = @json((object)($jsLang ?: []));
  window.__t = function (text, replace) {
    var out = window.AWAS_LANG[text] || text;
    for (var key in (replace || {})) out = out.split(':' + key).join(replace[key]);
    return out;
  };
</script>
