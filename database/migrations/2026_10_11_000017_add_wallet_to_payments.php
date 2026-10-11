<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** payments.wallet: which e-wallet a PayMongo payment used (gcash, paymaya, grab_pay, shopee_pay). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'wallet')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('wallet', 20)->nullable();
            });
        }
        // PayMongo payments made before there was a choice were all GCash.
        DB::table('payments')->where('payment_method', 'paymongo')->whereNull('wallet')->update(['wallet' => 'gcash']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'wallet')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('wallet');
            });
        }
    }
};
