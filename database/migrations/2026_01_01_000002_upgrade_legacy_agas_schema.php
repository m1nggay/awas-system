<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings an awas_db created by an older version of the plain-PHP app up to
 * the current schema — the same changes as the legacy
 * database/legacy-sql/migration_*.sql files. Every step checks first, so
 * it is a no-op on a database created by the previous migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // migration_email_verification.sql + migration_membership_flow.sql
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','staff','resident','applicant') NOT NULL DEFAULT 'resident'");
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'active'");

        // migration_paymongo.sql
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `payment_method` ENUM('cash','online','gcash','bank_transfer','paymongo','other') NOT NULL DEFAULT 'cash'");

        // migration_billing_formula.sql + migration_membership_flow.sql
        Schema::table('consumers', function (Blueprint $table) {
            if (!Schema::hasColumn('consumers', 'is_senior')) {
                $table->boolean('is_senior')->default(false)->after('email');
            }
            if (!Schema::hasColumn('consumers', 'meter_status')) {
                $table->enum('meter_status', ['active', 'inactive', 'maintenance'])->default('active')->after('meter_serial_number');
            }
            if (!Schema::hasColumn('consumers', 'initial_meter_reading')) {
                $table->decimal('initial_meter_reading', 10, 2)->nullable()->after('meter_status');
            }
            if (!Schema::hasColumn('consumers', 'household_number')) {
                $table->string('household_number', 30)->nullable()->after('initial_meter_reading');
            }
        });

        Schema::table('water_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('water_bills', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('amount_due');
            }
            if (!Schema::hasColumn('water_bills', 'disconnection_date')) {
                $table->date('disconnection_date')->nullable()->after('due_date');
            }
        });

        // migration_membership_flow.sql — only for the original, pre-verification table.
        if (!Schema::hasColumn('membership_applications', 'email_verified_at')) {
            DB::statement("ALTER TABLE `membership_applications` MODIFY COLUMN `status` ENUM('pending','pending_verification','pending_review','approved','active','rejected') NOT NULL DEFAULT 'pending_verification'");
            DB::table('membership_applications')->where('status', 'pending')->update(['status' => 'pending_review']);
            DB::statement("ALTER TABLE `membership_applications` MODIFY COLUMN `status` ENUM('pending_verification','pending_review','approved','active','rejected') NOT NULL DEFAULT 'pending_verification'");

            Schema::table('membership_applications', function (Blueprint $table) {
                $table->unsignedInteger('user_id')->nullable()->after('reference_code');
                $table->date('birth_date')->nullable()->after('full_name');
                $table->enum('sex', ['male', 'female'])->nullable()->after('birth_date');
                $table->string('barangay', 100)->nullable()->after('purok_id');
                $table->string('municipality', 100)->nullable()->after('barangay');
                $table->string('province', 100)->nullable()->after('municipality');
                $table->string('household_number', 30)->nullable()->after('notes');
                $table->unsignedSmallInteger('household_members')->nullable()->after('household_number');
                $table->enum('residence_type', ['owned', 'rented', 'shared', 'other'])->nullable()->after('household_members');
                $table->string('id_type', 60)->nullable()->after('residence_type');
                $table->string('id_file', 80)->nullable()->after('id_type');
                $table->enum('id_status', ['not_submitted', 'submitted', 'verified', 'failed'])->default('not_submitted')->after('id_file');
                $table->string('face_file', 80)->nullable()->after('id_status');
                $table->enum('face_status', ['not_submitted', 'submitted', 'for_review', 'verified', 'failed'])->default('not_submitted')->after('face_file');
                $table->dateTime('email_verified_at')->nullable()->after('face_status');
                $table->dateTime('submitted_at')->nullable()->after('email_verified_at');
                $table->string('ip_address', 45)->nullable()->after('submitted_at');

                $table->foreign('user_id', 'fk_application_user')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->index('email', 'idx_application_email');
                $table->index(['ip_address', 'created_at'], 'idx_application_ip');
            });
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: these columns hold live data.
    }
};
