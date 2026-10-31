<?php

namespace App\Services;

use App\Http\Controllers\Admin\MeterReadingController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Checks readings typed without internet against their meter photo, once the photo can be read:
 * the photo's meter number and reading are compared with what was typed. A different number
 * (or a photo that can't be read) is flagged for the administrator's review (photo_check
 * mismatch / unreadable) and every admin is notified. While the reading service is down the
 * reading stays pending and is tried again (awas:check-meter-photos).
 */
class MeterPhotoCheck
{
    public function __construct(private MeterPhotoReader $reader, private ApplicationFiles $files, private BillingService $billing) {}

    /** Readings still waiting for their photo check (oldest first). Returns how many were checked. */
    public function checkPending(int $limit = 20): int
    {
        $done = 0;
        foreach (DB::table('meter_readings')->where('photo_check', 'pending')->orderBy('reading_id')->limit($limit)->pluck('reading_id') as $id) {
            $result = $this->check((int)$id);
            if ($result === 'pending') {
                break;   // the reading service is down: the rest will wait too
            }
            $done++;
        }
        return $done;
    }

    /** Checks one reading. Returns its photo_check afterwards (still 'pending' when the photo couldn't be read right now). */
    public function check(int $readingId): ?string
    {
        $lock = Cache::lock('meter-photo-check:' . $readingId, 180);
        if (!$lock->get()) {
            return 'pending';   // being checked by another request
        }
        try {
            $r = DB::table('meter_readings as mr')->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
                ->where('mr.reading_id', $readingId)
                ->first(['mr.*', 'c.meter_number', 'c.full_name']);
            if (!$r || $r->photo_check !== 'pending') {
                return $r->photo_check ?? null;
            }

            $bytes = $r->photo_file ? $this->files->in(MeterReadingController::PHOTO_FOLDER)->contents($r->photo_file) : null;
            if (!$bytes) {
                return $this->flag($r, 'unreadable', null, null, 'its photo is missing');
            }
            [$read, $error] = $this->reader->read((string)$bytes);
            if (!$read) {
                return $error === null ? 'pending' : $this->flag($r, 'unreadable', null, null, 'the photo could not be read: ' . rtrim($error, '.'));
            }

            $sameMeter = ltrim($read['meter'], '0') === ltrim((string)$r->meter_number, '0');
            // The photo shows whole m³ (the black wheels); a wheel caught turning may be one lower.
            $diff = floor((float)$r->current_reading) - (float)$read['reading'];
            $sameReading = $diff == 0 || (!empty($read['turning']) && abs($diff) <= 1);
            if ($sameMeter && $sameReading) {
                DB::table('meter_readings')->where('reading_id', $readingId)
                    ->update(['photo_check' => 'match', 'photo_meter' => $read['meter'], 'photo_reading' => $read['reading']]);
                return 'match';
            }
            $why = $sameMeter
                ? 'the photo shows ' . number_format((float)$read['reading'], 0, '.', '') . ' m³'
                : 'the photo shows meter number ' . $read['meter'];
            return $this->flag($r, 'mismatch', $read['meter'], (float)$read['reading'], $why);
        } finally {
            $lock->release();
        }
    }

    private function flag(object $r, string $status, ?string $meter, ?float $reading, string $why): string
    {
        DB::table('meter_readings')->where('reading_id', $r->reading_id)
            ->update(['photo_check' => $status, 'photo_meter' => $meter, 'photo_reading' => $reading]);

        $billId = DB::table('water_bills')->where('reading_id', $r->reading_id)->value('bill_id');
        $recordedBy = DB::table('users')->where('user_id', $r->recorded_by)->value('full_name');
        $message = "Meter {$r->meter_number} — {$r->full_name}, " . billingPeriodLabel($r->billing_period) . ': '
            . number_format((float)$r->current_reading, 2) . " m³ was typed without internet by $recordedBy, but $why. "
            . 'Open Meter Readings → Needs review to keep or correct it.';
        foreach (DB::table('users')->where('role', 'admin')->where('status', 'active')->pluck('user_id') as $adminId) {
            $this->billing->createNotification((int)$adminId, $billId, 'Meter Reading Needs Review', $message, 'general', false);
        }
        log_activity(null, 'meter_reading_flag', "Flagged the " . billingPeriodLabel($r->billing_period) . " reading of meter {$r->meter_number}: $why");
        return $status;
    }
}
