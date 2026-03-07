<?php

// This queued job runs AI clustering and persists the resulting segments.
namespace App\Jobs;

use App\Models\AiCluster;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Enums\AiRunStatus;
use App\Services\AiInsightsService;
use App\Services\AiSmartRetrainingService;
use App\Support\AiClusterProgress;
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
    public function handle(AiInsightsService $insights, AiSmartRetrainingService $smartRetraining): void
    {
        $lock = Cache::lock('ai_cluster_run', 1200);
        if (!$lock->get()) {
            AiClusterProgress::markFailed(null, 'Clustering skipped because another run is already active.');
            return;
        }

        // These configuration values define the feature schema and clustering bounds.
        $featureKeys = (array) config('ai.feature_keys', []);
        $minK = (int) data_get(config('ai.k_range'), 'min', 2);
        $maxK = (int) data_get(config('ai.k_range'), 'max', 6);
        $fixedK = config('ai.fixed_k');
        $fixedK = is_numeric($fixedK) ? (int) $fixedK : null;
        $minCustomers = (int) config('ai.min_customers_for_training', 20);
        $smartRetrainingEnabled = (bool) config('ai.smart_retraining_enabled', true);
        $retrainIntervalDays = (int) config('ai.retrain_interval_days', 7);

        // This records the start of a clustering run for audit and status tracking.
        $run = AiClusterRun::create([
            'status' => AiRunStatus::RUNNING->value,
            'started_at' => now(),
            'params' => [
                'feature_keys' => $featureKeys,
                'min_k' => $minK,
                'max_k' => $maxK,
                'fixed_k' => $fixedK,
                'outlier_cap_quantile' => config('ai.outlier_cap_quantile'),
                'log_transforms' => config('ai.log_transforms'),
                'feature_schema_version' => config('ai.feature_schema_version'),
                'algorithm_version' => config('ai.algorithm_version'),
                'exclude_zero_activity_customers' => config('ai.exclude_zero_activity_customers'),
                'exclude_refund_only_customers' => config('ai.exclude_refund_only_customers'),
                'smart_retraining_enabled' => $smartRetrainingEnabled,
                'retrain_interval_days' => $retrainIntervalDays,
            ],
        ]);
        AiClusterProgress::attachRun($run->id, "Cluster run #{$run->id} started.");
        $payloadPath = null;

        try {
            $health = $insights->getAiServiceHealth();
            if (!($health['ok'] ?? false)) {
                $message = 'AI service is offline: ' . ($health['message'] ?? 'unknown error');
                $run->update([
                    'status' => AiRunStatus::FAILED->value,
                    'error_message' => $message,
                    'completed_at' => now(),
                ]);
                AiClusterProgress::markFailed($run->id, $message);
                return;
            }

            $retrainDecision = $smartRetraining->evaluate();
            $shouldRetrain = (bool) ($retrainDecision['should_retrain'] ?? true);
            $decisionReason = (string) ($retrainDecision['reason'] ?? 'unknown');
            $decisionMessage = (string) ($retrainDecision['message'] ?? 'No decision message provided.');
            $decisionDetails = is_array($retrainDecision['details'] ?? null) ? $retrainDecision['details'] : [];

            Log::info('AI smart retraining decision computed', [
                'should_retrain' => $shouldRetrain,
                'reason' => $decisionReason,
                'message' => $decisionMessage,
                'fixed_k' => $fixedK,
                'details' => $decisionDetails,
            ]);
            AiClusterProgress::log(
                ($shouldRetrain ? 'Retraining triggered: ' : 'Retraining skipped: ') . $decisionMessage,
                'decision'
            );

            $trainingMetadata = [
                'last_trained_at' => now()->toIso8601String(),
                'last_seen_points_transaction_at' => data_get($retrainDecision, 'snapshot.last_seen_points_transaction_at'),
                'last_seen_points_transaction_id' => data_get($retrainDecision, 'snapshot.last_seen_points_transaction_id'),
                'customer_count_at_training' => data_get($retrainDecision, 'snapshot.customer_count_at_training'),
                'transaction_count_at_training' => data_get($retrainDecision, 'snapshot.transaction_count_at_training'),
            ];

            // This streams stored features to a JSON file so Laravel does not keep the full matrix in memory.
            AiClusterProgress::log('Preparing stored customer features for training.', 'dataset');
            $dataset = $insights->exportStoredTrainingPayload($trainingMetadata);
            $payloadPath = $dataset['path'] ?? null;
            $trainingCount = (int) ($dataset['count'] ?? 0);
            AiClusterProgress::log('Eligible customers ready for training: ' . number_format($trainingCount) . '.', 'dataset');

            // This enforces a minimum sample size so clustering has meaningful data.
            if ($trainingCount < $minCustomers) {
                $message = "At least {$minCustomers} customers are required for clustering.";
                $run->update([
                    'status' => AiRunStatus::FAILED->value,
                    'error_message' => $message,
                    'completed_at' => now(),
                ]);
                AiClusterProgress::markFailed($run->id, $message);
                return;
            }

            if ($shouldRetrain) {
                // This calls the AI service to train and assign cluster labels.
                Log::info('AI clustering retraining triggered', [
                    'run_id' => $run->id,
                    'training_count' => $trainingCount,
                    'fixed_k' => $fixedK,
                    'decision_reason' => $decisionReason,
                ]);
                AiClusterProgress::log('Sending feature matrix to the AI service for retraining.', 'training');
                $response = $insights->trainFromJsonFile($payloadPath);
            } else {
                // This reuses the existing model to assign labels without retraining.
                Log::info('AI clustering retraining skipped; reusing saved model for prediction', [
                    'run_id' => $run->id,
                    'training_count' => $trainingCount,
                    'fixed_k' => $fixedK,
                    'decision_reason' => $decisionReason,
                ]);
                AiClusterProgress::log('Reusing existing model to predict labels for current features.', 'predicting');
                $response = $insights->predictFromJsonFile($payloadPath);
            }
            AiClusterProgress::log('AI service returned cluster labels. Persisting results.', 'persisting');

            $labels = $response['labels'] ?? [];
            if (count($labels) !== $trainingCount) {
                throw new \RuntimeException('AI service returned mismatched labels.');
            }

            // This aggregates cluster stats in a streaming pass so large runs do not build customer snapshots in memory.
            $clusterRecords = [];
            $clusterStats = [];
            $labelIndex = 0;
            $insights->chunkStoredTrainingRows(500, function ($rows) use (&$clusterStats, &$labelIndex, $labels): void {
                foreach ($rows as $row) {
                    $labelKey = (string) ($labels[$labelIndex] ?? '');
                    $labelIndex++;
                    if ($labelKey === '') {
                        continue;
                    }

                    $clusterStats[$labelKey] ??= [
                        'customer_count' => 0,
                        'total_spent' => 0.0,
                        'orders_count' => 0,
                        'loyalty_points' => 0,
                        'points_spent' => 0,
                    ];

                    $clusterStats[$labelKey]['customer_count']++;
                    $clusterStats[$labelKey]['total_spent'] += (float) ($row->total_spent ?? 0);
                    $clusterStats[$labelKey]['orders_count'] += (int) ($row->orders_count ?? 0);
                    $clusterStats[$labelKey]['loyalty_points'] += (int) ($row->loyalty_points ?? 0);
                    $clusterStats[$labelKey]['points_spent'] += (int) ($row->points_spent ?? 0);
                }
            });

            foreach ($clusterStats as $labelKey => $totals) {
                $count = max(1, (int) ($totals['customer_count'] ?? 0));
                $clusterRecords[$labelKey] = [
                    'ai_cluster_run_id' => $run->id,
                    'label' => (string) $labelKey,
                    'cluster_index' => (int) $labelKey,
                    'customer_count' => $count,
                    'avg_total_spent' => ((float) ($totals['total_spent'] ?? 0)) / $count,
                    'avg_orders_count' => ((int) ($totals['orders_count'] ?? 0)) / $count,
                    'avg_loyalty_points' => ((int) ($totals['loyalty_points'] ?? 0)) / $count,
                    'avg_points_spent' => ((int) ($totals['points_spent'] ?? 0)) / $count,
                    'centroid' => $response['centroids'][(int) $labelKey] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // This applies friendly labels based on spending rank.
            $clusterRecords = $this->applyClusterLabels($clusterRecords);

            // This persists clusters and their customer assignments in streaming chunks.
            DB::transaction(function () use (
                $insights,
                $run,
                $clusterRecords,
                $labels,
                $response,
                $trainingCount,
                $retrainDecision,
                $shouldRetrain,
                $decisionReason,
                $decisionMessage,
                $decisionDetails
            ): void {
                $clusterIdMap = [];
                foreach ($clusterRecords as $labelKey => $data) {
                    $cluster = AiCluster::create($data);
                    $clusterIdMap[$labelKey] = $cluster->id;
                }

                $labelIndex = 0;
                $customerRows = [];
                $insights->chunkStoredTrainingRows(500, function ($rows) use (&$customerRows, &$labelIndex, $labels, $clusterIdMap, $run): void {
                    foreach ($rows as $row) {
                        $labelKey = (string) ($labels[$labelIndex] ?? '');
                        $labelIndex++;
                        $clusterId = $clusterIdMap[$labelKey] ?? null;
                        if (!$clusterId) {
                            continue;
                        }

                        $customerRows[] = [
                            'ai_cluster_run_id' => $run->id,
                            'ai_cluster_id' => $clusterId,
                            'customer_id' => (int) ($row->customer_id ?? 0),
                            'orders_count_snapshot' => (int) ($row->orders_count ?? 0),
                            'total_spent_snapshot' => (float) ($row->total_spent ?? 0),
                            'loyalty_points_snapshot' => (int) ($row->loyalty_points ?? 0),
                            'points_earned_snapshot' => (int) ($row->points_earned ?? 0),
                            'points_spent_snapshot' => (int) ($row->points_spent ?? 0),
                            'redeemed_coupons_snapshot' => (int) ($row->redeemed_coupons ?? 0),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (count($customerRows) >= 500) {
                            AiClusterCustomer::insert($customerRows);
                            $customerRows = [];
                        }
                    }
                });

                if ($customerRows !== []) {
                    AiClusterCustomer::insert($customerRows);
                }

                // This finalizes the run with metrics returned by the AI service.
                $run->update([
                    'status' => AiRunStatus::COMPLETED->value,
                    'completed_at' => now(),
                    'total_customers' => $trainingCount,
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
                    'model_metadata' => $response['model_metadata'] ?? data_get($retrainDecision, 'model_metadata'),
                    'params' => array_merge((array) ($run->params ?? []), [
                        'retraining' => [
                            'should_retrain' => $shouldRetrain,
                            'reason' => $decisionReason,
                            'message' => $decisionMessage,
                            'details' => $decisionDetails,
                        ],
                    ]),
                ]);
            });
            AiClusterProgress::markCompleted($run->id, "Cluster run #{$run->id} completed.");
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
            AiClusterProgress::markFailed($run->id, $exception->getMessage());
        } finally {
            if ($payloadPath && is_file($payloadPath)) {
                @unlink($payloadPath);
            }
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
