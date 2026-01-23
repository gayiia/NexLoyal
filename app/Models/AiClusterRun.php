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
        'params',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_customers' => 'integer',
        'total_clusters' => 'integer',
        'silhouette_score' => 'decimal:4',
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
