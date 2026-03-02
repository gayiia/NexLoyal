<?php

// This controller renders AI insights and manages clustering runs.
namespace App\Http\Controllers;

use App\Jobs\ComputeCustomerFeaturesJob;
use App\Jobs\RunAIClusteringJob;
use App\Models\AiAwardIssuance;
use App\Models\AiCluster;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Models\AiClusterAward;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use App\Support\AiClusterProgress;

// This class powers the AI insights dashboard and export endpoints.
class AiInsightsController extends Controller
{
    // This loads the latest clustering data and renders the AI insights page.
    public function index(Request $request)
    {
        // This fetches the most recent clustering run if one exists.
        $latestRun = $this->resolveLatestRun();
        $serviceHealth = app(\App\Services\AiInsightsService::class)->getAiServiceHealth();
        $featureStats = app(\App\Services\AiInsightsService::class)->getFeatureDatasetStats();
        $clusters = $latestRun
            ? AiCluster::query()
                ->where('ai_cluster_run_id', $latestRun->id)
                ->orderBy('label')
                ->get()
            : collect();

        // This loads configured AI awards and their linked coupon data.
        $awards = AiClusterAward::query()
            ->with(['cluster', 'coupon'])
            ->orderByDesc('id')
            ->get();

        // This aggregates award issuance counts by type for summary charts.
        $awardIssuanceCounts = AiAwardIssuance::query()
            ->select('ai_cluster_awards.type', DB::raw('COUNT(*) as total'))
            ->join('ai_cluster_awards', 'ai_cluster_awards.id', '=', 'ai_award_issuances.ai_cluster_award_id')
            ->groupBy('ai_cluster_awards.type')
            ->pluck('total', 'type')
            ->toArray();

        // These arrays are used to build charts in the Blade template.
        $chartLabels = $clusters->pluck('label')->values()->all();
        $chartDistribution = $clusters->pluck('customer_count')->values()->all();
        $chartAvgSpend = $clusters->pluck('avg_total_spent')->map(fn ($value) => (float) $value)->values()->all();
        $awardMix = [
            'points' => (int) ($awardIssuanceCounts['points'] ?? 0),
            'coupon' => (int) ($awardIssuanceCounts['coupon'] ?? 0),
        ];

        // This renders the dashboard view with all derived data.
        return view('ai-insights', [
            'latestRun' => $latestRun,
            'clusters' => $clusters,
            'awards' => $awards,
            'serviceHealth' => $serviceHealth,
            'featureStats' => $featureStats,
            'charts' => [
                'labels' => $chartLabels,
                'distribution' => $chartDistribution,
                'avg_spend' => $chartAvgSpend,
                'award_mix' => $awardMix,
            ],
        ]);
    }

    // This starts a new AI clustering run as a chained background job.
    public function run(Request $request)
    {
        $health = app(\App\Services\AiInsightsService::class)->getAiServiceHealth();
        if (!($health['ok'] ?? false)) {
            return redirect()
                ->route('ai-insights')
                ->with('error', 'AI service is offline. Start the FastAPI service before clustering.');
        }

        AiClusterProgress::startPending('AI clustering queued. Waiting for the worker to start.');

        // This ensures feature computation happens before clustering.
        Bus::chain([
            new ComputeCustomerFeaturesJob(),
            new RunAIClusteringJob(),
        ])->dispatch();

        return redirect()->route('ai-insights')->with('status', 'AI clustering started.');
    }

    // This streams a CSV export of customers in a selected cluster.
    public function exportCluster(AiCluster $cluster)
    {
        // This builds the query used for chunked CSV export.
        $query = AiClusterCustomer::query()
            ->with('customer')
            ->where('ai_cluster_id', $cluster->id)
            ->orderByDesc('total_spent_snapshot');

        // This names the file with the cluster ID and timestamp.
        $fileName = 'ai-cluster-' . $cluster->id . '-customers-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // This streams the CSV content to avoid loading all rows into memory.
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // This writes the header row expected by the export.
            fputcsv($handle, [
                'Customer ID',
                'Name',
                'Email',
                'Orders',
                'Total Spent',
                'Loyalty Points',
            ]);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    // This falls back to email when name fields are missing.
                    $customer = $row->customer;
                    $nameParts = array_filter([$customer?->first_name, $customer?->last_name]);
                    $name = $nameParts ? implode(' ', $nameParts) : ($customer?->email ?? 'Customer');
                    fputcsv($handle, [
                        $customer?->id,
                        $name,
                        $customer?->email,
                        $row->orders_count_snapshot,
                        $row->total_spent_snapshot,
                        $row->loyalty_points_snapshot,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, $headers);
    }

    // This returns a small customer sample for a cluster modal without loading the entire run into memory.
    public function clusterCustomers(AiCluster $cluster)
    {
        $rows = AiClusterCustomer::query()
            ->with('customer')
            ->where('ai_cluster_id', $cluster->id)
            ->orderByDesc('total_spent_snapshot')
            ->limit(200)
            ->get()
            ->map(function (AiClusterCustomer $row): array {
                $customer = $row->customer;

                return [
                    'customer_id' => $customer?->id,
                    'name' => $customer?->full_name ?: $customer?->email ?: 'Customer',
                    'email' => $customer?->email,
                    'orders_count_snapshot' => (int) $row->orders_count_snapshot,
                    'total_spent_snapshot' => (float) $row->total_spent_snapshot,
                    'loyalty_points_snapshot' => (int) $row->loyalty_points_snapshot,
                ];
            })
            ->values();

        return response()->json([
            'cluster_id' => $cluster->id,
            'cluster_label' => $cluster->label,
            'customer_count' => (int) $cluster->customer_count,
            'sample_limit' => 200,
            'rows' => $rows,
        ]);
    }

    // This returns the status of the latest clustering run for polling.
    public function status()
    {
        $latestRun = $this->resolveLatestRun();
        $progress = AiClusterProgress::snapshot();

        if (!$latestRun && !$progress) {
            return response()->json([
                'status' => 'none',
            ]);
        }

        $useProgressState = $this->shouldPreferProgressState($latestRun, $progress);
        $status = $useProgressState ? ($progress['status'] ?? 'pending') : ($latestRun?->status ?? ($progress['status'] ?? 'pending'));
        $errorMessage = $useProgressState ? null : $latestRun?->error_message;

        // This exposes run metrics used by the front-end progress UI.
        return response()->json([
            'run_id' => $useProgressState ? ($progress['run_id'] ?? null) : ($latestRun?->id ?? ($progress['run_id'] ?? null)),
            'status' => $status,
            'phase' => $progress['phase'] ?? null,
            'message' => $progress['message'] ?? null,
            'logs' => $progress['logs'] ?? [],
            'updated_at' => $progress['updated_at'] ?? optional($latestRun?->updated_at)->toIso8601String(),
            'total_customers' => (int) ($latestRun?->total_customers ?? 0),
            'total_clusters' => (int) ($latestRun?->total_clusters ?? 0),
            'silhouette_score' => $latestRun?->silhouette_score,
            'selected_k' => $latestRun?->selected_k,
            'final_inertia' => $latestRun?->final_inertia,
            'silhouette_scores' => $latestRun?->silhouette_scores,
            'inertia_scores' => $latestRun?->inertia_scores,
            'error_message' => $errorMessage,
            'started_at' => optional($latestRun?->started_at)->toIso8601String(),
            'completed_at' => optional($latestRun?->completed_at)->toIso8601String(),
            'feature_stats' => app(\App\Services\AiInsightsService::class)->getFeatureDatasetStats(),
            'service_health' => app(\App\Services\AiInsightsService::class)->getAiServiceHealth(),
        ]);
    }

    // This reconciles stale running runs when the worker has already crashed or exhausted retries.
    private function resolveLatestRun(): ?AiClusterRun
    {
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        if (!$latestRun || $latestRun->status !== 'running') {
            return $latestRun;
        }

        $hasQueuedAiJobs = DB::table('jobs')
            ->where(function ($query): void {
                $query->where('payload', 'like', '%App\\\\Jobs\\\\ComputeCustomerFeaturesJob%')
                    ->orWhere('payload', 'like', '%App\\\\Jobs\\\\RunAIClusteringJob%');
            })
            ->exists();

        $failedAiJob = DB::table('failed_jobs')
            ->where('failed_at', '>=', $latestRun->started_at)
            ->where(function ($query): void {
                $query->where('payload', 'like', '%App\\\\Jobs\\\\ComputeCustomerFeaturesJob%')
                    ->orWhere('payload', 'like', '%App\\\\Jobs\\\\RunAIClusteringJob%');
            })
            ->orderByDesc('failed_at')
            ->first();

        $progress = AiClusterProgress::snapshot();
        $progressUpdatedAt = !empty($progress['updated_at']) ? Carbon::parse($progress['updated_at']) : null;
        $lastKnownUpdate = collect([$latestRun->updated_at, $progressUpdatedAt])->filter()->max();

        if ($failedAiJob && !$hasQueuedAiJobs) {
            $message = 'The queue worker stopped before the cluster run could finish.';
            $latestRun->update([
                'status' => 'failed',
                'error_message' => $message,
                'completed_at' => now(),
            ]);
            AiClusterProgress::markFailed($latestRun->id, $message);

            return $latestRun->fresh();
        }

        if (!$hasQueuedAiJobs && $lastKnownUpdate instanceof Carbon && $lastKnownUpdate->lt(now()->subMinutes(30))) {
            $message = 'The cluster run appears stale. No AI queue activity has been recorded for over 30 minutes.';
            $latestRun->update([
                'status' => 'failed',
                'error_message' => $message,
                'completed_at' => now(),
            ]);
            AiClusterProgress::markFailed($latestRun->id, $message);

            return $latestRun->fresh();
        }

        return $latestRun;
    }

    // This prefers the queued progress feed over the previous run while a new pipeline waits for a worker.
    private function shouldPreferProgressState(?AiClusterRun $latestRun, array $progress): bool
    {
        if ($progress === [] || !in_array($progress['status'] ?? null, ['pending', 'running'], true)) {
            return false;
        }

        if (!$latestRun) {
            return true;
        }

        $progressUpdatedAt = !empty($progress['updated_at']) ? Carbon::parse($progress['updated_at']) : null;
        $runUpdatedAt = $latestRun->updated_at ? Carbon::parse($latestRun->updated_at) : null;

        if (!$progressUpdatedAt) {
            return false;
        }

        return !$runUpdatedAt || $progressUpdatedAt->gt($runUpdatedAt);
    }
}
