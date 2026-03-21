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
use Illuminate\Support\Collection;
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
        $insights = app(\App\Services\AiInsightsService::class);
        $serviceHealth = $insights->getAiServiceHealth();
        $featureStats = $insights->getFeatureDatasetStats();
        $clusters = $latestRun
            ? AiCluster::query()
                ->where('ai_cluster_run_id', $latestRun->id)
                ->get()
                ->sortBy(fn (AiCluster $cluster) => (float) $cluster->avg_total_spent)
                ->values()
            : collect();
        $clusterMetrics = $this->resolveClusterMetrics($latestRun);

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

        $awardMix = [
            'points' => (int) ($awardIssuanceCounts['points'] ?? 0),
            'coupon' => (int) ($awardIssuanceCounts['coupon'] ?? 0),
        ];
        $clusters = $this->decorateClusters($clusters, $clusterMetrics);
        $charts = $this->buildCharts($latestRun, $clusters, $clusterMetrics, $awardMix);
        $summaries = $this->buildSummaries($latestRun, $clusters, $clusterMetrics, $charts);

        // This renders the dashboard view with all derived data.
        return view('ai-insights', [
            'latestRun' => $latestRun,
            'clusters' => $clusters,
            'awards' => $awards,
            'serviceHealth' => $serviceHealth,
            'featureStats' => $featureStats,
            'charts' => $charts,
            'summaries' => $summaries,
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

    // This aggregates chart-ready cluster metrics from the persisted latest-run snapshots.
    private function resolveClusterMetrics(?AiClusterRun $latestRun): Collection
    {
        if (!$latestRun) {
            return collect();
        }

        return DB::table('ai_cluster_customers as cluster_customers')
            ->join('ai_clusters as clusters', 'clusters.id', '=', 'cluster_customers.ai_cluster_id')
            ->where('cluster_customers.ai_cluster_run_id', $latestRun->id)
            ->select([
                'clusters.id as cluster_id',
                'clusters.label',
                'clusters.cluster_index',
                DB::raw('COUNT(*) as customer_count'),
                DB::raw('AVG(cluster_customers.total_spent_snapshot) as avg_total_spent'),
                DB::raw('AVG(cluster_customers.points_earned_snapshot) as avg_points_earned'),
                DB::raw('AVG(cluster_customers.points_spent_snapshot) as avg_points_spent'),
                DB::raw('AVG(cluster_customers.orders_count_snapshot) as avg_orders_count'),
                DB::raw('AVG(cluster_customers.days_since_last_order_snapshot) as avg_days_since_last_order'),
            ])
            ->groupBy('clusters.id', 'clusters.label', 'clusters.cluster_index')
            ->get()
            ->map(function (object $row): array {
                return [
                    'cluster_id' => (int) $row->cluster_id,
                    'label' => (string) $row->label,
                    'cluster_index' => $row->cluster_index !== null ? (int) $row->cluster_index : null,
                    'customer_count' => (int) ($row->customer_count ?? 0),
                    'avg_total_spent' => round((float) ($row->avg_total_spent ?? 0), 2),
                    'avg_points_earned' => round((float) ($row->avg_points_earned ?? 0), 2),
                    'avg_points_spent' => round((float) ($row->avg_points_spent ?? 0), 2),
                    'avg_orders_count' => round((float) ($row->avg_orders_count ?? 0), 2),
                    'avg_days_since_last_order' => round((float) ($row->avg_days_since_last_order ?? 0), 2),
                ];
            })
            ->sortBy('avg_total_spent')
            ->values();
    }

    // This augments cluster models with the additional averages required by the dashboard cards.
    private function decorateClusters(Collection $clusters, Collection $clusterMetrics): Collection
    {
        $metricsByCluster = $clusterMetrics->keyBy('cluster_id');

        return $clusters
            ->map(function (AiCluster $cluster) use ($metricsByCluster): AiCluster {
                $metrics = $metricsByCluster->get($cluster->id, []);
                $cluster->setAttribute('avg_points_earned', (float) ($metrics['avg_points_earned'] ?? 0));
                $cluster->setAttribute('avg_days_since_last_order', (float) ($metrics['avg_days_since_last_order'] ?? 0));

                return $cluster;
            })
            ->sortBy(fn (AiCluster $cluster) => (float) $cluster->avg_total_spent)
            ->values();
    }

    // This prepares all datasets required by the AI Insights charts.
    private function buildCharts(?AiClusterRun $latestRun, Collection $clusters, Collection $clusterMetrics, array $awardMix): array
    {
        $metricMap = $clusterMetrics->keyBy('cluster_id');
        $labels = $clusters->pluck('label')->values()->all();

        return [
            'labels' => $labels,
            'distribution' => $clusters->pluck('customer_count')->map(fn ($value) => (int) $value)->values()->all(),
            'avg_spend' => $clusters->pluck('avg_total_spent')->map(fn ($value) => round((float) $value, 2))->values()->all(),
            'avg_points' => [
                'earned' => $clusters->map(fn (AiCluster $cluster) => round((float) data_get($metricMap->get($cluster->id, []), 'avg_points_earned', 0), 2))->values()->all(),
                'spent' => $clusters->map(fn (AiCluster $cluster) => round((float) data_get($metricMap->get($cluster->id, []), 'avg_points_spent', 0), 2))->values()->all(),
            ],
            'avg_orders' => $clusters->map(fn (AiCluster $cluster) => round((float) data_get($metricMap->get($cluster->id, []), 'avg_orders_count', (float) $cluster->avg_orders_count), 2))->values()->all(),
            'avg_days_since_last_order' => $clusters->map(fn (AiCluster $cluster) => round((float) data_get($metricMap->get($cluster->id, []), 'avg_days_since_last_order', 0), 2))->values()->all(),
            'scatter' => $this->buildScatterChart($latestRun, $clusters),
            'award_mix' => $awardMix,
        ];
    }

    // This prepares a 2D scatter chart using stored PCA coordinates when the latest run provides them.
    private function buildScatterChart(?AiClusterRun $latestRun, Collection $clusters): array
    {
        $emptyState = [
            'method' => 'fallback_features',
            'method_label' => 'Points Earned vs Points Spent',
            'description' => 'No clustering run is available yet.',
            'x_label' => 'Points Earned',
            'y_label' => 'Points Spent',
            'datasets' => [],
        ];

        if (!$latestRun) {
            return $emptyState;
        }

        $rows = DB::table('ai_cluster_customers as cluster_customers')
            ->join('ai_clusters as clusters', 'clusters.id', '=', 'cluster_customers.ai_cluster_id')
            ->where('cluster_customers.ai_cluster_run_id', $latestRun->id)
            ->select([
                'clusters.id as cluster_id',
                'clusters.label',
                'cluster_customers.points_earned_snapshot',
                'cluster_customers.points_spent_snapshot',
                'cluster_customers.projection_x',
                'cluster_customers.projection_y',
                'cluster_customers.projection_method',
            ])
            ->orderBy('clusters.id')
            ->orderBy('cluster_customers.id')
            ->get()
            ->map(function (object $row): array {
                return [
                    'cluster_id' => (int) $row->cluster_id,
                    'label' => (string) $row->label,
                    'points_earned_snapshot' => (float) ($row->points_earned_snapshot ?? 0),
                    'points_spent_snapshot' => (float) ($row->points_spent_snapshot ?? 0),
                    'projection_x' => $row->projection_x !== null ? (float) $row->projection_x : null,
                    'projection_y' => $row->projection_y !== null ? (float) $row->projection_y : null,
                    'projection_method' => $row->projection_method,
                ];
            });

        if ($rows->isEmpty()) {
            return $emptyState;
        }

        $rows = $this->limitScatterRows($rows, 1200);
        $usePca = $rows->every(fn (array $row) => $row['projection_method'] === 'pca_2d' && $row['projection_x'] !== null && $row['projection_y'] !== null);
        $variance = collect((array) data_get($latestRun?->params, 'projection.explained_variance_ratio', []))
            ->map(fn ($value) => round(((float) $value) * 100, 1))
            ->values()
            ->all();

        $datasets = $clusters
            ->map(function (AiCluster $cluster) use ($rows, $usePca): array {
                $points = $rows
                    ->where('cluster_id', $cluster->id)
                    ->map(function (array $row) use ($usePca): array {
                        return [
                            'x' => $usePca ? (float) $row['projection_x'] : (float) $row['points_earned_snapshot'],
                            'y' => $usePca ? (float) $row['projection_y'] : (float) $row['points_spent_snapshot'],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'label' => $cluster->label,
                    'data' => $points,
                ];
            })
            ->filter(fn (array $dataset) => $dataset['data'] !== [])
            ->values()
            ->all();

        $allPoints = collect($datasets)
            ->flatMap(fn (array $dataset) => $dataset['data'])
            ->values();
        $displayBounds = $this->resolveScatterDisplayBounds($allPoints);
        $trimmedPointCount = $allPoints
            ->filter(function (array $point) use ($displayBounds): bool {
                return $point['x'] < $displayBounds['x']['min']
                    || $point['x'] > $displayBounds['x']['max']
                    || $point['y'] < $displayBounds['y']['min']
                    || $point['y'] > $displayBounds['y']['max'];
            })
            ->count();
        $trimmedMessage = $trimmedPointCount > 0
            ? ' Display window trimmed to the 1st-99th percentile range for readability.'
            : '';

        return [
            'method' => $usePca ? 'pca_2d' : 'fallback_features',
            'method_label' => $usePca ? 'PCA 2D Projection' : 'Points Earned vs Points Spent',
            'description' => $usePca
                ? ($variance !== []
                    ? 'PC1 and PC2 explain ' . number_format(array_sum(array_slice($variance, 0, 2)), 1) . '% of the variance in the latest run.' . $trimmedMessage
                    : 'PCA coordinates were generated from the standardized clustering feature space.' . $trimmedMessage)
                : 'Fallback scatter using the stored points-earned and points-spent snapshots.' . $trimmedMessage,
            'x_label' => $usePca ? 'Principal Component 1 (PC1)' : 'Points Earned',
            'y_label' => $usePca ? 'Principal Component 2 (PC2)' : 'Points Spent',
            'datasets' => $datasets,
            'display_bounds' => $displayBounds,
            'trimmed_point_count' => $trimmedPointCount,
        ];
    }

    // This samples large scatter datasets so the browser stays responsive on the insights page.
    private function limitScatterRows(Collection $rows, int $maxPoints): Collection
    {
        if ($rows->count() <= $maxPoints) {
            return $rows->values();
        }

        $total = max(1, $rows->count());
        $remaining = $maxPoints;
        $groups = $rows->groupBy('cluster_id');
        $allocations = [];

        foreach ($groups as $clusterId => $group) {
            $allocated = max(1, (int) floor(($group->count() / $total) * $maxPoints));
            $allocations[$clusterId] = min($group->count(), $allocated);
            $remaining -= $allocations[$clusterId];
        }

        foreach ($groups->sortByDesc(fn (Collection $group) => $group->count()) as $clusterId => $group) {
            if ($remaining <= 0) {
                break;
            }

            if (($allocations[$clusterId] ?? 0) < $group->count()) {
                $allocations[$clusterId]++;
                $remaining--;
            }
        }

        return $groups
            ->flatMap(function (Collection $group, $clusterId) use ($allocations): Collection {
                $take = min($group->count(), (int) ($allocations[$clusterId] ?? 0));
                if ($take >= $group->count()) {
                    return $group->values();
                }

                $step = max(1, (int) ceil($group->count() / max(1, $take)));

                return $group
                    ->values()
                    ->filter(fn (array $row, int $index) => $index % $step === 0)
                    ->take($take)
                    ->values();
            })
            ->values();
    }

    // This computes robust scatter axis bounds so a small number of extreme points do not dominate the chart.
    private function resolveScatterDisplayBounds(Collection $points): array
    {
        $xValues = $points->pluck('x')->map(fn ($value) => (float) $value)->values()->all();
        $yValues = $points->pluck('y')->map(fn ($value) => (float) $value)->values()->all();

        return [
            'x' => $this->buildAxisBounds($xValues),
            'y' => $this->buildAxisBounds($yValues),
        ];
    }

    // This builds percentile-based chart bounds with a small margin so the scatter remains readable.
    private function buildAxisBounds(array $values): array
    {
        if ($values === []) {
            return ['min' => 0.0, 'max' => 1.0];
        }

        sort($values, SORT_NUMERIC);
        $lower = $this->percentileValue($values, 0.01);
        $upper = $this->percentileValue($values, 0.99);

        if ($lower === $upper) {
            $padding = max(1.0, abs($lower) * 0.1);

            return [
                'min' => $lower - $padding,
                'max' => $upper + $padding,
            ];
        }

        $padding = ($upper - $lower) * 0.08;

        return [
            'min' => $lower - $padding,
            'max' => $upper + $padding,
        ];
    }

    // This calculates a linear-interpolated percentile for display-only chart bounds.
    private function percentileValue(array $values, float $percentile): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        if ($count === 1) {
            return (float) $values[0];
        }

        $position = max(0, min($count - 1, $percentile * ($count - 1)));
        $lowerIndex = (int) floor($position);
        $upperIndex = (int) ceil($position);

        if ($lowerIndex === $upperIndex) {
            return (float) $values[$lowerIndex];
        }

        $weight = $position - $lowerIndex;

        return ((float) $values[$lowerIndex] * (1 - $weight)) + ((float) $values[$upperIndex] * $weight);
    }

    // This builds short narrative callouts so the dashboard reads like a chapter-ready summary.
    private function buildSummaries(?AiClusterRun $latestRun, Collection $clusters, Collection $clusterMetrics, array $charts): array
    {
        if (!$latestRun || $clusters->isEmpty()) {
            return [
                [
                    'title' => 'Largest cluster',
                    'value' => 'No clustering run',
                    'description' => 'Run clustering to generate segment sizes for the latest customer dataset.',
                ],
                [
                    'title' => 'Highest spend cluster',
                    'value' => '-',
                    'description' => 'Average spend summaries appear after the first completed clustering run.',
                ],
                [
                    'title' => 'Most dormant cluster',
                    'value' => '-',
                    'description' => 'Recency analysis depends on persisted cluster-customer snapshots.',
                ],
                [
                    'title' => 'Scatter basis',
                    'value' => 'Points feature space',
                    'description' => 'The page will switch to PCA automatically once the latest run stores 2D coordinates.',
                ],
            ];
        }

        $largestCluster = $clusters->sortByDesc('customer_count')->first();
        $highestSpendCluster = $clusters->sortByDesc('avg_total_spent')->first();
        $mostDormantCluster = $clusterMetrics->sortByDesc('avg_days_since_last_order')->first();

        return [
            [
                'title' => 'Largest cluster',
                'value' => $largestCluster?->label ?? '-',
                'description' => ($largestCluster?->customer_count ?? 0) . ' customers in the latest run.',
            ],
            [
                'title' => 'Highest spend cluster',
                'value' => $highestSpendCluster?->label ?? '-',
                'description' => 'Average spend: ' . number_format((float) ($highestSpendCluster?->avg_total_spent ?? 0), 2),
            ],
            [
                'title' => 'Most dormant cluster',
                'value' => data_get($mostDormantCluster, 'label', '-'),
                'description' => 'Average recency: ' . number_format((float) data_get($mostDormantCluster, 'avg_days_since_last_order', 0), 1) . ' days since last order.',
            ],
            [
                'title' => 'Scatter basis',
                'value' => $charts['scatter']['method_label'] ?? 'Points Earned vs Points Spent',
                'description' => $charts['scatter']['description'] ?? 'The latest run does not yet include PCA coordinates.',
            ],
        ];
    }
}
