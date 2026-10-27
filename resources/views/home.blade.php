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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --navy:#062a4a;          /* hero overlay, footer, headings */
    --primary-dark:#023e8a;
    --primary:#0077b6;
    --aqua:#00b4d8;
    --aqua-soft:#e3f4fa;     /* icon badges */
    --paper:#f4f8fb;         /* alternate section background */
    --card:#ffffff;
    --text:#14283a;          /* body text: ~14:1 on white */
    --text-2:#3e5568;        /* secondary text: ~7.5:1 on white */
    --line:#dde8ef;
  }
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{margin:0;font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:var(--text);background:#fff;
    font-size:15.5px;line-height:1.7;-webkit-font-smoothing:antialiased;}
  a{text-decoration:none;color:inherit;}
  .wrap{max-width:1140px;margin:0 auto;padding:0 28px;}

  /* Kill the browser's default blue tap-highlight/focus-ring flash on click. */
  a, button{-webkit-tap-highlight-color:transparent;}
  a:focus, button:focus{outline:none;}
  a:focus-visible, button:focus-visible{outline:3px solid var(--aqua);outline-offset:3px;}

  /* Brief curtain over the screen right before navigating. */
  #pageTransitionOverlay{position:fixed;inset:0;background:#fff;z-index:9999;opacity:0;pointer-events:none;}
  @media (prefers-reduced-motion: no-preference){
    #pageTransitionOverlay{transition:opacity .06s ease;}
  }
  #pageTransitionOverlay.active{opacity:1;}

  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 26px;border-radius:10px;
    font-weight:600;font-size:15px;line-height:1.2;border:1.5px solid transparent;transition:background .15s,color .15s,border-color .15s,transform .15s;}
  .btn:hover{transform:translateY(-1px);}
  .btn-light{background:#fff;color:var(--primary-dark);}
  .btn-light:hover{background:var(--aqua-soft);}
  .btn-ghost{border-color:rgba(255,255,255,.75);color:#fff;}
  .btn-ghost:hover{background:rgba(255,255,255,.12);}
  .btn-aqua{background:var(--aqua);color:var(--navy);}
  .btn-aqua:hover{background:#38c6e3;}

  /* ---------- Hero: the drone video, under a deep navy overlay so the text reads clearly ---------- */
  .hero{position:relative;overflow:hidden;color:#fff;background:var(--navy) url('{{ asset('assets/drone-poster.jpg') }}') center/cover no-repeat;
    min-height:100vh;min-height:100svh;display:flex;flex-direction:column;}   /* fills the whole first screen */
  .hero .bg-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;pointer-events:none;opacity:0;transition:opacity .6s ease;}
  .hero .bg-video.loaded{opacity:1;}
  .hero-tint{position:absolute;inset:0;z-index:1;pointer-events:none;
    background:
      linear-gradient(90deg, rgba(6,42,74,.94) 0%, rgba(6,42,74,.86) 42%, rgba(6,42,74,.62) 100%),
      linear-gradient(180deg, rgba(6,42,74,.35) 0%, rgba(6,42,74,0) 30%, rgba(6,42,74,.55) 100%);}
  @media (prefers-reduced-motion: reduce){ .hero .bg-video{display:none;} }

  .site-nav{position:relative;z-index:2;}
  .site-nav .wrap{display:flex;align-items:center;justify-content:space-between;height:84px;gap:14px;}
  .brand{display:flex;align-items:center;gap:12px;min-width:0;}
  .brand img{width:50px;height:50px;object-fit:contain;border-radius:50%;background:#fff;}
  .brand .word{font-weight:700;font-size:19px;letter-spacing:.3px;line-height:1.2;}
  .brand .tagline{font-size:11.5px;color:#c7dbea;line-height:1.3;}
  .nav-cta{padding:10px 18px;font-size:14px;}

  .hero-body{position:relative;z-index:2;flex:1;display:flex;align-items:center;}
  .hero-body .wrap{width:100%;padding-top:40px;padding-bottom:72px;}
  .hero-copy{max-width:620px;}
  .eyebrow{display:inline-block;font-size:12.5px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--aqua);margin-bottom:16px;}
  .hero h1{font-size:clamp(36px, 5.2vw, 58px);line-height:1.08;font-weight:700;letter-spacing:-.02em;margin:0 0 20px;text-wrap:balance;}
  .hero .lede{font-size:18px;line-height:1.7;color:#dbe8f2;margin:0 0 32px;max-width:54ch;}
  .cta-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:24px;}
  .trust-line{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#c7dbea;}
  .trust-line i{color:var(--aqua);font-size:17px;}
  /* "There's more below" arrow at the bottom of the hero */
  .scroll-cue{position:absolute;left:50%;bottom:22px;transform:translateX(-50%);z-index:2;width:44px;height:44px;border-radius:50%;
    border:1.5px solid rgba(255,255,255,.55);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;}
  .scroll-cue:hover{background:rgba(255,255,255,.12);}
  @media (prefers-reduced-motion: no-preference){
    .scroll-cue i{animation:cue 1.8s ease-in-out infinite;}
    @keyframes cue{0%,100%{transform:translateY(-2px);}50%{transform:translateY(3px);}}
  }

  /* ---------- Sections ---------- */
  .section{padding:84px 0;}
  .section.alt{background:var(--paper);}
  .sec-head{max-width:640px;margin-bottom:40px;}
  .sec-head .eyebrow{color:var(--primary);margin-bottom:10px;}
  .sec-head h2{font-size:clamp(26px, 3.2vw, 34px);line-height:1.2;font-weight:700;letter-spacing:-.015em;color:var(--navy);margin:0 0 12px;}
  .sec-head p{font-size:16px;color:var(--text-2);margin:0;}

  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;}
  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px 26px;
    box-shadow:0 1px 2px rgba(6,42,74,.04);transition:box-shadow .2s, transform .2s;}
  .card:hover{box-shadow:0 10px 28px rgba(6,42,74,.08);transform:translateY(-2px);}
  .icon{width:48px;height:48px;border-radius:12px;background:var(--aqua-soft);color:var(--primary);
    display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:18px;}
  .card h3{font-size:18px;line-height:1.35;font-weight:600;color:var(--navy);margin:0 0 10px;}
  .card p{font-size:15.5px;line-height:1.7;color:var(--text-2);margin:0;}
  .card p strong{color:var(--text);font-weight:600;}
  .card a{color:var(--primary);font-weight:500;}
  .card a:hover{text-decoration:underline;}

  /* Membership steps: a numbered badge instead of an icon */
  .step-no{width:40px;height:40px;border-radius:50%;background:var(--primary);color:#fff;font-weight:700;font-size:17px;
    display:flex;align-items:center;justify-content:center;margin-bottom:18px;}

  /* Rates: the minimum charge as a big number */
  .big-figure{font-size:38px;font-weight:700;line-height:1.1;color:var(--primary-dark);letter-spacing:-.02em;margin:2px 0 8px;}
  .rate-list{list-style:none;padding:0;margin:0 0 12px;}
  .rate-list li{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px dashed var(--line);font-size:15px;color:var(--text-2);}
  .rate-list li b{color:var(--navy);white-space:nowrap;}
  .example{display:inline-block;background:var(--paper);border-radius:8px;padding:6px 10px;font-size:14px;color:var(--text-2);}

  /* Closing call to action */
  .cta-band{background:linear-gradient(120deg, var(--primary-dark), var(--primary));color:#fff;border-radius:20px;padding:44px 40px;
    display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;}
  .cta-band h2{font-size:clamp(22px, 2.6vw, 28px);margin:0 0 6px;line-height:1.25;}
  .cta-band p{margin:0;color:#d6e8f5;font-size:15.5px;}

  footer.site-foot{background:var(--navy);color:#b9cddc;font-size:14px;padding:30px 0;}
  footer.site-foot .wrap{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;}
  footer.site-foot a{color:#fff;}
  footer.site-foot a:hover{text-decoration:underline;}

  @media (max-width: 900px){
    .grid{grid-template-columns:1fr;gap:16px;}
    .section{padding:60px 0;}
    .hero-tint{background:linear-gradient(180deg, rgba(6,42,74,.88), rgba(6,42,74,.80) 60%, rgba(6,42,74,.9));}
  }
  /* Phones: compact header, full-width buttons */
  @media (max-width: 560px){
    body{font-size:15px;}
    .wrap{padding:0 18px;}
    .site-nav .wrap{height:68px;gap:10px;}
    .brand{gap:9px;}
    .brand img{width:40px;height:40px;}
    .brand .word{font-size:16.5px;}
    .brand .tagline{font-size:10px;}
    .btn.nav-cta{padding:8px 12px;font-size:12.5px;white-space:nowrap;flex:none;}
    .hero-body .wrap{padding-top:28px;padding-bottom:88px;}
    .hero .lede{font-size:16px;}
    .cta-row .btn{flex:1 1 100%;}
    .card{padding:22px 20px;}
    .cta-band{padding:30px 22px;}
    .cta-band .btn{width:100%;}
  }
</style>
</head>
<body>

  <div id="pageTransitionOverlay" aria-hidden="true"></div>

  @php
    $m3 = fn ($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
    $peso = fn ($v) => '₱' . number_format((float)$v, 2);
    $ordinal = fn ($n) => $n . (in_array($n % 100, [11, 12, 13]) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
  @endphp

  <header class="hero">
    <video class="bg-video" autoplay muted loop playsinline preload="auto" poster="{{ asset('assets/drone-poster.jpg') }}" aria-hidden="true">
      <source src="{{ asset('assets/drone.mp4') }}?v={{ @filemtime(public_path('assets/drone.mp4')) ?: '1' }}" type="video/mp4">
    </video>
    <div class="hero-tint" aria-hidden="true"></div>

    <nav class="site-nav" aria-label="Main">
      <div class="wrap">
        <div class="brand">
          <img src="{{ asset('assets/img/logo.png') }}" alt="AWAS logo">
          <div>
            <div class="word">AWAS</div>
            <div class="tagline">Adlay Water Augmentation System</div>
          </div>
        </div>
        <a class="btn btn-aqua nav-cta" href="{{ route('apply') }}">Apply Membership</a>
      </div>
    </nav>

    <div class="hero-body">
      <div class="wrap">
        <div class="hero-copy">
          <span class="eyebrow">Barangay Adlay Water Service</span>
          <h1>Every drop, accounted for.</h1>
          <p class="lede">AWAS gives Barangay Adlay one place to record meter readings, generate bills, and take payments — so nothing is written on paper twice, and no one is billed by guesswork.</p>
          <div class="cta-row">
            <a class="btn btn-light" href="{{ route('login') }}"><i class="ph ph-sign-in" aria-hidden="true"></i> Login</a>
            <a class="btn btn-ghost" href="{{ route('register') }}">Create Account</a>
          </div>
          <div class="trust-line"><i class="ph ph-users-three" aria-hidden="true"></i> For barangay staff, water association personnel, and registered residents.</div>
        </div>
      </div>
    </div>
    <a class="scroll-cue" href="#features" aria-label="Scroll down to learn more"><i class="ph ph-caret-down" aria-hidden="true"></i></a>
  </header>

  <main>
    {{-- What AWAS does --}}
    <section class="section" id="features">
      <div class="wrap">
        <div class="sec-head">
          <span class="eyebrow">What AWAS does</span>
          <h2>Readings, bills and payments in one place</h2>
          <p>From the meter to your receipt, every step is recorded once and kept up to date.</p>
        </div>
        <div class="grid">
          <div class="card">
            <div class="icon"><i class="ph ph-drop" aria-hidden="true"></i></div>
            <h3>Digital meter reading</h3>
            <p>Field staff log current and previous readings for each household; consumption is calculated the moment it's saved.</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-invoice" aria-hidden="true"></i></div>
            <h3>Automated billing</h3>
            <p>Bills are computed against the barangay's configured rate tiers — no manual math, no spreadsheets to reconcile.</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-wallet" aria-hidden="true"></i></div>
            <h3>Online payments</h3>
            <p>Residents review their bill and pay online with GCash; the account updates on its own once the payment is confirmed.</p>
          </div>
        </div>
      </div>
    </section>

    {{-- How to become a member --}}
    <section class="section alt" id="how">
      <div class="wrap">
        <div class="sec-head">
          <span class="eyebrow">Membership</span>
          <h2>How to become a member</h2>
          <p>Three steps, all online. You only need a valid government ID and an email address.</p>
        </div>
        <div class="grid">
          <div class="card">
            <div class="step-no">1</div>
            <h3>Apply online</h3>
            <p>Tap <strong>Apply Membership</strong> and fill in your name, purok, contact number and household details.</p>
          </div>
          <div class="card">
            <div class="step-no">2</div>
            <h3>Verify your identity</h3>
            <p>Take photos of the front and back of a valid government ID and a quick selfie, then confirm your email with a 6-digit code.</p>
          </div>
          <div class="card">
            <div class="step-no">3</div>
            <h3>Get approved</h3>
            <p>The water office reviews your application and assigns your meter. Sign in anytime to check its status, then view and pay your bills.</p>
          </div>
        </div>
      </div>
    </section>

    {{-- Water rates (live from System Settings) --}}
    <section class="section" id="rates">
      <div class="wrap">
        <div class="sec-head">
          <span class="eyebrow">Water rates</span>
          <h2>What you pay each month</h2>
          <p>Rates are set by the barangay water office and are the same for every household.</p>
        </div>
        <div class="grid">
          <div class="card">
            <div class="icon"><i class="ph ph-drop-half-bottom" aria-hidden="true"></i></div>
            <h3>Minimum charge</h3>
            <div class="big-figure">{{ $peso($tariff['minimumCharge']) }}</div>
            <p>Covers the first <strong>{{ $m3($tariff['includedCum']) }} m³</strong> of water each month.</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-chart-line-up" aria-hidden="true"></i></div>
            <h3>Water used beyond {{ $m3($tariff['includedCum']) }} m³</h3>
            <ul class="rate-list">
              @foreach ($tariff['tiers'] as $t)
                <li><span>{{ $t['to'] === null ? 'Above ' . $m3($t['lower']) . ' m³' : 'Over ' . $m3($t['lower']) . ' up to ' . $m3($t['to']) . ' m³' }}</span><b>{{ $peso($t['rate']) }} / m³</b></li>
              @endforeach
            </ul>
            <span class="example">Example: {{ $m3($example) }} m³ = <strong>{{ $peso($exampleBill['total_amount']) }}</strong></span>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-calendar-check" aria-hidden="true"></i></div>
            <h3>Discounts and due dates</h3>
            <p>
              @if ($tariff['seniorPct'] > 0)Senior citizens get <strong>{{ $m3($tariff['seniorPct']) }}% off</strong>. @endif
              Bills are due on the <strong>{{ $ordinal($dueDay) }}</strong> of the following month{{ $latePct > 0 ? '; late payments get a ' . $m3($latePct) . '% penalty' : '' }}.
              Accounts unpaid {{ $discMonths }} {{ $discMonths === 1 ? 'month' : 'months' }} after the due date may be disconnected.
            </p>
          </div>
        </div>
      </div>
    </section>

    {{-- Paying your bill --}}
    <section class="section alt" id="pay">
      <div class="wrap">
        <div class="sec-head">
          <span class="eyebrow">Payments</span>
          <h2>Paying your bill</h2>
          <p>Pay online with GCash, or in person at the water office.</p>
        </div>
        <div class="grid">
          <div class="card">
            <div class="icon"><i class="ph ph-qr-code" aria-hidden="true"></i></div>
            <h3>Online, with GCash</h3>
            <p>Sign in, open <strong>Current Bills</strong> and tap <strong>Pay Bill</strong>. Tap <strong>Open GCash app</strong> to pay in GCash — your bill turns Paid by itself — or scan the barangay's GCash QR and upload your receipt for the water office to confirm.</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-storefront" aria-hidden="true"></i></div>
            <h3>At the water office</h3>
            <p>Pay in cash, or scan the office's QR code with GCash. Bring your meter number or name to find your bill quickly.</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-receipt" aria-hidden="true"></i></div>
            <h3>Receipts and history</h3>
            <p>Every payment shows in your account right away. Download your receipt and see past bills under <strong>Billing History</strong> and <strong>Payment History</strong>.</p>
          </div>
        </div>
      </div>
    </section>

    {{-- Common questions --}}
    <section class="section" id="faq">
      <div class="wrap">
        <div class="sec-head">
          <span class="eyebrow">Help</span>
          <h2>Common questions</h2>
        </div>
        <div class="grid">
          <div class="card">
            <div class="icon"><i class="ph ph-gauge" aria-hidden="true"></i></div>
            <h3>How is my water use measured?</h3>
            <p>Each month the meter reader records your meter. Your consumption is the present reading minus the previous reading, in cubic meters (m³).</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-lock-key-open" aria-hidden="true"></i></div>
            <h3>Forgot your password?</h3>
            <p>Tap <strong>Forgot your password?</strong> on the login page and enter your account's Gmail — we'll email a code to set a new one. This also unlocks a locked account.</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-shield-check" aria-hidden="true"></i></div>
            <h3>Is my information safe?</h3>
            <p>Your information is used only for the water service and is protected under the Data Privacy Act of 2012. Read our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
          </div>
        </div>
      </div>
    </section>

    {{-- Contact --}}
    <section class="section alt" id="contact">
      <div class="wrap">
        <div class="sec-head">
          <span class="eyebrow">Contact</span>
          <h2>Contact the water office</h2>
          <p>Report a wrong reading or a water leak, or ask about your account.</p>
        </div>
        <div class="grid">
          <div class="card">
            <div class="icon"><i class="ph ph-phone" aria-hidden="true"></i></div>
            <h3>Call or text</h3>
            <p>@if ($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>@else Visit the water office. @endif</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-envelope-simple" aria-hidden="true"></i></div>
            <h3>Email</h3>
            <p>@if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@else Visit the water office. @endif</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-map-pin" aria-hidden="true"></i></div>
            <h3>Visit</h3>
            <p>AWAS Water Office, {{ $barangay }}, Carrascal, Surigao del Sur.</p>
          </div>
        </div>

        <div class="cta-band" style="margin-top:56px;">
          <div>
            <h2>Not a member yet?</h2>
            <p>Apply online in a few minutes — no need to visit the office first.</p>
          </div>
          <a class="btn btn-light" href="{{ route('apply') }}">Apply Membership <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-foot">
    <div class="wrap">
      <span>&copy; {{ date('Y') }} AWAS — Adlay Water Augmentation System, {{ $barangay }}</span>
      <span><a href="{{ route('privacy') }}">Privacy Policy</a> · <a href="{{ route('terms') }}">Terms</a></span>
    </div>
  </footer>

<script src="{{ asset('assets/js/transitions.js') }}"></script>
</body>
</html>
