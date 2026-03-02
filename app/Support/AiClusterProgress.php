<?php

namespace App\Support;

use App\Models\AiClusterRun;
use Illuminate\Support\Facades\Cache;

// This helper keeps a lightweight progress feed for the latest AI clustering pipeline.
class AiClusterProgress
{
    private const CACHE_KEY = 'ai_cluster_progress';
    private const LOG_LIMIT = 25;

    // This resets the feed when a new clustering pipeline is queued.
    public static function startPending(string $message = 'AI clustering queued.'): void
    {
        self::write([
            'run_id' => null,
            'status' => 'pending',
            'phase' => 'queued',
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => [
                self::makeLog($message),
            ],
        ]);
    }

    // This attaches the active run id once the clustering job creates the run row.
    public static function attachRun(int $runId, string $message = 'Cluster run started.'): void
    {
        $snapshot = self::snapshot();

        self::write([
            'run_id' => $runId,
            'status' => 'running',
            'phase' => 'starting',
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => self::appendLogs($snapshot['logs'] ?? [], self::makeLog($message)),
        ]);
    }

    // This appends a new progress event and refreshes the current phase.
    public static function log(string $message, string $phase = 'running', string $status = 'running'): void
    {
        $snapshot = self::snapshot();

        self::write([
            'run_id' => $snapshot['run_id'] ?? null,
            'status' => $status,
            'phase' => $phase,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => self::appendLogs($snapshot['logs'] ?? [], self::makeLog($message)),
        ]);
    }

    // This marks the pipeline as completed so polling UIs can stop.
    public static function markCompleted(?int $runId, string $message = 'Clustering completed.'): void
    {
        $snapshot = self::snapshot();

        self::write([
            'run_id' => $runId,
            'status' => 'completed',
            'phase' => 'completed',
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => self::appendLogs($snapshot['logs'] ?? [], self::makeLog($message)),
        ]);
    }

    // This marks the pipeline as failed and preserves the latest error for the UI.
    public static function markFailed(?int $runId, string $message): void
    {
        $snapshot = self::snapshot();

        self::write([
            'run_id' => $runId,
            'status' => 'failed',
            'phase' => 'failed',
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'logs' => self::appendLogs($snapshot['logs'] ?? [], self::makeLog($message, 'error')),
        ]);
    }

    // This returns the current feed payload for polling endpoints.
    public static function snapshot(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    // This persists the feed for a limited period after completion.
    private static function write(array $payload): void
    {
        Cache::put(self::CACHE_KEY, $payload, now()->addHours(4));

        if (!empty($payload['run_id'])) {
            $run = AiClusterRun::query()->find($payload['run_id']);
            if ($run) {
                $params = is_array($run->params) ? $run->params : [];
                $params['progress'] = [
                    'status' => $payload['status'] ?? null,
                    'phase' => $payload['phase'] ?? null,
                    'message' => $payload['message'] ?? null,
                    'updated_at' => $payload['updated_at'] ?? null,
                    'logs' => $payload['logs'] ?? [],
                ];
                $run->forceFill(['params' => $params])->save();
            }
        }
    }

    // This creates a timestamped log entry for the frontend activity feed.
    private static function makeLog(string $message, string $level = 'info'): array
    {
        return [
            'time' => now()->format('H:i:s'),
            'message' => $message,
            'level' => $level,
        ];
    }

    // This keeps only the most recent log lines so the cache payload stays small.
    private static function appendLogs(array $existing, array $entry): array
    {
        $existing[] = $entry;

        return array_slice($existing, -self::LOG_LIMIT);
    }
}
