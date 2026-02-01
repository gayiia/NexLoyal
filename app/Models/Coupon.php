<?php

// This model represents a redeemable coupon in the loyalty program.
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class stores coupon configuration and Shopify linkage fields.
class Coupon extends Model
{
    use HasFactory;

    // These fields are mass assignable from the coupon management UI.
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

    // These casts normalize numeric values, dates, and arrays.
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

    // This links the coupon to an optional loyalty tier.
    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }
}
