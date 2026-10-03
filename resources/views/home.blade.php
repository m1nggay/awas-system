<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AWAS: Adlay Water Augmentation System</title>
<link rel="icon" type="image/png" href="{{ asset('assets/img/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#04263f;
    --dial-navy:#073763;
    --primary-dark:#023e8a;
    --primary:#0077b6;
    --primary-light:#00b4d8;
    --brass:#e0a327;
    --paper:#f6fbfd;
    --line:rgba(4,38,63,0.12);
    --muted:#5c7488;
  }
  *{box-sizing:border-box;}
  body{margin:0;font-family:'Poppins',sans-serif;color:var(--ink);background:var(--paper);}
  a{text-decoration:none;color:inherit;}
  .wrap{max-width:1120px;margin:0 auto;padding:0 28px;}

  /* Kill the browser's default blue tap-highlight/focus-ring flash on click. */
  a, button{-webkit-tap-highlight-color:transparent;}
  a:focus, button:focus{outline:none;}
  a:focus-visible, button:focus-visible{outline:2px solid var(--primary-light);outline-offset:2px;}

  /* Brief curtain over the screen right before navigating. */
  #pageTransitionOverlay{position:fixed;inset:0;background:var(--paper);z-index:9999;opacity:0;pointer-events:none;}
  @media (prefers-reduced-motion: no-preference){
    #pageTransitionOverlay{transition:opacity .06s ease;}
  }
  #pageTransitionOverlay.active{opacity:1;}

  .bg-video{
    position:fixed;inset:0;width:100%;height:100%;object-fit:cover;
    z-index:0;pointer-events:none;
    opacity:0;transition:opacity .6s ease;
  }
  .bg-video.loaded{opacity:1;}
  .bg-video-tint{
    position:fixed;inset:0;z-index:0;pointer-events:none;
    background:linear-gradient(180deg,rgba(246,251,253,0.55),rgba(246,251,253,0.68));
  }

  .site-nav{position:relative;z-index:2;border-bottom:1px solid var(--line);}
  .site-nav .wrap{display:flex;align-items:center;justify-content:space-between;height:76px;}
  .brand{display:flex;align-items:center;gap:11px;}
  .brand img{width:52px;height:52px;object-fit:contain;border-radius:8px;}
  .brand .word{font-weight:700;font-size:18px;letter-spacing:0.2px;}
  .brand .tagline{font-size:10.5px;color:var(--muted);margin-top:-2px;}
  .nav-cta{padding:10px 20px;font-size:13.5px;border-radius:8px;}

  .hero{position:relative;z-index:2;}
  .hero .wrap{padding:76px 28px 64px;}
  .eyebrow-line{font-size:13.5px;color:var(--muted);margin-bottom:18px;}
  .hero h1{font-size:45px;line-height:1.12;font-weight:700;letter-spacing:-0.5px;margin:0 0 20px;}
  .hero p.lede{font-size:16px;line-height:1.65;color:#37536b;max-width:46ch;margin:0 0 30px;}
  .cta-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px;}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;padding:13px 26px;
    border-radius:8px;font-weight:600;font-size:14.5px;border:1.5px solid transparent;
  }
  .btn-solid{background:var(--primary-dark);color:#fff;}
  .btn-outline{border-color:var(--ink);color:var(--ink);}
  .trust-line{font-size:12.5px;color:var(--muted);}

  .features{position:relative;z-index:2;border-top:1px solid var(--line);}
  .features .wrap{display:grid;grid-template-columns:repeat(3,1fr);padding:44px 28px;}
  .feature{padding:0 26px;border-left:1px solid var(--line);}
  .feature:first-child{border-left:none;padding-left:0;}
  .feature .glyph{font-size:20px;margin-bottom:12px;}
  .feature h3{font-size:15px;font-weight:600;margin:0 0 8px;}
  .feature p{font-size:13.5px;line-height:1.6;color:var(--muted);margin:0;}

  footer.site-foot{position:relative;z-index:2;padding:22px 28px 30px;font-size:12px;color:var(--muted);}

  @media (max-width: 880px){
    .hero .wrap{padding-top:48px;}
    .hero h1{font-size:34px;}
    .features .wrap{grid-template-columns:1fr;gap:26px;}
    .feature{border-left:none;padding-left:0;border-top:1px solid var(--line);padding-top:22px;}
    .feature:first-child{border-top:none;padding-top:0;}
  }
  /* Phones: compact header, full-width buttons */
  @media (max-width: 560px){
    .wrap{padding:0 18px;}
    .site-nav .wrap{height:64px;gap:10px;}
    .brand{gap:8px;min-width:0;}
    .brand img{width:40px;height:40px;}
    .brand .word{font-size:16px;}
    .brand .tagline{font-size:9.5px;line-height:1.25;}
    .btn.nav-cta{padding:8px 12px;font-size:12.5px;white-space:nowrap;flex:none;}
    .hero .wrap{padding:36px 18px 44px;}
    .eyebrow-line{font-size:12.5px;margin-bottom:12px;}
    .hero h1{font-size:30px;margin-bottom:14px;}
    .hero p.lede{font-size:15px;margin-bottom:24px;}
    .features .wrap{padding:32px 18px;}
    footer.site-foot{padding:18px 18px 26px;}
  }
</style>
</head>
<body>

  <div id="pageTransitionOverlay" aria-hidden="true"></div>

  <video class="bg-video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
    <source src="{{ asset('assets/drone.mp4') }}" type="video/mp4">
  </video>
  <div class="bg-video-tint" aria-hidden="true"></div>

  <nav class="site-nav">
    <div class="wrap">
      <div class="brand">
        <img src="{{ asset('assets/img/logo.png') }}" alt="AWAS logo">
        <div>
          <div class="word">AWAS</div>
          <div class="tagline">Adlay Water Augmentation System</div>
        </div>
      </div>
      <a class="btn btn-solid nav-cta" href="{{ route('apply') }}">Apply Membership</a>
    </div>
  </nav>

  <header class="hero">
    <div class="wrap">
      <div>
        <div class="eyebrow-line">AWAS: Adlay Water Augmentation System</div>
        <h1>Every drop, accounted for.</h1>
        <p class="lede">AWAS gives Barangay Adlay one place to record meter readings, generate bills, and take payments — so nothing is written on paper twice, and no one is billed by guesswork.</p>
        <div class="cta-row">
          <a class="btn btn-solid" href="{{ route('login') }}">Login</a>
          <a class="btn btn-outline" href="{{ route('register') }}">Create a resident account</a>
        </div>
        <div class="trust-line">For barangay staff, water association personnel, and registered residents.</div>
      </div>
    </div>
  </header>

  <section class="features">
    <div class="wrap">
      <div class="feature">
        <div class="glyph">💧</div>
        <h3>Digital meter reading</h3>
        <p>Field staff log current and previous readings for each household; consumption is calculated the moment it's saved.</p>
      </div>
      <div class="feature">
        <div class="glyph">🧾</div>
        <h3>Automated billing</h3>
        <p>Bills are computed against the barangay's configured rate tiers — no manual math, no spreadsheets to reconcile.</p>
      </div>
      <div class="feature">
        <div class="glyph">💳</div>
        <h3>Online payments</h3>
        <p>Residents review their bill and submit payment online; staff verify it and the account updates on its own.</p>
      </div>
    </div>
  </section>

  <footer class="site-foot">
    <div class="wrap">
      &copy; {{ date('Y') }} AWAS — Adlay Water Augmentation System
    </div>
  </footer>

<script src="{{ asset('assets/js/transitions.js') }}"></script>
</body>
</html>
