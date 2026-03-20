<?php

namespace App\Jobs;

use App\Services\AiImportResetService;
use App\Support\AiImportResetProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// This queued job runs heavy AI import reset logic outside the web request timeout.
class ResetAiImportDataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private bool $deleteCustomersIfSafe = false)
    {
        $this->onQueue((string) config('ai.queue', 'default'));
    }

    public function handle(AiImportResetService $service): void
    {
        $lock = Cache::lock('ai_import_reset_lock', 1800);
        if (!$lock->get()) {
            AiImportResetProgress::markFailed('AI import reset skipped because another reset is already running.');
            return;
        }

        try {
            AiImportResetProgress::log('AI import reset started.', 'starting', 10);
            $summary = $service->runReset(
                $this->deleteCustomersIfSafe,
                function (string $message, string $phase, int $progress): void {
                    AiImportResetProgress::log($message, $phase, $progress);
                }
            );

            if ($this->deleteCustomersIfSafe && !($summary['deleted_customers'] ?? false)) {
                AiImportResetProgress::log(
                    'Strict customer cleanup requested, but safety check did not pass after reset cleanup.',
                    'safety_check',
                    96
                );
            }

            $message = 'AI import reset completed.';
            if ($summary['deleted_customers'] ?? false) {
                $message .= ' Removed ' . number_format((int) ($summary['deleted_customers_count'] ?? 0)) . ' customers after strict safety check.';
            }

            Log::info('AI import reset completed', [
                'delete_customers_if_safe' => $this->deleteCustomersIfSafe,
                'batch_id' => $summary['batch_id'] ?? null,
                'deleted_customers' => (bool) ($summary['deleted_customers'] ?? false),
                'deleted_customers_count' => (int) ($summary['deleted_customers_count'] ?? 0),
            ]);
            AiImportResetProgress::markCompleted($message);
        } catch (\Throwable $exception) {
            Log::error('AI import reset failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            AiImportResetProgress::markFailed($exception->getMessage());
            throw $exception;
        } finally {
            optional($lock)->release();
        }
    }
}
