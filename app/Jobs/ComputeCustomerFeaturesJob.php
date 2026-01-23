<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\CustomerFeature;
use App\Models\PointsTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ComputeCustomerFeaturesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const FEATURE_KEYS = [
        'orders_count',
        'total_spent',
        'avg_order_value',
        'redeemed_coupons',
        'points_earned',
        'points_spent',
        'loyalty_points',
        'days_since_last_order',
    ];

    public function handle(): void
    {
        $lock = Cache::lock('ai_features_compute', 600);
        if (!$lock->get()) {
            return;
        }

        try {
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
                ->where('source_type', 'ORDER')
                ->groupBy('customer_id')
                ->pluck('last_order_at', 'customer_id');

            $rows = [];
            $featureBuckets = array_fill_keys(self::FEATURE_KEYS, []);

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
                $daysSinceLastOrder = $lastOrderAt ? $lastOrderAt->diffInDays(now()) : 0;

                if ($ordersCount === 0 && $totalSpent <= 0 && $pointsEarned <= 0 && $redeemedCoupons <= 0) {
                    continue;
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
                ];

                foreach (self::FEATURE_KEYS as $key) {
                    $featureBuckets[$key][] = $raw[$key];
                }

                $rows[$customer->id] = [
                    'customer' => $customer,
                    'raw' => $raw,
                    'last_order_at' => $lastOrderAt,
                    'points_pending' => $pointsPending,
                ];
            }

            $caps = [];
            foreach (self::FEATURE_KEYS as $key) {
                $caps[$key] = $this->percentile($featureBuckets[$key] ?? [], 0.99);
            }

            $now = now();
            foreach ($rows as $customerId => $payload) {
                $raw = $payload['raw'];
                $features = [];
                foreach (self::FEATURE_KEYS as $key) {
                    $value = (float) ($raw[$key] ?? 0);
                    $cap = (float) ($caps[$key] ?? $value);
                    $capped = $cap > 0 ? min($value, $cap) : $value;
                    $features[$key] = log(1 + max(0, $capped));
                }

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
                        'features' => $features,
                        'computed_at' => $now,
                    ]
                );
            }
        } finally {
            optional($lock)->release();
        }
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
