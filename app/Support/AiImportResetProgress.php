<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

// This helper tracks reset progress so the AI import page can show a live status bar.
class AiImportResetProgress
{
    private const CACHE_KEY = 'ai_import_reset_progress';
    private const LOG_LIMIT = 25;

    // This marks reset as queued before the worker picks up the job.
    public static function startPending(string $message = 'AI import reset queued.'): void
    {
        self::write([
            'status' => 'pending',
            'phase' => 'queued',
            'progress' => 5,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => [
                self::makeLog($message),
            ],
        ]);
    }

    // This appends a progress event while reset is executing in the queue.
    public static function log(string $message, string $phase = 'running', int $progress = 50, string $status = 'running'): void
    {
        $snapshot = self::snapshot();

        self::write([
            'status' => $status,
            'phase' => $phase,
            'progress' => max(0, min(100, $progress)),
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => self::appendLogs($snapshot['logs'] ?? [], self::makeLog($message)),
        ]);
    }

    // This marks reset as completed so polling can stop.
    public static function markCompleted(string $message = 'AI import reset completed.'): void
    {
        $snapshot = self::snapshot();

        self::write([
            'status' => 'completed',
            'phase' => 'completed',
            'progress' => 100,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => self::appendLogs($snapshot['logs'] ?? [], self::makeLog($message)),
        ]);
    }

    // This marks reset as failed and stores the latest error message.
    public static function markFailed(string $message): void
    {
        $snapshot = self::snapshot();

        self::write([
            'status' => 'failed',
            'phase' => 'failed',
            'progress' => max(0, min(95, (int) ($snapshot['progress'] ?? 0))),
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => self::appendLogs($snapshot['logs'] ?? [], self::makeLog($message, 'error')),
        ]);
    }

    // This returns the current reset progress payload for polling endpoints.
    public static function snapshot(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    private static function write(array $payload): void
    {
        Cache::put(self::CACHE_KEY, $payload, now()->addHours(4));
    }

    private static function makeLog(string $message, string $level = 'info'): array
    {
        return [
            'time' => now()->format('H:i:s'),
            'message' => $message,
            'level' => $level,
        ];
    }

    private static function appendLogs(array $existing, array $entry): array
    {
        $existing[] = $entry;

        return array_slice($existing, -self::LOG_LIMIT);
    }
}

