<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Imports the Purok 6 consumer list (119 accounts): Residential (CTP Shipping Lines Corp. is Commercial),
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
        $purokId = DB::table('puroks')->where('purok_name', 'Purok 6')->value('purok_id');
        if (!$purokId) {
            return;
        }
        $adminId = DB::table('users')->where('role', 'admin')->orderBy('user_id')->value('user_id');

        $rows = [
            ['Pedrito Acero', '160301286'],
            ['Catherine Almaza', '16031907'],
            ['Juvie Almaza / Stella Marie Grumo', '160302153'],
            ['Ramon Altizo', '160300784'],
            ['Lina Angeles / Alla Villalba', '160401502'],
            ['Maricel Aporbo', '160401554'],
            ['Jay France Arbon', '160301582'],
            ['Jasmine Armayan', '160300709'],
            ['Baby Boy Arreza', '160301573'],
            ['Corazon Arrienza', '160300338'],
            ['Anita Asilum', '160302609'],
            ['Ramil Ayong', '160400457'],
            ['Bayanito Azarcon', '160300737'],
            ['Lester Azarcon', '160400561'],
            ['Aireene Basa', '160301431'],
            ['Lourdes Basa', '160400515'],
            ['Vincent Bayonla', '160400215'],
            ['Guilberto Bordas', '160304072'],
            ['Ryan Borja', '160301893'],
            ['Eulando Bucalan', '151002667'],
            ['Florence Bungcaras', '160301435'],
            ['Violeta Bungcaras', '160400437'],
            ['William Bungcaras Jr.', '160400463'],
            ['Charlito Cabahug', '160302170'],
            ['Anita Cabales', '160400550'],
            ['Genevieve Cacho', '160400525'],
            ['Alecia Cancio', '160400544'],
            ['Realyn Candolada', '160300234'],
            ['Leonardo Casil Jr.', '160302298'],
            ['Ferdinand Celeste', '160400539'],
            ['Jemark Coartina', '160300778'],
            ['Ludibeth Corpuz', '160400507'],
            ['Teresita Corpuz', '160300336'],
            ['Tessie Corpuz', '160400435'],
            ['Rixon Cortes', '160300326'],
            ['Jolan Crabajales', '160301461'],
            ['CTP Shipping Lines Corp.', '160301561', 'commercial'],
            ['Thelma Dahili', '160302176'],
            ['Edwin Dalaguit II', '160400260'],
            ['Micah Davines', '160400391'],
            ['Joseph Del Valle', '160300320'],
            ['Armand Delmonte', '160300790'],
            ['Virgo Delos Arcos', '160303486'],
            ['Antonieta Deloso I', '160400393'],
            ['Melissa Galabia Deloso', '160301458'],
            ['Remelyn Dialino / Mark Pendon', '160300782'],
            ['Dafrosa Diaz', '160300787'],
            ['Gie Ejos', '160401538'],
            ['Gretchen Ejos', '160401521'],
            ['Remalyn Ejos', '160301513'],
            ['Bernadeth Eviota', '160400403'],
            ['Gemalyn Eviota', '160400419'],
            ['Helen Failana', '15002654'],
            ['Wilma Failana', '160402039'],
            ['Lolito Flor', '160300797'],
            ['Beverly Fuentes', '160401505'],
            ['Melissa Galabia', '160301434'],
            ['Sandra Gede', '160301992'],
            ['Lilibeth Gestosani', '160301999'],
            ['Ric Gordon', '160400381'],
            ['Maryjoy Guitguitin', '160400565'],
            ['Junard Gustino', '160301594'],
            ['Marilyn Hijos', '151002628'],
            ['Gemman Hunahunan', '160300796'],
            ['Edmar Hunahunan/Pendon', '160400552'],
            ['Jocelyn Ibay', '160400417'],
            ['Maricris Initia', '160400547'],
            ['Dj Jimenez', '160400449'],
            ['Julibert Jimenez', '160300559'],
            ['Efren Labial', '160400578'],
            ['Glaiza Lagahit', '160300330'],
            ['Mariodev Lagua', '16301464'],
            ['Mary Grace Larong', '160301467'],
            ['Jaime Las/Cortes Jr.', '160400343'],
            ['Elpedia Lita', '160302092'],
            ['Anelita Lumayno', '160300793'],
            ['Marlon Lumayno', '160300732'],
            ['Santos Lumayno', '160300786'],
            ['Reynalda Maaliw', '160302142'],
            ['Susan Macawile', '160400385'],
            ['Arnold Malazarte', '160400530'],
            ['Michael Mendoza', '160301995'],
            ['Jomari Mercado', '160400424'],
            ['Adela Mollanida', '160300789'],
            ['Concordio Mollanida Jr. / Dede Mollanida', '160300762'],
            ['Gina Mollanida I', '160300096'],
            ['Jerry Mondano', '160301584'],
            ['Rossa Mongcayo', '160400522'],
            ['Ramon Montilla', '160301572'],
            ['Remelyn Montilla', '160301404'],
            ['Antonio Montilla', '160300056'],
            ['Joan Mora', '160301950'],
            ['Anecito Morang', '160300734'],
            ['Heldita Morante', '160401507'],
            ['Jonalyn Morante', '160400224'],
            ['Nicholas Orillaneda', '160400451'],
            ['Jocelyn Ortoyo', '160301515'],
            ['Arsenia Pacudan', '160400400'],
            ['Felix Pacudan', '160400394'],
            ['Rodel Pacudan', '160400415'],
            ['Ivy Mea Palma', '160302019'],
            ['Nerelyn Pelonia', '160401979'],
            ['Georgie Pendon', '160300229'],
            ['Maricel Piedad', '160300063'],
            ['Elgin Plaza', '160302282'],
            ['Virginia Ramirez', '160301264'],
            ['Christopher Rance', '160400521'],
            ['Virginia Rosit', '160400349'],
            ['Jovena Sinday', '160301260'],
            ['Rosita Solejon', '160400581'],
            ['Erick Suazo', '160401509'],
            ['Gregorio Suniel', '160400247'],
            ['Vicente Suya', '160300761'],
            ['Marvin Tamiok', '160400267'],
            ['Joel Urquia', '160400231'],
            ['Nelma Vertudez', '160400520'],
            ['Noel Vertudez', '151002630'],
            ['Edwin Yprraguirre', '160400562'],
            ['Catherine Morante', '160400492'],
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
                'details' => "Imported $added Purok 6 consumers from the consumer list",
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Imported consumers are kept (they may already have readings and bills).
    }
};
