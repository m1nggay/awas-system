<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Readings typed without internet: the meter reader types the number, the photo is kept and
 * read later (MeterPhotoCheck). photo_check is null for readings taken from the photo in the
 * form; otherwise pending → match, mismatch or unreadable (the last two need the admin's review),
 * then kept or corrected once reviewed. photo_meter / photo_reading: what the photo showed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('meter_readings', 'photo_check')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->string('photo_check', 12)->nullable()->index();
                $table->string('photo_meter', 30)->nullable();
                $table->decimal('photo_reading', 12, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meter_readings', 'photo_check')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->dropColumn(['photo_check', 'photo_meter', 'photo_reading']);
            });
        }
    }
};
