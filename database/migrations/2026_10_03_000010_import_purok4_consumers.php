<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Imports the Purok 4(Phase 2) consumer list (94 accounts): Residential,
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
        $purokId = DB::table('puroks')->where('purok_name', 'Purok 4(Phase 2)')->value('purok_id');
        if (!$purokId) {
            return;
        }
        $adminId = DB::table('users')->where('role', 'admin')->orderBy('user_id')->value('user_id');

        $rows = [
            ['Generio Acevedo', '160302002'],
            ['Michael Adlawan', '1501002658'],
            ['Carolina Ala II', '160408063'],
            ['Richard Alboroto', '160301544'],
            ['Rosalie Arguillas', '160302350'],
            ['Ireneo Arreza', '201770276'],
            ['Ramil Ayong', '160400477'],
            ['Teddy Balderas', '160400596'],
            ['Vismindo Bergado', '160400211'],
            ['Leolinda Bongcawil', '160302342'],
            ['Myracell Buenaflor', '160400478'],
            ['Eleazar Bungcaras', '160400598'],
            ['Alman Cambe', '160400582'],
            ['Mary Ann Carasco', '160402059'],
            ['Phoebe S. Choi', '201770291'],
            ['Gilbert Corpuz', '160300053'],
            ['Bernandita Crabajales', '160400538'],
            ['Brian Crabajales', '201770209'],
            ['Chesna Crabajales', '160400548'],
            ['Joshua Curato', '151002122'],
            ['Neil Daganie', '160400586'],
            ['Japhet Dagodoy', '201770106'],
            ['Prudenciana Dagohoy', '160400471'],
            ['Danny Deguit', '201770136'],
            ['Nora Devio', '160405629'],
            ['Ruperta Dublan', '201770092'],
            ['Juliana Duerme', '201770016'],
            ['Nerissa Dumaboc', '201770070'],
            ['Roy Edrada', '201770138'],
            ['Diocena Federicos', '201770148'],
            ['Shirlyn Gador', '201770058'],
            ['Marichu Gallardo', '201770170'],
            ['Erlinda G. Gealogo', '201770281'],
            ['Rose Ann Guartel', '160300553'],
            ['Christy Guerta', '101770009'],
            ['Liezel Guerta', '160400531'],
            ['Mercy Felicitas Gutas', '201770160'],
            ['Emmanuel Huerte', '160401627'],
            ['Nick Into / Michael Rosal', '201770246'],
            ['Modesto Jamero', '201770129'],
            ['Gemma Jamili', '160301531'],
            ['Mirachel Lauro', '160300224'],
            ['Samuel Malangsa', '201770078'],
            ['Elizabeth Manlangit', '201770122'],
            ['Floravielle Manlimos', '201770045'],
            ['Antonieta Maron', '160300563'],
            ['Mary Ann B. Medina', '160400238'],
            ['Felipe Mollanida', '160302275'],
            ['Alberto Moring Jr.', '160301810'],
            ['Emelicita Naman', '160302276'],
            ['Cheryll Oliva', '160400479'],
            ['Niel Oliva', '201770065'],
            ['Jenny Pebojot', '160400504'],
            ['Rolly Jake Pecate', '160301436'],
            ['Ritchel Pegarro', '201770040'],
            ['Shiela Mae Pegarro', '160301408'],
            ['Welina Pegarro', '201770236'],
            ['Randy Peje', '20177165'],
            ['Diomedes Pepito', '160400219'],
            ['Analle D. Petalcorin', '160400536'],
            ['Laurito Puyos', '201770208'],
            ['Arlene Quimson', '201770023'],
            ['Romeo Ravelo', '201770299'],
            ['Noel Recamara', '160400446'],
            ['Genaro R. Repaso', '160400554'],
            ['Jeriths Romero', '201770003'],
            ['Lia Rosal', '201770001'],
            ['Norberto Rubi Sr.', '160301216'],
            ['Isidro Ruiz', '160402063'],
            ['Julieta Santero', '160400563'],
            ['Wilma Sarong', '160400414'],
            ['Pedro Warren Suazo', '201770008'],
            ['Antonio Suniel', '20070024'],
            ['Arthur Suniel', '1603400498'],
            ['Gwenlie Antonette Suniel', '201770242'],
            ['Gyle Elton Suniel', NULL],
            ['Rocky Suniel', '20177028'],
            ['Rosilyn Suniel', '160300204'],
            ['Samuel Suniel', '160300526'],
            ['Marife S. Tañare', '201770228'],
            ['Rewel Tesado', '160302363'],
            ['Ricky Tesado', '160301250'],
            ['Janice Jane Uriarte', '201770290'],
            ['Sancho Uriarte', '20177006'],
            ['Juan Johnny Urquia', '201770108'],
            ['Kelf Kevin Urquia', '201770212'],
            ['Reneto Urquia', '201770217'],
            ['Waiting Shed', '160302060'],
            ['Elmer Montañez', '201772030'],
            ['Arnel Remitar', '160300283'],
            ['Lovelie Azarcon', '160300271'],
            ['Jouknie Hicum', '251203846'],
            ['Lia Corporal', '1485114'],
            ['Nening Suniel II', '251203849'],
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
                'details' => "Imported $added Purok 4(Phase 2) consumers from the consumer list",
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Imported consumers are kept (they may already have readings and bills).
    }
};
