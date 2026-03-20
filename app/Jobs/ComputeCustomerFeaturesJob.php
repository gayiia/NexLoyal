<?php

// This queued job computes and persists AI customer features in the background.
namespace App\Jobs;

use App\Services\AiInsightsService;
use App\Support\AiClusterProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// This job ensures feature computation runs asynchronously and avoids overlapping runs.
class ComputeCustomerFeaturesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue((string) config('ai.queue', 'default'));
    }

    // This acquires a lock to prevent concurrent feature computations.
    public function handle(AiInsightsService $insights): void
    {
        $lock = Cache::lock('ai_features_compute', 600);
        if (!$lock->get()) {
            AiClusterProgress::log('Feature computation skipped because another run already holds the lock.', 'features');
            return;
        }

        try {
            // This generates and persists the latest customer features for AI.
            AiClusterProgress::log('Computing customer features in incremental mode.', 'features');
            $result = $insights->computeCustomerFeatures(true, false, true);
            $computation = is_array($result['computation'] ?? null) ? $result['computation'] : [];
            Cache::put('ai_cluster_last_feature_compute_context', [
                'mode' => $computation['mode'] ?? 'unknown',
                'upserted_customers' => (int) ($computation['upserted_customers'] ?? 0),
                'computed_at' => $computation['computed_at'] ?? null,
                'fallback_to_full' => (bool) ($computation['fallback_to_full'] ?? false),
                'reason' => $computation['reason'] ?? 'unknown',
                'updated_at' => now()->toIso8601String(),
            ], now()->addMinutes(30));
            $insights->flushFeatureDatasetStatsCache();
            $stats = $insights->getFeatureDatasetStats();
            Log::info('AI customer feature computation finished', [
                'mode' => $computation['mode'] ?? 'unknown',
                'target_customers' => (int) ($computation['target_customers'] ?? 0),
                'upserted_customers' => (int) ($computation['upserted_customers'] ?? 0),
                'fallback_to_full' => (bool) ($computation['fallback_to_full'] ?? false),
                'reason' => $computation['reason'] ?? 'unknown',
            ]);
            AiClusterProgress::log(
                'Customer features computed. Recomputed customers: ' . number_format((int) ($computation['upserted_customers'] ?? 0))
                . '. Eligible customers: ' . number_format((int) ($stats['eligible_customer_features'] ?? 0)) . '.',
                'features'
            );
        } finally {
            // This ensures the lock is released even if computation fails.
            optional($lock)->release();
        }
    }
}
