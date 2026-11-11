<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AWAS: Adlay Water Augmentation System</title>
<link rel="icon" type="image/png" href="{{ asset('assets/img/logo-round.png') }}">
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
  section[id]{scroll-margin-top:12px;}

  /* Kill the browser's default blue tap-highlight/focus-ring flash on click. */
  a, button, summary{-webkit-tap-highlight-color:transparent;}
  a:focus, button:focus, summary:focus{outline:none;}
  a:focus-visible, button:focus-visible, summary:focus-visible{outline:3px solid var(--aqua);outline-offset:3px;}

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
  .btn-primary{background:var(--primary);color:#fff;}
  .btn-primary:hover{background:var(--primary-dark);}

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
  .site-nav .wrap{display:flex;align-items:center;justify-content:space-between;height:84px;gap:20px;}
  .brand{display:flex;align-items:center;gap:12px;min-width:0;flex:none;}
  .brand img{width:52px;height:52px;object-fit:cover;border-radius:50%;box-shadow:0 0 0 2px rgba(255,255,255,.85);}
  .brand .word{font-weight:700;font-size:19px;letter-spacing:.3px;line-height:1.2;}
  .brand .tagline{font-size:11.5px;color:#c7dbea;line-height:1.3;}
  .nav-links{display:flex;gap:4px;margin-left:auto;}
  .nav-links a{font-size:14px;font-weight:500;color:#dbe8f2;padding:8px 12px;border-radius:8px;white-space:nowrap;}
  .nav-links a:hover{color:#fff;background:rgba(255,255,255,.1);}
  /* English / Tagalog switch: stays visible on phones, where the links above are hidden */
  .lang-switch{display:flex;flex:none;padding:3px;border:1px solid rgba(255,255,255,.35);border-radius:99px;}
  .lang-switch a{font-size:12.5px;font-weight:600;letter-spacing:.04em;color:#dbe8f2;padding:4px 11px;border-radius:99px;line-height:1.4;}
  .lang-switch a:hover{color:#fff;background:rgba(255,255,255,.12);}
  .lang-switch a.on{background:#fff;color:var(--navy);}
  /* Wide screens: the menu and switch sit a little further right, into the page margin */
  @media (min-width: 1061px){ .site-nav .lang-switch{margin-right:-24px;} }

  .hero-body{position:relative;z-index:2;flex:1;display:flex;align-items:center;}
  .hero-body .wrap{width:100%;padding-top:40px;padding-bottom:72px;}
  .hero-copy{max-width:620px;}
  .eyebrow{display:inline-block;font-size:12.5px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--aqua);margin-bottom:16px;}
  .hero h1{font-size:clamp(36px, 5.2vw, 58px);line-height:1.08;font-weight:700;letter-spacing:-.02em;margin:0 0 20px;text-wrap:balance;}
  .hero h1 span{display:block;}   /* one sentence per line */
  /* Tagalog lines are longer: a wider block and a slightly smaller size keep each sentence on one line */
  html[lang="tl"] .hero-copy{max-width:760px;}
  html[lang="tl"] .hero h1{font-size:clamp(32px, 4.4vw, 52px);}
  .hero .lede{font-size:18px;line-height:1.7;color:#dbe8f2;margin:0 0 32px;max-width:54ch;}
  .cta-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;}
  .member-line{font-size:15px;color:#dbe8f2;margin:0 0 24px;}
  .member-line a{color:var(--aqua);font-weight:600;text-decoration:underline;text-underline-offset:3px;}
  .member-line a:hover{color:#fff;}
  .trust-line{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#c7dbea;}
  .trust-line i{color:var(--aqua);font-size:17px;}

  /* ---------- Sections ---------- */
  .section{padding:84px 0;}
  .section.alt{background:var(--paper);}
  .sec-head{max-width:640px;margin-bottom:40px;}
  .sec-head.center{margin-left:auto;margin-right:auto;text-align:center;}
  .sec-head .eyebrow{color:var(--primary);margin-bottom:10px;}
  .sec-head h2{font-size:clamp(26px, 3.2vw, 34px);line-height:1.2;font-weight:700;letter-spacing:-.015em;color:var(--navy);margin:0 0 12px;}
  .sec-head p{font-size:16px;color:var(--text-2);margin:0;}

  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px 26px;box-shadow:0 1px 2px rgba(6,42,74,.04);}
  .icon{width:48px;height:48px;border-radius:12px;background:var(--aqua-soft);color:var(--primary);flex:none;
    display:flex;align-items:center;justify-content:center;font-size:24px;}
  .card h3, .step h3, .pay-item h3{font-size:18px;line-height:1.35;font-weight:600;color:var(--navy);margin:0 0 8px;}
  .card p, .step p, .pay-item p{font-size:15.5px;line-height:1.7;color:var(--text-2);margin:0;}
  strong{color:var(--text);font-weight:600;}

  /* What AWAS does */
  .features{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;}
  .features .icon{margin-bottom:18px;}
  .features .card{transition:box-shadow .2s, transform .2s;}
  .features .card:hover{box-shadow:0 10px 28px rgba(6,42,74,.08);transform:translateY(-2px);}

  /* Membership: three numbered steps joined by a line */
  .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:28px;position:relative;list-style:none;padding:0;margin:0;}
  .steps::before{content:"";position:absolute;top:22px;left:calc(16.66% + 6px);right:calc(16.66% + 6px);height:2px;
    background:repeating-linear-gradient(90deg,var(--aqua) 0 8px,transparent 8px 14px);}
  .step{text-align:center;position:relative;}
  .step-no{width:46px;height:46px;border-radius:50%;background:var(--primary);color:#fff;font-weight:700;font-size:18px;
    display:flex;align-items:center;justify-content:center;margin:0 auto 18px;box-shadow:0 0 0 6px var(--paper);position:relative;}
  .step p{max-width:32ch;margin:0 auto;}
  .steps-cta{text-align:center;margin-top:40px;}

  /* Rates & payments: two panels side by side */
  .split{display:grid;grid-template-columns:1.1fr .9fr;gap:24px;align-items:start;}
  .panel-title{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
  .panel-title h3{margin:0;font-size:20px;}
  .rate-hero{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;padding:18px 20px;border-radius:12px;background:var(--aqua-soft);margin-bottom:18px;}
  .rate-hero .big{font-size:38px;font-weight:700;line-height:1;color:var(--primary-dark);letter-spacing:-.02em;}
  .rate-hero span{color:var(--text-2);font-size:15px;}
  .rate-table{width:100%;border-collapse:collapse;font-size:15px;margin-bottom:12px;}
  .rate-table th{text-align:left;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--text-2);padding:0 0 8px;border-bottom:1px solid var(--line);}
  .rate-table td{padding:10px 0;border-bottom:1px dashed var(--line);color:var(--text-2);}
  .rate-table th:last-child, .rate-table td:last-child{text-align:right;}
  .rate-table td b{color:var(--navy);}
  .example{display:inline-block;background:var(--paper);border-radius:8px;padding:6px 12px;font-size:14px;color:var(--text-2);margin-bottom:22px;}
  .facts{list-style:none;padding:18px 0 0;margin:0;border-top:1px solid var(--line);display:grid;grid-template-columns:1fr 1fr;gap:14px 20px;}
  .facts li{display:flex;gap:10px;align-items:flex-start;font-size:14.5px;line-height:1.5;color:var(--text-2);}
  .facts i{color:var(--primary);font-size:20px;flex:none;margin-top:1px;}
  .facts b{display:block;color:var(--navy);font-weight:600;}
  .pay-list{display:flex;flex-direction:column;}
  .pay-item{display:flex;gap:16px;padding:18px 0;border-bottom:1px solid var(--line);}
  .pay-item:first-child{padding-top:0;}
  .pay-item:last-child{border-bottom:none;padding-bottom:0;}
  .pay-item h3{font-size:16.5px;margin-bottom:4px;}
  .pay-item p{font-size:15px;}

  /* FAQ: click a question to open its answer */
  .faq{max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:12px;}
  .faq details{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:0 22px;transition:box-shadow .2s;}
  .faq details[open]{box-shadow:0 8px 24px rgba(6,42,74,.07);}
  .faq summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 0;
    font-size:16.5px;font-weight:600;color:var(--navy);}
  .faq summary::-webkit-details-marker{display:none;}
  .faq summary i{font-size:20px;color:var(--primary);transition:transform .2s;flex:none;}
  .faq details[open] summary i{transform:rotate(45deg);}
  .faq details p{margin:0;padding:0 0 20px;color:var(--text-2);}
  .faq a{color:var(--primary);font-weight:500;text-decoration:underline;}

  /* Contact + closing call to action, one navy band */
  .contact-band{background:linear-gradient(120deg, var(--navy), var(--primary-dark));color:#fff;border-radius:22px;padding:44px;}
  .contact-band .top{display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;
    padding-bottom:30px;margin-bottom:30px;border-bottom:1px solid rgba(255,255,255,.15);}
  .contact-band h2{font-size:clamp(22px, 2.6vw, 30px);margin:0 0 6px;line-height:1.25;}
  .contact-band .top p{margin:0;color:#c7dbea;}
  .contacts{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;}
  .contact{display:flex;gap:14px;align-items:flex-start;}
  .contact .icon{background:rgba(255,255,255,.1);color:var(--aqua);}
  .contact small{display:block;font-size:12.5px;letter-spacing:.08em;text-transform:uppercase;color:#9fc0d8;font-weight:600;}
  .contact a, .contact span{color:#fff;font-weight:500;line-height:1.5;word-break:break-word;}
  .contact a:hover{text-decoration:underline;}

  footer.site-foot{background:var(--navy);color:#b9cddc;font-size:14px;padding:26px 0;}
  footer.site-foot .wrap{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;}
  footer.site-foot a{color:#fff;}
  footer.site-foot a:hover{text-decoration:underline;}

  @media (max-width: 1060px){ .nav-links{display:none;} .lang-switch{margin-left:auto;} }
  @media (max-width: 900px){
    .features, .split, .contacts{grid-template-columns:1fr;}
    .features{gap:16px;}
    .steps{grid-template-columns:1fr;gap:26px;}
    .steps::before{display:none;}
    /* The dotted line runs from each number down to the next one, and stops at the last. */
    .step:not(:last-child)::after{content:"";position:absolute;left:22px;top:54px;bottom:-22px;width:2px;
      background:repeating-linear-gradient(180deg,var(--aqua) 0 8px,transparent 8px 14px);}
    .step{text-align:left;display:grid;grid-template-columns:46px 1fr;gap:0 16px;}
    .step-no{margin:0;grid-row:span 2;}
    .step p{margin:0;max-width:none;}
    .section{padding:60px 0;}
    .hero-tint{background:linear-gradient(180deg, rgba(6,42,74,.88), rgba(6,42,74,.80) 60%, rgba(6,42,74,.9));}
    .contact-band{padding:32px 26px;}
  }
  /* Phones: compact header, full-width buttons */
  @media (max-width: 560px){
    body{font-size:15px;}
    .wrap{padding:0 18px;}
    .site-nav .wrap{height:68px;gap:10px;}
    .brand{gap:9px;flex:1;}
    .brand img{width:42px;height:42px;}
    .brand .word{font-size:16.5px;}
    .brand .tagline{font-size:10px;}
    .hero-body .wrap{padding-top:28px;padding-bottom:52px;}
    .hero .lede{font-size:16px;}
    .cta-row .btn, .steps-cta .btn{flex:1 1 100%;width:100%;}
    .card{padding:22px 20px;}
    .facts{grid-template-columns:1fr;}
    .faq details{padding:0 18px;}
    .contact-band{padding:28px 20px;border-radius:18px;}
    .contact-band .top .btn{width:100%;}
  }
</style>
</head>
<body>

  <div id="pageTransitionOverlay" aria-hidden="true"></div>

  @php
    $m3 = fn ($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
    $peso = fn ($v) => '₱' . number_format((float)$v, 2);
    $ordinal = fn ($n) => $n . (in_array($n % 100, [11, 12, 13]) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
    // Due day for the translations: English uses :day ("19th"), Tagalog uses :n ("ika-19").
    $due = ['day' => $ordinal($dueDay), 'n' => $dueDay];
  @endphp

  <header class="hero">
    <video class="bg-video" autoplay muted loop playsinline preload="auto" poster="{{ asset('assets/drone-poster.jpg') }}" aria-hidden="true">
      <source src="{{ asset('assets/drone.mp4') }}?v={{ @filemtime(public_path('assets/drone.mp4')) ?: '1' }}" type="video/mp4">
    </video>
    <div class="hero-tint" aria-hidden="true"></div>

    <nav class="site-nav" aria-label="Main">
      <div class="wrap">
        <a class="brand" href="{{ url('/') }}">
          <img src="{{ asset('assets/img/logo-round.png') }}" alt="AWAS logo">
          <div>
            <div class="word">AWAS</div>
            <div class="tagline">Adlay Water Augmentation System</div>
          </div>
        </a>
        <div class="nav-links">
          <a href="#features">{{ __('Features') }}</a>
          <a href="#how">{{ __('Membership') }}</a>
          <a href="#rates">{{ __('Rates & Payments') }}</a>
          <a href="#faq">{{ __('FAQ') }}</a>
          <a href="#contact">{{ __('Contact') }}</a>
        </div>
        @include('partials.lang-switch')
      </div>
    </nav>

    <div class="hero-body">
      <div class="wrap">
        <div class="hero-copy">
          <h1><span>{{ __('Track the Flow.') }}</span> <span>{{ __('Trust the Bill.') }}</span></h1>
          <p class="lede">{{ __('AWAS brings water meter readings, billing, and payment monitoring together in one convenient system for Barangay Adlay.') }}</p>
          <div class="cta-row">
            <a class="btn btn-light" href="{{ route('login') }}"><i class="ph ph-sign-in" aria-hidden="true"></i> {{ __('Login') }}</a>
            <a class="btn btn-ghost" href="{{ route('activate') }}">{{ __('Activate Account') }}</a>
          </div>
          <p class="member-line">{{ __('Not a member yet?') }} <a href="{{ route('apply') }}">{{ __('Apply for Membership') }}</a></p>
          <div class="trust-line"><i class="ph ph-users-three" aria-hidden="true"></i> {{ __('For barangay staff, water association personnel, and registered residents.') }}</div>
        </div>
      </div>
    </div>
  </header>

  <main>
    {{-- 1. What AWAS does --}}
    <section class="section" id="features">
      <div class="wrap">
        <div class="sec-head">
          <span class="eyebrow">{{ __('What AWAS does') }}</span>
          <h2>{{ __('Readings, bills and payments in one place') }}</h2>
          <p>{{ __('From the meter to your receipt, every step is recorded once and kept up to date.') }}</p>
        </div>
        <div class="features">
          <div class="card">
            <div class="icon"><i class="ph ph-drop" aria-hidden="true"></i></div>
            <h3>{{ __('Digital meter reading') }}</h3>
            <p>{{ __("Field staff log current and previous readings for each household; consumption is calculated the moment it's saved.") }}</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-invoice" aria-hidden="true"></i></div>
            <h3>{{ __('Automated billing') }}</h3>
            <p>{{ __("Bills are computed against the barangay's configured rate tiers — no manual math, no spreadsheets to reconcile.") }}</p>
          </div>
          <div class="card">
            <div class="icon"><i class="ph ph-wallet" aria-hidden="true"></i></div>
            <h3>{{ __('Online payments') }}</h3>
            <p>{{ __('Residents review their bill and pay online with GCash; the account updates on its own once the payment is confirmed.') }}</p>
          </div>
        </div>
      </div>
    </section>

    {{-- 2. How to become a member --}}
    <section class="section alt" id="how">
      <div class="wrap">
        <div class="sec-head center">
          <span class="eyebrow">{{ __('Membership') }}</span>
          <h2>{{ __('How to become a member') }}</h2>
          <p>{{ __('Three steps, all online. You only need a valid government ID and an email address.') }}</p>
        </div>
        <ol class="steps">
          <li class="step">
            <div class="step-no">1</div>
            <h3>{{ __('Apply online') }}</h3>
            <p>{!! __('Tap <strong>Apply for Membership</strong> and fill in your name, purok, contact number and household details.') !!}</p>
          </li>
          <li class="step">
            <div class="step-no">2</div>
            <h3>{{ __('Verify your identity') }}</h3>
            <p>{{ __('Take photos of the front and back of a valid government ID and a quick selfie, then confirm your email with a 6-digit code.') }}</p>
          </li>
          <li class="step">
            <div class="step-no">3</div>
            <h3>{{ __('Get approved') }}</h3>
            <p>{{ __('The water office reviews your application and assigns your meter. Sign in anytime to check its status.') }}</p>
          </li>
        </ol>
        <div class="steps-cta"><a class="btn btn-primary" href="{{ route('apply') }}">{{ __('Start your application') }} <i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
      </div>
    </section>

    {{-- 3. Rates (live from System Settings) and how to pay --}}
    <section class="section" id="rates">
      <div class="wrap">
        <div class="sec-head">
          <span class="eyebrow">{{ __('Rates & payments') }}</span>
          <h2>{{ __('What you pay, and how to pay it') }}</h2>
          <p>{{ __('Rates are set by the barangay water office and are the same for every household.') }}</p>
        </div>
        <div class="split">
          <div class="card">
            <div class="panel-title">
              <div class="icon"><i class="ph ph-drop-half-bottom" aria-hidden="true"></i></div>
              <h3>{{ __('Water rates') }}</h3>
            </div>
            <div class="rate-hero">
              <span class="big">{{ $peso($tariff['minimumCharge']) }}</span>
              <span>{!! __('minimum charge — covers the first <strong>:m3 m³</strong> each month', ['m3' => e($m3($tariff['includedCum']))]) !!}</span>
            </div>
            <table class="rate-table">
              <thead><tr><th>{{ __('Water used beyond :m3 m³', ['m3' => $m3($tariff['includedCum'])]) }}</th><th>{{ __('Rate') }}</th></tr></thead>
              <tbody>
              @foreach ($tariff['tiers'] as $t)
                <tr><td>{{ $t['to'] === null ? __('Above :from m³', ['from' => $m3($t['lower'])]) : __('Over :from up to :to m³', ['from' => $m3($t['lower']), 'to' => $m3($t['to'])]) }}</td><td><b>{{ $peso($t['rate']) }}</b> {{ __('per m³') }}</td></tr>
              @endforeach
              </tbody>
            </table>
            <span class="example">{!! __('Example: :m3 m³ = <strong>:amount</strong>', ['m3' => e($m3($example)), 'amount' => e($peso($exampleBill['total_amount']))]) !!}</span>
            <ul class="facts">
              <li><i class="ph ph-calendar-check" aria-hidden="true"></i><div><b>{{ __('Due date') }}</b>{{ __(':day of the following month', $due) }}</div></li>
              @if ($latePct > 0)
                <li><i class="ph ph-warning-circle" aria-hidden="true"></i><div><b>{{ __('Late payment') }}</b>{{ __(':pct% penalty', ['pct' => $m3($latePct)]) }}</div></li>
              @endif
              @if ($tariff['seniorPct'] > 0)
                <li><i class="ph ph-user-circle-plus" aria-hidden="true"></i><div><b>{{ __('Senior citizens') }}</b>{{ __(':pct% discount', ['pct' => $m3($tariff['seniorPct'])]) }}</div></li>
              @endif
              <li><i class="ph ph-plugs" aria-hidden="true"></i><div><b>{{ __('Disconnection') }}</b>{{ $discMonths === 1 ? __('after 1 unpaid month past due') : __('after :n unpaid months past due', ['n' => $discMonths]) }}</div></li>
            </ul>
          </div>

          <div class="card">
            <div class="panel-title">
              <div class="icon"><i class="ph ph-wallet" aria-hidden="true"></i></div>
              <h3>{{ __('How to pay') }}</h3>
            </div>
            <div class="pay-list">
              <div class="pay-item">
                <div class="icon"><i class="ph ph-qr-code" aria-hidden="true"></i></div>
                <div>
                  <h3>{{ __('Online with GCash') }}</h3>
                  <p>{!! __("Sign in, open <strong>Current Bills</strong> and tap <strong>Pay Bill</strong>. Pay in the GCash app and your bill turns Paid by itself — or scan the barangay's GCash QR and upload your receipt.") !!}</p>
                </div>
              </div>
              <div class="pay-item">
                <div class="icon"><i class="ph ph-storefront" aria-hidden="true"></i></div>
                <div>
                  <h3>{{ __('At the water office') }}</h3>
                  <p>{{ __("Pay in cash, or scan the office's QR code with GCash. Bring your meter number or name to find your bill quickly.") }}</p>
                </div>
              </div>
              <div class="pay-item">
                <div class="icon"><i class="ph ph-receipt" aria-hidden="true"></i></div>
                <div>
                  <h3>{{ __('Receipts and history') }}</h3>
                  <p>{!! __('Every payment shows in your account right away. Download receipts under <strong>Payment History</strong>.') !!}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    {{-- 4. Common questions --}}
    <section class="section alt" id="faq">
      <div class="wrap">
        <div class="sec-head center">
          <span class="eyebrow">{{ __('Help') }}</span>
          <h2>{{ __('Common questions') }}</h2>
        </div>
        <div class="faq">
          <details>
            <summary>{{ __('How is my water use measured?') }} <i class="ph ph-plus" aria-hidden="true"></i></summary>
            <p>{{ __('Each month the meter reader records your meter. Your consumption is the present reading minus the previous reading, in cubic meters (m³).') }}</p>
          </details>
          <details>
            <summary>{{ __('When is my bill due?') }} <i class="ph ph-plus" aria-hidden="true"></i></summary>
            <p>
              {{ __('Bills are due on the :day of the month after the reading.', $due) }}
              @if ($latePct > 0) {{ __('Late payments get a :pct% penalty.', ['pct' => $m3($latePct)]) }} @endif
              {{ $discMonths === 1 ? __('Accounts unpaid 1 month after the due date may be disconnected.') : __('Accounts unpaid :n months after the due date may be disconnected.', ['n' => $discMonths]) }}
              {{ __('You also get reminders in AWAS and by email.') }}
            </p>
          </details>
          <details>
            <summary>{{ __('Forgot your password?') }} <i class="ph ph-plus" aria-hidden="true"></i></summary>
            <p>{!! __("Tap <strong>Forgot your password?</strong> on the login page and enter your account's Gmail — we'll email a code to set a new one. This also unlocks a locked account.") !!}</p>
          </details>
          <details>
            <summary>{{ __('My reading looks wrong, or I see a leak. What do I do?') }} <i class="ph ph-plus" aria-hidden="true"></i></summary>
            <p>{{ __('Contact the water office by phone, email or in person (see below). They can check your meter and correct the reading before you pay.') }}</p>
          </details>
          <details>
            <summary>{{ __('Is my information safe?') }} <i class="ph ph-plus" aria-hidden="true"></i></summary>
            <p>{!! __('Your information is used only for the water service and is protected under the Data Privacy Act of 2012. Read our <a href=":url">Privacy Policy</a>.', ['url' => e(route('privacy'))]) !!}</p>
          </details>
        </div>
      </div>
    </section>

    {{-- 5. Contact + call to action --}}
    <section class="section" id="contact">
      <div class="wrap">
        <div class="contact-band">
          <div class="top">
            <div>
              <h2>{{ __('Not a member yet?') }}</h2>
              <p>{{ __('Apply online in a few minutes — no need to visit the office first.') }}</p>
            </div>
            <a class="btn btn-aqua" href="{{ route('apply') }}">{{ __('Apply for Membership') }} <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
          </div>
          <div class="contacts">
            <div class="contact">
              <div class="icon"><i class="ph ph-phone" aria-hidden="true"></i></div>
              <div><small>{{ __('Call or text') }}</small>@if ($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>@else<span>{{ __('Visit the water office') }}</span>@endif</div>
            </div>
            <div class="contact">
              <div class="icon"><i class="ph ph-envelope-simple" aria-hidden="true"></i></div>
              <div><small>{{ __('Email') }}</small>@if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@else<span>{{ __('Visit the water office') }}</span>@endif</div>
            </div>
            <div class="contact">
              <div class="icon"><i class="ph ph-map-pin" aria-hidden="true"></i></div>
              <div><small>{{ __('Visit') }}</small><span>AWAS Water Office, {{ $barangay }}, Carrascal, Surigao del Sur</span></div>
            </div>
          </div>
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
