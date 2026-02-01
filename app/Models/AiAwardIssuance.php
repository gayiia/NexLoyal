<?php

// This model records the issuance of an AI award to a customer.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class links award issuance to reference records and timestamps.
class AiAwardIssuance extends Model
{
    // These fields are mass assignable when recording issuances.
    protected $fillable = [
        'ai_cluster_award_id',
        'customer_id',
        'reference_type',
        'reference_id',
        'issued_at',
    ];

    // This cast normalizes the issued timestamp.
    protected $casts = [
        'issued_at' => 'datetime',
    ];

    // This links the issuance to its award.
    public function award(): BelongsTo
    {
        return $this->belongsTo(AiClusterAward::class, 'ai_cluster_award_id');
    }

    // This links the issuance to its customer.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
