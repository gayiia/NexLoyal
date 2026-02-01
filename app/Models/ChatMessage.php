<?php

// This model represents an exclusive chat message sent to customers.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// This class stores message content, visibility rules, and related data.
class ChatMessage extends Model
{
    // These fields are mass assignable when creating chat messages.
    protected $fillable = [
        'store_id',
        'type',
        'title',
        'body',
        'tier_visibility',
        'sent_at',
    ];

    // These casts normalize visibility arrays and timestamps.
    protected $casts = [
        'tier_visibility' => 'array',
        'sent_at' => 'datetime',
    ];

    // This lists attachments associated with the message.
    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class)->orderBy('sort_order');
    }

    // This links a message to a poll when the type is POLL.
    public function poll(): HasOne
    {
        return $this->hasOne(ChatPoll::class);
    }
}
