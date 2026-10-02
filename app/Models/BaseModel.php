<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared defaults for every AGAS table: timestamps are filled by MySQL
 * column defaults (CURRENT_TIMESTAMP / ON UPDATE), exactly as in the
 * original schema, so Eloquent must not try to write them itself.
 */
abstract class BaseModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
