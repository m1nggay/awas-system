<?php

namespace App\Console\Commands;

use App\Services\MeterPhotoCheck;
use Illuminate\Console\Command;

/** For servers with a scheduled task: php artisan awas:check-meter-photos */
class CheckMeterPhotos extends Command
{
    protected $signature = 'awas:check-meter-photos';

    protected $description = 'Read the photos of readings typed without internet and flag the ones that do not match';

    public function handle(MeterPhotoCheck $check): int
    {
        $this->info($check->checkPending() . ' reading photo(s) checked.');
        return self::SUCCESS;
    }
}
