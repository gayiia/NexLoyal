<?php

// This model tracks which customers are targeted for an AI award.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class records award issuance status per customer.
class AiClusterAwardCustomer extends Model
{
    // These fields are mass assignable when syncing award recipients.
    protected $fillable = [
        'ai_cluster_award_id',
        'customer_id',
        'status',
        'issued_at',
    ];

    // This cast normalizes the issued timestamp.
    protected $casts = [
        'issued_at' => 'datetime',
    ];

    // This links the record to its award.
    public function award(): BelongsTo
    {
        return $this->belongsTo(AiClusterAward::class, 'ai_cluster_award_id');
    }

    // This links the record to its customer.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
