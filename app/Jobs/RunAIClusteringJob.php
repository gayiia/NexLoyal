<?php

// This queued job runs AI clustering and persists the resulting segments.
namespace App\Jobs;

use App\Enums\AiRunStatus;
use App\Models\AiCluster;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
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

// This job coordinates full retraining runs and delta prediction runs.
class RunAIClusteringJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public bool $forceRetrain = false)
    {
        $this->onQueue((string) config('ai.queue', 'default'));
    }

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
        $payloadPath = null;

        try {
            $health = $insights->getAiServiceHealth();
            if (!($health['ok'] ?? false)) {
                $message = 'AI service is offline: ' . ($health['message'] ?? 'unknown error');
                AiClusterProgress::markFailed(null, $message);
                return;
            }

            if ($this->forceRetrain) {
                $retrainDecision = [
                    'should_retrain' => true,
                    'reason' => 'manual_force_retrain',
                    'message' => 'Manual force retrain requested from AI Insights.',
                    'details' => [
                        'requested_from' => 'ai_insights',
                        'smart_retraining_bypassed' => true,
                    ],
                ];
            } else {
                $retrainDecision = $smartRetraining->evaluate();
            }

            $shouldRetrain = (bool) ($retrainDecision['should_retrain'] ?? true);
            $decisionReason = (string) ($retrainDecision['reason'] ?? 'unknown');
            $decisionMessage = (string) ($retrainDecision['message'] ?? 'No decision message provided.');
            $decisionDetails = is_array($retrainDecision['details'] ?? null) ? $retrainDecision['details'] : [];

            Log::info('AI smart retraining decision computed', [
                'should_retrain' => $shouldRetrain,
                'reason' => $decisionReason,
                'message' => $decisionMessage,
                'fixed_k' => $fixedK,
                'force_retrain' => $this->forceRetrain,
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
                'feature_payload_mode' => 'preprocessed_vectors',
            ];

            $latestCompletedRun = $this->latestCompletedRunWithClusters();
            if (!$shouldRetrain && !$latestCompletedRun) {
                // Delta mode cannot proceed safely without a base run containing cluster records.
                $shouldRetrain = true;
                $decisionReason = 'delta_requires_base_run';
                $decisionMessage = 'No previous completed cluster run was found. Switching to full retraining.';
                $decisionDetails['fallback'] = 'forced_retrain_without_base_run';
                AiClusterProgress::log($decisionMessage, 'decision');
            }

            if ($shouldRetrain) {
                $run = AiClusterRun::create([
                    'status' => AiRunStatus::RUNNING->value,
                    'started_at' => now(),
                    'params' => [
                        'feature_keys' => $featureKeys,
                        'min_k' => $minK,
                        'max_k' => $maxK,
                        'fixed_k' => $fixedK,
                        'prediction_mode' => 'full',
                        'outlier_cap_quantile' => config('ai.outlier_cap_quantile'),
                        'log_transforms' => config('ai.log_transforms'),
                        'feature_schema_version' => config('ai.feature_schema_version'),
                        'algorithm_version' => config('ai.algorithm_version'),
                        'exclude_zero_activity_customers' => config('ai.exclude_zero_activity_customers'),
                        'exclude_refund_only_customers' => config('ai.exclude_refund_only_customers'),
                        'smart_retraining_enabled' => $smartRetrainingEnabled,
                        'retrain_interval_days' => $retrainIntervalDays,
                        'force_retrain' => $this->forceRetrain,
                        'feature_payload_mode' => 'preprocessed_vectors',
                    ],
                ]);
                AiClusterProgress::attachRun($run->id, "Cluster run #{$run->id} started.");

                // This streams stored features to a JSON file so Laravel does not keep the full matrix in memory.
                AiClusterProgress::log('Preparing full stored customer feature dataset for retraining.', 'dataset');
                $dataset = $insights->exportStoredTrainingPayload($trainingMetadata);
                $payloadPath = $dataset['path'] ?? null;
                $trainingCount = (int) ($dataset['count'] ?? 0);
                AiClusterProgress::log('Eligible customers ready for full prediction: ' . number_format($trainingCount) . '.', 'dataset');

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

                Log::info('AI clustering full mode started', [
                    'run_id' => $run->id,
                    'prediction_mode' => 'full',
                    'training_count' => $trainingCount,
                    'fixed_k' => $fixedK,
                    'decision_reason' => $decisionReason,
                ]);
                AiClusterProgress::log('Sending full feature matrix to the AI service for retraining.', 'training');
                $response = $insights->trainFromJsonFile($payloadPath);

                $labels = $response['labels'] ?? [];
                if (count($labels) !== $trainingCount) {
                    throw new \RuntimeException('AI service returned mismatched labels for full prediction.');
                }

                AiClusterProgress::log('AI service returned full-dataset labels. Persisting results.', 'persisting');
                $this->persistFullRun(
                    $insights,
                    $run,
                    $labels,
                    $response,
                    $trainingCount,
                    $shouldRetrain,
                    $decisionReason,
                    $decisionMessage,
                    $decisionDetails,
                    $retrainDecision
                );
                AiClusterProgress::markCompleted($run->id, "Cluster run #{$run->id} completed in full mode.");
            } else {
                $baseRun = $latestCompletedRun;
                $computeContext = Cache::get('ai_cluster_last_feature_compute_context', []);
                $computedAt = is_string($computeContext['computed_at'] ?? null) ? $computeContext['computed_at'] : null;
                $changedFeatureCount = (int) ($computeContext['upserted_customers'] ?? 0);

                Log::info('AI clustering delta mode selected', [
                    'base_run_id' => $baseRun?->id,
                    'prediction_mode' => 'delta',
                    'changed_feature_count' => $changedFeatureCount,
                    'computed_at' => $computedAt,
                    'decision_reason' => $decisionReason,
                ]);

                if (!$computedAt || $changedFeatureCount < 1) {
                    $message = 'Retraining skipped and no changed customers detected. No delta prediction required.';
                    AiClusterProgress::log($message, 'predicting');
                    AiClusterProgress::markCompleted($baseRun?->id, $message);
                    Log::info('AI clustering delta mode ended without prediction', [
                        'base_run_id' => $baseRun?->id,
                        'prediction_mode' => 'delta',
                        'changed_feature_count' => $changedFeatureCount,
                        'computed_at' => $computedAt,
                    ]);
                    return;
                }

                AiClusterProgress::log('Preparing delta prediction payload for changed customers only.', 'dataset');
                $dataset = $insights->exportStoredDeltaPredictionPayloadByComputedAt($computedAt);
                $payloadPath = $dataset['path'] ?? null;
                $deltaCount = (int) ($dataset['count'] ?? 0);
                AiClusterProgress::log('Changed eligible customers ready for delta prediction: ' . number_format($deltaCount) . '.', 'dataset');

                if ($deltaCount < 1) {
                    $message = 'Retraining skipped and delta export contained no eligible changed customers.';
                    AiClusterProgress::log($message, 'predicting');
                    AiClusterProgress::markCompleted($baseRun?->id, $message);
                    Log::info('AI clustering delta mode found no eligible changed customers', [
                        'base_run_id' => $baseRun?->id,
                        'prediction_mode' => 'delta',
                        'computed_at' => $computedAt,
                    ]);
                    return;
                }

                AiClusterProgress::log('Reusing saved model to predict labels for changed customers only.', 'predicting');
                $response = $insights->predictFromJsonFile($payloadPath);
                $labels = $response['labels'] ?? [];
                if (count($labels) !== $deltaCount) {
                    throw new \RuntimeException('AI service returned mismatched labels for delta prediction.');
                }

                $persistedCount = $this->persistDeltaRun(
                    $insights,
                    $baseRun,
                    $labels,
                    $response,
                    $computedAt,
                    $decisionReason,
                    $decisionMessage
                );
                AiClusterProgress::markCompleted(
                    $baseRun->id,
                    'Delta prediction completed. Persisted changed customers: ' . number_format($persistedCount) . '.'
                );
                Log::info('AI clustering delta mode completed', [
                    'base_run_id' => $baseRun->id,
                    'prediction_mode' => 'delta',
                    'changed_customer_count' => $deltaCount,
                    'persisted_customer_count' => $persistedCount,
                    'model_reused' => true,
                    'decision_reason' => $decisionReason,
                    'decision_message' => $decisionMessage,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('AI clustering failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            AiClusterProgress::markFailed(null, $exception->getMessage());
        } finally {
            if ($payloadPath && is_file($payloadPath)) {
                @unlink($payloadPath);
            }
            optional($lock)->release();
        }
    }

    // This persists clusters and assignments for a full retraining run.
    private function persistFullRun(
        AiInsightsService $insights,
        AiClusterRun $run,
        array $labels,
        array $response,
        int $trainingCount,
        bool $shouldRetrain,
        string $decisionReason,
        string $decisionMessage,
        array $decisionDetails,
        array $retrainDecision
    ): void {
        // This aggregates cluster stats in a streaming pass so large runs do not build customer snapshots in memory.
        $clusterRecords = [];
        $clusterStats = [];
        $labelIndex = 0;
        $projectionPoints = $this->resolveProjectionPoints($response, $trainingCount);
        $projectionMethod = is_string(data_get($response, 'projection.method'))
            ? data_get($response, 'projection.method')
            : null;
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

        $clusterRecords = $this->applyClusterLabels($clusterRecords);

        DB::transaction(function () use (
            $insights,
            $run,
            $clusterRecords,
            $labels,
            $response,
            $projectionPoints,
            $projectionMethod,
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
            $now = now();
            $insights->chunkStoredTrainingRows(500, function ($rows) use (
                &$customerRows,
                &$labelIndex,
                $labels,
                $clusterIdMap,
                $run,
                $now,
                $projectionPoints,
                $projectionMethod
            ): void {
                foreach ($rows as $row) {
                    $currentIndex = $labelIndex;
                    $labelKey = (string) ($labels[$currentIndex] ?? '');
                    $labelIndex++;
                    $clusterId = $clusterIdMap[$labelKey] ?? null;
                    if (!$clusterId) {
                        continue;
                    }

                    $projection = $projectionPoints[$currentIndex] ?? null;
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
                        'days_since_last_order_snapshot' => (int) ($row->days_since_last_order ?? 0),
                        'projection_x' => $projection['x'] ?? null,
                        'projection_y' => $projection['y'] ?? null,
                        'projection_method' => $projectionMethod,
                        'created_at' => $now,
                        'updated_at' => $now,
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
                    'prediction_mode' => 'full',
                    'projection' => [
                        'method' => $projectionMethod,
                        'explained_variance_ratio' => data_get($response, 'projection.explained_variance_ratio'),
                    ],
                    'retraining' => [
                        'should_retrain' => $shouldRetrain,
                        'reason' => $decisionReason,
                        'message' => $decisionMessage,
                        'details' => $decisionDetails,
                    ],
                ]),
            ]);
        });
    }

    // This persists only changed customer assignments into the latest completed run.
    private function persistDeltaRun(
        AiInsightsService $insights,
        AiClusterRun $baseRun,
        array $labels,
        array $response,
        string $computedAt,
        string $decisionReason,
        string $decisionMessage
    ): int {
        $clustersByIndex = $baseRun->clusters()
            ->get()
            ->keyBy(fn (AiCluster $cluster) => (int) ($cluster->cluster_index ?? -1));

        if ($clustersByIndex->isEmpty()) {
            throw new \RuntimeException('Delta prediction requires an existing run with cluster records.');
        }

        $persisted = 0;
        $labelIndex = 0;
        $now = now();
        $projectionPoints = $this->resolveProjectionPoints($response, count($labels));
        $projectionMethod = is_string(data_get($response, 'projection.method'))
            ? data_get($response, 'projection.method')
            : null;

        DB::transaction(function () use (
            $insights,
            $baseRun,
            $computedAt,
            $labels,
            $projectionPoints,
            $projectionMethod,
            $clustersByIndex,
            $now,
            &$persisted,
            &$labelIndex,
            $decisionReason,
            $decisionMessage
        ): void {
            $upsertRows = [];
            $insights->chunkStoredRowsByComputedAt($computedAt, 500, function ($rows) use (
                &$upsertRows,
                $labels,
                $projectionPoints,
                $projectionMethod,
                $clustersByIndex,
                $baseRun,
                $now,
                &$persisted,
                &$labelIndex
            ): void {
                foreach ($rows as $row) {
                    $currentIndex = $labelIndex;
                    $labelKey = (int) ($labels[$currentIndex] ?? -1);
                    $labelIndex++;

                    /** @var AiCluster|null $cluster */
                    $cluster = $clustersByIndex->get($labelKey);
                    if (!$cluster) {
                        continue;
                    }

                    $projection = $projectionPoints[$currentIndex] ?? null;
                    $upsertRows[] = [
                        'ai_cluster_run_id' => $baseRun->id,
                        'ai_cluster_id' => $cluster->id,
                        'customer_id' => (int) ($row->customer_id ?? 0),
                        'orders_count_snapshot' => (int) ($row->orders_count ?? 0),
                        'total_spent_snapshot' => (float) ($row->total_spent ?? 0),
                        'loyalty_points_snapshot' => (int) ($row->loyalty_points ?? 0),
                        'points_earned_snapshot' => (int) ($row->points_earned ?? 0),
                        'points_spent_snapshot' => (int) ($row->points_spent ?? 0),
                        'redeemed_coupons_snapshot' => (int) ($row->redeemed_coupons ?? 0),
                        'days_since_last_order_snapshot' => (int) ($row->days_since_last_order ?? 0),
                        'projection_x' => $projection['x'] ?? null,
                        'projection_y' => $projection['y'] ?? null,
                        'projection_method' => $projectionMethod,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($upsertRows) >= 500) {
                        AiClusterCustomer::upsert(
                            $upsertRows,
                            ['ai_cluster_run_id', 'customer_id'],
                            [
                                'ai_cluster_id',
                                'orders_count_snapshot',
                                'total_spent_snapshot',
                                'loyalty_points_snapshot',
                                'points_earned_snapshot',
                                'points_spent_snapshot',
                                'redeemed_coupons_snapshot',
                                'days_since_last_order_snapshot',
                                'projection_x',
                                'projection_y',
                                'projection_method',
                                'updated_at',
                            ]
                        );
                        $persisted += count($upsertRows);
                        $upsertRows = [];
                    }
                }
            });

            if ($upsertRows !== []) {
                AiClusterCustomer::upsert(
                    $upsertRows,
                    ['ai_cluster_run_id', 'customer_id'],
                    [
                        'ai_cluster_id',
                        'orders_count_snapshot',
                        'total_spent_snapshot',
                        'loyalty_points_snapshot',
                        'points_earned_snapshot',
                        'points_spent_snapshot',
                        'redeemed_coupons_snapshot',
                        'days_since_last_order_snapshot',
                        'projection_x',
                        'projection_y',
                        'projection_method',
                        'updated_at',
                    ]
                );
                $persisted += count($upsertRows);
            }

            $this->refreshClusterStatsForRun($baseRun);

            $params = array_merge((array) ($baseRun->params ?? []), [
                'last_delta_prediction' => [
                    'computed_at' => $computedAt,
                    'persisted_customers' => $persisted,
                    'model_reused' => true,
                    'decision_reason' => $decisionReason,
                    'decision_message' => $decisionMessage,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
            $baseRun->update([
                'completed_at' => now(),
                'params' => $params,
            ]);
        });

        return $persisted;
    }

    // This refreshes cluster-level aggregates after delta updates.
    private function refreshClusterStatsForRun(AiClusterRun $run): void
    {
        $aggregates = DB::table('ai_cluster_customers')
            ->select([
                'ai_cluster_id',
                DB::raw('COUNT(*) as customer_count'),
                DB::raw('AVG(total_spent_snapshot) as avg_total_spent'),
                DB::raw('AVG(orders_count_snapshot) as avg_orders_count'),
                DB::raw('AVG(loyalty_points_snapshot) as avg_loyalty_points'),
                DB::raw('AVG(points_spent_snapshot) as avg_points_spent'),
            ])
            ->where('ai_cluster_run_id', $run->id)
            ->groupBy('ai_cluster_id')
            ->get()
            ->keyBy('ai_cluster_id');

        $clusters = $run->clusters()->get();
        $clusterRecords = [];
        foreach ($clusters as $cluster) {
            $aggregate = $aggregates->get($cluster->id);
            $clusterRecords[$cluster->id] = [
                'label' => (string) ($cluster->label ?? ''),
                'customer_count' => (int) ($aggregate->customer_count ?? 0),
                'avg_total_spent' => (float) ($aggregate->avg_total_spent ?? 0),
                'avg_orders_count' => (float) ($aggregate->avg_orders_count ?? 0),
                'avg_loyalty_points' => (float) ($aggregate->avg_loyalty_points ?? 0),
                'avg_points_spent' => (float) ($aggregate->avg_points_spent ?? 0),
            ];
        }

        $clusterRecords = $this->applyClusterLabels($clusterRecords);
        foreach ($clusters as $cluster) {
            $record = $clusterRecords[$cluster->id] ?? null;
            if (!$record) {
                continue;
            }

            $cluster->update([
                'label' => $record['label'],
                'customer_count' => (int) ($record['customer_count'] ?? 0),
                'avg_total_spent' => (float) ($record['avg_total_spent'] ?? 0),
                'avg_orders_count' => (float) ($record['avg_orders_count'] ?? 0),
                'avg_loyalty_points' => (float) ($record['avg_loyalty_points'] ?? 0),
                'avg_points_spent' => (float) ($record['avg_points_spent'] ?? 0),
            ]);
        }

        $run->update([
            'total_customers' => (int) DB::table('ai_cluster_customers')
                ->where('ai_cluster_run_id', $run->id)
                ->count(),
            'total_clusters' => (int) $clusters->count(),
        ]);
    }

    // This returns the latest completed run that has cluster records.
    private function latestCompletedRunWithClusters(): ?AiClusterRun
    {
        return AiClusterRun::query()
            ->where('status', AiRunStatus::COMPLETED->value)
            ->whereHas('clusters')
            ->orderByDesc('id')
            ->first();
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
            $rankMap[$labelKey] = $labelsByRank[min($index, $maxIndex)];
            $index++;
        }

        foreach ($clusterRecords as $labelKey => $data) {
            $clusterRecords[$labelKey]['label'] = $rankMap[$labelKey] ?? $data['label'];
        }

        return $clusterRecords;
    }

    // This normalizes AI-service projection points so row persistence can align by label index.
    private function resolveProjectionPoints(array $response, int $expectedCount): array
    {
        $points = data_get($response, 'projection.points');
        if (!is_array($points) || count($points) !== $expectedCount) {
            return [];
        }

        return array_map(function ($point): array {
            return [
                'x' => isset($point[0]) ? (float) $point[0] : null,
                'y' => isset($point[1]) ? (float) $point[1] : null,
            ];
        }, $points);
    }
}
