<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The barangay's GCash QR (InstaPay QR Ph) shown on the consumer's Pay Bill
 * "Scan QR" tab. The image ships in database/data/gcash-qr.jpg and is saved
 * the same way an uploaded QR is: in stored_files (folder "gcash").
 */
return new class extends Migration
{
    private const SOURCE = 'data/gcash-qr.jpg';

    public function up(): void
    {
        $bytes = @file_get_contents(database_path(self::SOURCE));
        if ($bytes === false || $bytes === '') {
            return;
        }
        $name = md5($bytes) . '.jpg';
        $old = DB::table('system_settings')->where('setting_key', 'gcash_qr_file')->value('setting_value');

        DB::transaction(function () use ($bytes, $name, $old) {
            if (!DB::table('stored_files')->where('folder', 'gcash')->where('name', $name)->exists()) {
                DB::table('stored_files')->insert([
                    'name' => $name, 'folder' => 'gcash', 'mime' => 'image/jpeg',
                    'size' => strlen($bytes), 'data' => base64_encode($bytes), 'created_at' => now(),
                ]);
            }
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => 'gcash_qr_file'],
                ['setting_value' => $name, 'updated_at' => now()]
            );
            // The previous QR is no longer used anywhere.
            if ($old && $old !== $name) {
                DB::table('stored_files')->where('folder', 'gcash')->where('name', $old)->delete();
            }
        });
    }

    public function down(): void
    {
        // The previous image is not kept; upload another QR if needed.
    }
};
