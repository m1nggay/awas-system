<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes for confirming an email change (sent to the CURRENT email). A table of
 * their own, like the password and registration codes, so a code issued for
 * one purpose can never be used for another.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_change_otps')) {
            return;
        }
        Schema::create('email_change_otps', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('otp_hash', 64)->comment('SHA-256 hash of the 6-digit code — the plaintext code is never stored');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_email_change_otp_user')->references('user_id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index('user_id', 'idx_email_change_otp_user');
            $table->index('expires_at', 'idx_email_change_otp_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_otps');
    }
};
