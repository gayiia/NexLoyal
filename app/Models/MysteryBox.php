<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MysteryBox extends Model
{
    protected $fillable = [
        'store_id',
        'name',
        'tiers',
        'is_active',
        'starts_at',
        'ends_at',
        'claim_rule',
    ];

    protected $casts = [
        'tiers' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MysteryBoxItem::class);
    }
}
