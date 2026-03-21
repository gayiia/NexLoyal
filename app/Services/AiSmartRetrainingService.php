<?php

// This service decides whether the AI model should be retrained based on points activity deltas.
namespace App\Services;

use App\Models\AiClusterRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// This class encapsulates smart retraining checks so job logic remains focused on orchestration.
class AiSmartRetrainingService
{
    public function __construct(private AiClusterClient $client)
    {
    }

    // This evaluates whether retraining should run and returns a reasoned decision payload.
    public function evaluate(): array
    {
        $snapshot = $this->currentPointTransactionSnapshot();
        $intervalDays = max(1, (int) config('ai.retrain_interval_days', 7));
        $fixedK = config('ai.fixed_k');
        $fixedK = is_numeric($fixedK) ? (int) $fixedK : null;
        $minK = (int) data_get(config('ai.k_range'), 'min', 2);
        $maxK = (int) data_get(config('ai.k_range'), 'max', 6);
        $featureKeys = array_values((array) config('ai.feature_keys', []));
        $featureSchemaVersion = (int) config('ai.feature_schema_version', 1);
        $featurePayloadMode = 'preprocessed_vectors';

        if (!(bool) config('ai.smart_retraining_enabled', true)) {
            return [
                'should_retrain' => true,
                'reason' => 'smart_retraining_disabled',
                'message' => 'Smart retraining is disabled by configuration.',
                'snapshot' => $snapshot,
                'details' => [],
            ];
        }

        $model = $this->readModelMetadata();
        $metadata = $model['metadata'];
        $selectedK = is_numeric($model['selected_k'] ?? null) ? (int) $model['selected_k'] : null;
        if (!$model['ok'] || !is_array($metadata)) {
            return [
                'should_retrain' => true,
                'reason' => 'missing_or_invalid_model_metadata',
                'message' => 'Model metadata is missing or invalid. Forcing retraining.',
                'snapshot' => $snapshot,
                'details' => [
                    'error' => $model['error'],
                ],
            ];
        }

        $latestCompletedRun = $this->latestCompletedRunWithClusters();
        if ($latestCompletedRun) {
            $runFixedK = $this->toNullableInt(data_get($latestCompletedRun->params, 'fixed_k'));
            $runMinK = (int) data_get($latestCompletedRun->params, 'min_k', $minK);
            $runMaxK = (int) data_get($latestCompletedRun->params, 'max_k', $maxK);
            $runFeatureKeys = array_values((array) data_get($latestCompletedRun->params, 'feature_keys', []));
            $runFeatureSchemaVersion = (int) data_get($latestCompletedRun->params, 'feature_schema_version', 0);
            $runFeaturePayloadMode = (string) data_get($latestCompletedRun->params, 'feature_payload_mode', '');

            if (
                $runFixedK !== $fixedK
                || ($fixedK === null && ($runMinK !== $minK || $runMaxK !== $maxK))
            ) {
                $currentStrategy = $fixedK !== null ? "fixed K={$fixedK}" : "K-search {$minK}-{$maxK}";
                $previousStrategy = $runFixedK !== null ? "fixed K={$runFixedK}" : "K-search {$runMinK}-{$runMaxK}";

                return [
                    'should_retrain' => true,
                    'reason' => 'cluster_configuration_changed',
                    'message' => "Clustering configuration changed from {$previousStrategy} to {$currentStrategy}. Forcing retraining.",
                    'snapshot' => $snapshot,
                    'details' => [
                        'latest_run_id' => $latestCompletedRun->id,
                        'previous_fixed_k' => $runFixedK,
                        'previous_min_k' => $runMinK,
                        'previous_max_k' => $runMaxK,
                        'current_fixed_k' => $fixedK,
                        'current_min_k' => $minK,
                        'current_max_k' => $maxK,
                    ],
                    'model_metadata' => $metadata,
                ];
            }

            if ($runFeaturePayloadMode !== $featurePayloadMode) {
                return [
                    'should_retrain' => true,
                    'reason' => 'feature_payload_mode_changed',
                    'message' => 'Feature preprocessing payload mode changed. Forcing retraining.',
                    'snapshot' => $snapshot,
                    'details' => [
                        'latest_run_id' => $latestCompletedRun->id,
                        'previous_feature_payload_mode' => $runFeaturePayloadMode ?: null,
                        'current_feature_payload_mode' => $featurePayloadMode,
                    ],
                    'model_metadata' => $metadata,
                ];
            }

            if ($runFeatureSchemaVersion !== $featureSchemaVersion || $runFeatureKeys !== $featureKeys) {
                return [
                    'should_retrain' => true,
                    'reason' => 'feature_schema_changed',
                    'message' => 'Clustering feature schema changed. Forcing retraining.',
                    'snapshot' => $snapshot,
                    'details' => [
                        'latest_run_id' => $latestCompletedRun->id,
                        'previous_feature_schema_version' => $runFeatureSchemaVersion,
                        'current_feature_schema_version' => $featureSchemaVersion,
                        'previous_feature_keys' => $runFeatureKeys,
                        'current_feature_keys' => $featureKeys,
                    ],
                    'model_metadata' => $metadata,
                ];
            }
        }

        if ($fixedK !== null && $selectedK === null) {
            return [
                'should_retrain' => true,
                'reason' => 'missing_selected_k_for_fixed_k',
                'message' => 'Fixed K is configured, but the saved model is missing selected_k. Forcing retraining.',
                'snapshot' => $snapshot,
                'details' => [
                    'fixed_k' => $fixedK,
                    'selected_k' => $selectedK,
                ],
                'model_metadata' => $metadata,
            ];
        }

        if ($fixedK !== null && $selectedK !== $fixedK) {
            return [
                'should_retrain' => true,
                'reason' => 'fixed_k_mismatch',
                'message' => "Fixed K is {$fixedK}, but saved model was trained with K={$selectedK}. Forcing retraining.",
                'snapshot' => $snapshot,
                'details' => [
                    'fixed_k' => $fixedK,
                    'selected_k' => $selectedK,
                ],
                'model_metadata' => $metadata,
            ];
        }

        $lastTrainedAt = $this->parseDate(
            data_get($metadata, 'last_trained_at')
            ?? data_get($metadata, 'trained_at')
        );
        if (!$lastTrainedAt) {
            return [
                'should_retrain' => true,
                'reason' => 'missing_last_trained_at',
                'message' => 'Model metadata is missing last_trained_at. Forcing retraining.',
                'snapshot' => $snapshot,
                'details' => [
                    'model_metadata' => $metadata,
                ],
            ];
        }

        $staleThreshold = now()->subDays($intervalDays);
        if ($lastTrainedAt->lt($staleThreshold)) {
            return [
                'should_retrain' => true,
                'reason' => 'retrain_interval_exceeded',
                'message' => "Model is older than {$intervalDays} day(s). Retraining triggered by time fallback.",
                'snapshot' => $snapshot,
                'details' => [
                    'last_trained_at' => $lastTrainedAt->toIso8601String(),
                    'interval_days' => $intervalDays,
                ],
                'model_metadata' => $metadata,
            ];
        }

        $lastSeenTransactionId = $this->toPositiveInt(data_get($metadata, 'last_seen_points_transaction_id'));
        $lastSeenTransactionAt = $this->parseDate(data_get($metadata, 'last_seen_points_transaction_at'));

        // Missing watermarks are treated as unsafe metadata and force retraining.
        if ($lastSeenTransactionId === null && !$lastSeenTransactionAt) {
            return [
                'should_retrain' => true,
                'reason' => 'missing_transaction_watermark',
                'message' => 'Model metadata has no points transaction watermark. Forcing retraining.',
                'snapshot' => $snapshot,
                'details' => [
                    'model_metadata' => $metadata,
                ],
                'model_metadata' => $metadata,
            ];
        }

        $changeSet = $this->countMeaningfulPointActivityChanges(
            $lastTrainedAt,
            $lastSeenTransactionId,
            $lastSeenTransactionAt
        );

        $hasRelevantActivity = ($changeSet['new_customers_with_points'] > 0)
            || ($changeSet['existing_customers_with_new_points'] > 0);

        if ($hasRelevantActivity) {
            return [
                'should_retrain' => true,
                'reason' => 'new_relevant_points_activity',
                'message' => 'New points transactions were detected for active customers. Retraining required.',
                'snapshot' => $snapshot,
                'details' => $changeSet,
                'model_metadata' => $metadata,
            ];
        }

        return [
            'should_retrain' => false,
            'reason' => 'no_new_relevant_points_activity',
            'message' => 'No new relevant points transactions since the last training. Reusing saved model.',
            'snapshot' => $snapshot,
            'details' => array_merge($changeSet, [
                'fixed_k' => $fixedK,
                'selected_k' => $selectedK,
            ]),
            'model_metadata' => $metadata,
        ];
    }

    // This returns current points transaction watermarks to persist with successful training metadata.
    public function currentPointTransactionSnapshot(): array
    {
        return [
            'last_seen_points_transaction_id' => DB::table('points_transactions')->max('id'),
            'last_seen_points_transaction_at' => DB::table('points_transactions')->max('created_at'),
            'transaction_count_at_training' => (int) DB::table('points_transactions')->count(),
            'customer_count_at_training' => (int) DB::table('customers')->count(),
        ];
    }

    // This loads model metadata from the AI service in a fail-safe structure.
    private function readModelMetadata(): array
    {
        try {
            $payload = $this->client->modelMetadata();
            return [
                'ok' => is_array($payload),
                'metadata' => is_array($payload) ? ($payload['model_metadata'] ?? null) : null,
                'selected_k' => is_array($payload) ? ($payload['selected_k'] ?? null) : null,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'metadata' => null,
                'selected_k' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    // This counts meaningful changes: only EARN/SPEND points transactions since the last model watermark.
    private function countMeaningfulPointActivityChanges(
        Carbon $lastTrainedAt,
        ?int $lastSeenTransactionId,
        ?Carbon $lastSeenTransactionAt
    ): array {
        $relevantTypes = ['EARN', 'SPEND'];

        $baseTransactions = DB::table('points_transactions as pt')
            ->whereIn('pt.type', $relevantTypes);
        $this->applyTransactionWatermark($baseTransactions, $lastSeenTransactionId, $lastSeenTransactionAt);

        $baseCustomers = DB::table('points_transactions as pt')
            ->join('customers as c', 'c.id', '=', 'pt.customer_id')
            ->whereNotNull('pt.customer_id')
            ->whereIn('pt.type', $relevantTypes);
        $this->applyTransactionWatermark($baseCustomers, $lastSeenTransactionId, $lastSeenTransactionAt);

        $newCustomers = (clone $baseCustomers)
            ->where('c.created_at', '>', $lastTrainedAt)
            ->distinct()
            ->count('c.id');

        $existingCustomers = (clone $baseCustomers)
            ->where(function ($query) use ($lastTrainedAt): void {
                $query->whereNull('c.created_at')
                    ->orWhere('c.created_at', '<=', $lastTrainedAt);
            })
            ->distinct()
            ->count('c.id');

        return [
            'new_points_transactions' => (int) (clone $baseTransactions)->count(),
            'new_customers_with_points' => (int) $newCustomers,
            'existing_customers_with_new_points' => (int) $existingCustomers,
            'watermark_transaction_id' => $lastSeenTransactionId,
            'watermark_transaction_at' => $lastSeenTransactionAt?->toIso8601String(),
            'last_trained_at' => $lastTrainedAt->toIso8601String(),
        ];
    }

    // This applies the transaction watermark predicate to keep the delta query consistent.
    private function applyTransactionWatermark($query, ?int $lastSeenTransactionId, ?Carbon $lastSeenTransactionAt): void
    {
        if ($lastSeenTransactionId !== null) {
            $query->where('pt.id', '>', $lastSeenTransactionId);
            return;
        }

        if ($lastSeenTransactionAt) {
            $query->where('pt.created_at', '>', $lastSeenTransactionAt);
        }
    }

    // This parses an arbitrary date string into Carbon while handling invalid inputs.
    private function parseDate(mixed $value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    // This safely converts metadata scalar values to positive integers.
    private function toPositiveInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;
        return $normalized > 0 ? $normalized : null;
    }

    // This safely converts scalar values to nullable ints so config comparisons stay strict.
    private function toNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    // This uses the latest completed run with persisted clusters as the baseline clustering configuration.
    private function latestCompletedRunWithClusters(): ?AiClusterRun
    {
        return AiClusterRun::query()
            ->where('status', 'completed')
            ->whereHas('clusters')
            ->orderByDesc('id')
            ->first();
    }
}
