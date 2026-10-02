<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Membership applications now carry the back of the valid ID as well as the front. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('membership_applications', 'id_back_file')) {
            Schema::table('membership_applications', function (Blueprint $table) {
                $table->string('id_back_file', 80)->nullable()->after('id_file');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('membership_applications', 'id_back_file')) {
            Schema::table('membership_applications', function (Blueprint $table) {
                $table->dropColumn('id_back_file');
            });
        }
    }
};
