<?php

// This model represents a Shopify customer with loyalty and profile data.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// This class defines customer attributes, casts, and relationships.
class Customer extends Model
{
    // These fields are mass assignable from sync and import flows.
    protected $fillable = [
        'shopify_id',
        'first_name',
        'last_name',
        'gender',
        'email',
        'phone',
        'status',
        'orders_count',
        'total_spent',
        'currency',
        'shopify_created_at',
        'loyalty_points',
        'points_pending',
        'tier_id',
        'birthday',
        'profile_completed_at',
        'birthday_rewarded_at',
    ];

    // These casts normalize date and numeric fields for consistent access.
    protected $casts = [
        'shopify_created_at' => 'datetime',
        'loyalty_points' => 'integer',
        'points_pending' => 'integer',
        'birthday' => 'date',
        'profile_completed_at' => 'datetime',
        'birthday_rewarded_at' => 'date',
    ];

    // This links the customer to their current loyalty tier.
    public function tier()
    {
        return $this->belongsTo(Tier::class);
    }

    // This lists coupon redemptions associated with the customer.
    public function coupons()
    {
        return $this->hasMany(CustomerCoupon::class);
    }

    // This links the customer to their computed AI feature record.
    public function feature()
    {
        return $this->hasOne(CustomerFeature::class);
    }

    // This provides a convenient full name accessor for UI display.
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
