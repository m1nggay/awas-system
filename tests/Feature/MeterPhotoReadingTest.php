<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MeterPhotoReader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Meter readings from a photo: the numbers come from the photo (a stand-in reader
 * here, no API calls), are checked, and anything typed by the page is ignored.
 * Runs inside a transaction — nothing is saved.
 */
class MeterPhotoReadingTest extends TestCase
{
    use DatabaseTransactions;

    /** A real 1×1 PNG, as the phone would send a photo. */
    private const PHOTO = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private User $reader;
    private object $consumer;
    private float $previous;
    /** What the stand-in reader says the next photo shows: [meter, reading], or an error / null (service down). */
    private $next;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'agas.admin_2fa' => false]);
        URL::forceRootUrl('http://localhost');

        $this->reader = User::where('role', 'staff')->where('status', 'active')->get()
            ->first(fn ($u) => !empty($u->assignedPurokIds()));
        $this->assertNotNull($this->reader, 'needs a meter reader with assigned puroks');
        $period = currentBillingPeriod();
        $this->consumer = DB::table('consumers')->where('status', 'active')->whereIn('purok_id', $this->reader->assignedPurokIds())
            ->whereNotExists(fn ($q) => $q->from('meter_readings as m')->whereColumn('m.consumer_id', 'consumers.consumer_id')->where('m.billing_period', '>=', $period))
            ->whereNotNull('meter_number')->first();
        $this->assertNotNull($this->consumer, 'needs an active consumer without a reading this month');
        $last = DB::table('meter_readings')->where('consumer_id', $this->consumer->consumer_id)->orderByDesc('billing_period')->value('current_reading');
        $this->previous = (float)($last ?? $this->consumer->initial_meter_reading ?? 0);

        $test = $this;
        $this->app->instance(MeterPhotoReader::class, new class($test) extends MeterPhotoReader {
            public function __construct(private $test) {}
            public function read(string $bytes): array { return $this->test->nextRead(); }
        });
        $this->actingAs($this->reader)->withSession(['last_activity' => time()]);
    }

    public function nextRead(): array
    {
        if ($this->next === null) return [null, null];
        if (is_string($this->next)) return [null, $this->next];
        return [['meter' => $this->next[0], 'reading' => (float)$this->next[1]], null];
    }

    private function scan(array $extra = [])
    {
        return $this->postJson(route('admin.readings.scan'), ['photo_data' => self::PHOTO, 'billing_period' => currentBillingPeriod()] + $extra);
    }

    public function test_photo_fills_the_form_and_typed_numbers_are_ignored(): void
    {
        $this->next = [$this->consumer->meter_number, $this->previous + 12];
        $scan = $this->scan()->assertOk()->assertJson(['ok' => true, 'meter' => $this->consumer->meter_number, 'name' => $this->consumer->full_name]);
        $this->assertEquals($this->previous + 12, $scan->json('present_reading'));
        $this->assertEquals($this->previous, $scan->json('previous_reading'));

        // The page tries to send its own numbers and a different meter: they are ignored.
        $this->postJson(route('admin.readings.sync'), [
            'client_ref' => 'test-ref-' . uniqid(), 'billing_period' => currentBillingPeriod(), 'reading_date' => date('Y-m-d'),
            'photo' => $scan->json('photo'), 'current_reading' => 999999, 'previous_reading' => 0, 'meter_number' => '1',
        ])->assertOk()->assertJson(['status' => 'saved']);

        $row = DB::table('meter_readings')->where('consumer_id', $this->consumer->consumer_id)->where('billing_period', currentBillingPeriod())->first();
        $this->assertEquals($this->previous + 12, (float)$row->current_reading);
        $this->assertEquals($this->previous, (float)$row->previous_reading);
        $this->assertSame($scan->json('photo'), $row->photo_file);
        $this->assertTrue(DB::table('water_bills')->where('reading_id', $row->reading_id)->exists());
        $this->get(route('admin.readings.photo', $row->reading_id))->assertOk();
    }

    public function test_bad_photos_are_refused(): void
    {
        $this->next = 'The reading on the meter could not be read clearly (dirty glass).';
        $this->scan()->assertStatus(422)->assertJsonFragment(['message' => $this->next]);

        $this->next = ['999999999999', 50];
        $this->scan()->assertStatus(422)->assertJson(['ok' => false])->assertSee('not in AWAS');

        $this->next = [$this->consumer->meter_number, max(0, $this->previous - 5)];
        if ($this->previous >= 5) {
            $this->scan()->assertStatus(422)->assertSee('lower than the previous reading');
        }

        $this->next = [$this->consumer->meter_number, $this->previous + 3];
        $this->scan(['expect_meter' => '123'])->assertStatus(422)->assertSee('you are recording meter 123');

        $this->next = null;   // the reading service is down
        $this->scan()->assertStatus(503)->assertJson(['retry' => true]);

        // Saving without a photo, or with a photo that was never read, is refused.
        $this->postJson(route('admin.readings.sync'), ['client_ref' => 'test-ref-' . uniqid(), 'billing_period' => currentBillingPeriod(),
            'current_reading' => $this->previous + 1])->assertJson(['status' => 'error']);
        $this->postJson(route('admin.readings.sync'), ['client_ref' => 'test-ref-' . uniqid(), 'billing_period' => currentBillingPeriod(),
            'photo' => str_repeat('a', 32) . '.jpg'])->assertJson(['status' => 'error']);
        $this->assertFalse(DB::table('meter_readings')->where('consumer_id', $this->consumer->consumer_id)->where('billing_period', currentBillingPeriod())->exists());
    }

    /** A reading typed without internet, sent from the phone's outbox with its photo. */
    private function sendTyped(float $reading, array $extra = [])
    {
        return $this->postJson(route('admin.readings.sync'), [
            'client_ref' => 'test-ref-' . uniqid(), 'billing_period' => currentBillingPeriod(), 'reading_date' => date('Y-m-d'),
            'photo_data' => self::PHOTO, 'typed_meter' => $this->consumer->meter_number, 'typed_reading' => $reading,
        ] + $extra);
    }

    private function thisMonth(): ?object
    {
        return DB::table('meter_readings')->where('consumer_id', $this->consumer->consumer_id)->where('billing_period', currentBillingPeriod())->first();
    }

    public function test_photo_without_typed_numbers_is_refused(): void
    {
        $this->next = [$this->consumer->meter_number, $this->previous + 7];
        $this->postJson(route('admin.readings.sync'), ['client_ref' => 'test-ref-' . uniqid(), 'billing_period' => currentBillingPeriod(),
            'photo_data' => self::PHOTO])->assertOk()->assertJson(['status' => 'error']);
        $this->assertNull($this->thisMonth());
    }

    public function test_typed_reading_is_saved_and_its_photo_confirms_it(): void
    {
        $this->next = [$this->consumer->meter_number, $this->previous + 7];
        $this->sendTyped($this->previous + 7)->assertOk()->assertJson(['status' => 'saved']);

        $row = $this->thisMonth();
        $this->assertEquals($this->previous + 7, (float)$row->current_reading);
        $this->assertNotNull($row->photo_file);
        $this->assertSame('match', $row->photo_check);   // checked after the answer was sent
        $this->assertTrue(DB::table('water_bills')->where('reading_id', $row->reading_id)->exists());
    }

    public function test_typed_reading_the_photo_disagrees_with_is_flagged_and_reviewed(): void
    {
        $this->next = [$this->consumer->meter_number, $this->previous + 9];
        $notes = DB::table('notifications')->where('title', 'Meter Reading Needs Review')->count();
        $this->sendTyped($this->previous + 90)->assertJson(['status' => 'saved']);

        $row = $this->thisMonth();
        $this->assertEquals($this->previous + 90, (float)$row->current_reading);   // saved as typed
        $this->assertSame('mismatch', $row->photo_check);
        $this->assertEquals($this->previous + 9, (float)$row->photo_reading);
        $this->assertGreaterThan($notes, DB::table('notifications')->where('title', 'Meter Reading Needs Review')->count());

        // The meter reader can't review it; the admin uses the photo's reading and the bill follows.
        $this->post(route('admin.readings.review', $row->reading_id), ['decision' => 'photo'])->assertForbidden();
        $admin = User::where('role', 'admin')->where('status', 'active')->first();
        $this->actingAs($admin)->post(route('admin.readings.review', $row->reading_id), ['decision' => 'photo'])->assertRedirect();
        $row = $this->thisMonth();
        $this->assertSame('corrected', $row->photo_check);
        $this->assertEquals($this->previous + 9, (float)$row->current_reading);
        $this->assertEquals(9, (float)DB::table('water_bills')->where('reading_id', $row->reading_id)->value('consumption'));
    }

    public function test_typed_reading_waits_while_the_photo_cannot_be_read(): void
    {
        $this->next = null;   // the reading service is down
        $this->sendTyped($this->previous + 4)->assertJson(['status' => 'saved']);
        $this->assertSame('pending', $this->thisMonth()->photo_check);

        // The scheduled check reads it later.
        $this->next = [$this->consumer->meter_number, $this->previous + 4];
        $this->artisan('awas:check-meter-photos')->assertSuccessful();
        $this->assertSame('match', $this->thisMonth()->photo_check);
    }

    public function test_typed_reading_lower_than_last_month_is_refused(): void
    {
        if ($this->previous < 5) {
            $this->markTestSkipped('needs a previous reading of at least 5');
        }
        $this->sendTyped($this->previous - 5)->assertJson(['status' => 'error'])->assertSee('You typed');
        $this->assertNull($this->thisMonth());
    }

    public function test_correction_needs_a_new_photo(): void
    {
        $this->next = [$this->consumer->meter_number, $this->previous + 12];
        $photo = $this->scan()->json('photo');
        $this->postJson(route('admin.readings.sync'), ['client_ref' => 'test-ref-' . uniqid(), 'billing_period' => currentBillingPeriod(), 'photo' => $photo])
            ->assertJson(['status' => 'saved']);
        $readingId = DB::table('meter_readings')->where('photo_file', $photo)->value('reading_id');

        // Typed numbers alone change nothing.
        $this->put(route('admin.readings.update', $readingId), ['current_reading' => 5000, 'previous_reading' => 0]);
        $this->assertEquals($this->previous + 12, (float)DB::table('meter_readings')->where('reading_id', $readingId)->value('current_reading'));

        // A new photo of the same meter does.
        $this->next = [$this->consumer->meter_number, $this->previous + 10];
        $newPhoto = $this->scan(['reading_id' => $readingId])->assertOk()->json('photo');
        $this->put(route('admin.readings.update', $readingId), ['photo' => $newPhoto]);
        $row = DB::table('meter_readings')->where('reading_id', $readingId)->first();
        $this->assertEquals($this->previous + 10, (float)$row->current_reading);
        $this->assertSame($newPhoto, $row->photo_file);
    }
}
