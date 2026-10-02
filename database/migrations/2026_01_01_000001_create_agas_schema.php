<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full AGAS schema (awas_db.sql + every legacy migration_*.sql folded in).
 *
 * Every table is only created when it does not exist yet, so this can be
 * run against an existing awas_db from the plain-PHP version without
 * touching its data. Older databases that are missing later columns are
 * brought up to date by the next migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->increments('user_id');
                $table->string('username', 50)->unique();
                $table->string('password_hash', 255);
                $table->string('full_name', 150);
                $table->string('email', 150)->nullable();
                $table->string('contact_number', 20)->nullable();
                $table->enum('role', ['admin', 'staff', 'resident', 'applicant'])->default('resident');
                $table->enum('status', ['active', 'inactive', 'pending'])->default('active')
                    ->comment('pending = self-registered, awaiting email verification');
                $table->dateTime('last_login')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->index('role', 'idx_users_role');
                $table->index('status', 'idx_users_status');
            });
        }

        if (!Schema::hasTable('puroks')) {
            Schema::create('puroks', function (Blueprint $table) {
                $table->increments('purok_id');
                $table->string('purok_name', 100)->unique();
                $table->string('description', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('consumers')) {
            Schema::create('consumers', function (Blueprint $table) {
                $table->increments('consumer_id');
                $table->unsignedInteger('user_id')->nullable()->comment('linked resident login account, nullable');
                $table->string('full_name', 150);
                $table->string('address', 255);
                $table->unsignedInteger('purok_id');
                $table->string('contact_number', 20)->nullable();
                $table->string('email', 150)->nullable();
                $table->boolean('is_senior')->default(false)->comment('1 = senior citizen, gets the senior discount on bills');
                $table->string('meter_number', 20)->nullable()->unique()->comment('digits only, e.g. 1001 — no prefix');
                $table->string('consumer_type', 20)->default('residential')->comment('residential | commercial | institutional');
                $table->enum('meter_status', ['active', 'inactive', 'maintenance'])->default('active');
                $table->decimal('initial_meter_reading', 10, 2)->nullable();
                $table->string('household_number', 30)->nullable();
                $table->date('connection_date')->nullable();
                $table->enum('status', ['active', 'disconnected', 'inactive'])->default('active');
                $table->unsignedInteger('created_by')->nullable()->comment('staff/admin who registered consumer');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('user_id', 'fk_consumers_user')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('purok_id', 'fk_consumers_purok')->references('purok_id')->on('puroks')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('created_by', 'fk_consumers_creator')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->index('full_name', 'idx_consumers_name');
                $table->index('status', 'idx_consumers_status');
            });
        }

        if (!Schema::hasTable('billing_rates')) {
            Schema::create('billing_rates', function (Blueprint $table) {
                $table->increments('rate_id');
                $table->string('rate_name', 100);
                $table->decimal('min_consumption', 10, 2)->default(0);
                $table->decimal('max_consumption', 10, 2)->nullable()->comment('NULL = no upper limit');
                $table->decimal('rate_per_cubic_meter', 10, 2);
                $table->decimal('base_charge', 10, 2)->default(0)->comment('flat minimum charge for the bracket');
                $table->decimal('penalty_percentage', 5, 2)->default(0)->comment('percentage penalty applied when overdue');
                $table->boolean('is_active')->default(true);
                $table->date('effective_date');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('created_by', 'fk_rates_creator')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->index('is_active', 'idx_rates_active');
            });
        }

        if (!Schema::hasTable('meter_readings')) {
            Schema::create('meter_readings', function (Blueprint $table) {
                $table->increments('reading_id');
                $table->unsignedInteger('consumer_id');
                $table->string('billing_period', 7)->comment('format YYYY-MM');
                $table->decimal('previous_reading', 10, 2)->default(0);
                $table->decimal('current_reading', 10, 2);
                $table->decimal('consumption', 10, 2)->storedAs('current_reading - previous_reading');
                $table->date('reading_date');
                $table->unsignedInteger('recorded_by')->comment('staff/admin user_id');
                $table->string('remarks', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('consumer_id', 'fk_reading_consumer')->references('consumer_id')->on('consumers')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('recorded_by', 'fk_reading_staff')->references('user_id')->on('users')->restrictOnDelete()->cascadeOnUpdate();
                $table->unique(['consumer_id', 'billing_period'], 'uq_consumer_period');
                $table->index('billing_period', 'idx_reading_period');
            });
        }

        if (!Schema::hasTable('water_bills')) {
            Schema::create('water_bills', function (Blueprint $table) {
                $table->increments('bill_id');
                $table->string('bill_number', 30)->unique()->comment('internal reference "{meter number}-{YYYY-MM}"; screens show the Meter Number');
                $table->unsignedInteger('consumer_id');
                $table->unsignedInteger('reading_id');
                $table->string('billing_period', 7);
                $table->decimal('consumption', 10, 2);
                $table->unsignedInteger('rate_id')->nullable();
                $table->decimal('amount_due', 10, 2)->comment('sub-total: minimum charge + excess charge, before discount');
                $table->decimal('discount_amount', 10, 2)->default(0)->comment('senior citizen discount');
                $table->decimal('penalty_amount', 10, 2)->default(0);
                $table->decimal('total_amount', 10, 2);
                $table->decimal('amount_paid', 10, 2)->default(0);
                $table->date('bill_date');
                $table->date('due_date');
                $table->date('disconnection_date')->nullable();
                $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'overdue'])->default('unpaid');
                $table->unsignedInteger('generated_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('consumer_id', 'fk_bill_consumer')->references('consumer_id')->on('consumers')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('reading_id', 'fk_bill_reading')->references('reading_id')->on('meter_readings')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('rate_id', 'fk_bill_rate')->references('rate_id')->on('billing_rates')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('generated_by', 'fk_bill_generator')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->index('status', 'idx_bill_status');
                $table->index('billing_period', 'idx_bill_period');
                $table->index('due_date', 'idx_bill_due_date');
            });
        }

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->increments('payment_id');
                $table->string('payment_reference', 40)->unique();
                $table->unsignedInteger('bill_id');
                $table->unsignedInteger('consumer_id');
                $table->decimal('amount_paid', 10, 2);
                $table->enum('payment_method', ['cash', 'online', 'gcash', 'bank_transfer', 'paymongo', 'other'])->default('cash');
                $table->string('payment_gateway_txn_id', 100)->nullable()->comment('GCash / gateway reference number only, never card/account numbers');
                $table->string('channel', 10)->default('counter')->comment('counter = paid at the barangay office, online = submitted by the consumer');
                $table->dateTime('payment_date');
                $table->enum('status', ['pending', 'verified', 'failed', 'refunded', 'rejected'])->default('pending')
                    ->comment('pending = Pending Verification, verified = Paid, rejected = Rejected');
                $table->unsignedInteger('received_by')->nullable()->comment('staff/admin who recorded/verified, NULL for self-service online');
                $table->dateTime('verified_at')->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->string('receipt_file', 80)->nullable()->comment('optional GCash receipt screenshot (storage/app/payment-receipts)');
                $table->string('remarks', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('bill_id', 'fk_payment_bill')->references('bill_id')->on('water_bills')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('consumer_id', 'fk_payment_consumer')->references('consumer_id')->on('consumers')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('received_by', 'fk_payment_staff')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->index('status', 'idx_payment_status');
                $table->index('payment_date', 'idx_payment_date');
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->increments('notification_id');
                $table->unsignedInteger('user_id')->comment('recipient');
                $table->unsignedInteger('bill_id')->nullable();
                $table->string('title', 150);
                $table->string('message', 500);
                $table->enum('type', ['bill_due', 'bill_overdue', 'payment_received', 'general'])->default('general');
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('user_id', 'fk_notif_user')->references('user_id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('bill_id', 'fk_notif_bill')->references('bill_id')->on('water_bills')->cascadeOnDelete()->cascadeOnUpdate();
                $table->index(['user_id', 'is_read'], 'idx_notif_user_read');
            });
        }

        foreach (['password_reset_otps' => 'otp', 'email_verification_otps' => 'email_otp'] as $otpTable => $prefix) {
            if (!Schema::hasTable($otpTable)) {
                Schema::create($otpTable, function (Blueprint $table) use ($prefix) {
                    $table->increments('id');
                    $table->unsignedInteger('user_id');
                    $table->string('otp_hash', 64)->comment('SHA-256 hash of the 6-digit code — the plaintext code is never stored');
                    $table->unsignedTinyInteger('attempts')->default(0);
                    $table->dateTime('expires_at');
                    $table->dateTime('used_at')->nullable();
                    $table->timestamp('created_at')->useCurrent();

                    $table->foreign('user_id', "fk_{$prefix}_user")->references('user_id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
                    $table->index('user_id', "idx_{$prefix}_user");
                    $table->index('expires_at', "idx_{$prefix}_expires");
                });
            }
        }

        if (!Schema::hasTable('chatbot_faqs')) {
            Schema::create('chatbot_faqs', function (Blueprint $table) {
                $table->increments('id');
                $table->string('question', 255)->unique('uq_faq_question');
                $table->text('answer');
                $table->enum('category', ['billing', 'meter_reading', 'payments', 'account', 'system', 'general'])->default('general');
                $table->string('keywords', 500)->nullable()->comment('comma-separated keywords used for FAQ matching');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedInteger('hit_count')->default(0);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('created_by', 'fk_faq_creator')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->index('status', 'idx_faq_status');
                $table->index('category', 'idx_faq_category');
            });
        }

        if (!Schema::hasTable('chatbot_unanswered')) {
            Schema::create('chatbot_unanswered', function (Blueprint $table) {
                $table->increments('id');
                $table->string('question', 500);
                $table->unsignedInteger('asked_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('asked_by', 'fk_unanswered_user')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->increments('setting_id');
                $table->string('setting_key', 100)->unique();
                $table->string('setting_value', 500);
                $table->string('description', 255)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->increments('log_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->string('action', 255);
                $table->string('details', 500)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('user_id', 'fk_log_user')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('membership_applications')) {
            Schema::create('membership_applications', function (Blueprint $table) {
                $table->increments('application_id');
                $table->string('reference_code', 20)->unique()->comment('e.g. APP-2026-0001');
                $table->unsignedInteger('user_id')->nullable()->comment('applicant login account');
                $table->string('full_name', 150);
                $table->date('birth_date')->nullable();
                $table->enum('sex', ['male', 'female'])->nullable();
                $table->string('address', 255);
                $table->unsignedInteger('purok_id');
                $table->string('barangay', 100)->nullable();
                $table->string('municipality', 100)->nullable();
                $table->string('province', 100)->nullable();
                $table->string('contact_number', 20);
                $table->string('email', 150)->nullable();
                $table->text('notes')->nullable();
                $table->string('household_number', 30)->nullable();
                $table->unsignedSmallInteger('household_members')->nullable();
                $table->enum('residence_type', ['owned', 'rented', 'shared', 'other'])->nullable();
                $table->string('consumer_type', 20)->default('residential')->comment('residential | commercial | institutional — copied to the consumer on activation');
                $table->string('id_type', 60)->nullable();
                $table->string('id_file', 80)->nullable()->comment('random file name inside storage/app/applications (never web-accessible)');
                $table->enum('id_status', ['not_submitted', 'submitted', 'verified', 'failed'])->default('not_submitted');
                $table->string('face_file', 80)->nullable();
                $table->string('liveness_status', 20)->default('not_performed')->comment('passed | not_performed — blink check done in the browser');
                $table->enum('face_status', ['not_submitted', 'submitted', 'for_review', 'verified', 'failed'])->default('not_submitted')
                    ->comment('set by an administrator after a manual look — no automated biometric matching');
                $table->dateTime('email_verified_at')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->enum('status', ['pending_verification', 'pending_review', 'approved', 'active', 'rejected'])->default('pending_verification');
                $table->string('review_notes', 500)->nullable()->comment('rejection reason shown to the applicant');
                $table->unsignedInteger('reviewed_by')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->unsignedInteger('consumer_id')->nullable()->comment('set to the consumer record created on activation');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('purok_id', 'fk_application_purok')->references('purok_id')->on('puroks')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('reviewed_by', 'fk_application_reviewer')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('consumer_id', 'fk_application_consumer')->references('consumer_id')->on('consumers')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('user_id', 'fk_application_user')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->index('status', 'idx_application_status');
                $table->index('purok_id', 'idx_application_purok');
                $table->index('email', 'idx_application_email');
                $table->index(['ip_address', 'created_at'], 'idx_application_ip');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'membership_applications', 'activity_logs', 'system_settings', 'chatbot_unanswered', 'chatbot_faqs',
            'email_verification_otps', 'password_reset_otps', 'notifications', 'payments', 'water_bills',
            'meter_readings', 'billing_rates', 'consumers', 'puroks', 'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
};
