<?php

use App\Http\Controllers\PayMongoWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Stateless endpoints (prefix /api, no session, no CSRF).
|
| PayMongo webhook — register https://<your-domain>/api/paymongo/webhook in
| the PayMongo Dashboard > Developers > Webhooks, subscribed at least to
| "checkout_session.payment.paid". The old plain-PHP path is kept as an
| alias so an already-registered webhook keeps working.
*/
Route::post('/paymongo/webhook', PayMongoWebhookController::class)->name('paymongo.webhook');
Route::post('/paymongo_webhook.php', PayMongoWebhookController::class);
