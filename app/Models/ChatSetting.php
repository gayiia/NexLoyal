<?php

// This model stores configuration for the exclusive chat feature.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// This class controls whether chat is enabled and which tiers can access it.
class ChatSetting extends Model
{
    // These fields are mass assignable from the settings UI.
    protected $fillable = [
        'store_id',
        'enabled',
        'allowed_tiers',
    ];

    // These casts normalize booleans and tier arrays.
    protected $casts = [
        'enabled' => 'boolean',
        'allowed_tiers' => 'array',
    ];
}
