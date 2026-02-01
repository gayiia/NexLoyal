<?php

// This service prepares customer features for AI clustering and calls the AI service for training and predictions.
namespace App\Services;

use App\Enums\SourceType;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\CustomerFeature;
use App\Models\PointsTransaction;
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
    public function computeCustomerFeatures(bool $persist = true): array
    {
        // These configuration values define which features exist and how they are scaled for clustering.
        $featureKeys = (array) config('ai.feature_keys', []);
        $logTransforms = (array) config('ai.log_transforms', []);
        $outlierQuantile = (float) config('ai.outlier_cap_quantile', 0.99);
        $excludeZeroActivity = (bool) config('ai.exclude_zero_activity_customers', true);
        $excludeRefundOnly = (bool) config('ai.exclude_refund_only_customers', true);
        $newCustomerRecency = (int) config('ai.new_customer_recency_days', 365);

        // This loads all customers to compute a full feature set in one pass.
        $customers = Customer::query()->get();

        // This aggregates points earned and spent per customer for feature generation.
        $pointsTotals = PointsTransaction::query()
            ->select('customer_id', DB::raw('SUM(CASE WHEN type = "EARN" AND points > 0 THEN points ELSE 0 END) as earned'))
            ->selectRaw('SUM(CASE WHEN type = "SPEND" THEN points ELSE 0 END) as spent')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        // This counts how many coupons each customer has redeemed.
        $redeemedTotals = CustomerCoupon::query()
            ->select('customer_id', DB::raw('COUNT(*) as total'))
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        // This captures the most recent order date to compute recency features.
        $lastOrderTotals = PointsTransaction::query()
            ->select('customer_id', DB::raw('MAX(created_at) as last_order_at'))
            ->where('source_type', SourceType::ORDER->value)
            ->groupBy('customer_id')
            ->pluck('last_order_at', 'customer_id');

        $rows = [];
        $featureBuckets = array_fill_keys($featureKeys, []);
        $excluded = [];

        foreach ($customers as $customer) {
            // These basic values are read from the customer record and normalized into numeric features.
            $ordersCount = (int) ($customer->orders_count ?? 0);
            $totalSpent = (float) ($customer->total_spent ?? 0);
            $avgOrderValue = $ordersCount > 0 ? ($totalSpent / $ordersCount) : 0.0;

            // This pulls in the aggregated points totals if they exist.
            $points = $pointsTotals[$customer->id] ?? null;
            $pointsEarned = (int) ($points?->earned ?? 0);
            $pointsSpent = (int) ($points?->spent ?? 0);

            // These values represent engagement through coupons and existing loyalty balance.
            $redeemedCoupons = (int) ($redeemedTotals[$customer->id] ?? 0);
            $loyaltyPoints = (int) ($customer->loyalty_points ?? 0);
            $pointsPending = (int) ($customer->points_pending ?? 0);

            // This computes recency based on the last order timestamp or a default for new customers.
            $lastOrderRaw = $lastOrderTotals[$customer->id] ?? null;
            $lastOrderAt = $lastOrderRaw ? Carbon::parse($lastOrderRaw) : null;
            $daysSinceLastOrder = $lastOrderAt ? $lastOrderAt->diffInDays(now()) : $newCustomerRecency;

            // This captures how long the customer has existed in Shopify if the data is present.
            $tenureDays = $customer->shopify_created_at
                ? Carbon::parse($customer->shopify_created_at)->diffInDays(now())
                : null;

            // These flags are used to optionally exclude customers from clustering.
            $isNewCustomer = $ordersCount === 0 && $totalSpent <= 0 && $pointsEarned <= 0 && $redeemedCoupons <= 0;
            $isRefundOnly = $ordersCount > 0 && $totalSpent <= 0 && $pointsEarned <= 0;

            // This selects the first matching exclusion reason to keep downstream logic simple.
            $excludedReason = null;
            if ($excludeZeroActivity && $isNewCustomer) {
                $excludedReason = 'no_activity';
            } elseif ($excludeRefundOnly && $isRefundOnly) {
                $excludedReason = 'refund_only';
            }

            // This raw feature map is used both for persistence and for vector generation.
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
                // This collects values per feature to compute outlier caps later.
                foreach ($featureKeys as $key) {
                    $featureBuckets[$key][] = $raw[$key] ?? 0;
                }
            } else {
                // This tracks excluded customers so UI and logging can explain missing predictions.
                $excluded[$customer->id] = $excludedReason;
            }

            // This stores per-customer data for later transformation and optional persistence.
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
            // This caps extreme values so clustering is less sensitive to outliers.
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
                // This applies per-feature capping and optional log transform to stabilize distributions.
                $value = (float) ($raw[$key] ?? 0);
                $cap = (float) ($caps[$key] ?? $value);
                $capped = $cap > 0 ? min($value, $cap) : $value;
                $features[$key] = in_array($key, $logTransforms, true)
                    ? log(1 + max(0, $capped))
                    : $capped;
            }

            if ($persist) {
                // This stores the computed features for reporting and future AI runs.
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
                // This builds the final dataset sent to the clustering service.
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

    // This sends the prepared vectors and metadata to the AI service to train clustering.
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
