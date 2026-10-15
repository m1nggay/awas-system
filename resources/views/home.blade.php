<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
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
    /* A slight blur on the video itself (one layer, cheap to draw — no lag like blurred cards). Scaled a little so the blurred edges stay off-screen. */
    filter:blur(2px);transform:scale(1.04);
  }
  .bg-video.loaded{opacity:1;}
  .bg-video-tint{
    position:fixed;inset:0;z-index:0;pointer-events:none;
    /* The same soft white over the whole video, like its left side. */
    background:rgba(246,251,253,0.55);
  }

  .site-nav{position:relative;z-index:2;border-bottom:1px solid var(--line);}
  .site-nav .wrap{display:flex;align-items:center;justify-content:space-between;height:76px;}
  .brand{display:flex;align-items:center;gap:11px;}
  .brand img{width:52px;height:52px;object-fit:contain;border-radius:8px;}
  .brand .word{font-weight:700;font-size:18px;letter-spacing:0.2px;}
  .brand .tagline{font-size:10.5px;color:var(--muted);margin-top:-2px;}
  .nav-cta{padding:10px 20px;font-size:13.5px;border-radius:8px;}

  .hero{position:relative;z-index:2;}
  .hero .wrap{padding:64px 28px 22px;}
  .eyebrow-line{font-size:13.5px;color:#062a4d;font-weight:600;margin-bottom:18px;}
  .hero h1{font-size:45px;line-height:1.12;font-weight:700;letter-spacing:-0.5px;margin:0 0 20px;}
  .hero p.lede{font-size:16px;line-height:1.65;color:#04263f;font-weight:500;max-width:46ch;margin:0 0 30px;}
  .cta-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px;}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;padding:13px 26px;
    border-radius:8px;font-weight:600;font-size:14.5px;border:1.5px solid transparent;
  }
  .btn-solid{background:var(--primary-dark);color:#fff;}
  .btn-outline{border-color:var(--ink);color:var(--ink);}
  .trust-line{font-size:12.5px;color:#062a4d;font-weight:600;}

  /* Information in soft frosted cards: easy to read, with the drone video showing around them. */
  .features{position:relative;z-index:2;}
  .features .wrap{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;padding:26px 28px;}
  .feature{
    padding:22px 22px 24px;border-radius:16px;
    background:rgba(255,255,255,0.84);   /* no blur: blurring over a playing video makes the page lag */
    border:1px solid rgba(255,255,255,0.7);
    box-shadow:0 8px 24px rgba(4,38,63,0.08);
    transition:transform .2s ease, box-shadow .2s ease;
  }
  .feature:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(4,38,63,0.13);}
  .feature .glyph{width:42px;height:42px;border-radius:12px;background:#e1f1f8;color:var(--primary-dark);display:flex;align-items:center;justify-content:center;font-size:21px;margin-bottom:14px;}
  .feature h3{font-size:15.5px;font-weight:600;margin:0 0 8px;color:var(--ink);}
  .feature p{font-size:14px;line-height:1.65;color:#2e4659;margin:0;}

  /* Section titles: a small white pill above each group of cards. */
  .features .sec-title{
    grid-column:1 / -1;justify-self:start;margin:14px 0 0;
    padding:7px 16px;border-radius:999px;
    background:rgba(255,255,255,0.9);
    color:var(--primary-dark);font-size:13px;font-weight:600;letter-spacing:.3px;
    box-shadow:0 4px 14px rgba(4,38,63,0.08);
  }

  /* Contact: plain text, no cards; dark, bold text stands out over the video. */
  .features.plain .feature{background:none;border:0;box-shadow:none;padding:4px 2px;}
  .features.plain .feature:hover{transform:none;box-shadow:none;}
  .features.plain .feature h3{color:#021b33;font-weight:700;}
  .features.plain .feature p{color:#021b33;font-weight:600;}
  .features.plain .feature a{color:var(--primary-dark);font-weight:600;}

  footer.site-foot{position:relative;z-index:2;padding:22px 28px 30px;font-size:12px;color:#062a4d;font-weight:500;}

  @media (prefers-reduced-motion: reduce){ .feature, .feature:hover{transition:none;transform:none;} }
  @media (max-width: 880px){
    .hero .wrap{padding-top:48px;}
    .hero h1{font-size:34px;}
    .features .wrap{grid-template-columns:1fr;gap:12px;}
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
    .hero .wrap{padding:32px 18px 16px;}
    .eyebrow-line{font-size:12.5px;margin-bottom:12px;}
    .hero h1{font-size:30px;margin-bottom:14px;}
    .hero p.lede{font-size:15px;margin-bottom:24px;}
    .features .wrap{padding:18px 18px;}
    .feature{padding:18px 18px 20px;}
    footer.site-foot{padding:18px 18px 26px;}
  }
</style>
</head>
<body>

  <div id="pageTransitionOverlay" aria-hidden="true"></div>

  <video class="bg-video" autoplay muted loop playsinline preload="auto" poster="{{ asset('assets/drone-poster.jpg') }}" aria-hidden="true">
    <source src="{{ asset('assets/drone.mp4') }}?v={{ @filemtime(public_path('assets/drone.mp4')) ?: '1' }}" type="video/mp4">
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
          <a class="btn btn-outline" href="{{ route('register') }}">Create Account</a>
        </div>
        <div class="trust-line">For barangay staff, water association personnel, and registered residents.</div>
      </div>
    </div>
  </header>

  <section class="features">
    <div class="wrap">
      <div class="feature">
        <div class="glyph"><i class="ph ph-drop" aria-hidden="true"></i></div>
        <h3>Digital meter reading</h3>
        <p>Field staff log current and previous readings for each household; consumption is calculated the moment it's saved.</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-invoice" aria-hidden="true"></i></div>
        <h3>Automated billing</h3>
        <p>Bills are computed against the barangay's configured rate tiers — no manual math, no spreadsheets to reconcile.</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-wallet" aria-hidden="true"></i></div>
        <h3>Online payments</h3>
        <p>Residents review their bill and submit payment online; staff verify it and the account updates on its own.</p>
      </div>
    </div>
  </section>

  @php
    $m3 = fn ($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
    $peso = fn ($v) => '₱' . number_format((float)$v, 2);
    $ordinal = fn ($n) => $n . (in_array($n % 100, [11, 12, 13]) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
  @endphp

  {{-- How to become a member --}}
  <section class="features" id="how">
    <div class="wrap">
      <div class="eyebrow-line sec-title">How to become a member</div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-note-pencil" aria-hidden="true"></i></div>
        <h3>1. Apply online</h3>
        <p>Tap <strong>Apply Membership</strong> and fill in your name, purok, contact number and household details.</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-identification-card" aria-hidden="true"></i></div>
        <h3>2. Verify your identity</h3>
        <p>Take photos of the front and back of a valid government ID and a quick selfie, then confirm your email with a 6-digit code.</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-check-circle" aria-hidden="true"></i></div>
        <h3>3. Get approved</h3>
        <p>The water office reviews your application and assigns your meter. Sign in anytime to check its status, then view and pay your bills.</p>
      </div>
    </div>
  </section>

  {{-- Water rates (live from System Settings) --}}
  <section class="features" id="rates">
    <div class="wrap">
      <div class="eyebrow-line sec-title">Water rates</div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-drop-half-bottom" aria-hidden="true"></i></div>
        <h3>Minimum charge: {{ $peso($tariff['minimumCharge']) }}</h3>
        <p>Covers the first {{ $m3($tariff['includedCum']) }} m³ of water each month.</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-chart-line-up" aria-hidden="true"></i></div>
        <h3>Water used beyond {{ $m3($tariff['includedCum']) }} m³</h3>
        <p>
          @foreach ($tariff['tiers'] as $t)
            {{ $t['to'] === null ? 'Above ' . $m3($t['lower']) . ' m³' : 'Over ' . $m3($t['lower']) . ' up to ' . $m3($t['to']) . ' m³' }}: {{ $peso($t['rate']) }} per m³.{!! $loop->last ? '' : '<br>' !!}
          @endforeach
          <br>Example: {{ $m3($example) }} m³ = {{ $peso($exampleBill['total_amount']) }}.
        </p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-calendar-check" aria-hidden="true"></i></div>
        <h3>Discounts and due dates</h3>
        <p>
          @if ($tariff['seniorPct'] > 0)Senior citizens get {{ $m3($tariff['seniorPct']) }}% off. @endif
          Bills are due on the {{ $ordinal($dueDay) }} of the following month{{ $latePct > 0 ? '; late payments get a ' . $m3($latePct) . '% penalty' : '' }}.
          Accounts unpaid {{ $discMonths }} {{ $discMonths === 1 ? 'month' : 'months' }} after the due date may be disconnected.
        </p>
      </div>
    </div>
  </section>

  {{-- Paying your bill --}}
  <section class="features" id="pay">
    <div class="wrap">
      <div class="eyebrow-line sec-title">Paying your bill</div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-qr-code" aria-hidden="true"></i></div>
        <h3>Online, with a QR code</h3>
        <p>Sign in, open <strong>Current Bills</strong> and tap <strong>Pay Bill</strong>. Scan the QR code with GCash, Maya or any bank app that supports QR Ph — your bill turns Paid by itself.</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-storefront" aria-hidden="true"></i></div>
        <h3>At the water office</h3>
        <p>Pay in cash, or scan the office's QR code with your e-wallet. Bring your meter number or name to find your bill quickly.</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-receipt" aria-hidden="true"></i></div>
        <h3>Receipts and history</h3>
        <p>Every payment shows in your account right away. Download your receipt and see past bills under <strong>Billing History</strong> and <strong>Payment History</strong>.</p>
      </div>
    </div>
  </section>

  {{-- Contact --}}
  <section class="features plain" id="contact">
    <div class="wrap">
      <div class="eyebrow-line sec-title">Contact the water office</div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-phone" aria-hidden="true"></i></div>
        <h3>Call or text</h3>
        <p>@if ($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>@else Visit the water office. @endif</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-envelope-simple" aria-hidden="true"></i></div>
        <h3>Email</h3>
        <p>@if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@else Visit the water office. @endif</p>
      </div>
      <div class="feature">
        <div class="glyph"><i class="ph ph-map-pin" aria-hidden="true"></i></div>
        <h3>Visit</h3>
        <p>AWAS Water Office, {{ $barangay }}, Carrascal, Surigao del Sur. Report a wrong reading or a water leak here.</p>
      </div>
    </div>
  </section>

  <footer class="site-foot">
    <div class="wrap">
      &copy; {{ date('Y') }} AWAS — Adlay Water Augmentation System · <a href="{{ route('privacy') }}" style="color:inherit;">Privacy Policy</a> · <a href="{{ route('terms') }}" style="color:inherit;">Terms</a>
    </div>
  </footer>

<script src="{{ asset('assets/js/transitions.js') }}"></script>
</body>
</html>
