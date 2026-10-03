<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Imports the Purok 8 consumer list (100 accounts): Residential (Adlay Production Center and Adlay Public Market are Institutional),
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
        $purokId = DB::table('puroks')->where('purok_name', 'Purok 8')->value('purok_id');
        if (!$purokId) {
            return;
        }
        $adminId = DB::table('users')->where('role', 'admin')->orderBy('user_id')->value('user_id');

        $rows = [
            ['Adlay Production Center', '160300567', 'institutional'],
            ['Adlay Public Market', '160300510', 'institutional'],
            ['Flor Aguirre/Revera', '160300729'],
            ['Elecita Ancla', '151002707'],
            ['Tirso Antigo', '160402152'],
            ['Sherla Bayonla', '160300785'],
            ['Romel Bersabal', '160301534'],
            ['Peter Bucalan', '160300226'],
            ['Evelyn Cabaltera', '160400428'],
            ['Marjorie Ann Campos', '160301599'],
            ['Virgie Castin', '160301511'],
            ['Jonah Cid', '160400303'],
            ['Glenn Compañados', '160301872'],
            ['Maricar Corpuz', '151002665'],
            ['Celia Crabajales', '160301444'],
            ['Eric Crabajales', '160400450'],
            ['Helen Crabajales', '160301442'],
            ['Joseph Crabajales', '160301441'],
            ['Jundel Crabajales', '160400456'],
            ['Lyod Crabajales', '160400567'],
            ['Paquito Crabajales', '160300702'],
            ['Porferia Crabajales', '160300241'],
            ['Rodrigo Crabajales', '160300720'],
            ['Vivian Crabajales', '160301503'],
            ['Agosto Daano', '160402085'],
            ['Donald Daano', '160400509'],
            ['Elizabeth Daano', '160302296'],
            ['Pablito Daano', '160301459'],
            ['Roselyn Dagasdas', '151003663'],
            ['Rafaela Dagohoy', '160400230'],
            ['Philip De Oro', '160301445'],
            ['Flordeliza Dedicatoria', '160400342'],
            ['Nelita Dedicatoria', '160301462'],
            ['Elvy Deguit', '160400352'],
            ['Grace Samor Diaz', '160402066'],
            ['Armando Domagtoy', '160400244'],
            ['Cristine Domagtoy', '160300532'],
            ['David Domagtoy', '160301551'],
            ['Genaro Domagtoy', '160301203'],
            ['Jessela Domagtoy', '160300788'],
            ['Arnel Dua', '160300218'],
            ['Arjee Dumagtoy', '160400251'],
            ['Genaro Dumagtoy II', '160400524'],
            ['Lenie Dumagtoy', '160301502'],
            ['Genalyn Edillor', '160300266'],
            ['Lucena Ejos', '160300712'],
            ['Jerry Ellustre', '160301507'],
            ['Juanito Empeño', '160402070'],
            ['Roy Evio', '160300547'],
            ['Roland Failana', '160300763'],
            ['Ronnie Flores', '160300222'],
            ['Danilo Frias', '160301894'],
            ['Marivic Gallego', '160301484'],
            ['Cheryl Gasulas', '160301231'],
            ['Pompio Gede Jr.', '160400533'],
            ['Felix Gemola Jr.', '160300718'],
            ['Jovelyn Gualvez', '160302029'],
            ['Peter Ryan Guartel', '160302178'],
            ['Gerry Guevarra', '160301496'],
            ['Charlita Haranta', '160400453'],
            ['Ariel Perez Huinda', '151002644'],
            ['Genelyn Jaranta', '160400537'],
            ['Nanie Jaranta', '160301530'],
            ['Florecito Jordan', '160301585'],
            ['Elmer Lumayno', '160302021'],
            ['Vangeline Mago', '160301586'],
            ['Esterlita Medriano', '160301587'],
            ['Frozer Mendoza', '160300764'],
            ['Marlon Mendoza', '160400500'],
            ['Joel Mergullas', '160400443'],
            ['Luchie Mergullas', '160300536'],
            ['Marivic Mollanida', '160400212'],
            ['Ceferino Montañez', '160300368'],
            ['Henry Morado', '160301869'],
            ['Jurah Nodalo', '160301485'],
            ['Albert Pacudan', '160301590'],
            ['Elvis Pacudan', '160301490'],
            ['Marcelo Parker', '160400347'],
            ['Alfonsa Pebojot', '160300506'],
            ['Benvienido Peniza', '160400459'],
            ['Genevieve Quesio', '160300351'],
            ['Rosemarie Quisto', '160400583'],
            ['Precilla Reyes', '160300225'],
            ['Rolito Rosal', '160301899'],
            ['Julieta Ruaya', '160302295'],
            ['Manuel Ruaya', '160301863'],
            ['Antonio Rubilla', '160300248'],
            ['Maricel Saga', '160402099'],
            ['Jennifer Sanipa', '160300360'],
            ['Crisanto Santisas', '160402160'],
            ['Jelly Ann Sayson', '160300223'],
            ['Rogelyn Silagan', '160301495'],
            ['Raymond Sinoc', '151002643'],
            ['Jerome Sugian', '151002609'],
            ['Checlet Ann Tambis', '160400333'],
            ['Bevelyn Tesado/Ponte', '160300714'],
            ['Marissa Torion', '160302015'],
            ['Amelia Urquia', '160302024'],
            ['Jasmin/Sharmaine Urquia', '160400543'],
            ['Ray Kennith Urquia', '160302032'],
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
                'details' => "Imported $added Purok 8 consumers from the consumer list",
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Imported consumers are kept (they may already have readings and bills).
    }
};
