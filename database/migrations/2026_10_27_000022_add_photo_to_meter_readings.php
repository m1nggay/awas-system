<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meter_readings.photo_file: the photo of the meter the reading was read from
 * (stored_files, folder meter-photos). Readings are taken from the photo, not
 * typed, and the photo stays as proof. Older readings have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('meter_readings', 'photo_file')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->string('photo_file', 80)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meter_readings', 'photo_file')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->dropColumn('photo_file');
            });
        }
    }
};
