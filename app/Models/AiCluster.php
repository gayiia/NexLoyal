<?php

// This model represents a single cluster produced by an AI run.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// This class stores cluster metrics, labels, and relationships.
class AiCluster extends Model
{
    // These fields are mass assignable when persisting cluster results.
    protected $fillable = [
        'ai_cluster_run_id',
        'label',
        'cluster_index',
        'customer_count',
        'avg_total_spent',
        'avg_orders_count',
        'avg_loyalty_points',
        'avg_points_spent',
        'centroid',
    ];

    // These casts normalize numeric metrics and centroid arrays.
    protected $casts = [
        'cluster_index' => 'integer',
        'customer_count' => 'integer',
        'avg_total_spent' => 'decimal:2',
        'avg_orders_count' => 'decimal:2',
        'avg_loyalty_points' => 'decimal:2',
        'avg_points_spent' => 'decimal:2',
        'centroid' => 'array',
    ];

    // This links the cluster to the run that generated it.
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiClusterRun::class, 'ai_cluster_run_id');
    }

    // This lists customer assignments for the cluster.
    public function customers(): HasMany
    {
        return $this->hasMany(AiClusterCustomer::class);
    }

    // This lists AI awards configured for the cluster.
    public function awards(): HasMany
    {
        return $this->hasMany(AiClusterAward::class);
    }
}
