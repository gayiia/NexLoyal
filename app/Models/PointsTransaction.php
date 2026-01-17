<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointsTransaction extends Model
{
    protected $fillable = [
        'store_id',
        'customer_id',
        'points',
        'status',
        'source',
        'source_type',
        'type',
        'order_id',
        'event_key',
        'reason',
        'title',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'points' => 'integer',
        'order_id' => 'integer',
        'meta' => 'array',
    ];
}
