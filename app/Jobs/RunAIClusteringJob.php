<?php

// This queued job runs AI clustering and persists the resulting segments.
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

// This job coordinates feature computation, AI training, and cluster persistence.
class RunAIClusteringJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // This acquires a lock so clustering runs are not overlapping.
    public function handle(AiInsightsService $insights): void
    {
        $lock = Cache::lock('ai_cluster_run', 1200);
        if (!$lock->get()) {
            return;
        }

        // These configuration values define the feature schema and clustering bounds.
        $featureKeys = (array) config('ai.feature_keys', []);
        $minK = (int) data_get(config('ai.k_range'), 'min', 2);
        $maxK = (int) data_get(config('ai.k_range'), 'max', 6);
        $minCustomers = (int) config('ai.min_customers_for_training', 20);

        // This records the start of a clustering run for audit and status tracking.
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
            // This computes features and prepares the training dataset.
            $dataset = $insights->computeCustomerFeatures(true);
            $customerIds = $dataset['customer_ids'] ?? [];
            $vectors = $dataset['vectors'] ?? [];
            $snapshots = $dataset['snapshots'] ?? [];

            // This enforces a minimum sample size so clustering has meaningful data.
            if (count($customerIds) < $minCustomers) {
                $run->update([
                    'status' => AiRunStatus::FAILED->value,
                    'error_message' => "At least {$minCustomers} customers are required for clustering.",
                    'completed_at' => now(),
                ]);
                return;
            }

            // This calls the AI service to train and assign cluster labels.
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

            // This groups customer IDs by their assigned cluster label.
            $clusterGroups = [];
            foreach ($labels as $index => $label) {
                $labelKey = (string) $label;
                $clusterGroups[$labelKey] ??= [];
                $clusterGroups[$labelKey][] = $customerIds[$index];
            }

            $clusterRecords = [];
            foreach ($clusterGroups as $labelKey => $ids) {
                // This aggregates summary stats used in cluster reporting.
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

                // This calculates averages for cluster-level metrics.
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

            // This applies friendly labels based on spending rank.
            $clusterRecords = $this->applyClusterLabels($clusterRecords);

            // This persists clusters and their customer assignments in a single transaction.
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
                        // This stores a snapshot of customer features at clustering time.
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

                // This inserts in chunks to avoid large single insert statements.
                foreach (array_chunk($customerRows, 500) as $chunk) {
                    AiClusterCustomer::insert($chunk);
                }

                // This finalizes the run with metrics returned by the AI service.
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
            // This logs failures and marks the run as failed for visibility.
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
            // This ensures the lock is released even when errors occur.
            optional($lock)->release();
        }
    }

    // This assigns human-readable labels to clusters based on average spending.
    private function applyClusterLabels(array $clusterRecords): array
    {
        if (!$clusterRecords) {
            return $clusterRecords;
        }

        // These labels are ordered from lowest to highest spender segments.
        $labelsByRank = [
            'Value Seekers',
            'Budget Buyers',
            'Growing Shoppers',
            'Core Shoppers',
            'Loyal Spenders',
            'VIP Loyalists',
        ];

        // This ranks clusters by average spend so labels align with relative value.
        $sorted = $clusterRecords;
        uasort($sorted, function ($a, $b) {
            return ($a['avg_total_spent'] ?? 0) <=> ($b['avg_total_spent'] ?? 0);
        });

        $rankMap = [];
        $index = 0;
        $maxIndex = count($labelsByRank) - 1;
        foreach (array_keys($sorted) as $labelKey) {
            // This caps label index when there are more clusters than labels.
            $rankMap[$labelKey] = $labelsByRank[min($index, $maxIndex)];
            $index++;
        }

        // This replaces the numeric label with the ranked label while preserving the key.
        foreach ($clusterRecords as $labelKey => $data) {
            $clusterRecords[$labelKey]['label'] = $rankMap[$labelKey] ?? $data['label'];
        }

        return $clusterRecords;
    }
}
