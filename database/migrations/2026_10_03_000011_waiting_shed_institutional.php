<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The barangay waiting shed (meter 160302060) is an Institutional consumer. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('consumers')->where('full_name', 'Waiting Shed')->where('consumer_type', '!=', 'institutional')
            ->update(['consumer_type' => 'institutional', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('consumers')->where('full_name', 'Waiting Shed')->update(['consumer_type' => 'residential']);
    }
};
