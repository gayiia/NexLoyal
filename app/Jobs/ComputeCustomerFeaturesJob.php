<?php

// This queued job computes and persists AI customer features in the background.
namespace App\Jobs;

use App\Services\AiInsightsService;
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
            return;
        }

        try {
            // This generates and persists the latest customer features for AI.
            $insights->computeCustomerFeatures(true);
        } finally {
            // This ensures the lock is released even if computation fails.
            optional($lock)->release();
        }
    }
}
