<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MysteryBoxItem extends Model
{
    protected $fillable = [
        'mystery_box_id',
        'coupon_id',
        'weight',
    ];

    protected $casts = [
        'weight' => 'integer',
    ];

    public function mysteryBox(): BelongsTo
    {
        return $this->belongsTo(MysteryBox::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
