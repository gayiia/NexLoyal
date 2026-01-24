<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiClusterRun extends Model
{
    protected $fillable = [
        'status',
        'started_at',
        'completed_at',
        'total_customers',
        'total_clusters',
        'silhouette_score',
        'selected_k',
        'final_inertia',
        'silhouette_scores',
        'inertia_scores',
        'data_stats',
        'timing',
        'scaler_mean',
        'scaler_scale',
        'feature_names',
        'outlier_caps',
        'log_transforms',
        'model_metadata',
        'params',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_customers' => 'integer',
        'total_clusters' => 'integer',
        'silhouette_score' => 'decimal:4',
        'selected_k' => 'integer',
        'final_inertia' => 'decimal:4',
        'silhouette_scores' => 'array',
        'inertia_scores' => 'array',
        'data_stats' => 'array',
        'timing' => 'array',
        'scaler_mean' => 'array',
        'scaler_scale' => 'array',
        'feature_names' => 'array',
        'outlier_caps' => 'array',
        'log_transforms' => 'array',
        'model_metadata' => 'array',
        'params' => 'array',
    ];

    public function clusters(): HasMany
    {
        return $this->hasMany(AiCluster::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(AiClusterCustomer::class);
    }
}
