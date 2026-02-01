<?php

// This model records a customer's vote in a chat poll.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class links votes to polls, options, and customers.
class ChatPollVote extends Model
{
    // These fields are mass assignable when recording votes.
    protected $fillable = [
        'store_id',
        'chat_poll_id',
        'option_id',
        'customer_id',
        'voted_at',
    ];

    // This cast normalizes the vote timestamp.
    protected $casts = [
        'voted_at' => 'datetime',
    ];

    // This links the vote to its poll.
    public function poll(): BelongsTo
    {
        return $this->belongsTo(ChatPoll::class, 'chat_poll_id');
    }

    // This links the vote to its selected option.
    public function option(): BelongsTo
    {
        return $this->belongsTo(ChatPollOption::class, 'option_id');
    }

    // This links the vote to the customer who cast it.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
