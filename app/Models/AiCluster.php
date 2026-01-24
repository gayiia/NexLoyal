<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiCluster extends Model
{
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

    protected $casts = [
        'cluster_index' => 'integer',
        'customer_count' => 'integer',
        'avg_total_spent' => 'decimal:2',
        'avg_orders_count' => 'decimal:2',
        'avg_loyalty_points' => 'decimal:2',
        'avg_points_spent' => 'decimal:2',
        'centroid' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiClusterRun::class, 'ai_cluster_run_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(AiClusterCustomer::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(AiClusterAward::class);
    }
}
