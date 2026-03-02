<?php

// This controller exposes manual AI feature and training tools for admins.
namespace App\Http\Controllers;

use App\Jobs\ComputeCustomerFeaturesJob;
use App\Jobs\RunAIClusteringJob;
use App\Models\AiClusterRun;
use App\Models\AiCluster;
use App\Models\CustomerFeature;
use App\Services\AiInsightsService;
use App\Support\AiClusterProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

// This class supports the AI sandbox screens and test predictions.
class AiSandboxController extends Controller
{
    // This shows the AI sandbox page with the latest run and configuration.
    public function index()
    {
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();

        return view('ai-sandbox', [
            'latestRun' => $latestRun,
            'settings' => config('ai'),
        ]);
    }

    // This kicks off background computation of customer features.
    public function computeFeatures(): RedirectResponse
    {
        ComputeCustomerFeaturesJob::dispatch();

        return redirect()
            ->route('ai-sandbox')
            ->with('status', 'Feature computation started.');
    }

    // This runs a full training chain in the background.
    public function train(): RedirectResponse
    {
        $health = app(\App\Services\AiInsightsService::class)->getAiServiceHealth();
        if (!($health['ok'] ?? false)) {
            return redirect()
                ->route('ai-sandbox')
                ->with('error', 'AI service is offline. Start the FastAPI service before training.');
        }

        AiClusterProgress::startPending('AI training queued from the sandbox.');

        Bus::chain([
            new ComputeCustomerFeaturesJob(),
            new RunAIClusteringJob(),
        ])->dispatch();

        return redirect()
            ->route('ai-sandbox')
            ->with('status', 'AI training started.');
    }

    // This lists computed features with optional customer search filters.
    public function featurePreview(Request $request)
    {
        // This uses a simple search to match customer ID, email, or Shopify ID.
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

    // This predicts a cluster for a specific customer and returns JSON.
    public function predict(Request $request, AiInsightsService $insights)
    {
        // This validates that the customer exists locally.
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        try {
            // This calls the AI service to generate a prediction.
            $result = $insights->predictForCustomer((int) $validated['customer_id']);
        } catch (\Throwable $exception) {
            // This returns a validation-style error when prediction fails.
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        // This maps the numeric cluster ID to a human-readable label.
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        $clusterLabel = null;
        if ($latestRun && array_key_exists('cluster_id', $result)) {
            $cluster = AiCluster::query()
                ->where('ai_cluster_run_id', $latestRun->id)
                ->where('cluster_index', (int) $result['cluster_id'])
                ->first();
            $clusterLabel = $cluster?->label;
        }

        // This returns prediction details for the sandbox UI.
        return response()->json([
            'cluster_id' => $result['cluster_id'] ?? null,
            'cluster_label' => $clusterLabel,
            'distance' => $result['distance'] ?? null,
            'model_metadata' => $result['model_metadata'] ?? null,
        ]);
    }
}
