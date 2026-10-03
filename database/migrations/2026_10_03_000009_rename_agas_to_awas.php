<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The system is now called AWAS (Adlay Water Augmentation System). Updates
 * stored texts that still say "AGAS" (chatbot FAQs, setting descriptions)
 * and names the default administrator account Jenela Costan.
 */
return new class extends Migration
{
    private const REPLACE = [
        'AGAS (Smart Water Management and Billing System with Online Payment)' => 'AWAS (Adlay Water Augmentation System)',
        'AGAS: Smart Water Management and Billing System with Online Payment' => 'AWAS: Adlay Water Augmentation System',
        'AGAS: Smart Water Management and Billing System' => 'AWAS: Adlay Water Augmentation System',
        'AGAS Smart Water Management and Billing System' => 'AWAS — Adlay Water Augmentation System',
        'AGAS / AWAS' => 'AWAS',
        'AGAS' => 'AWAS',
    ];

    private function rename(?string $text): ?string
    {
        return $text === null ? null : strtr($text, self::REPLACE);
    }

    public function up(): void
    {
        foreach (DB::table('chatbot_faqs')->get(['id', 'question', 'answer', 'keywords']) as $faq) {
            $keywords = $faq->keywords;
            if ($keywords !== null && stripos($keywords, 'agas') !== false && stripos($keywords, 'awas') === false) {
                $keywords .= ', ' . str_ireplace('agas', 'awas', $keywords);   // keep the old words, add the new ones
            }
            DB::table('chatbot_faqs')->where('id', $faq->id)->update([
                'question' => $this->rename($faq->question),
                'answer'   => $this->rename($faq->answer),
                'keywords' => $keywords,
            ]);
        }

        foreach (DB::table('system_settings')->where('description', 'like', '%AGAS%')->get(['setting_key', 'description']) as $s) {
            DB::table('system_settings')->where('setting_key', $s->setting_key)->update(['description' => $this->rename($s->description)]);
        }

        DB::table('users')->where('username', 'admin')->where('full_name', 'Barangay AGAS Administrator')
            ->update(['full_name' => 'Jenela Costan']);
    }

    public function down(): void
    {
        // Text renames are not reversed.
    }
};
