<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'shopify_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'status',
        'orders_count',
        'total_spent',
        'currency',
        'shopify_created_at',
    ];

    protected $casts = [
        'shopify_created_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
