<?php

namespace App\Models;

class ChatbotFaq extends BaseModel
{
    public const CATEGORIES = [
        'billing'       => 'Water Billing',
        'meter_reading' => 'Meter Reading',
        'payments'      => 'Payments',
        'account'       => 'Account',
        'system'        => 'AWAS System',
        'general'       => 'General',
    ];
}
