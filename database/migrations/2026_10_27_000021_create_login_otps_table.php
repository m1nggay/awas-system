<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-factor login codes for administrator accounts: after the password, a
 * 6-digit code is emailed and must be entered to finish signing in. A table
 * of its own, like the other codes, so a code for one purpose can never be
 * used for another.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('login_otps')) {
            return;
        }
        Schema::create('login_otps', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('otp_hash', 64)->comment('SHA-256 hash of the 6-digit code — the plaintext code is never stored');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_login_otp_user')->references('user_id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index('user_id', 'idx_login_otp_user');
            $table->index('expires_at', 'idx_login_otp_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_otps');
    }
};
