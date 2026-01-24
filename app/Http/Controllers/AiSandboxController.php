<?php

namespace App\Http\Controllers;

use App\Jobs\ComputeCustomerFeaturesJob;
use App\Jobs\RunAIClusteringJob;
use App\Models\AiClusterRun;
use App\Models\AiCluster;
use App\Models\CustomerFeature;
use App\Services\AiInsightsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class AiSandboxController extends Controller
{
    public function index()
    {
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();

        return view('ai-sandbox', [
            'latestRun' => $latestRun,
            'settings' => config('ai'),
        ]);
    }

    public function computeFeatures(): RedirectResponse
    {
        ComputeCustomerFeaturesJob::dispatch();

        return redirect()
            ->route('ai-sandbox')
            ->with('status', 'Feature computation started.');
    }

    public function train(): RedirectResponse
    {
        Bus::chain([
            new ComputeCustomerFeaturesJob(),
            new RunAIClusteringJob(),
        ])->dispatch();

        return redirect()
            ->route('ai-sandbox')
            ->with('status', 'AI training started.');
    }

    public function featurePreview(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = CustomerFeature::query()->with('customer');
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('customer_id', $search)
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('shopify_id', 'like', "%{$search}%");
                    });
            });
        }

        $features = $query->orderByDesc('computed_at')->paginate(25)->withQueryString();

        return view('ai-feature-preview', [
            'features' => $features,
            'search' => $search,
        ]);
    }

    public function predict(Request $request, AiInsightsService $insights)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        try {
            $result = $insights->predictForCustomer((int) $validated['customer_id']);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        $clusterLabel = null;
        if ($latestRun && array_key_exists('cluster_id', $result)) {
            $cluster = AiCluster::query()
                ->where('ai_cluster_run_id', $latestRun->id)
                ->where('cluster_index', (int) $result['cluster_id'])
                ->first();
            $clusterLabel = $cluster?->label;
        }

        return response()->json([
            'cluster_id' => $result['cluster_id'] ?? null,
            'cluster_label' => $clusterLabel,
            'distance' => $result['distance'] ?? null,
            'model_metadata' => $result['model_metadata'] ?? null,
        ]);
    }
}
