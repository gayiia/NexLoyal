<?php

// This model stores computed AI feature vectors for customers.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class holds normalized features and metadata for clustering.
class CustomerFeature extends Model
{
    // These fields are mass assignable from the feature computation service.
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

    // These casts normalize numeric, date, and JSON feature data.
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

    // This links the feature record to its customer.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
