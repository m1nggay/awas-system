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
        config(['agas.meter_reader.provider' => 'gemini', 'agas.meter_reader.gemini_key' => 'test-key', 'agas.meter_reader.gemini_model' => 'gemini-test']);
    }

    private function answer(array $info): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($info)]]], 'finishReason' => 'STOP']],
        ])]);
    }

    private function read(): array
    {
        return (new MeterPhotoReader())->read(base64_decode(self::PNG));
    }

    public function test_reads_meter_number_and_reading(): void
    {
        $this->answer(['is_water_meter' => true, 'meter_number' => '1604 00403', 'reading' => '00123', 'reading_clear' => true, 'problem' => '']);
        $this->assertSame([['meter' => '160400403', 'reading' => 123.0], null], $this->read());

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'models/gemini-test:generateContent')
            && $r->header('x-goog-api-key')[0] === 'test-key'
            && $r['contents'][0]['parts'][0]['inline_data']['mime_type'] === 'image/png'
            && $r['generationConfig']['responseMimeType'] === 'application/json');
    }

    public function test_unclear_reading_asks_for_a_retake(): void
    {
        $this->answer(['is_water_meter' => true, 'meter_number' => '160400403', 'reading' => '', 'reading_clear' => false, 'problem' => 'glare']);
        [$read, $error] = $this->read();
        $this->assertNull($read);
        $this->assertStringContainsString('could not be read clearly (glare)', $error);
    }

    public function test_retired_or_busy_model_moves_on_to_another_flash_model(): void
    {
        $forget = fn () => Cache::forget('meter-reader:gemini-model') && Cache::forget('meter-reader:gemini-models');
        $forget();
        $ok = ['candidates' => [['content' => ['parts' => [['text' => json_encode(
            ['is_water_meter' => true, 'meter_number' => '160400403', 'reading' => '01011', 'reading_clear' => true, 'problem' => ''])]]]]]];
        Http::fake([
            '*/models/gemini-test:generateContent' => Http::response(['error' => ['code' => 404]], 404),
            '*/models/gemini-3.8-flash:generateContent' => Http::response(['error' => ['code' => 503, 'message' => 'high demand']], 503),
            '*/models/gemini-3.5-flash:generateContent' => Http::response($ok),
            '*/models?*' => Http::response(['models' => [
                ['name' => 'models/gemini-2.5-flash', 'supportedGenerationMethods' => ['generateContent']],
                ['name' => 'models/gemini-3.8-flash', 'supportedGenerationMethods' => ['generateContent']],
                ['name' => 'models/gemini-3.5-flash', 'supportedGenerationMethods' => ['generateContent']],
                ['name' => 'models/gemini-3.8-flash-lite', 'supportedGenerationMethods' => ['generateContent']],
                ['name' => 'models/gemini-3-flash-preview', 'supportedGenerationMethods' => ['generateContent']],
            ]]),
        ]);
        $this->assertSame([['meter' => '160400403', 'reading' => 1011.0], null], $this->read());
        $this->assertSame('gemini-3.5-flash', Cache::get('meter-reader:gemini-model'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'lite') || str_contains($r->url(), 'preview') || str_contains($r->url(), '2.5'));
        $forget();
    }

    public function test_quota_or_outage_means_try_later(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429)]);
        $this->assertSame([null, null], $this->read());

        config(['agas.meter_reader.gemini_key' => '']);
        $this->assertSame([null, null], $this->read());
    }
}
