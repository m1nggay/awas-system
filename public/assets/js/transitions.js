/**
 * transitions.js — a brief plain-white curtain (#pageTransitionOverlay)
 * covers the screen right before navigating, so internal link clicks
 * don't feel like a plain, instant jump. Deliberately NOT a fade of the
 * page's own content — that would reveal/hide whatever color the page
 * happens to be (e.g. the blue auth pages). Pure progressive enhancement:
 * if this script fails to load, links just navigate normally.
 */
(function () {
  const overlay = document.getElementById('pageTransitionOverlay');

  document.addEventListener('click', function (e) {
    // Let other handlers (e.g. data-confirm dialogs) veto the click first.
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    const link = e.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

    let url;
    try {
      url = new URL(href, window.location.href);
    } catch (err) {
      return;
    }
    if (url.origin !== window.location.origin) return; // leave external links alone
    if (!overlay) return; // no overlay on this page — just navigate normally

    e.preventDefault();
    overlay.classList.add('active');
    setTimeout(function () {
      window.location.href = url.href;
    }, 60); // matches the .06s overlay fade in style.css / index.php
  });

  // If the page is restored from the back/forward cache mid-transition,
  // undo it so the user isn't stuck looking at a white screen.
  window.addEventListener('pageshow', function (e) {
    if (overlay) overlay.classList.remove('active');
    // A page restored with the Back button still holds an old security token
    // (e.g. the login form after signing out) — reload it so forms get a fresh one.
    if (e.persisted && document.querySelector('input[name="_token"]')) location.reload();
  });
})();

/**
 * Crossfades each background video in only once it actually has a frame
 * ready, instead of letting it "pop" over the solid fallback color the
 * instant the (large) file finishes loading — see .bg-video in style.css.
 */
(function () {
  document.querySelectorAll('video.bg-video').forEach(function (video) {
    if (video.readyState >= 2) { // HAVE_CURRENT_DATA or better — a frame is already decoded
      video.classList.add('loaded');
      return;
    }
    video.addEventListener('loadeddata', function () {
      video.classList.add('loaded');
    }, { once: true });
  });
})();
