<?php

// This service prepares customer features for AI clustering and calls the AI service for training and predictions.
namespace App\Services;

use App\Enums\SourceType;
use App\Models\Customer;
use App\Models\CustomerFeature;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// This class centralizes AI feature engineering for customers and delegates model requests to the AI client.
class AiInsightsService
{
    // This injects the AI client so feature preparation and model calls stay testable and consistent.
    public function __construct(private AiClusterClient $client)
    {
    }

    // This builds feature vectors from customer activity and optionally persists them for later reuse.
    public function computeCustomerFeatures(bool $persist = true, bool $buildDataset = true): array
    {
        // These configuration values define which features exist and how they are scaled for clustering.
        $featureKeys = (array) config('ai.feature_keys', []);
        $logTransforms = (array) config('ai.log_transforms', []);
        $outlierQuantile = (float) config('ai.outlier_cap_quantile', 0.99);
        $excludeZeroActivity = (bool) config('ai.exclude_zero_activity_customers', true);
        $excludeRefundOnly = (bool) config('ai.exclude_refund_only_customers', true);
        $newCustomerRecency = (int) config('ai.new_customer_recency_days', 365);
        $chunkSize = 500;

        $pointsTotals = DB::table('points_transactions')
            ->select('customer_id')
            ->selectRaw('SUM(CASE WHEN type = "EARN" AND points > 0 THEN points ELSE 0 END) as earned')
            ->selectRaw('SUM(CASE WHEN type = "SPEND" THEN points ELSE 0 END) as spent')
            ->groupBy('customer_id');

        $redeemedTotals = DB::table('customer_coupons')
            ->select('customer_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('customer_id');

        $lastOrderTotals = DB::table('points_transactions')
            ->select('customer_id')
            ->selectRaw('MAX(created_at) as last_order_at')
            ->where('source_type', SourceType::ORDER->value)
            ->groupBy('customer_id');

        $featureBuckets = array_fill_keys($featureKeys, []);
        $excluded = [];

        $this->customerFeatureBaseQuery($pointsTotals, $redeemedTotals, $lastOrderTotals)
            ->chunkById($chunkSize, function ($customers) use (
                &$featureBuckets,
                &$excluded,
                $featureKeys,
                $excludeZeroActivity,
                $excludeRefundOnly,
                $newCustomerRecency
            ): void {
                foreach ($customers as $customer) {
                    $payload = $this->buildFeaturePayload(
                        $customer,
                        $excludeZeroActivity,
                        $excludeRefundOnly,
                        $newCustomerRecency
                    );

                    if (!$payload['excluded_reason']) {
                        foreach ($featureKeys as $key) {
                            $featureBuckets[$key][] = $payload['raw'][$key] ?? 0;
                        }
                    } else {
                        $excluded[$customer->id] = $payload['excluded_reason'];
                    }
                }
            }, 'customers.id', 'id');

        $caps = [];
        foreach ($featureKeys as $key) {
            // This caps extreme values so clustering is less sensitive to outliers.
            $caps[$key] = $this->percentile($featureBuckets[$key] ?? [], $outlierQuantile);
        }

        $now = now();
        $vectors = [];
        $customerIds = [];
        $snapshots = [];

        $this->customerFeatureBaseQuery($pointsTotals, $redeemedTotals, $lastOrderTotals)
            ->chunkById($chunkSize, function ($customers) use (
                $persist,
                $buildDataset,
                $featureKeys,
                $logTransforms,
                $caps,
                $now,
                &$vectors,
                &$customerIds,
                &$snapshots,
                $excludeZeroActivity,
                $excludeRefundOnly,
                $newCustomerRecency
            ): void {
                $featureRows = [];

                foreach ($customers as $customer) {
                    $payload = $this->buildFeaturePayload(
                        $customer,
                        $excludeZeroActivity,
                        $excludeRefundOnly,
                        $newCustomerRecency
                    );

                    $raw = $payload['raw'];
                    $features = [];
                    foreach ($featureKeys as $key) {
                        $value = (float) ($raw[$key] ?? 0);
                        $cap = (float) ($caps[$key] ?? $value);
                        $capped = $cap > 0 ? min($value, $cap) : $value;
                        $features[$key] = in_array($key, $logTransforms, true)
                            ? log(1 + max(0, $capped))
                            : $capped;
                    }

                    if ($persist) {
                        $featureRows[] = [
                            'customer_id' => $customer->id,
                            'orders_count' => $raw['orders_count'],
                            'total_spent' => $raw['total_spent'],
                            'avg_order_value' => $raw['avg_order_value'],
                            'redeemed_coupons' => $raw['redeemed_coupons'],
                            'points_earned' => $raw['points_earned'],
                            'points_spent' => $raw['points_spent'],
                            'loyalty_points' => $raw['loyalty_points'],
                            'points_pending' => $payload['points_pending'],
                            'last_order_at' => $payload['last_order_at'],
                            'days_since_last_order' => $raw['days_since_last_order'],
                            'tenure_days' => $payload['tenure_days'],
                            'features' => json_encode($features),
                            'computed_at' => $now,
                            'is_new_customer' => (bool) $payload['is_new_customer'],
                            'excluded_reason' => $payload['excluded_reason'],
                            'is_excluded' => $payload['excluded_reason'] !== null,
                        ];
                    }

                    if ($buildDataset && !$payload['excluded_reason']) {
                        $customerIds[] = $customer->id;
                        $vectors[] = array_values(array_map(fn ($key) => (float) ($features[$key] ?? 0), $featureKeys));
                        $snapshots[$customer->id] = [
                            'orders_count_snapshot' => (int) ($raw['orders_count'] ?? 0),
                            'total_spent_snapshot' => (float) ($raw['total_spent'] ?? 0),
                            'loyalty_points_snapshot' => (int) ($raw['loyalty_points'] ?? 0),
                            'points_earned_snapshot' => (int) ($raw['points_earned'] ?? 0),
                            'points_spent_snapshot' => (int) ($raw['points_spent'] ?? 0),
                            'redeemed_coupons_snapshot' => (int) ($raw['redeemed_coupons'] ?? 0),
                        ];
                    }
                }

                if ($persist && $featureRows !== []) {
                    DB::table('customer_features')->upsert(
                        $featureRows,
                        ['customer_id'],
                        [
                            'orders_count',
                            'total_spent',
                            'avg_order_value',
                            'redeemed_coupons',
                            'points_earned',
                            'points_spent',
                            'loyalty_points',
                            'points_pending',
                            'last_order_at',
                            'days_since_last_order',
                            'tenure_days',
                            'features',
                            'computed_at',
                            'is_new_customer',
                            'excluded_reason',
                            'is_excluded',
                        ]
                    );
                }
            }, 'customers.id', 'id');

        return [
            'feature_keys' => $featureKeys,
            'log_transforms' => $logTransforms,
            'outlier_caps' => $caps,
            'customer_ids' => $buildDataset ? $customerIds : [],
            'vectors' => $buildDataset ? $vectors : [],
            'snapshots' => $buildDataset ? $snapshots : [],
            'excluded' => $excluded,
        ];
    }

    // This summarizes the current feature dataset so preflight checks can explain cluster readiness.
    public function getFeatureDatasetStats(): array
    {
        $minCustomers = (int) config('ai.min_customers_for_training', 20);

        $customerCount = Customer::query()->count();
        $featureCount = CustomerFeature::query()->count();
        $eligibleCount = CustomerFeature::query()->where('is_excluded', false)->count();
        $excludedCount = CustomerFeature::query()->where('is_excluded', true)->count();
        $excludedBreakdown = CustomerFeature::query()
            ->where('is_excluded', true)
            ->selectRaw('excluded_reason, COUNT(*) as total')
            ->groupBy('excluded_reason')
            ->pluck('total', 'excluded_reason')
            ->toArray();

        return [
            'customers' => $customerCount,
            'customer_features' => $featureCount,
            'eligible_customer_features' => $eligibleCount,
            'excluded_customer_features' => $excludedCount,
            'excluded_breakdown' => $excludedBreakdown,
            'min_customers_for_training' => $minCustomers,
            'is_ready_for_training' => $eligibleCount >= $minCustomers,
        ];
    }

    // This checks the AI service health and returns a normalized payload for preflight logic.
    public function getAiServiceHealth(): array
    {
        try {
            $health = $this->client->health();

            return [
                'ok' => true,
                'details' => $health,
                'message' => 'AI service is reachable.',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'details' => [],
                'message' => $exception->getMessage(),
            ];
        }
    }

    // This base query joins customer aggregates so feature computation can stream in chunks.
    private function customerFeatureBaseQuery($pointsTotals, $redeemedTotals, $lastOrderTotals)
    {
        return Customer::query()
            ->leftJoinSub($pointsTotals, 'points_totals', function ($join): void {
                $join->on('points_totals.customer_id', '=', 'customers.id');
            })
            ->leftJoinSub($redeemedTotals, 'redeemed_totals', function ($join): void {
                $join->on('redeemed_totals.customer_id', '=', 'customers.id');
            })
            ->leftJoinSub($lastOrderTotals, 'last_order_totals', function ($join): void {
                $join->on('last_order_totals.customer_id', '=', 'customers.id');
            })
            ->select([
                'customers.id',
                'customers.orders_count',
                'customers.total_spent',
                'customers.loyalty_points',
                'customers.points_pending',
                'customers.shopify_created_at',
            ])
            ->selectRaw('COALESCE(points_totals.earned, 0) as points_earned_total')
            ->selectRaw('COALESCE(points_totals.spent, 0) as points_spent_total')
            ->selectRaw('COALESCE(redeemed_totals.total, 0) as redeemed_coupons_total')
            ->selectRaw('last_order_totals.last_order_at as last_order_total_at')
            ->orderBy('customers.id');
    }

    // This normalizes a joined customer row into reusable raw-feature and exclusion data.
    private function buildFeaturePayload(
        object $customer,
        bool $excludeZeroActivity,
        bool $excludeRefundOnly,
        int $newCustomerRecency
    ): array {
        $ordersCount = (int) ($customer->orders_count ?? 0);
        $totalSpent = (float) ($customer->total_spent ?? 0);
        $avgOrderValue = $ordersCount > 0 ? ($totalSpent / $ordersCount) : 0.0;
        $pointsEarned = (int) ($customer->points_earned_total ?? 0);
        $pointsSpent = (int) ($customer->points_spent_total ?? 0);
        $redeemedCoupons = (int) ($customer->redeemed_coupons_total ?? 0);
        $loyaltyPoints = (int) ($customer->loyalty_points ?? 0);
        $pointsPending = (int) ($customer->points_pending ?? 0);

        $lastOrderRaw = $customer->last_order_total_at ?? null;
        $lastOrderAt = $lastOrderRaw ? Carbon::parse($lastOrderRaw) : null;
        $daysSinceLastOrder = $lastOrderAt ? $lastOrderAt->diffInDays(now()) : $newCustomerRecency;

        $tenureDays = $customer->shopify_created_at
            ? Carbon::parse($customer->shopify_created_at)->diffInDays(now())
            : null;

        $isNewCustomer = $ordersCount === 0 && $totalSpent <= 0 && $pointsEarned <= 0 && $redeemedCoupons <= 0;
        $isRefundOnly = $ordersCount > 0 && $totalSpent <= 0 && $pointsEarned <= 0;

        $excludedReason = null;
        if ($excludeZeroActivity && $isNewCustomer) {
            $excludedReason = 'no_activity';
        } elseif ($excludeRefundOnly && $isRefundOnly) {
            $excludedReason = 'refund_only';
        }

        return [
            'raw' => [
                'orders_count' => $ordersCount,
                'total_spent' => $totalSpent,
                'avg_order_value' => $avgOrderValue,
                'redeemed_coupons' => $redeemedCoupons,
                'points_earned' => $pointsEarned,
                'points_spent' => $pointsSpent,
                'loyalty_points' => $loyaltyPoints,
                'days_since_last_order' => $daysSinceLastOrder,
                'tenure_days' => $tenureDays ?? 0,
            ],
            'last_order_at' => $lastOrderAt,
            'points_pending' => $pointsPending,
            'excluded_reason' => $excludedReason,
            'is_new_customer' => $isNewCustomer,
            'tenure_days' => $tenureDays,
        ];
    }

    // This sends the prepared vectors and metadata to the AI service to train clustering.
    public function train(array $vectors, array $featureKeys, array|object $caps, array $logTransforms): array
    {
        return $this->client->train([
            'features' => $vectors,
            'feature_names' => $featureKeys,
            'min_k' => (int) data_get(config('ai.k_range'), 'min', 2),
            'max_k' => (int) data_get(config('ai.k_range'), 'max', 6),
            'outlier_caps' => $caps,
            'log_transforms' => $logTransforms,
            'feature_schema_version' => config('ai.feature_schema_version'),
            'algorithm_version' => config('ai.algorithm_version'),
            'code_version' => config('ai.code_version'),
        ]);
    }

    // This exports the stored feature dataset to a JSON file so training can stream from disk.
    public function exportStoredTrainingPayload(): array
    {
        $featureKeys = (array) config('ai.feature_keys', []);
        $logTransforms = (array) config('ai.log_transforms', []);
        $path = tempnam(sys_get_temp_dir(), 'ai-train-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create AI training payload file.');
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            @unlink($path);
            throw new \RuntimeException('Unable to open AI training payload file.');
        }

        $count = 0;

        try {
            fwrite($handle, '{"feature_names":');
            fwrite($handle, json_encode($featureKeys, JSON_THROW_ON_ERROR));
            fwrite($handle, ',"min_k":'.(int) data_get(config('ai.k_range'), 'min', 2));
            fwrite($handle, ',"max_k":'.(int) data_get(config('ai.k_range'), 'max', 6));
            fwrite($handle, ',"outlier_caps":{}');
            fwrite($handle, ',"log_transforms":');
            fwrite($handle, json_encode($logTransforms, JSON_THROW_ON_ERROR));
            fwrite($handle, ',"feature_schema_version":');
            fwrite($handle, json_encode(config('ai.feature_schema_version'), JSON_THROW_ON_ERROR));
            fwrite($handle, ',"algorithm_version":');
            fwrite($handle, json_encode(config('ai.algorithm_version'), JSON_THROW_ON_ERROR));
            fwrite($handle, ',"code_version":');
            fwrite($handle, json_encode(config('ai.code_version'), JSON_THROW_ON_ERROR));
            fwrite($handle, ',"features":[');

            $isFirst = true;
            DB::table('customer_features')
                ->select(['features'])
                ->where('is_excluded', false)
                ->orderBy('customer_id')
                ->chunk(500, function ($rows) use (&$count, &$isFirst, $handle, $featureKeys): void {
                    foreach ($rows as $row) {
                        $decoded = json_decode((string) ($row->features ?? '[]'), true);
                        $features = is_array($decoded) ? $decoded : [];
                        $vector = array_values(array_map(
                            fn ($key) => (float) Arr::get($features, $key, 0),
                            $featureKeys
                        ));

                        if (!$isFirst) {
                            fwrite($handle, ',');
                        }
                        fwrite($handle, json_encode($vector, JSON_THROW_ON_ERROR));
                        $isFirst = false;
                        $count++;
                    }
                });

            fwrite($handle, ']}');
        } catch (\Throwable $exception) {
            fclose($handle);
            @unlink($path);
            throw $exception;
        }

        fclose($handle);

        return [
            'path' => $path,
            'count' => $count,
            'feature_keys' => $featureKeys,
            'log_transforms' => $logTransforms,
        ];
    }

    // This returns the number of stored, eligible features available for clustering.
    public function getStoredTrainingCount(): int
    {
        return (int) DB::table('customer_features')
            ->where('is_excluded', false)
            ->count();
    }

    // This trains clustering from a JSON payload file to keep memory usage bounded in Laravel.
    public function trainFromJsonFile(string $path): array
    {
        return $this->client->trainFromJsonFile($path);
    }

    // This streams the stored feature rows in training order so callers can align them with returned labels.
    public function chunkStoredTrainingRows(int $size, callable $callback): void
    {
        DB::table('customer_features')
            ->select([
                'customer_id',
                'orders_count',
                'total_spent',
                'loyalty_points',
                'points_earned',
                'points_spent',
                'redeemed_coupons',
            ])
            ->where('is_excluded', false)
            ->orderBy('customer_id')
            ->chunk($size, $callback);
    }

    // This rebuilds the clustering dataset from stored feature rows instead of recomputing raw aggregates.
    public function getTrainingDatasetFromStoredFeatures(): array
    {
        $featureKeys = (array) config('ai.feature_keys', []);
        $logTransforms = (array) config('ai.log_transforms', []);

        $customerIds = [];
        $vectors = [];
        $snapshots = [];

        DB::table('customer_features')
            ->select([
                'customer_id',
                'orders_count',
                'total_spent',
                'loyalty_points',
                'points_earned',
                'points_spent',
                'redeemed_coupons',
                'features',
            ])
            ->where('is_excluded', false)
            ->orderBy('customer_id')
            ->chunk(500, function ($rows) use (&$customerIds, &$vectors, &$snapshots, $featureKeys): void {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) ($row->features ?? '[]'), true);
                    $features = is_array($decoded) ? $decoded : [];

                    $customerIds[] = (int) $row->customer_id;
                    $vectors[] = array_values(array_map(
                        fn ($key) => (float) ($features[$key] ?? 0),
                        $featureKeys
                    ));
                    $snapshots[] = [
                        'customer_id' => (int) $row->customer_id,
                        'orders_count_snapshot' => (int) ($row->orders_count ?? 0),
                        'total_spent_snapshot' => (float) ($row->total_spent ?? 0),
                        'loyalty_points_snapshot' => (int) ($row->loyalty_points ?? 0),
                        'points_earned_snapshot' => (int) ($row->points_earned ?? 0),
                        'points_spent_snapshot' => (int) ($row->points_spent ?? 0),
                        'redeemed_coupons_snapshot' => (int) ($row->redeemed_coupons ?? 0),
                    ];
                }
            });

        return [
            'feature_keys' => $featureKeys,
            'log_transforms' => $logTransforms,
            'outlier_caps' => new \stdClass(),
            'customer_ids' => $customerIds,
            'vectors' => $vectors,
            'snapshots' => $snapshots,
            'excluded' => [],
        ];
    }

    // This predicts a cluster for a single customer using their stored feature vector.
    public function predictForCustomer(int $customerId): array
    {
        // This loads the stored feature record and fails fast if it is missing.
        $feature = CustomerFeature::query()
            ->where('customer_id', $customerId)
            ->first();

        if (!$feature) {
            throw new \RuntimeException('Customer features not found.');
        }
        if ($feature->is_excluded) {
            // This preserves the exclusion reason so callers can show meaningful messages.
            $reason = $feature->excluded_reason ?: 'excluded';
            throw new \RuntimeException("Customer is excluded from AI predictions ({$reason}).");
        }

        // This reconstructs the raw feature values from the stored record.
        $featureKeys = (array) config('ai.feature_keys', []);
        $raw = [
            'orders_count' => (int) $feature->orders_count,
            'total_spent' => (float) $feature->total_spent,
            'avg_order_value' => (float) $feature->avg_order_value,
            'redeemed_coupons' => (int) $feature->redeemed_coupons,
            'points_earned' => (int) $feature->points_earned,
            'points_spent' => (int) $feature->points_spent,
            'loyalty_points' => (int) $feature->loyalty_points,
            'days_since_last_order' => (int) ($feature->days_since_last_order ?? 0),
            'tenure_days' => (int) ($feature->tenure_days ?? 0),
        ];

        $vector = [];
        foreach ($featureKeys as $key) {
            // This preserves feature ordering expected by the AI service.
            $vector[] = (float) ($raw[$key] ?? 0);
        }

        // This calls the AI service for a single-customer prediction.
        return $this->client->predict([
            'features' => $vector,
        ]);
    }

    // This computes a simple percentile to cap outliers without heavy statistics dependencies.
    private function percentile(array $values, float $percentile): float
    {
        // This filters out nulls because missing values are not meaningful for percentile math.
        $filtered = array_values(array_filter($values, function ($value) {
            return $value !== null;
        }));

        if (!$filtered) {
            return 0.0;
        }

        // This uses a nearest-rank approach with zero-based indexing.
        sort($filtered, SORT_NUMERIC);
        $index = (int) floor(($percentile * (count($filtered) - 1)));
        return (float) $filtered[$index];
    }
}
