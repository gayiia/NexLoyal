<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
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
        'tier_id',
        'birthday',
        'profile_completed_at',
        'birthday_rewarded_at',
    ];

    protected $casts = [
        'shopify_created_at' => 'datetime',
        'loyalty_points' => 'integer',
        'birthday' => 'date',
        'profile_completed_at' => 'datetime',
        'birthday_rewarded_at' => 'date',
    ];

    public function tier()
    {
        return $this->belongsTo(Tier::class);
    }

    public function coupons()
    {
        return $this->hasMany(CustomerCoupon::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
