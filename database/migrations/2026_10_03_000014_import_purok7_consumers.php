<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Imports the Purok 7 consumer list (56 accounts): Residential (Catholic Church is Institutional, the Urquia boarding house Commercial),
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
        $purokId = DB::table('puroks')->where('purok_name', 'Purok 7')->value('purok_id');
        if (!$purokId) {
            return;
        }
        $adminId = DB::table('users')->where('role', 'admin')->orderBy('user_id')->value('user_id');

        $rows = [
            ['Larry Alino', '160400467'],
            ['Nelson Alparo', '160300703'],
            ['Jonaliza Arcueno', '160400411'],
            ['Liza Mae Armayan', '160401556'],
            ['Vicente Armayan', '160401515'],
            ['Michelle Badlaan', '160300716'],
            ['Dina Bagasbas', '160301518'],
            ['Julieta Balderas', '151002629'],
            ['Welihardo Balderas', '151002629'],
            ['Benjie Bayonla', '160300713'],
            ['Elecita Candontol', '160400465'],
            ['Anselmo Capindit', '160402054'],
            ['Catholic Church', '160302147', 'institutional'],
            ['Segundino Consigna', '160401543'],
            ['Sherla Maria Crizaldo', '160401558'],
            ['Ester Cuajao', '160301831'],
            ['Sabrina Cuajao', '160301834'],
            ['Ronaldo Del Rosario', '160400335'],
            ['Lyndon Dua', '160400470'],
            ['Jocelyn Enario', '160302605'],
            ['Marilyn Erlina', '160400334'],
            ['Ginalyn Espinedo', '160301514'],
            ['Charlenic Eviota', '160300328'],
            ['Lorvelle Gianchand', '160401643'],
            ['Nariel B. Gustino', '160301563'],
            ['Domingo Ihong', '160400558'],
            ['Ferdinand Jaranta', '160301566'],
            ['Rusabilla Joaquino', '160300747'],
            ['Blessel Juaquino', '160400336'],
            ['Joselito Mater', '160400423'],
            ['Anselmo Morado', '160300717'],
            ['Jocelyn Morado', '160301266'],
            ['Luisita P. Moreno III', '160301865'],
            ['Melwar Noguerra', '160402096'],
            ['Arturo Omapoy', '151002303'],
            ['Elizabeth Orascion', '160300701'],
            ['Genelita B. Ornieta', '160400301'],
            ['Eliza Pebojot', '160400314'],
            ['Terdelito Pebojot', '160400264'],
            ['Lourdes Pegarro', '160301269'],
            ['Beverly Pelisan', '160301274'],
            ['May Ponson', '160300349'],
            ['Cresencia Quia', '160400466'],
            ['Jerry Ramoso', '160300409'],
            ['Aida Ramoso', '160301524'],
            ['Edwin Rosal', '160400313'],
            ['Jeffrey Semeros', '160304075'],
            ['Erlinda Tesado', '160302601'],
            ['Rodolfo Tesado', '160302604'],
            ['Donnalyn Torculas', '160400469'],
            ['Ma. Fe Urquia I', '160301516'],
            ['Ma. Fe Urquia II (Boarding House)', '160401501', 'commercial'],
            ['Wenefredo Urquia', '160401544'],
            ['Nickel Vilchez', '160301258'],
            ['Jimuel Gemola', '160400426'],
            ['Marlon Cuajao', '160300002'],
        ];

        $added = 0;
        DB::transaction(function () use ($rows, $purokId, $adminId, &$added) {
            foreach ($rows as $row) {
                [$name, $meter] = $row;
                $type = $row[2] ?? 'residential';
                // Already imported: same person in this purok, with this meter or saved without one.
                if (DB::table('consumers')->where('full_name', $name)->where('purok_id', $purokId)
                        ->where(fn ($q) => $q->whereNull('meter_number')->when($meter !== null, fn ($w) => $w->orWhere('meter_number', $meter)))->exists()) {
                    continue;   // already imported
                }
                if ($meter !== null && DB::table('consumers')->where('meter_number', $meter)->exists()) {
                    $meter = null;   // meter number taken by another consumer — admin assigns the right one
                }
                DB::table('consumers')->insert([
                    'full_name' => $name, 'address' => 'Adlay Carrascal Surigao Del Sur', 'purok_id' => $purokId,
                    'meter_number' => $meter, 'consumer_type' => $type, 'is_senior' => false,
                    'status' => 'active', 'meter_status' => 'active', 'connection_date' => '2026-07-30',
                    'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $added++;
            }
        });

        if ($added > 0 && $adminId) {
            DB::table('activity_logs')->insert([
                'user_id' => $adminId, 'action' => 'consumer_add',
                'details' => "Imported $added Purok 7 consumers from the consumer list",
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Imported consumers are kept (they may already have readings and bills).
    }
};
