<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership application: the name in parts (to match barangay records) and a
 * landmark so meter readers and installers can find the house. full_name stays
 * the joined name used everywhere else. photos_deleted_at: when the ID and face
 * photos were deleted (they are kept 30 days after approval or rejection).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('membership_applications', 'first_name')) {
                $table->string('first_name', 60)->nullable();
                $table->string('middle_name', 60)->nullable();
                $table->string('last_name', 60)->nullable();
                $table->string('suffix', 10)->nullable();
            }
            if (!Schema::hasColumn('membership_applications', 'landmark')) {
                $table->string('landmark', 150)->nullable();
            }
            if (!Schema::hasColumn('membership_applications', 'photos_deleted_at')) {
                $table->timestamp('photos_deleted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'landmark', 'photos_deleted_at'] as $column) {
                if (Schema::hasColumn('membership_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
