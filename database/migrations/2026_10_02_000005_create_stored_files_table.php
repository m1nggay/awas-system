<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded images (applicants' valid ID and selfie, payment receipt
 * screenshots, the GCash QR code) are kept in the database so they survive
 * on hosts whose disk is wiped on every restart (e.g. Render's free plan).
 *
 * Files already on this server's disk are copied in — only the ones a record
 * still points to.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stored_files')) {
            Schema::create('stored_files', function (Blueprint $table) {
                $table->string('name', 40)->primary();     // random 32-hex name + extension
                $table->string('folder', 40);              // applications | payment-receipts | gcash
                $table->string('mime', 40);
                $table->unsignedInteger('size');
                $table->longText('data');                  // base64 (portable across PostgreSQL and MySQL)
                $table->timestamp('created_at')->useCurrent();
                $table->index('folder');
            });
        }

        $referenced = [
            'applications'     => Schema::hasTable('membership_applications')
                ? DB::table('membership_applications')->pluck('id_file')
                    ->merge(DB::table('membership_applications')->pluck('face_file')) : collect(),
            'payment-receipts' => Schema::hasColumn('payments', 'receipt_file')
                ? DB::table('payments')->pluck('receipt_file') : collect(),
            'gcash'            => DB::table('system_settings')->where('setting_key', 'gcash_qr_file')->pluck('setting_value'),
        ];

        foreach ($referenced as $folder => $names) {
            foreach ($names->filter()->unique() as $name) {
                $path = storage_path("app/$folder/$name");
                if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name) || !is_file($path)
                    || DB::table('stored_files')->where('name', $name)->exists()) {
                    continue;
                }
                $bytes = file_get_contents($path);
                DB::table('stored_files')->insert([
                    'name'       => $name,
                    'folder'     => $folder,
                    'mime'       => (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes),
                    'size'       => strlen($bytes),
                    'data'       => base64_encode($bytes),
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
