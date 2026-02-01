<?php

// This model defines a reward to be issued to customers in a cluster.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// This class stores award settings and issuance relationships.
class AiClusterAward extends Model
{
    // These fields are mass assignable when creating AI awards.
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

    // These casts normalize numeric fields and timestamps.
    protected $casts = [
        'points_amount' => 'integer',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    // This links the award to its cluster.
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(AiCluster::class, 'ai_cluster_id');
    }

    // This links the award to a coupon when the type is coupon.
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    // This lists customers targeted for this award.
    public function customers(): HasMany
    {
        return $this->hasMany(AiClusterAwardCustomer::class);
    }

    // This lists issuance records for the award.
    public function issuances(): HasMany
    {
        return $this->hasMany(AiAwardIssuance::class);
    }
}
