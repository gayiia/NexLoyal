<?php

// This model represents a poll associated with a chat message.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// This class stores poll settings and relationships to options and votes.
class ChatPoll extends Model
{
    // These fields are mass assignable when creating polls.
    protected $fillable = [
        'chat_message_id',
        'allow_multiple',
        'closes_at',
    ];

    // These casts normalize boolean and date fields.
    protected $casts = [
        'allow_multiple' => 'boolean',
        'closes_at' => 'datetime',
    ];

    // This links the poll to its parent message.
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    // This lists the options available in the poll.
    public function options(): HasMany
    {
        return $this->hasMany(ChatPollOption::class)->orderBy('sort_order');
    }

    // This lists votes cast for the poll.
    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class);
    }
}
