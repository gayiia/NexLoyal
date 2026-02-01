<?php

// This model represents a selectable option within a chat poll.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// This class stores option labels and their vote relationships.
class ChatPollOption extends Model
{
    // These fields are mass assignable when creating poll options.
    protected $fillable = [
        'chat_poll_id',
        'label',
        'sort_order',
    ];

    // This cast normalizes the sort order for display.
    protected $casts = [
        'sort_order' => 'integer',
    ];

    // This links the option to its parent poll.
    public function poll(): BelongsTo
    {
        return $this->belongsTo(ChatPoll::class, 'chat_poll_id');
    }

    // This lists votes cast for this option.
    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class, 'option_id');
    }
}
