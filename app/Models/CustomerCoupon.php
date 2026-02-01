<?php

// This model records a coupon redemption made by a customer.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class tracks coupon codes, status, and redemption timing.
class CustomerCoupon extends Model
{
    // These fields are mass assignable from redemption workflows.
    protected $fillable = [
        'customer_id',
        'coupon_id',
        'points_spent',
        'code',
        'status',
        'source',
        'mystery_box_id',
        'redeemed_at',
        'used_at',
        'expires_at',
    ];

    // These casts normalize numeric and timestamp fields.
    protected $casts = [
        'points_spent' => 'integer',
        'redeemed_at' => 'datetime',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // This links the redemption to the customer.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // This links the redemption to the coupon definition.
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    // This links the redemption to a mystery box when applicable.
    public function mysteryBox(): BelongsTo
    {
        return $this->belongsTo(MysteryBox::class);
    }
}
