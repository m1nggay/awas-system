<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each Meter Reader reads only the puroks assigned to them (set by an
 * administrator in Manage Users). A purok belongs to at most one reader.
 *
 * Also adds "Purok 8" when it doesn't exist yet, and gives the original
 * "meterreader" account (Meter Reader 1) Purok 1 and Purok 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meter_reader_puroks')) {
            Schema::create('meter_reader_puroks', function (Blueprint $table) {
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('purok_id')->unique();
                $table->primary(['user_id', 'purok_id']);
                $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnUpdate()->cascadeOnDelete();
                $table->foreign('purok_id')->references('purok_id')->on('puroks')->cascadeOnUpdate()->cascadeOnDelete();
            });
        }

        // Existing databases only — a fresh install gets Purok 8 from the seeder.
        if (DB::table('puroks')->exists() && !DB::table('puroks')->whereRaw('LOWER(purok_name) = ?', ['purok 8'])->exists()) {
            DB::table('puroks')->insert(['purok_name' => 'Purok 8']);
        }

        $reader = DB::table('users')->where('username', 'meterreader')->where('role', 'staff')->value('user_id');
        if ($reader && !DB::table('meter_reader_puroks')->where('user_id', $reader)->exists()) {
            $puroks = DB::table('puroks')->whereIn(DB::raw('LOWER(purok_name)'), ['purok 1', 'purok 6'])->pluck('purok_id');
            foreach ($puroks as $purokId) {
                DB::table('meter_reader_puroks')->insertOrIgnore(['user_id' => $reader, 'purok_id' => $purokId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_reader_puroks');
    }
};
