<?php

// This model represents a single loyalty points ledger entry.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// This class records points earned, spent, or adjusted for customers.
class PointsTransaction extends Model
{
    // These fields are mass assignable for rule engines and imports.
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

    // These casts normalize numeric fields and JSON metadata.
    protected $casts = [
        'points' => 'integer',
        'order_id' => 'integer',
        'meta' => 'array',
    ];
}
