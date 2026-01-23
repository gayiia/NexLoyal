<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFeature extends Model
{
    protected $fillable = [
        'customer_id',
        'orders_count',
        'total_spent',
        'avg_order_value',
        'redeemed_coupons',
        'points_earned',
        'points_spent',
        'loyalty_points',
        'points_pending',
        'last_order_at',
        'days_since_last_order',
        'features',
        'computed_at',
    ];

    protected $casts = [
        'orders_count' => 'integer',
        'total_spent' => 'decimal:2',
        'avg_order_value' => 'decimal:2',
        'redeemed_coupons' => 'integer',
        'points_earned' => 'integer',
        'points_spent' => 'integer',
        'loyalty_points' => 'integer',
        'points_pending' => 'integer',
        'last_order_at' => 'datetime',
        'days_since_last_order' => 'integer',
        'features' => 'array',
        'computed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
