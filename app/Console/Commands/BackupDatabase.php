<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

/** Nightly automatic backup (scheduled), also runnable by hand: php artisan awas:backup */
class BackupDatabase extends Command
{
    protected $signature = 'awas:backup';

    protected $description = 'Back up the database (all records and uploaded files) and keep the newest automatic backups';

    public function handle(DatabaseBackup $backups): int
    {
        $name = $backups->create('automatic', 'Automatic (nightly)');
        $removed = $backups->pruneAutomatic();
        log_activity(null, 'backup_create', "Automatic backup $name" . ($removed ? " — removed $removed old automatic backups" : ''));
        $this->info("Backup saved: $name");
        return self::SUCCESS;
    }
}
