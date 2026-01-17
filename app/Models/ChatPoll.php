<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatPoll extends Model
{
    protected $fillable = [
        'chat_message_id',
        'allow_multiple',
        'closes_at',
    ];

    protected $casts = [
        'allow_multiple' => 'boolean',
        'closes_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ChatPollOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class);
    }
}
