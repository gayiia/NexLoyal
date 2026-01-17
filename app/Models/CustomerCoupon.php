<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCoupon extends Model
{
    protected $fillable = [
        'customer_id',
        'coupon_id',
        'points_spent',
        'code',
        'status',
        'redeemed_at',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'points_spent' => 'integer',
        'redeemed_at' => 'datetime',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
