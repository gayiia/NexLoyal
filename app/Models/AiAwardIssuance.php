<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAwardIssuance extends Model
{
    protected $fillable = [
        'ai_cluster_award_id',
        'customer_id',
        'reference_type',
        'reference_id',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function award(): BelongsTo
    {
        return $this->belongsTo(AiClusterAward::class, 'ai_cluster_award_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
