<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AWAS: Adlay Water Augmentation System</title>
<meta name="description" content="AWAS is the water service and billing system of Barangay Adlay, Carrascal, Surigao del Sur — apply for membership, view your bills and pay online.">
<link rel="icon" type="image/png" href="{{ asset('assets/img/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
@php
  $m3 = fn ($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
  $peso = fn ($v) => '₱' . number_format((float)$v, 2);
  $ordinal = fn ($n) => $n . (in_array($n % 100, [11, 12, 13]) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
@endphp
<style>
  :root{
    --ink:#04263f;
    --body:#1f3a50;          /* paragraph text: dark enough over the light tint */
    --muted:#3e566a;
    --primary-dark:#023e8a;
    --primary:#0077b6;
    --primary-light:#00b4d8;
    --paper:#f6fbfd;
    --line:rgba(4,38,63,0.14);
  }
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{margin:0;font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,'Noto Sans',Arial,sans-serif;color:var(--ink);background:var(--paper);}
  a{text-decoration:none;color:inherit;}
  .wrap{max-width:1120px;margin:0 auto;padding:0 28px;}

  a, button{-webkit-tap-highlight-color:transparent;}
  a:focus, button:focus{outline:none;}
  a:focus-visible, button:focus-visible, summary:focus-visible{outline:2px solid var(--primary-light);outline-offset:2px;}

  #pageTransitionOverlay{position:fixed;inset:0;background:var(--paper);z-index:9999;opacity:0;pointer-events:none;}
  @media (prefers-reduced-motion: no-preference){ #pageTransitionOverlay{transition:opacity .06s ease;} }
  #pageTransitionOverlay.active{opacity:1;}

  .bg-video{position:fixed;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;pointer-events:none;opacity:0;transition:opacity .6s ease;}
  .bg-video.loaded, .bg-video[poster]{opacity:1;}
  /* Stronger on the left, where the text is, so the letters stay sharp over the video. */
  .bg-video-tint{position:fixed;inset:0;z-index:0;pointer-events:none;
    background:linear-gradient(90deg,rgba(246,251,253,0.93) 0%,rgba(246,251,253,0.86) 45%,rgba(246,251,253,0.55) 100%);}

  .site-nav{position:sticky;top:0;z-index:5;background:rgba(246,251,253,0.94);backdrop-filter:blur(6px);border-bottom:1px solid var(--line);}
  .site-nav .wrap{display:flex;align-items:center;justify-content:space-between;height:72px;gap:16px;}
  .brand{display:flex;align-items:center;gap:11px;}
  .brand img{width:48px;height:48px;object-fit:contain;border-radius:8px;}
  .brand .word{font-weight:700;font-size:18px;letter-spacing:0.2px;}
  .brand .tagline{font-size:11px;color:var(--muted);margin-top:-2px;}
  .nav-links{display:flex;gap:22px;font-size:14px;font-weight:500;color:var(--body);}
  .nav-links a:hover{color:var(--primary);}
  .nav-cta{padding:10px 20px;font-size:13.5px;border-radius:8px;}

  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 26px;border-radius:8px;font-weight:600;font-size:14.5px;border:1.5px solid transparent;}
  .btn-solid{background:var(--primary-dark);color:#fff;}
  .btn-solid:hover{background:#012f6b;}
  .btn-outline{border-color:var(--ink);color:var(--ink);background:rgba(255,255,255,0.7);}
  .btn-outline:hover{background:#fff;}

  .hero{position:relative;z-index:2;}
  .hero .wrap{padding:72px 28px 64px;}
  .eyebrow-line{display:inline-block;font-size:13px;font-weight:600;color:var(--primary-dark);background:rgba(255,255,255,0.85);border:1px solid var(--line);border-radius:99px;padding:5px 14px;margin-bottom:18px;}
  .hero h1{font-size:46px;line-height:1.12;font-weight:700;letter-spacing:-0.5px;margin:0 0 20px;color:var(--ink);}
  .hero p.lede{font-size:17px;line-height:1.7;color:var(--body);max-width:50ch;margin:0 0 30px;font-weight:500;}
  .cta-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px;}
  .trust-line{font-size:13px;color:var(--body);}

  /* Sections below the hero sit on solid backgrounds — no text directly over the video. */
  .band{position:relative;z-index:2;background:#fff;border-top:1px solid var(--line);}
  .band.alt{background:var(--paper);}
  .band .wrap{padding:56px 28px;}
  .sec-head{max-width:640px;margin-bottom:28px;}
  .sec-kicker{font-size:12.5px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--primary);margin-bottom:6px;}
  .sec-head h2{font-size:28px;margin:0 0 8px;line-height:1.25;}
  .sec-head p{font-size:15px;line-height:1.65;color:var(--body);margin:0;}

  .features{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;}
  .band:not(.alt) .card{background:var(--paper);}
  .card .glyph{width:42px;height:42px;border-radius:10px;background:#e1f1f8;color:var(--primary-dark);display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px;}
  .card h3{font-size:16px;font-weight:600;margin:0 0 8px;}
  .card p{font-size:14px;line-height:1.65;color:var(--body);margin:0;}

  .steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;counter-reset:step;}
  .step{position:relative;padding-top:52px;}
  .step::before{counter-increment:step;content:counter(step);position:absolute;top:0;left:22px;width:38px;height:38px;border-radius:50%;background:var(--primary-dark);color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;transform:translateY(-12px);}
  .prepare{margin-top:22px;display:flex;flex-wrap:wrap;gap:10px 22px;font-size:14px;color:var(--body);}
  .prepare span{display:flex;align-items:center;gap:8px;}
  .prepare i{color:var(--primary);font-size:19px;}

  .rates{display:grid;grid-template-columns:1.1fr 1fr;gap:18px;align-items:start;}
  .rate-table{width:100%;border-collapse:collapse;font-size:15px;}
  .rate-table td{padding:12px 0;border-bottom:1px dashed var(--line);color:var(--body);}
  .rate-table td:last-child{text-align:right;font-weight:700;color:var(--ink);white-space:nowrap;}
  .rate-table tr:last-child td{border-bottom:0;}
  .example .line{display:flex;justify-content:space-between;font-size:14.5px;color:var(--body);padding:6px 0;}
  .example .total{border-top:2px solid var(--ink);margin-top:6px;padding-top:10px;font-size:17px;font-weight:700;color:var(--ink);}
  .note{font-size:13px;color:var(--muted);margin-top:12px;line-height:1.6;}

  .pay{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;}
  .pay ul{margin:10px 0 0;padding-left:20px;font-size:14px;line-height:1.75;color:var(--body);}

  .faq{max-width:820px;}
  .faq details{border:1px solid var(--line);border-radius:12px;background:#fff;margin-bottom:10px;}
  .faq summary{cursor:pointer;list-style:none;padding:16px 18px;font-weight:600;font-size:15px;display:flex;justify-content:space-between;gap:12px;}
  .faq summary::-webkit-details-marker{display:none;}
  .faq summary::after{content:"+";font-size:20px;line-height:1;color:var(--primary);}
  .faq details[open] summary::after{content:"–";}
  .faq details p{margin:0;padding:0 18px 16px;font-size:14.5px;line-height:1.7;color:var(--body);}

  .contact{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
  .contact a.card:hover{border-color:var(--primary);}
  .contact .val{font-weight:600;color:var(--primary-dark);font-size:15px;word-break:break-word;}

  footer.site-foot{position:relative;z-index:2;background:var(--ink);color:#d5e3ec;padding:24px 0 30px;font-size:13px;}
  footer.site-foot a{color:#fff;}

  @media (max-width: 960px){
    .nav-links{display:none;}
    .features, .contact{grid-template-columns:1fr;}
    .steps{grid-template-columns:repeat(2,1fr);}
    .rates, .pay{grid-template-columns:1fr;}
  }
  @media (max-width: 880px){
    .hero .wrap{padding-top:48px;}
    .hero h1{font-size:34px;}
    .bg-video-tint{background:rgba(246,251,253,0.88);}
  }
  @media (max-width: 560px){
    .wrap{padding:0 18px;}
    .site-nav .wrap{height:64px;gap:10px;}
    .brand{gap:8px;min-width:0;}
    .brand img{width:40px;height:40px;}
    .brand .word{font-size:16px;}
    .brand .tagline{font-size:9.5px;line-height:1.25;}
    .btn.nav-cta{padding:8px 12px;font-size:12.5px;white-space:nowrap;flex:none;}
    .hero .wrap{padding:36px 18px 44px;}
    .hero h1{font-size:30px;margin-bottom:14px;}
    .hero p.lede{font-size:15.5px;margin-bottom:24px;}
    .cta-row .btn{flex:1;}
    .band .wrap{padding:40px 18px;}
    .sec-head h2{font-size:23px;}
    .steps{grid-template-columns:1fr;row-gap:28px;}
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
      <a class="brand" href="{{ url('/') }}">
        <img src="{{ asset('assets/img/logo.png') }}" alt="AWAS logo">
        <div>
          <div class="word">AWAS</div>
          <div class="tagline">Adlay Water Augmentation System</div>
        </div>
      </a>
      <div class="nav-links">
        <a href="#how">How to apply</a>
        <a href="#rates">Water rates</a>
        <a href="#pay">Paying</a>
        <a href="#faq">FAQ</a>
        <a href="#contact">Contact</a>
      </div>
      <a class="btn btn-solid nav-cta" href="{{ route('apply') }}">Apply Membership</a>
    </div>
  </nav>

  <header class="hero">
    <div class="wrap">
      <div class="eyebrow-line">{{ $barangay }} · Carrascal, Surigao del Sur</div>
      <h1>Every drop, accounted for.</h1>
      <p class="lede">AWAS is {{ $barangay }}'s water service system. Apply for a water connection, see your monthly bill the moment your meter is read, and pay with GCash, Maya or your bank app — without lining up at the office.</p>
      <div class="cta-row">
        <a class="btn btn-solid" href="{{ route('login') }}"><i class="ph ph-sign-in" aria-hidden="true"></i> Login</a>
        <a class="btn btn-outline" href="{{ route('apply') }}"><i class="ph ph-user-plus" aria-hidden="true"></i> Apply for Membership</a>
      </div>
      <div class="trust-line">Already connected to the water system? <a href="{{ route('register') }}" style="color:var(--primary-dark);font-weight:600;text-decoration:underline;">Create your online account</a> with your meter number.</div>
    </div>
  </header>

  {{-- What AWAS does --}}
  <section class="band">
    <div class="wrap">
      <div class="sec-head">
        <div class="sec-kicker">What AWAS does</div>
        <h2>From the meter to your receipt</h2>
        <p>One system for the whole water service — so readings, bills and payments always match.</p>
      </div>
      <div class="features">
        <div class="card">
          <div class="glyph"><i class="ph ph-gauge" aria-hidden="true"></i></div>
          <h3>Monthly meter reading</h3>
          <p>The meter reader records your meter on a phone. Your consumption is computed right away — present reading minus previous reading.</p>
        </div>
        <div class="card">
          <div class="glyph"><i class="ph ph-receipt" aria-hidden="true"></i></div>
          <h3>Automatic, accurate bills</h3>
          <p>Your bill is computed with the official water rate, including the senior citizen discount. You get a notice by email and in your account.</p>
        </div>
        <div class="card">
          <div class="glyph"><i class="ph ph-qr-code" aria-hidden="true"></i></div>
          <h3>Pay online, anytime</h3>
          <p>Scan a QR code with GCash, Maya or your bank app. Your bill turns <strong>Paid</strong> by itself, and your receipt is ready to download.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- How to apply --}}
  <section class="band alt" id="how">
    <div class="wrap">
      <div class="sec-head">
        <div class="sec-kicker">New connection</div>
        <h2>How to become a member</h2>
        <p>Apply online from your phone — no need to bring forms to the office first.</p>
      </div>
      <div class="steps">
        <div class="card step">
          <h3>Fill in the application</h3>
          <p>Tap <strong>Apply Membership</strong> and enter your name, purok, contact number and household details.</p>
        </div>
        <div class="card step">
          <h3>Verify your identity</h3>
          <p>Take photos of the front and back of a valid government ID and a quick selfie, then confirm your email with a 6-digit code.</p>
        </div>
        <div class="card step">
          <h3>Wait for approval</h3>
          <p>The water office reviews your application and assigns your water meter. You can check its status anytime by signing in.</p>
        </div>
        <div class="card step">
          <h3>View and pay your bills</h3>
          <p>Once approved, sign in to see your readings, bills and receipts, and to pay online.</p>
        </div>
      </div>
      <div class="prepare">
        <strong>Prepare:</strong>
        <span><i class="ph ph-identification-card" aria-hidden="true"></i> A valid government ID</span>
        <span><i class="ph ph-envelope-simple" aria-hidden="true"></i> An email address (Gmail recommended)</span>
        <span><i class="ph ph-device-mobile" aria-hidden="true"></i> A phone with a camera</span>
      </div>
    </div>
  </section>

  {{-- Water rates (live from System Settings) --}}
  <section class="band" id="rates">
    <div class="wrap">
      <div class="sec-head">
        <div class="sec-kicker">Water rates</div>
        <h2>How your bill is computed</h2>
        <p>Every household pays a minimum charge for the first {{ $m3($tariff['includedCum']) }} m³. Water used beyond that is charged per cubic meter.</p>
      </div>
      <div class="rates">
        <div class="card">
          <table class="rate-table">
            <tr><td>Minimum charge — first {{ $m3($tariff['includedCum']) }} m³</td><td>{{ $peso($tariff['minimumCharge']) }}</td></tr>
            @foreach ($tariff['tiers'] as $t)
              <tr>
                <td>{{ $t['to'] === null ? 'Above ' . $m3($t['lower']) . ' m³' : 'Over ' . $m3($t['lower']) . ' up to ' . $m3($t['to']) . ' m³' }}</td>
                <td>{{ $peso($t['rate']) }} / m³</td>
              </tr>
            @endforeach
            @if ($tariff['seniorPct'] > 0)
              <tr><td>Senior citizen discount</td><td>{{ $m3($tariff['seniorPct']) }}% off</td></tr>
            @endif
            @if ($latePct > 0)
              <tr><td>Late payment penalty</td><td>{{ $m3($latePct) }}%</td></tr>
            @endif
          </table>
        </div>
        <div class="card example">
          <h3>Example: {{ $m3($example) }} m³ used in a month</h3>
          <div class="line"><span>Minimum charge (first {{ $m3($tariff['includedCum']) }} m³)</span><span>{{ $peso($exampleBill['minimum_charge']) }}</span></div>
          @foreach ($exampleBill['excess_lines'] as $l)
            <div class="line"><span>{{ $m3($l['cum']) }} m³ × {{ $peso($l['rate']) }}</span><span>{{ $peso($l['amount']) }}</span></div>
          @endforeach
          <div class="line total"><span>Total</span><span>{{ $peso($exampleBill['total_amount']) }}</span></div>
          <p class="note">Bills are due on the {{ $ordinal($dueDay) }} of the following month. Accounts left unpaid {{ $discMonths }} {{ $discMonths === 1 ? 'month' : 'months' }} after the due date may be disconnected.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- Ways to pay --}}
  <section class="band alt" id="pay">
    <div class="wrap">
      <div class="sec-head">
        <div class="sec-kicker">Paying your bill</div>
        <h2>Two easy ways to pay</h2>
      </div>
      <div class="pay">
        <div class="card">
          <div class="glyph"><i class="ph ph-device-mobile" aria-hidden="true"></i></div>
          <h3>Online, from your phone</h3>
          <ul>
            <li>Sign in, open <strong>Current Bills</strong> and tap <strong>Pay Bill</strong>.</li>
            <li>Scan the QR code with <strong>GCash, Maya</strong> or any bank app that supports <strong>QR Ph</strong>.</li>
            <li>Your bill turns <strong>Paid</strong> automatically — download your receipt anytime.</li>
          </ul>
        </div>
        <div class="card">
          <div class="glyph"><i class="ph ph-storefront" aria-hidden="true"></i></div>
          <h3>At the water office</h3>
          <ul>
            <li>Pay in <strong>cash</strong>, or scan the office's QR code with your e-wallet.</li>
            <li>The payment is recorded right away and shows in your account.</li>
            <li>Bring your meter number or name to find your bill quickly.</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  {{-- FAQ --}}
  <section class="band" id="faq">
    <div class="wrap">
      <div class="sec-head">
        <div class="sec-kicker">Questions</div>
        <h2>Frequently asked questions</h2>
      </div>
      <div class="faq">
        <details>
          <summary>Who can apply for a water connection?</summary>
          <p>Residents of {{ $barangay }} with a valid government ID. Apply online with <strong>Apply Membership</strong>; the water office reviews every application.</p>
        </details>
        <details>
          <summary>How is my water consumption measured?</summary>
          <p>Each month the meter reader records your meter. Consumption is the present reading minus the previous reading, in cubic meters (m³).</p>
        </details>
        <details>
          <summary>When is my bill due?</summary>
          <p>On the {{ $ordinal($dueDay) }} of the month after the reading. The exact due date is printed on your bill and shown in your account@if ($latePct > 0); bills paid late get a {{ $m3($latePct) }}% penalty@endif.</p>
        </details>
        <details>
          <summary>I forgot my password or my account is locked.</summary>
          <p>On the login page, tap <strong>Forgot your password?</strong> and enter the Gmail address of your account — we'll email you a code to set a new password. This also unlocks an account locked after 5 wrong passwords.</p>
        </details>
        <details>
          <summary>Is my personal information safe?</summary>
          <p>Yes. Your information is used only for the water service and is protected under the Data Privacy Act of 2012. Read our <a href="{{ route('privacy') }}" style="color:var(--primary);font-weight:600;">Privacy Policy</a>.</p>
        </details>
        <details>
          <summary>I think my reading or bill is wrong.</summary>
          <p>Contact the water office before the due date with your meter number, so the reading can be checked.</p>
        </details>
      </div>
    </div>
  </section>

  {{-- Contact --}}
  <section class="band alt" id="contact">
    <div class="wrap">
      <div class="sec-head">
        <div class="sec-kicker">Contact</div>
        <h2>Need help? Reach the water office</h2>
      </div>
      <div class="contact">
        @if ($phone)
          <a class="card" href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">
            <div class="glyph"><i class="ph ph-phone" aria-hidden="true"></i></div>
            <h3>Call or text</h3>
            <div class="val">{{ $phone }}</div>
          </a>
        @endif
        @if ($email)
          <a class="card" href="mailto:{{ $email }}">
            <div class="glyph"><i class="ph ph-envelope-simple" aria-hidden="true"></i></div>
            <h3>Email</h3>
            <div class="val">{{ $email }}</div>
          </a>
        @endif
        <div class="card">
          <div class="glyph"><i class="ph ph-map-pin" aria-hidden="true"></i></div>
          <h3>Visit</h3>
          <div class="val">AWAS Water Office, {{ $barangay }}, Carrascal, Surigao del Sur</div>
        </div>
      </div>
    </div>
  </section>

  <footer class="site-foot">
    <div class="wrap">
      &copy; {{ date('Y') }} AWAS — Adlay Water Augmentation System · <a href="{{ route('privacy') }}">Privacy Policy</a> · <a href="{{ route('terms') }}">Terms of Service</a> · <a href="{{ route('login') }}">Login</a>
    </div>
  </footer>

<script src="{{ asset('assets/js/transitions.js') }}"></script>
</body>
</html>
