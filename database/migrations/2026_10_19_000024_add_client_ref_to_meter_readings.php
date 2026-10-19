<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Offline recording: the phone's own code for each reading, so a reading re-sent over a bad connection is saved only once. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->string('client_ref', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropUnique(['client_ref']);
            $table->dropColumn('client_ref');
        });
    }
};
