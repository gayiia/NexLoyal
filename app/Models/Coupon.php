<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'value_type',
        'value',
        'points_value',
        'tier_id',
        'start_date',
        'end_date',
        'description',
        'status',
        'is_mystery_box_coupon',
        'is_ai_cluster_coupon',
        'product_ids',
        'buy_product_ids',
        'get_product_ids',
        'buy_quantity',
        'get_quantity',
        'buyx_discount_type',
        'buyx_discount_value',
        'code',
        'shopify_price_rule_id',
        'shopify_discount_code_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'points_value' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_mystery_box_coupon' => 'boolean',
        'is_ai_cluster_coupon' => 'boolean',
        'product_ids' => 'array',
        'buy_product_ids' => 'array',
        'get_product_ids' => 'array',
        'buy_quantity' => 'integer',
        'get_quantity' => 'integer',
        'buyx_discount_value' => 'decimal:2',
    ];

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }
}
