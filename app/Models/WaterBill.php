<?php

namespace App\Models;

class WaterBill extends BaseModel
{
    protected $primaryKey = 'bill_id';

    public function getBalanceAttribute(): float
    {
        return (float)$this->total_amount - (float)$this->amount_paid;
    }
}
