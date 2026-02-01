<?php

// This model represents a mystery box configuration and schedule.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// This class stores mystery box settings and related items.
class MysteryBox extends Model
{
    // These fields are mass assignable from the admin UI.
    protected $fillable = [
        'store_id',
        'name',
        'tiers',
        'is_active',
        'starts_at',
        'ends_at',
        'claim_rule',
    ];

    // These casts normalize JSON tiers and timestamps.
    protected $casts = [
        'tiers' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    // This links the box to its reward items.
    public function items(): HasMany
    {
        return $this->hasMany(MysteryBoxItem::class);
    }
}
