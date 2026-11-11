<?php

namespace App\Console\Commands;

use App\Services\ApplicationFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes the ID (front/back) and face photos of membership applications approved or rejected
 * more than config('agas.application_photo_days') days ago, as the Apply page and the Privacy
 * Policy promise. The application record itself is kept. Scheduled daily.
 */
class PurgeApplicationPhotos extends Command
{
    protected $signature = 'awas:purge-application-photos';

    protected $description = 'Delete the ID and face photos of applications decided more than the set number of days ago';

    public function handle(ApplicationFiles $files): int
    {
        $days = max(1, (int)config('agas.application_photo_days', 30));
        try {
            $due = DB::table('membership_applications')
                ->whereIn('status', ['approved', 'active', 'rejected'])
                ->whereNotNull('reviewed_at')->where('reviewed_at', '<', now()->subDays($days))
                ->whereNull('photos_deleted_at')
                ->where(fn ($q) => $q->whereNotNull('id_file')->orWhereNotNull('id_back_file')->orWhereNotNull('face_file'))
                ->get(['application_id', 'reference_code', 'id_file', 'id_back_file', 'face_file']);

            foreach ($due as $app) {
                $files->delete($app->id_file);
                $files->delete($app->id_back_file);
                $files->delete($app->face_file);
                DB::table('membership_applications')->where('application_id', $app->application_id)
                    ->update(['id_file' => null, 'id_back_file' => null, 'face_file' => null, 'photos_deleted_at' => now()]);
                log_activity(null, 'application_photos_deleted', "Deleted the ID and face photos of {$app->reference_code} ($days days after the decision)");
            }
            $this->info($due->count() . ' application(s) had their photos deleted.');
        } catch (\Throwable $e) {
            Log::error($this->signature . ' failed: ' . $e->getMessage());
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
