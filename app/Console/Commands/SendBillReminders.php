<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

/** For servers with a scheduled task: php artisan awas:bill-reminders */
class SendBillReminders extends Command
{
    protected $signature = 'awas:bill-reminders';

    protected $description = 'Notify consumers about bills due soon, overdue bills and upcoming disconnections';

    public function handle(BillingService $billing): int
    {
        $billing->sendBillReminders();
        $this->info('Bill reminders sent.');
        return self::SUCCESS;
    }
}
