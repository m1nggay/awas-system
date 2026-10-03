<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Imports the Purok 4(Extension) consumer list (22 accounts): Residential,
 * Active, connected July 30, 2026, address Adlay Carrascal Surigao Del Sur.
 *
 * Runs safely on any database (local or live): a person already saved with
 * the same name and meter is skipped, and a meter number already used by
 * someone else is left blank for the administrator to fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        $purokId = DB::table('puroks')->where('purok_name', 'Purok 4(Extension)')->value('purok_id');
        if (!$purokId) {
            return;
        }
        $adminId = DB::table('users')->where('role', 'admin')->orderBy('user_id')->value('user_id');

        $rows = [
            ['Meriam Asis', '160301547'],
            ['Joery Balili / Wilmor Dao', '160301541'],
            ['Paulino Baul', '160301546'],
            ['Angela Bayonla', '160400412'],
            ['Angelbert Bayonla', '160301517'],
            ['Joselito Calising', '160301542'],
            ['Jeane Cubil', '160400208'],
            ['Arturo Dao', '160400239'],
            ['Gege Geneston', '160301545'],
            ['Gemma Lecong', '160301564'],
            ['Brenda Maglasang', '160301832'],
            ['Annie Maquiling', '151002318'],
            ['Leonito Mercado', '151002306'],
            ['Susan Mercado', '160301898'],
            ['Sionita Montañez', '10300706'],
            ['Lorena Pegarro', '201770054'],
            ['Ritchefe Pelayo', '160301568'],
            ['Mary Ann Raniola', '160402278'],
            ['Jon Tambuyat', '160301414'],
            ['Elizer Cuatibel', '16030738'],
            ['Sherwin Estoria', '160300010'],
            ['Chesna Crabajales', '1465139'],
        ];

        $added = 0;
        DB::transaction(function () use ($rows, $purokId, $adminId, &$added) {
            foreach ($rows as [$name, $meter]) {
                if (DB::table('consumers')->where('full_name', $name)
                        ->where(fn ($q) => $meter === null ? $q->whereNull('meter_number') : $q->where('meter_number', $meter))->exists()) {
                    continue;   // already imported
                }
                if ($meter !== null && DB::table('consumers')->where('meter_number', $meter)->exists()) {
                    $meter = null;   // meter number taken by another consumer — admin assigns the right one
                }
                DB::table('consumers')->insert([
                    'full_name' => $name, 'address' => 'Adlay Carrascal Surigao Del Sur', 'purok_id' => $purokId,
                    'meter_number' => $meter, 'consumer_type' => 'residential', 'is_senior' => false,
                    'status' => 'active', 'meter_status' => 'active', 'connection_date' => '2026-07-30',
                    'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $added++;
            }
        });

        if ($added > 0 && $adminId) {
            DB::table('activity_logs')->insert([
                'user_id' => $adminId, 'action' => 'consumer_add',
                'details' => "Imported $added Purok 4(Extension) consumers from the consumer list",
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Imported consumers are kept (they may already have readings and bills).
    }
};
