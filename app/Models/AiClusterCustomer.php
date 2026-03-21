<?php

// This model links a customer to an AI cluster run with snapshot metrics.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class stores per-customer cluster assignments and summary stats.
class AiClusterCustomer extends Model
{
    // These fields are mass assignable when saving cluster memberships.
    protected $fillable = [
        'ai_cluster_run_id',
        'ai_cluster_id',
        'customer_id',
        'total_spent_snapshot',
        'orders_count_snapshot',
        'loyalty_points_snapshot',
        'points_earned_snapshot',
        'points_spent_snapshot',
        'redeemed_coupons_snapshot',
        'days_since_last_order_snapshot',
        'projection_x',
        'projection_y',
        'projection_method',
    ];

    // These casts normalize numeric snapshot fields.
    protected $casts = [
        'total_spent_snapshot' => 'decimal:2',
        'orders_count_snapshot' => 'integer',
        'loyalty_points_snapshot' => 'integer',
        'points_earned_snapshot' => 'integer',
        'points_spent_snapshot' => 'integer',
        'redeemed_coupons_snapshot' => 'integer',
        'days_since_last_order_snapshot' => 'integer',
        'projection_x' => 'decimal:6',
        'projection_y' => 'decimal:6',
    ];

    // This links the record to the clustering run.
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiClusterRun::class, 'ai_cluster_run_id');
    }

    // This links the record to the cluster.
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(AiCluster::class, 'ai_cluster_id');
    }

    // This links the record to the customer.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
