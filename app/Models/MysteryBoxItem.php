<?php

// This model links a mystery box to a coupon reward with a weight.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class defines the reward items available in a mystery box.
class MysteryBoxItem extends Model
{
    // These fields are mass assignable when building box items.
    protected $fillable = [
        'mystery_box_id',
        'coupon_id',
        'weight',
    ];

    // This cast normalizes the weight value.
    protected $casts = [
        'weight' => 'integer',
    ];

    // This links the item to its parent mystery box.
    public function mysteryBox(): BelongsTo
    {
        return $this->belongsTo(MysteryBox::class);
    }

    // This links the item to the coupon reward it grants.
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
