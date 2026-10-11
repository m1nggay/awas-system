<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sends the due-soon / overdue / disconnection reminders about once an hour
 * while the system is being used, so they go out even where no scheduled
 * task (cron) is set up, e.g. on XAMPP. Runs after the page is returned.
 */
class SendBillReminders
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (!Cache::add('awas:bill-reminders', true, now()->addHour())) {
            return;
        }
        try {
            app(BillingService::class)->sendBillReminders();
        } catch (\Throwable $e) {
            Log::error('Bill reminders failed: ' . $e->getMessage());
        }
    }
}
