<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** users.profile_photo: the consumer's profile picture (a stored file in the "profile-photos" folder). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'profile_photo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('profile_photo', 80)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'profile_photo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('profile_photo');
            });
        }
    }
};
