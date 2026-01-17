<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMessage extends Model
{
    protected $fillable = [
        'store_id',
        'type',
        'title',
        'body',
        'tier_visibility',
        'sent_at',
    ];

    protected $casts = [
        'tier_visibility' => 'array',
        'sent_at' => 'datetime',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class)->orderBy('sort_order');
    }

    public function poll(): HasOne
    {
        return $this->hasOne(ChatPoll::class);
    }
}
