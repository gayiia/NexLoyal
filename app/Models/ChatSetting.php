<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSetting extends Model
{
    protected $fillable = [
        'store_id',
        'enabled',
        'allowed_tiers',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'allowed_tiers' => 'array',
    ];
}
