<?php

// This model stores attachments linked to exclusive chat messages.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

// This class resolves attachment URLs and links to messages.
class ChatAttachment extends Model
{
    // These fields are mass assignable when uploading attachments.
    protected $fillable = [
        'chat_message_id',
        'file_url',
        'file_type',
        'sort_order',
    ];

    // This cast normalizes sort order for display.
    protected $casts = [
        'sort_order' => 'integer',
    ];

    // This accessor returns a fully qualified URL for the attachment.
    public function getResolvedUrlAttribute(): ?string
    {
        // This handles stored URLs that may be absolute or relative.
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

        // This falls back to legacy URL properties if present.
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

    // This links the attachment to its parent chat message.
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
