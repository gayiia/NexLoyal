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
        'tenure_days',
        'features',
        'computed_at',
        'is_new_customer',
        'is_excluded',
        'excluded_reason',
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
        'tenure_days' => 'integer',
        'features' => 'array',
        'computed_at' => 'datetime',
        'is_new_customer' => 'boolean',
        'is_excluded' => 'boolean',
        'excluded_reason' => 'string',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
