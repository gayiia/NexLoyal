<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiImportBatch extends Model
{
    protected $fillable = [
        'status',
        'started_at',
        'completed_at',
        'rolled_back_at',
        'summary',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
        'summary' => 'array',
    ];
}
