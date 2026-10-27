<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Database Backup & Restore, a section of System Settings (administrators only): make a backup, download
 * it, restore one from the list or from an uploaded file, delete old ones.
 * Restoring asks for the administrator's password and "RESTORE" typed in.
 */
class BackupController extends Controller
{
    /** The Backup & Restore section lives in System Settings; old links land there. */
    public function index()
    {
        return redirect()->to(self::back());
    }

    /** What System Settings needs to show the Backup & Restore section. */
    public static function sectionData(DatabaseBackup $backups): array
    {
        return [
            'backups'   => $backups->all(),
            'keepAuto'  => DatabaseBackup::KEEP_AUTOMATIC,
            'maxUpload' => min(self::bytes(ini_get('upload_max_filesize')), self::bytes(ini_get('post_max_size'))),
        ];
    }

    private static function back(): string
    {
        return route('admin.settings.edit') . '#backup';
    }

    public function store(Request $request, DatabaseBackup $backups)
    {
        try {
            $name = $backups->create('manual', $request->user()->full_name);
            log_activity($request->user()->user_id, 'backup_create', "Created backup $name");
            flash('success', "Backup created: $name. Download it to keep a copy outside the server.");
        } catch (\Throwable $e) {
            Log::error('Backup failed: ' . $e->getMessage());
            flash('danger', 'The backup could not be created: ' . $e->getMessage());
        }
        return redirect()->to(self::back());
    }

    public function download(Request $request, string $name, DatabaseBackup $backups)
    {
        abort_unless($backups->isValidName($name) && is_file($backups->path($name)), 404);
        log_activity($request->user()->user_id, 'backup_download', "Downloaded backup $name");
        return response()->download($backups->path($name), $name, ['Content-Type' => 'application/gzip']);
    }

    /** Restore one of the saved backups. */
    public function restore(Request $request, string $name, DatabaseBackup $backups)
    {
        abort_unless($backups->isValidName($name) && is_file($backups->path($name)), 404);
        if ($error = $this->confirmError($request)) {
            flash('danger', $error);
            return redirect()->to(self::back());
        }
        return $this->doRestore($request, $backups, $backups->path($name), $name);
    }

    /** Restore from a backup file uploaded from the administrator's computer (it is also kept in the list). */
    public function upload(Request $request, DatabaseBackup $backups)
    {
        $file = $request->file('backup_file');
        if (!$file || !$file->isValid()) {
            flash('danger', 'Please choose a backup file (.json.gz). If you did, it may be larger than the server allows.');
            return redirect()->to(self::back());
        }
        if ($error = $this->confirmError($request)) {
            flash('danger', $error);
            return redirect()->to(self::back());
        }
        $name = 'awas-backup-' . now()->format('Ymd-His') . '-uploaded.json.gz';
        $file->move($backups->directory(), $name);
        try {
            $backups->inspect($backups->path($name));
        } catch (\Throwable $e) {
            $backups->delete($name);
            flash('danger', $e->getMessage());
            return redirect()->to(self::back());
        }
        return $this->doRestore($request, $backups, $backups->path($name), $name . ' (uploaded: ' . $file->getClientOriginalName() . ')');
    }

    public function destroy(Request $request, string $name, DatabaseBackup $backups)
    {
        abort_unless($backups->isValidName($name), 404);
        $backups->delete($name)
            ? flash('success', "Backup $name deleted.")
            : flash('warning', 'That backup no longer exists.');
        log_activity($request->user()->user_id, 'backup_delete', "Deleted backup $name");
        return redirect()->to(self::back());
    }

    private function confirmError(Request $request): ?string
    {
        if (strtoupper(trim((string)$request->input('confirm_text'))) !== 'RESTORE') {
            return 'Type RESTORE to confirm. Nothing was changed.';
        }
        if (!Hash::check((string)$request->input('password'), (string)$request->user()->password_hash)) {
            return 'Your password is incorrect. Nothing was changed.';
        }
        return null;
    }

    private function doRestore(Request $request, DatabaseBackup $backups, string $path, string $label)
    {
        $user = $request->user();
        try {
            [$safety, $counts] = $backups->restore($path, $user->full_name);
        } catch (\Throwable $e) {
            Log::error("Restore of $label failed: " . $e->getMessage());
            flash('danger', 'The restore did not finish, so nothing was changed. ' . $e->getMessage());
            return redirect()->to(self::back());
        }
        // Written after the restore, so the restored activity log keeps this entry.
        log_activity($user->user_id, 'backup_restore', "Restored backup $label — " . number_format(array_sum($counts))
            . " records. The data from before is saved as $safety");
        flash('success', 'Restore complete: ' . number_format(array_sum($counts)) . " records restored from $label. "
            . "The data from before the restore was saved as $safety — restore it to undo.");
        return redirect()->to(self::back());
    }

    private static function bytes(string|false $size): int
    {
        $size = trim((string)$size);
        $n = (int)$size;
        return match (strtolower(substr($size, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
