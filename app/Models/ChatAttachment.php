<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatAttachment extends Model
{
    protected $fillable = [
        'chat_message_id',
        'file_url',
        'file_type',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function getResolvedUrlAttribute(): ?string
    {
        if (!empty($this->file_url)) {
            $raw = trim((string) $this->file_url);
            if (Str::startsWith($raw, ['http://', 'https://'])) {
                $path = parse_url($raw, PHP_URL_PATH);
                if ($path && Str::startsWith($path, '/storage/')) {
                    return url($path);
                }
                return $raw;
            }
            if (Str::startsWith($raw, '/storage/')) {
                return url($raw);
            }
            return url('/storage/' . ltrim($raw, '/'));
        }

        if (!empty($this->url)) {
            return $this->url;
        }

        $path = $this->file_path ?? $this->path ?? null;
        if (!$path) {
            return null;
        }

        $path = (string) $path;
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return url('/storage/' . ltrim($path, '/'));
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
