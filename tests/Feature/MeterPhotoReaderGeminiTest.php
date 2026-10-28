<?php

namespace Tests\Feature;

use App\Services\MeterPhotoReader;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The meter photo reader with Google Gemini (faked answers, no API calls). */
class MeterPhotoReaderGeminiTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        config(['agas.meter_reader.provider' => 'gemini', 'agas.meter_reader.gemini_key' => 'test-key',
            'agas.meter_reader.gemini_models' => ['fast-a', 'fast-b', 'fast-c'], 'agas.meter_reader.gemini_model' => 'slow-x']);
        Cache::forget('meter-reader:gemini-models');
    }

    /** A Gemini answer: the wheels as a string, with T after a digit for a turning wheel ("0101T1"). */
    private function said(string $wheels, string $meter = '160400403', bool $clear = true, string $problem = ''): array
    {
        preg_match_all('/(\d)(T?)/', $wheels, $m, PREG_SET_ORDER);
        $info = ['is_water_meter' => true, 'meter_number' => $meter, 'reading_clear' => $clear, 'problem' => $problem,
            'wheels' => array_map(fn ($w) => ['digit' => $w[1], 'turning' => $w[2] === 'T'], $m)];
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($info)]]], 'finishReason' => 'STOP']]];
    }

    private function read(): array
    {
        return (new MeterPhotoReader())->read(base64_decode(self::PNG));
    }

    public function test_fast_models_are_asked_together_and_the_majority_wins(): void
    {
        Http::fake([
            '*/fast-a:generateContent' => Http::response($this->said('01011T')),
            '*/fast-b:generateContent' => Http::response($this->said('01019T', '1604 00403')),
            '*/fast-c:generateContent' => Http::response($this->said('01011')),
        ]);
        $this->assertSame([['meter' => '160400403', 'reading' => 1011.0, 'turning' => true], null], $this->read());

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'models/fast-a:generateContent')
            && $r->header('x-goog-api-key')[0] === 'test-key'
            && $r['contents'][0]['parts'][0]['inline_data']['mime_type'] === 'image/png'
            && $r['generationConfig']['responseSchema']['properties']['wheels']['type'] === 'ARRAY');
    }

    public function test_one_answer_is_enough_when_the_others_are_busy(): void
    {
        Http::fake([
            '*/fast-a:generateContent' => Http::response(['error' => ['code' => 503]], 503),
            '*/fast-b:generateContent' => Http::response($this->said('00123')),
            '*/fast-c:generateContent' => Http::response(['error' => ['code' => 429]], 429),
        ]);
        $this->assertSame([['meter' => '160400403', 'reading' => 123.0, 'turning' => false], null], $this->read());
    }

    public function test_when_no_fast_model_answers_the_bigger_models_are_tried(): void
    {
        Http::fake([
            '*/fast-*' => Http::response(['error' => ['code' => 503]], 503),
            '*/slow-x:generateContent' => Http::response(['error' => ['code' => 404]], 404),
            '*/models?*' => Http::response(['models' => [
                ['name' => 'models/gemini-3.8-flash', 'supportedGenerationMethods' => ['generateContent']],
                ['name' => 'models/gemini-3.8-flash-lite', 'supportedGenerationMethods' => ['generateContent']],
            ]]),
            '*/gemini-3.8-flash:generateContent' => Http::response($this->said('00456')),
        ]);
        $this->assertSame([['meter' => '160400403', 'reading' => 456.0, 'turning' => false], null], $this->read());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'flash-lite'));
    }

    public function test_unclear_reading_asks_for_a_retake(): void
    {
        Http::fake(['*' => Http::response($this->said('0101', '160400403', false, 'glare'))]);
        [$read, $error] = $this->read();
        $this->assertNull($read);
        $this->assertStringContainsString('could not be read clearly (glare)', $error);
    }

    public function test_live_check_is_green_only_with_readable_meter_numbers(): void
    {
        $said = fn (array $info) => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($info)]]]]]]);
        Http::fakeSequence('*')
            ->pushResponse($said(['meter_visible' => false, 'wheels_readable' => false, 'hint' => 'No meter in view']))
            ->pushResponse($said(['meter_visible' => true, 'wheels_readable' => false, 'hint' => '']))
            ->pushResponse($said(['meter_visible' => true, 'wheels_readable' => true, 'hint' => '']))
            ->pushResponse(Http::response(['error' => ['code' => 503]], 503));
        $reader = new MeterPhotoReader();
        $photo = base64_decode(self::PNG);

        $this->assertSame(['ok' => false, 'hint' => 'No meter in view'], $reader->check($photo));
        $this->assertSame(['ok' => false, 'hint' => 'Move closer to the numbers.'], $reader->check($photo));
        $this->assertSame(['ok' => true, 'hint' => ''], $reader->check($photo));
        $this->assertNull($reader->check($photo));   // busy: the phone goes by picture quality

        config(['agas.meter_reader.provider' => 'claude']);
        $this->assertNull($reader->check($photo));
    }

    public function test_quota_or_outage_means_try_later(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 503]], 503)]);
        $this->assertSame([null, null], $this->read());

        config(['agas.meter_reader.gemini_key' => '']);
        $this->assertSame([null, null], $this->read());
    }
}
