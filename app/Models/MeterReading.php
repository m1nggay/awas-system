<?php

namespace App\Models;

class MeterReading extends BaseModel
{
    protected $primaryKey = 'reading_id';

    /** `consumption` is a MySQL generated column — never written. */
    protected $guarded = ['consumption'];
}
