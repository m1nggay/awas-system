<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Key/value rows in system_settings, cached for the lifetime of the request. */
class Settings
{
    private array $cache = [];

    public function get(string $key, $default = null)
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = DB::table('system_settings')->where('setting_key', $key)->value('setting_value');
        }
        return $this->cache[$key] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        DB::table('system_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value]);
        $this->cache[$key] = $value;
    }
}
