<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'value_type',
        'value',
        'points_value',
        'tier_id',
        'start_date',
        'end_date',
        'description',
        'status',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'points_value' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }
}
