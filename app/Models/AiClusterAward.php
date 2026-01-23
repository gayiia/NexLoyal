<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiClusterAward extends Model
{
    protected $fillable = [
        'ai_cluster_id',
        'title',
        'type',
        'points_amount',
        'coupon_id',
        'status',
        'activated_at',
        'deactivated_at',
    ];

    protected $casts = [
        'points_amount' => 'integer',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(AiCluster::class, 'ai_cluster_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(AiClusterAwardCustomer::class);
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(AiAwardIssuance::class);
    }
}
