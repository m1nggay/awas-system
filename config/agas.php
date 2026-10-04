<?php

/*
 * AWAS application settings and third-party credentials. All secrets come
 * from .env only — they are read server-side and never sent to the browser.
 */
return [

    'short_name' => 'AWAS',

    /* Idle minutes before a logged-in user is signed out (original: 30). */
    'idle_timeout_minutes' => (int)env('AGAS_IDLE_TIMEOUT', 30),

    /* Hosting proxy to trust for HTTPS detection; empty on XAMPP. Render (which
       sets RENDER=true) ends HTTPS at its proxy, so it is trusted automatically there. */
    'trusted_proxies' => env('TRUSTED_PROXIES', env('RENDER') ? '*' : null),

    /* Outbound email via the Brevo transactional HTTP API (OTP codes, application updates). */
    'brevo' => [
        'api_key'    => env('BREVO_API_KEY', ''),
        'from_email' => env('MAIL_FROM_EMAIL', 'no-reply@agas-adlay.local'),
        'from_name'  => env('MAIL_FROM_NAME_AWAS', 'AWAS - Adlay Water Augmentation System'),
    ],

    /* PayMongo hosted Checkout Sessions (GCash / card). */
    'paymongo' => [
        'secret_key'     => env('PAYMONGO_SECRET_KEY', ''),
        'public_key'     => env('PAYMONGO_PUBLIC_KEY', ''),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET', ''),
    ],

    /* Optional AI fallback for the AWAS Assistant chatbot (Claude API). */
    'chatbot' => [
        'api_key' => env('CHATBOT_AI_API_KEY', ''),
        'model'   => env('CHATBOT_AI_MODEL', 'claude-haiku-4-5-20251001'),
    ],
];
