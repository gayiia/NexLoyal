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

// This job ensures feature computation runs asynchronously and avoids overlapping runs.
class ComputeCustomerFeaturesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
            AiClusterProgress::log('Computing customer features.', 'features');
            $insights->computeCustomerFeatures(true, false);
            $stats = $insights->getFeatureDatasetStats();
            AiClusterProgress::log(
                'Customer features computed. Eligible customers: ' . number_format((int) ($stats['eligible_customer_features'] ?? 0)) . '.',
                'features'
            );
        } finally {
            // This ensures the lock is released even if computation fails.
            optional($lock)->release();
        }
    }
}
