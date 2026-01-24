<?php

namespace App\Jobs;

use App\Services\AiInsightsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ComputeCustomerFeaturesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(AiInsightsService $insights): void
    {
        $lock = Cache::lock('ai_features_compute', 600);
        if (!$lock->get()) {
            return;
        }

        try {
            $insights->computeCustomerFeatures(true);
        } finally {
            optional($lock)->release();
        }
    }
}
