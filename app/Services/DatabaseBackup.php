<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Database backup and restore for administrators.
 *
 * A backup is one gzip file of JSON lines: a header, then one line per row
 * ({"t": table, "r": row}), then a footer with each table's row count. Uploaded
 * files (receipts, IDs, photos) live in the stored_files table, so they are
 * included. Plain queries, so it works the same on PostgreSQL and MySQL and
 * needs no pg_dump on the server.
 *
 * Restore checks the whole file first, saves a "before restore" backup of the
 * current data, then replaces every table inside one transaction: if anything
 * fails, nothing is changed.
 */
class DatabaseBackup
{
    public const FORMAT = 1;

    /** Rebuilt by Laravel or only temporary — never backed up or replaced. */
    private const SKIP_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    /** Automatic (nightly) backups kept; manual and "before restore" ones stay until deleted. */
    public const KEEP_AUTOMATIC = 14;

    public function directory(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /** A backup's file name is only ever one we made: awas-backup-YYYYmmdd-HHMMSS-<kind>.json.gz */
    public function isValidName(?string $name): bool
    {
        return is_string($name) && preg_match('/^awas-backup-\d{8}-\d{6}-(manual|automatic|before-restore|uploaded)\.json\.gz$/', $name) === 1;
    }

    public function path(string $name): string
    {
        if (!$this->isValidName($name)) {
            throw new RuntimeException('Invalid backup name.');
        }
        return $this->directory() . DIRECTORY_SEPARATOR . $name;
    }

    /** Saved backups, newest first, with what the header says about each. */
    public function all(): array
    {
        $list = [];
        foreach (glob($this->directory() . DIRECTORY_SEPARATOR . 'awas-backup-*.json.gz') ?: [] as $file) {
            $name = basename($file);
            if (!$this->isValidName($name)) {
                continue;
            }
            $header = $this->readHeader($file);
            $list[] = [
                'name'       => $name,
                'kind'       => preg_replace('/^awas-backup-\d{8}-\d{6}-|\.json\.gz$/', '', $name),
                'size'       => filesize($file),
                'created_at' => isset($header['created_at']) ? Carbon::parse($header['created_at']) : Carbon::createFromTimestamp(filemtime($file)),
                'created_by' => $header['created_by'] ?? null,
            ];
        }
        usort($list, fn ($a, $b) => strcmp($b['name'], $a['name']));
        return $list;
    }

    /** Writes a new backup of every table and returns its file name. $kind: manual, automatic or before-restore. */
    public function create(string $kind = 'manual', ?string $createdBy = null): string
    {
        @set_time_limit(0);
        $name = 'awas-backup-' . now()->format('Ymd-His') . "-$kind.json.gz";
        $path = $this->path($name);
        $tmp = $path . '.part';

        $gz = gzopen($tmp, 'wb6');
        if (!$gz) {
            throw new RuntimeException('Could not write the backup file. Check that storage/app/backups is writable.');
        }
        try {
            $tables = $this->tablesInInsertOrder();
            $this->writeLine($gz, [
                'awas_backup' => self::FORMAT,
                'created_at'  => now()->toIso8601String(),
                'created_by'  => $createdBy,
                'driver'      => DB::getDriverName(),
                'app_url'     => config('app.url'),
                'tables'      => $tables,
            ]);
            $counts = [];
            foreach ($tables as $table) {
                $counts[$table] = 0;
                $order = $this->orderColumn($table);
                foreach (DB::table($table)->orderBy($order)->lazy(500) as $row) {
                    $this->writeLine($gz, ['t' => $table, 'r' => (array)$row]);
                    $counts[$table]++;
                }
            }
            $this->writeLine($gz, ['end' => true, 'counts' => $counts]);
        } finally {
            gzclose($gz);
        }
        rename($tmp, $path);
        return $name;
    }

    /** Removes automatic backups beyond the newest KEEP_AUTOMATIC. */
    public function pruneAutomatic(): int
    {
        $auto = array_values(array_filter($this->all(), fn ($b) => $b['kind'] === 'automatic'));
        $removed = 0;
        foreach (array_slice($auto, self::KEEP_AUTOMATIC) as $b) {
            @unlink($this->path($b['name'])) && $removed++;
        }
        return $removed;
    }

    /**
     * Reads the whole file and checks it is a complete AWAS backup.
     * @return array{header: array, counts: array<string,int>}
     */
    public function inspect(string $path): array
    {
        $gz = @gzopen($path, 'rb');
        if (!$gz) {
            throw new RuntimeException('The file could not be opened as a backup.');
        }
        try {
            $header = json_decode((string)gzgets($gz), true);
            if (!is_array($header) || ($header['awas_backup'] ?? null) !== self::FORMAT || !is_array($header['tables'] ?? null)) {
                throw new RuntimeException('This is not an AWAS backup file.');
            }
            $seen = [];
            $footer = null;
            while (($line = gzgets($gz)) !== false) {
                if (trim($line) === '') {
                    continue;
                }
                $item = json_decode($line, true);
                if (!is_array($item)) {
                    throw new RuntimeException('The backup file is damaged.');
                }
                if (!empty($item['end'])) {
                    $footer = $item;
                    break;
                }
                $seen[$item['t']] = ($seen[$item['t']] ?? 0) + 1;
            }
            if (!$footer) {
                throw new RuntimeException('The backup file is incomplete (it was cut off). Nothing was changed.');
            }
            foreach ($footer['counts'] as $table => $count) {
                if (($seen[$table] ?? 0) !== (int)$count) {
                    throw new RuntimeException("The backup file is damaged (table $table). Nothing was changed.");
                }
            }
            return ['header' => $header, 'counts' => $footer['counts']];
        } finally {
            gzclose($gz);
        }
    }

    /**
     * Replaces every table's data with the backup's. Returns [before-restore backup name, row counts].
     * @return array{0: string, 1: array<string,int>}
     */
    public function restore(string $path, ?string $restoredBy = null): array
    {
        @set_time_limit(0);
        $info = $this->inspect($path);

        // The current data, so this restore can be undone.
        $safety = $this->create('before-restore', $restoredBy);

        $current = $this->tablesInInsertOrder();
        $tables = array_values(array_intersect($current, $info['header']['tables']));
        $columns = [];
        foreach ($tables as $table) {
            $columns[$table] = array_flip(array_diff(Schema::getColumnListing($table), $this->generatedColumns($table)));
        }

        DB::transaction(function () use ($path, $tables, $columns) {
            $this->emptyTables($tables);

            $gz = gzopen($path, 'rb');
            try {
                gzgets($gz); // header
                $batch = [];
                $batchTable = null;
                $flush = function () use (&$batch, &$batchTable) {
                    if ($batch) {
                        DB::table($batchTable)->insert($batch);
                    }
                    $batch = [];
                };
                while (($line = gzgets($gz)) !== false) {
                    $item = json_decode($line, true);
                    if (!is_array($item) || !empty($item['end'])) {
                        break;
                    }
                    if (!isset($columns[$item['t']])) {
                        continue; // a table this version no longer has
                    }
                    if ($item['t'] !== $batchTable || count($batch) >= 200) {
                        $flush();
                        $batchTable = $item['t'];
                    }
                    // Only columns the table still has; new columns take their defaults.
                    $batch[] = array_intersect_key($item['r'], $columns[$item['t']]);
                }
                $flush();
            } finally {
                gzclose($gz);
            }

            $this->resetSequences($tables);
        });

        Cache::flush(); // settings and other cached values from before the restore
        return [$safety, $info['counts']];
    }

    public function delete(string $name): bool
    {
        $path = $this->path($name);
        return is_file($path) && unlink($path);
    }

    /* ---------------- helpers ---------------- */

    private function writeLine($gz, array $data): void
    {
        gzwrite($gz, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
    }

    private function readHeader(string $file): ?array
    {
        $gz = @gzopen($file, 'rb');
        if (!$gz) {
            return null;
        }
        $header = json_decode((string)gzgets($gz), true);
        gzclose($gz);
        return is_array($header) ? $header : null;
    }

    /** Every app table, parents before the tables that point to them (so inserts satisfy foreign keys). */
    private function tablesInInsertOrder(): array
    {
        $tables = DB::getDriverName() === 'pgsql'
            ? array_column(DB::select('SELECT tablename FROM pg_tables WHERE schemaname = current_schema()'), 'tablename')
            : array_map(fn ($r) => array_values((array)$r)[0], DB::select('SHOW TABLES'));
        $tables = array_values(array_diff($tables, self::SKIP_TABLES));
        sort($tables);

        $edges = DB::getDriverName() === 'pgsql'
            ? DB::select("SELECT DISTINCT tc.table_name AS child, ccu.table_name AS parent
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = current_schema()")
            : DB::select('SELECT DISTINCT TABLE_NAME AS child, REFERENCED_TABLE_NAME AS parent FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
        $parents = array_fill_keys($tables, []);
        foreach ($edges as $e) {
            if ($e->child !== $e->parent && isset($parents[$e->child]) && isset($parents[$e->parent])) {
                $parents[$e->child][] = $e->parent;
            }
        }

        $ordered = [];
        while ($parents) {
            $ready = array_keys(array_filter($parents, fn ($p) => !array_diff($p, $ordered)));
            if (!$ready) {
                $ready = array_keys($parents); // a cycle: keep going in name order
            }
            foreach ($ready as $t) {
                $ordered[] = $t;
                unset($parents[$t]);
            }
        }
        return $ordered;
    }

    /** The primary key (so self-referencing rows come after the rows they point to), else the first column. */
    private function orderColumn(string $table): string
    {
        $pk = DB::getDriverName() === 'pgsql'
            ? DB::selectOne("SELECT a.attname AS col FROM pg_index i JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                WHERE i.indrelid = ?::regclass AND i.indisprimary LIMIT 1", [$table])
            : DB::selectOne("SELECT COLUMN_NAME AS col FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' LIMIT 1", [$table]);
        return $pk->col ?? Schema::getColumnListing($table)[0];
    }

    /** Columns the database computes itself (e.g. meter_readings.consumption) — never inserted. */
    private function generatedColumns(string $table): array
    {
        return DB::getDriverName() === 'pgsql'
            ? array_column(DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND is_generated = 'ALWAYS'", [$table]), 'column_name')
            : array_column(DB::select("SELECT COLUMN_NAME AS column_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND EXTRA LIKE '%GENERATED%'", [$table]), 'column_name');
    }

    private function emptyTables(array $tables): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('TRUNCATE TABLE ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $tables)) . ' RESTART IDENTITY CASCADE');
            return;
        }
        // MySQL: DELETE (TRUNCATE would end the transaction).
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (array_reverse($tables) as $t) {
            DB::table($t)->delete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** PostgreSQL: new rows continue after the restored IDs instead of colliding with them. */
    private function resetSequences(array $tables): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $serials = DB::select("SELECT table_name, column_name FROM information_schema.columns
            WHERE table_schema = current_schema() AND column_default LIKE 'nextval(%'");
        foreach ($serials as $s) {
            if (!in_array($s->table_name, $tables, true)) {
                continue;
            }
            DB::statement("SELECT setval(pg_get_serial_sequence(?, ?), COALESCE((SELECT MAX(\"{$s->column_name}\") FROM \"{$s->table_name}\"), 1),
                (SELECT MAX(\"{$s->column_name}\") FROM \"{$s->table_name}\") IS NOT NULL)", [$s->table_name, $s->column_name]);
        }
    }
}
