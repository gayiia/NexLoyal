<?php

namespace App\Jobs;

use App\Models\AiCluster;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Enums\AiRunStatus;
use App\Models\CustomerFeature;
use App\Services\AiInsightsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunAIClusteringJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(AiInsightsService $insights): void
    {
        $lock = Cache::lock('ai_cluster_run', 1200);
        if (!$lock->get()) {
            return;
        }

        $featureKeys = (array) config('ai.feature_keys', []);
        $minK = (int) data_get(config('ai.k_range'), 'min', 2);
        $maxK = (int) data_get(config('ai.k_range'), 'max', 6);
        $minCustomers = (int) config('ai.min_customers_for_training', 20);

        $run = AiClusterRun::create([
            'status' => AiRunStatus::RUNNING->value,
            'started_at' => now(),
            'params' => [
                'feature_keys' => $featureKeys,
                'min_k' => $minK,
                'max_k' => $maxK,
                'outlier_cap_quantile' => config('ai.outlier_cap_quantile'),
                'log_transforms' => config('ai.log_transforms'),
                'feature_schema_version' => config('ai.feature_schema_version'),
                'algorithm_version' => config('ai.algorithm_version'),
                'exclude_zero_activity_customers' => config('ai.exclude_zero_activity_customers'),
                'exclude_refund_only_customers' => config('ai.exclude_refund_only_customers'),
            ],
        ]);

        try {
            $dataset = $insights->computeCustomerFeatures(true);
            $customerIds = $dataset['customer_ids'] ?? [];
            $vectors = $dataset['vectors'] ?? [];
            $snapshots = $dataset['snapshots'] ?? [];

            if (count($customerIds) < $minCustomers) {
                $run->update([
                    'status' => AiRunStatus::FAILED->value,
                    'error_message' => "At least {$minCustomers} customers are required for clustering.",
                    'completed_at' => now(),
                ]);
                return;
            }

            $response = $insights->train(
                $vectors,
                $featureKeys,
                $dataset['outlier_caps'] ?? [],
                $dataset['log_transforms'] ?? []
            );

            $labels = $response['labels'] ?? [];
            if (count($labels) !== count($customerIds)) {
                throw new \RuntimeException('AI service returned mismatched labels.');
            }

            $clusterGroups = [];
            foreach ($labels as $index => $label) {
                $labelKey = (string) $label;
                $clusterGroups[$labelKey] ??= [];
                $clusterGroups[$labelKey][] = $customerIds[$index];
            }

            $clusterRecords = [];
            foreach ($clusterGroups as $labelKey => $ids) {
                $totals = [
                    'total_spent' => 0,
                    'orders_count' => 0,
                    'loyalty_points' => 0,
                    'points_spent' => 0,
                ];
                foreach ($ids as $customerId) {
                    $snapshot = $snapshots[$customerId] ?? [];
                    $totals['total_spent'] += (float) ($snapshot['total_spent_snapshot'] ?? 0);
                    $totals['orders_count'] += (int) ($snapshot['orders_count_snapshot'] ?? 0);
                    $totals['loyalty_points'] += (int) ($snapshot['loyalty_points_snapshot'] ?? 0);
                    $totals['points_spent'] += (int) ($snapshot['points_spent_snapshot'] ?? 0);
                }

                $count = max(1, count($ids));

                $clusterRecords[$labelKey] = [
                    'ai_cluster_run_id' => $run->id,
                    'label' => (string) $labelKey,
                    'cluster_index' => (int) $labelKey,
                    'customer_count' => count($ids),
                    'avg_total_spent' => $totals['total_spent'] / $count,
                    'avg_orders_count' => $totals['orders_count'] / $count,
                    'avg_loyalty_points' => $totals['loyalty_points'] / $count,
                    'avg_points_spent' => $totals['points_spent'] / $count,
                    'centroid' => $response['centroids'][(int) $labelKey] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $clusterRecords = $this->applyClusterLabels($clusterRecords);

            DB::transaction(function () use ($run, $clusterRecords, $clusterGroups, $snapshots, $response): void {
                $clusterIdMap = [];
                foreach ($clusterRecords as $labelKey => $data) {
                    $cluster = AiCluster::create($data);
                    $clusterIdMap[$labelKey] = $cluster->id;
                }

                $customerRows = [];
                foreach ($clusterGroups as $labelKey => $ids) {
                    $clusterId = $clusterIdMap[$labelKey] ?? null;
                    if (!$clusterId) {
                        continue;
                    }
                    foreach ($ids as $customerId) {
                        $snapshot = $snapshots[$customerId] ?? [];
                        $customerRows[] = array_merge($snapshot, [
                            'ai_cluster_run_id' => $run->id,
                            'ai_cluster_id' => $clusterId,
                            'customer_id' => $customerId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                foreach (array_chunk($customerRows, 500) as $chunk) {
                    AiClusterCustomer::insert($chunk);
                }

                $run->update([
                    'status' => AiRunStatus::COMPLETED->value,
                    'completed_at' => now(),
                    'total_customers' => count($snapshots),
                    'total_clusters' => count($clusterRecords),
                    'silhouette_score' => $response['final_silhouette'] ?? null,
                    'selected_k' => $response['selected_k'] ?? null,
                    'final_inertia' => $response['final_inertia'] ?? null,
                    'silhouette_scores' => $response['silhouette_scores'] ?? null,
                    'inertia_scores' => $response['inertia_scores'] ?? null,
                    'data_stats' => $response['data_stats'] ?? null,
                    'timing' => $response['timing'] ?? null,
                    'scaler_mean' => data_get($response, 'scaler.mean'),
                    'scaler_scale' => data_get($response, 'scaler.scale'),
                    'feature_names' => data_get($response, 'scaler.feature_names'),
                    'outlier_caps' => data_get($response, 'scaler.outlier_caps'),
                    'log_transforms' => data_get($response, 'scaler.log_transforms'),
                    'model_metadata' => $response['model_metadata'] ?? null,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('AI clustering failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $run->update([
                'status' => AiRunStatus::FAILED->value,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    private function applyClusterLabels(array $clusterRecords): array
    {
        if (!$clusterRecords) {
            return $clusterRecords;
        }

        $labelsByRank = [
            'Value Seekers',
            'Budget Buyers',
            'Growing Shoppers',
            'Core Shoppers',
            'Loyal Spenders',
            'VIP Loyalists',
        ];

        $sorted = $clusterRecords;
        uasort($sorted, function ($a, $b) {
            return ($a['avg_total_spent'] ?? 0) <=> ($b['avg_total_spent'] ?? 0);
        });

        $rankMap = [];
        $index = 0;
        $maxIndex = count($labelsByRank) - 1;
        foreach (array_keys($sorted) as $labelKey) {
            $rankMap[$labelKey] = $labelsByRank[min($index, $maxIndex)];
            $index++;
        }

        foreach ($clusterRecords as $labelKey => $data) {
            $clusterRecords[$labelKey]['label'] = $rankMap[$labelKey] ?? $data['label'];
        }

        return $clusterRecords;
    }
}
