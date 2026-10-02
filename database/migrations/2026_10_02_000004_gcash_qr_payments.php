<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cash / GCash QR payments:
 *   - payments.status gains 'rejected' (Unpaid → Pending Verification → Paid / Rejected)
 *   - payments.channel: 'counter' (paid at the barangay) or 'online' (consumer submitted)
 *   - payments.verified_at, rejection_reason, receipt_file (optional GCash screenshot)
 *   - settings for the barangay's official GCash QR code
 *
 * Safe to run on a fresh database (already in this shape) and on older ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        $statuses = "'pending','verified','failed','refunded','rejected'";
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status::text IN ($statuses))");
        } else {
            DB::statement("ALTER TABLE `payments` MODIFY COLUMN `status` ENUM($statuses) NOT NULL DEFAULT 'pending'");
        }

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'channel')) {
                $table->string('channel', 10)->default('counter');
            }
            if (!Schema::hasColumn('payments', 'verified_at')) {
                $table->dateTime('verified_at')->nullable();
            }
            if (!Schema::hasColumn('payments', 'rejection_reason')) {
                $table->string('rejection_reason', 255)->nullable();
            }
            if (!Schema::hasColumn('payments', 'receipt_file')) {
                $table->string('receipt_file', 80)->nullable();
            }
        });

        // Earlier self-service payments (no staff member recorded them) were online.
        DB::table('payments')->whereNull('received_by')->orWhere('payment_method', 'paymongo')->update(['channel' => 'online']);
        DB::table('payments')->where('status', 'verified')->whereNull('verified_at')->update(['verified_at' => DB::raw('payment_date')]);

        DB::table('system_settings')->insertOrIgnore([
            ['setting_key' => 'gcash_qr_file', 'setting_value' => '', 'description' => 'Barangay official GCash QR code image (uploaded in System Settings)'],
            ['setting_key' => 'gcash_account_name', 'setting_value' => '', 'description' => 'Name shown on the barangay GCash account'],
            ['setting_key' => 'gcash_number', 'setting_value' => '', 'description' => 'Barangay GCash mobile number'],
        ]);

        DB::table('chatbot_faqs')->where('question', 'How can I pay my water bill?')->update([
            'answer' => 'Pay in person at the barangay water office (Cash or GCash QR), or online: open "Current Bills", click "Pay Bill", '
                . 'scan the barangay GCash QR code with your GCash app, pay the exact amount, then click "I Have Paid" and enter your GCash reference number. '
                . 'Your bill shows "Pending Verification" until the water office confirms the payment.',
        ]);
        DB::table('chatbot_faqs')->where('question', 'Why is my payment still pending?')->update([
            'answer' => 'Online GCash payments stay "Pending Verification" until the barangay water office matches your GCash reference number with the money received. '
                . 'Once verified, your bill changes to "Paid". If it is rejected, you will see the reason and can pay again.',
        ]);
    }

    public function down(): void
    {
        // Payment history is kept; nothing to undo safely.
    }
};
