<?php

namespace App\Services;

use App\Enums\SourceType;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\CustomerFeature;
use App\Models\PointsTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AiInsightsService
{
    public function __construct(private AiClusterClient $client)
    {
    }

    public function computeCustomerFeatures(bool $persist = true): array
    {
        $featureKeys = (array) config('ai.feature_keys', []);
        $logTransforms = (array) config('ai.log_transforms', []);
        $outlierQuantile = (float) config('ai.outlier_cap_quantile', 0.99);
        $excludeZeroActivity = (bool) config('ai.exclude_zero_activity_customers', true);
        $excludeRefundOnly = (bool) config('ai.exclude_refund_only_customers', true);
        $newCustomerRecency = (int) config('ai.new_customer_recency_days', 365);

        $customers = Customer::query()->get();

        $pointsTotals = PointsTransaction::query()
            ->select('customer_id', DB::raw('SUM(CASE WHEN type = "EARN" AND points > 0 THEN points ELSE 0 END) as earned'))
            ->selectRaw('SUM(CASE WHEN type = "SPEND" THEN points ELSE 0 END) as spent')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $redeemedTotals = CustomerCoupon::query()
            ->select('customer_id', DB::raw('COUNT(*) as total'))
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $lastOrderTotals = PointsTransaction::query()
            ->select('customer_id', DB::raw('MAX(created_at) as last_order_at'))
            ->where('source_type', SourceType::ORDER->value)
            ->groupBy('customer_id')
            ->pluck('last_order_at', 'customer_id');

        $rows = [];
        $featureBuckets = array_fill_keys($featureKeys, []);
        $excluded = [];

        foreach ($customers as $customer) {
            $ordersCount = (int) ($customer->orders_count ?? 0);
            $totalSpent = (float) ($customer->total_spent ?? 0);
            $avgOrderValue = $ordersCount > 0 ? ($totalSpent / $ordersCount) : 0.0;

            $points = $pointsTotals[$customer->id] ?? null;
            $pointsEarned = (int) ($points?->earned ?? 0);
            $pointsSpent = (int) ($points?->spent ?? 0);

            $redeemedCoupons = (int) ($redeemedTotals[$customer->id] ?? 0);
            $loyaltyPoints = (int) ($customer->loyalty_points ?? 0);
            $pointsPending = (int) ($customer->points_pending ?? 0);

            $lastOrderRaw = $lastOrderTotals[$customer->id] ?? null;
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

            $raw = [
                'orders_count' => $ordersCount,
                'total_spent' => $totalSpent,
                'avg_order_value' => $avgOrderValue,
                'redeemed_coupons' => $redeemedCoupons,
                'points_earned' => $pointsEarned,
                'points_spent' => $pointsSpent,
                'loyalty_points' => $loyaltyPoints,
                'days_since_last_order' => $daysSinceLastOrder,
                'tenure_days' => $tenureDays ?? 0,
            ];

            if (!$excludedReason) {
                foreach ($featureKeys as $key) {
                    $featureBuckets[$key][] = $raw[$key] ?? 0;
                }
            } else {
                $excluded[$customer->id] = $excludedReason;
            }

            $rows[$customer->id] = [
                'customer' => $customer,
                'raw' => $raw,
                'last_order_at' => $lastOrderAt,
                'points_pending' => $pointsPending,
                'excluded_reason' => $excludedReason,
                'is_new_customer' => $isNewCustomer,
                'tenure_days' => $tenureDays,
            ];
        }

        $caps = [];
        foreach ($featureKeys as $key) {
            $caps[$key] = $this->percentile($featureBuckets[$key] ?? [], $outlierQuantile);
        }

        $now = now();
        $vectors = [];
        $customerIds = [];
        $snapshots = [];

        foreach ($rows as $customerId => $payload) {
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
                CustomerFeature::updateOrCreate(
                    ['customer_id' => $customerId],
                    [
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
                        'features' => $features,
                        'computed_at' => $now,
                        'is_new_customer' => (bool) $payload['is_new_customer'],
                        'excluded_reason' => $payload['excluded_reason'],
                        'is_excluded' => $payload['excluded_reason'] !== null,
                    ]
                );
            }

            if (!$payload['excluded_reason']) {
                $customerIds[] = $customerId;
                $vectors[] = array_values(array_map(fn ($key) => (float) ($features[$key] ?? 0), $featureKeys));
                $snapshots[$customerId] = [
                    'orders_count_snapshot' => (int) ($raw['orders_count'] ?? 0),
                    'total_spent_snapshot' => (float) ($raw['total_spent'] ?? 0),
                    'loyalty_points_snapshot' => (int) ($raw['loyalty_points'] ?? 0),
                    'points_earned_snapshot' => (int) ($raw['points_earned'] ?? 0),
                    'points_spent_snapshot' => (int) ($raw['points_spent'] ?? 0),
                    'redeemed_coupons_snapshot' => (int) ($raw['redeemed_coupons'] ?? 0),
                ];
            }
        }

        return [
            'feature_keys' => $featureKeys,
            'log_transforms' => $logTransforms,
            'outlier_caps' => $caps,
            'customer_ids' => $customerIds,
            'vectors' => $vectors,
            'snapshots' => $snapshots,
            'excluded' => $excluded,
        ];
    }

    public function train(array $vectors, array $featureKeys, array $caps, array $logTransforms): array
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

    public function predictForCustomer(int $customerId): array
    {
        $feature = CustomerFeature::query()
            ->where('customer_id', $customerId)
            ->first();

        if (!$feature) {
            throw new \RuntimeException('Customer features not found.');
        }
        if ($feature->is_excluded) {
            $reason = $feature->excluded_reason ?: 'excluded';
            throw new \RuntimeException("Customer is excluded from AI predictions ({$reason}).");
        }

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
            $vector[] = (float) ($raw[$key] ?? 0);
        }

        return $this->client->predict([
            'features' => $vector,
        ]);
    }

    private function percentile(array $values, float $percentile): float
    {
        $filtered = array_values(array_filter($values, function ($value) {
            return $value !== null;
        }));

        if (!$filtered) {
            return 0.0;
        }

        sort($filtered, SORT_NUMERIC);
        $index = (int) floor(($percentile * (count($filtered) - 1)));
        return (float) $filtered[$index];
    }
}
