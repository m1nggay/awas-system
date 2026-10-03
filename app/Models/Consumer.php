<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consumer extends BaseModel
{
    protected $primaryKey = 'consumer_id';

    /** Type of Consumer (stored value => label). */
    public const TYPES = [
        'residential'   => 'Residential',
        'commercial'    => 'Commercial',
        'institutional' => 'Institutional',
    ];

    /** Short explanations shown by the (i) help buttons. */
    public const TYPE_HELP = [
        'residential'   => 'A home or household primarily used for living.',
        'commercial'    => 'A business establishment used for commercial activities.',
        'institutional' => 'An organization such as a school, government office, hospital, or similar institution.',
    ];

    protected $casts = [
        'is_senior' => 'boolean',
    ];

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class, 'purok_id', 'purok_id');
    }

    /** Words that belong to the surname that follows them (Del Valle, Delos Arcos, Dela Cruz, De Leon, San Jose). */
    public const SURNAME_PARTICLES = '/^(del|dela|de|delos|los|la|san|santa|sta\.?)$/i';

    /** Name suffixes kept after the given names: "Caliwatan, Angel Jr." */
    public const NAME_SUFFIX_PATTERN = '/^(jr\.?|sr\.?|i{1,3}|iv|#\d+)$/i';

    /** "Surname, Given names" — how the consumer list is displayed and sorted. */
    public function getDisplayNameAttribute(): string
    {
        $name = trim($this->full_name);
        // Businesses and institutions have no surname — show them as written.
        if (($this->consumer_type ?? 'residential') !== 'residential') {
            return $name;
        }
        // A trailing note such as "(Bukid)" stays at the end.
        $note = preg_match('/\s*(\([^)]*\))$/', $name, $m) ? $m[1] : '';
        if ($note !== '') {
            $name = trim(substr($name, 0, -strlen($m[0])));
        }
        $display = implode(' / ', array_map([self::class, 'surnameFirst'], explode(' / ', $name)));
        return $note !== '' ? "$display $note" : $display;
    }

    /** One person's name, "Angel Caliwatan Jr." → "Caliwatan, Angel Jr." (joint accounts are "Name / Name"). */
    private static function surnameFirst(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName));
        $suffix = count($parts) > 2 && preg_match(self::NAME_SUFFIX_PATTERN, end($parts)) ? array_pop($parts) : null;
        $surname = array_pop($parts);
        // Two-word surnames: "Joseph Del Valle" → "Del Valle, Joseph", "Virgo Delos Arcos" → "Delos Arcos, Virgo".
        while (count($parts) > 1 && preg_match(self::SURNAME_PARTICLES, end($parts))) {
            $surname = array_pop($parts) . ' ' . $surname;
        }
        $name = $parts ? $surname . ', ' . implode(' ', $parts) : $surname;
        return $suffix ? "$name $suffix" : $name;
    }
}
