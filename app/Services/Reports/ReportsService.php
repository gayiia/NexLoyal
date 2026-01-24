<?php

namespace App\Services\Reports;

use App\Enums\AiRunStatus;
use App\Enums\PointsTransactionType;
use App\Enums\SourceType;
use App\Models\AiCluster;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\CustomerFeature;
use App\Models\PointsTransaction;
use App\Models\Tier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    public const REPORTS = [
        'loyalty_participation_rate' => 'Loyalty participation rate',
        'loyalty_driven_revenue' => 'Loyalty driven revenue',
        'aov_loyalty_vs_non' => 'AOV: loyalty vs non-loyalty',
        'repeat_purchase_rate_loyalty_vs_non' => 'Repeat purchase rate (loyalty vs non)',
        'customer_retention_rate' => 'Customer retention rate',
        'points_issued_redeemed_expired' => 'Points issued vs redeemed vs expired',
        'points_liability_outstanding' => 'Points liability outstanding',
        'redemption_rate' => 'Redemption rate',
        'reward_effectiveness' => 'Reward effectiveness',
        'time_to_redeem_points_velocity' => 'Time to redeem (points velocity)',
        'customers_per_tier' => 'Customers per tier',
        'revenue_per_tier' => 'Revenue per tier',
        'tier_upgrade_frequency' => 'Tier upgrade frequency',
        'gamification_engagement_report' => 'Gamification engagement report',
        'customer_segmentation_cluster_overview' => 'Customer segmentation: cluster overview',
        'cluster_revenue_comparison' => 'Cluster revenue comparison',
        'cluster_quality_metrics_silhouette' => 'Cluster quality metrics (silhouette)',
        'high_risk_churn_customers' => 'High risk churn customers',
        'cohort_retention_analysis' => 'Cohort retention analysis',
        'loyalty_growth_over_time' => 'Loyalty growth over time',
    ];

    public function availableReports(): array
    {
        return self::REPORTS;
    }

    public function generate(string $reportKey, array $filters): array
    {
        $filters['_report_key'] = $reportKey;
        return match ($reportKey) {
            'loyalty_participation_rate' => $this->loyaltyParticipationRate($filters),
            'loyalty_driven_revenue' => $this->loyaltyDrivenRevenue($filters),
            'aov_loyalty_vs_non' => $this->aovLoyaltyVsNon($filters),
            'repeat_purchase_rate_loyalty_vs_non' => $this->repeatPurchaseRateLoyaltyVsNon($filters),
            'customer_retention_rate' => $this->customerRetentionRate($filters),
            'points_issued_redeemed_expired' => $this->pointsIssuedRedeemedExpired($filters),
            'points_liability_outstanding' => $this->pointsLiabilityOutstanding($filters),
            'redemption_rate' => $this->redemptionRate($filters),
            'reward_effectiveness' => $this->rewardEffectiveness($filters),
            'time_to_redeem_points_velocity' => $this->timeToRedeemPointsVelocity($filters),
            'customers_per_tier' => $this->customersPerTier($filters),
            'revenue_per_tier' => $this->revenuePerTier($filters),
            'tier_upgrade_frequency' => $this->tierUpgradeFrequency($filters),
            'gamification_engagement_report' => $this->gamificationEngagementReport($filters),
            'customer_segmentation_cluster_overview' => $this->customerSegmentationClusterOverview($filters),
            'cluster_revenue_comparison' => $this->clusterRevenueComparison($filters),
            'cluster_quality_metrics_silhouette' => $this->clusterQualityMetricsSilhouette($filters),
            'high_risk_churn_customers' => $this->highRiskChurnCustomers($filters),
            'cohort_retention_analysis' => $this->cohortRetentionAnalysis($filters),
            'loyalty_growth_over_time' => $this->loyaltyGrowthOverTime($filters),
            default => $this->emptyReport('Unknown report', $filters),
        };
    }

    private function loyaltyParticipationRate(array $filters): array
    {
        $base = $this->baseCustomerQuery($filters, false);
        $total = (int) $base->count();
        $loyalty = (int) $this->applyLoyaltyCondition(clone $base, true)->count();
        $nonLoyalty = max($total - $loyalty, 0);
        $rate = $total > 0 ? round(($loyalty / $total) * 100, 1) : 0.0;

        return $this->payload(
            'Loyalty participation rate',
            [
                ['label' => 'Total customers', 'value' => $total],
                ['label' => 'Loyalty members', 'value' => $loyalty],
                ['label' => 'Participation rate', 'value' => $rate . '%'],
            ],
            [
                'columns' => ['Segment', 'Customers', 'Share'],
                'rows' => [
                    ['Loyalty members', $loyalty, $total > 0 ? round(($loyalty / $total) * 100, 1) . '%' : '0%'],
                    ['Non-loyalty', $nonLoyalty, $total > 0 ? round(($nonLoyalty / $total) * 100, 1) . '%' : '0%'],
                ],
            ],
            [],
            $filters
        );
    }

    private function loyaltyDrivenRevenue(array $filters): array
    {
        // Revenue uses customer total_spent as a proxy when orders are not stored separately.
        $base = $this->baseCustomerQuery($filters, false);
        $totalRevenue = (float) $base->sum('total_spent');
        $loyaltyRevenue = (float) $this->applyLoyaltyCondition(clone $base, true)->sum('total_spent');
        $nonRevenue = max($totalRevenue - $loyaltyRevenue, 0);
        $share = $totalRevenue > 0 ? round(($loyaltyRevenue / $totalRevenue) * 100, 1) : 0.0;

        return $this->payload(
            'Loyalty driven revenue',
            [
                ['label' => 'Total revenue', 'value' => round($totalRevenue, 2)],
                ['label' => 'Loyalty revenue', 'value' => round($loyaltyRevenue, 2)],
                ['label' => 'Loyalty share', 'value' => $share . '%'],
            ],
            [
                'columns' => ['Segment', 'Revenue', 'Share'],
                'rows' => [
                    ['Loyalty members', round($loyaltyRevenue, 2), $share . '%'],
                    ['Non-loyalty', round($nonRevenue, 2), $totalRevenue > 0 ? round(($nonRevenue / $totalRevenue) * 100, 1) . '%' : '0%'],
                ],
            ],
            [],
            $filters
        );
    }

    private function aovLoyaltyVsNon(array $filters): array
    {
        $base = $this->baseCustomerQuery($filters, false);

        $loyaltyBase = $this->applyLoyaltyCondition(clone $base, true);
        $nonBase = $this->applyLoyaltyCondition(clone $base, false);

        $loyaltyRevenue = (float) $loyaltyBase->sum('total_spent');
        $loyaltyOrders = (int) $loyaltyBase->sum('orders_count');
        $nonRevenue = (float) $nonBase->sum('total_spent');
        $nonOrders = (int) $nonBase->sum('orders_count');

        $loyaltyAov = $loyaltyOrders > 0 ? round($loyaltyRevenue / $loyaltyOrders, 2) : 0.0;
        $nonAov = $nonOrders > 0 ? round($nonRevenue / $nonOrders, 2) : 0.0;

        return $this->payload(
            'AOV: loyalty vs non-loyalty',
            [
                ['label' => 'Loyalty AOV', 'value' => $loyaltyAov],
                ['label' => 'Non-loyalty AOV', 'value' => $nonAov],
            ],
            [
                'columns' => ['Segment', 'Revenue', 'Orders', 'AOV'],
                'rows' => [
                    ['Loyalty members', round($loyaltyRevenue, 2), $loyaltyOrders, $loyaltyAov],
                    ['Non-loyalty', round($nonRevenue, 2), $nonOrders, $nonAov],
                ],
            ],
            [],
            $filters
        );
    }

    private function repeatPurchaseRateLoyaltyVsNon(array $filters): array
    {
        $base = $this->baseCustomerQuery($filters, false);

        $loyalty = $this->applyLoyaltyCondition(clone $base, true);
        $non = $this->applyLoyaltyCondition(clone $base, false);

        $loyaltyTotal = (int) $loyalty->count();
        $loyaltyRepeat = (int) (clone $loyalty)->where('orders_count', '>', 1)->count();
        $nonTotal = (int) $non->count();
        $nonRepeat = (int) (clone $non)->where('orders_count', '>', 1)->count();

        $loyaltyRate = $loyaltyTotal > 0 ? round(($loyaltyRepeat / $loyaltyTotal) * 100, 1) : 0.0;
        $nonRate = $nonTotal > 0 ? round(($nonRepeat / $nonTotal) * 100, 1) : 0.0;

        return $this->payload(
            'Repeat purchase rate (loyalty vs non)',
            [
                ['label' => 'Loyalty repeat rate', 'value' => $loyaltyRate . '%'],
                ['label' => 'Non-loyalty repeat rate', 'value' => $nonRate . '%'],
            ],
            [
                'columns' => ['Segment', 'Customers', 'Repeat customers', 'Repeat rate'],
                'rows' => [
                    ['Loyalty members', $loyaltyTotal, $loyaltyRepeat, $loyaltyRate . '%'],
                    ['Non-loyalty', $nonTotal, $nonRepeat, $nonRate . '%'],
                ],
            ],
            [],
            $filters
        );
    }

    private function customerRetentionRate(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $periodDays = $start->diffInDays($end) + 1;
        $previousStart = $start->copy()->subDays($periodDays);
        $previousEnd = $start->copy()->subSecond();

        $customerIds = $this->baseCustomerQuery($filters)->select('id');

        // Using points transactions as a proxy for activity when order tables are unavailable.
        $previous = PointsTransaction::query()
            ->select('customer_id')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->whereIn('customer_id', $customerIds)
            ->distinct()
            ->pluck('customer_id');

        $current = PointsTransaction::query()
            ->select('customer_id')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('customer_id', $customerIds)
            ->distinct()
            ->pluck('customer_id');

        $retained = $previous->intersect($current)->count();
        $previousCount = $previous->count();
        $rate = $previousCount > 0 ? round(($retained / $previousCount) * 100, 1) : 0.0;

        return $this->payload(
            'Customer retention rate',
            [
                ['label' => 'Previous period active', 'value' => $previousCount],
                ['label' => 'Retained customers', 'value' => $retained],
                ['label' => 'Retention rate', 'value' => $rate . '%'],
            ],
            [
                'columns' => ['Metric', 'Value'],
                'rows' => [
                    ['Previous period', $previousCount],
                    ['Current period', $current->count()],
                    ['Retained', $retained],
                ],
            ],
            [],
            $filters
        );
    }

    private function pointsIssuedRedeemedExpired(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $customerIds = $this->baseCustomerQuery($filters)->select('id');

        $issued = (int) PointsTransaction::query()
            ->where('type', PointsTransactionType::EARN->value)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('customer_id', $customerIds)
            ->sum('points');

        $redeemed = (int) PointsTransaction::query()
            ->where('type', PointsTransactionType::SPEND->value)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('customer_id', $customerIds)
            ->sum('points');

        // No explicit expire event exists in the current schema.
        $expired = 0;

        return $this->payload(
            'Points issued vs redeemed vs expired',
            [
                ['label' => 'Issued points', 'value' => $issued],
                ['label' => 'Redeemed points', 'value' => $redeemed],
                ['label' => 'Expired points', 'value' => $expired],
            ],
            [
                'columns' => ['Type', 'Points'],
                'rows' => [
                    ['Issued', $issued],
                    ['Redeemed', $redeemed],
                    ['Expired', $expired],
                ],
            ],
            [],
            $filters
        );
    }

    private function pointsLiabilityOutstanding(array $filters): array
    {
        $customerIds = $this->baseCustomerQuery($filters)->select('id');

        $issued = (int) PointsTransaction::query()
            ->where('type', PointsTransactionType::EARN->value)
            ->whereIn('customer_id', $customerIds)
            ->sum('points');

        $redeemed = (int) PointsTransaction::query()
            ->where('type', PointsTransactionType::SPEND->value)
            ->whereIn('customer_id', $customerIds)
            ->sum('points');

        $expired = 0;
        $liability = $issued - $redeemed - $expired;

        return $this->payload(
            'Points liability outstanding',
            [
                ['label' => 'Issued points', 'value' => $issued],
                ['label' => 'Redeemed points', 'value' => $redeemed],
                ['label' => 'Outstanding liability', 'value' => $liability],
            ],
            [
                'columns' => ['Metric', 'Points'],
                'rows' => [
                    ['Issued', $issued],
                    ['Redeemed', $redeemed],
                    ['Expired (not tracked)', $expired],
                    ['Outstanding', $liability],
                ],
            ],
            [],
            $filters
        );
    }

    private function redemptionRate(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $customerIds = $this->baseCustomerQuery($filters)->select('id');

        $issued = (int) PointsTransaction::query()
            ->where('type', PointsTransactionType::EARN->value)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('customer_id', $customerIds)
            ->sum('points');

        $redeemed = (int) PointsTransaction::query()
            ->where('type', PointsTransactionType::SPEND->value)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('customer_id', $customerIds)
            ->sum('points');

        $rate = $issued > 0 ? round(($redeemed / $issued) * 100, 1) : 0.0;

        return $this->payload(
            'Redemption rate',
            [
                ['label' => 'Issued points', 'value' => $issued],
                ['label' => 'Redeemed points', 'value' => $redeemed],
                ['label' => 'Redemption rate', 'value' => $rate . '%'],
            ],
            [
                'columns' => ['Metric', 'Value'],
                'rows' => [
                    ['Issued points', $issued],
                    ['Redeemed points', $redeemed],
                    ['Rate', $rate . '%'],
                ],
            ],
            [],
            $filters
        );
    }

    private function rewardEffectiveness(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $customerIds = $this->baseCustomerQuery($filters)->select('id');
        $limit = (int) ($filters['top_n'] ?? 10);
        $limit = $limit > 0 ? $limit : 10;

        $rows = CustomerCoupon::query()
            ->select('coupons.title', DB::raw('COUNT(*) as total_redemptions'), DB::raw('SUM(customer_coupons.points_spent) as points_spent'))
            ->join('coupons', 'coupons.id', '=', 'customer_coupons.coupon_id')
            ->whereNotNull('customer_coupons.redeemed_at')
            ->whereBetween('customer_coupons.redeemed_at', [$start, $end])
            ->whereIn('customer_coupons.customer_id', $customerIds)
            ->groupBy('coupons.title')
            ->orderByDesc('total_redemptions')
            ->limit($limit)
            ->get();

        $totalRedemptions = (int) $rows->sum('total_redemptions');
        $topReward = $rows->first()?->title;

        return $this->payload(
            'Reward effectiveness',
            [
                ['label' => 'Total redemptions', 'value' => $totalRedemptions],
                ['label' => 'Top reward', 'value' => $topReward ?? 'N/A'],
            ],
            [
                'columns' => ['Reward', 'Redemptions', 'Points spent'],
                'rows' => $rows->map(function ($row) {
                    return [
                        $row->title,
                        (int) $row->total_redemptions,
                        (int) $row->points_spent,
                    ];
                })->toArray(),
            ],
            [],
            $filters
        );
    }

    private function timeToRedeemPointsVelocity(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $customerIds = $this->baseCustomerQuery($filters)->select('id');
        $limit = (int) ($filters['top_n'] ?? 10);
        $limit = $limit > 0 ? $limit : 10;

        $earnSub = PointsTransaction::query()
            ->select('customer_id', DB::raw('MIN(created_at) as first_earn_at'))
            ->where('type', PointsTransactionType::EARN->value)
            ->groupBy('customer_id');

        $spendSub = PointsTransaction::query()
            ->select('customer_id', DB::raw('MIN(created_at) as first_spend_at'))
            ->where('type', PointsTransactionType::SPEND->value)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('customer_id');

        $rows = DB::table('customers')
            ->joinSub($earnSub, 'earn', 'earn.customer_id', '=', 'customers.id')
            ->joinSub($spendSub, 'spend', 'spend.customer_id', '=', 'customers.id')
            ->select('customers.id', 'earn.first_earn_at', 'spend.first_spend_at')
            ->whereIn('customers.id', $customerIds)
            ->get();

        $data = $rows->map(function ($row) {
            $firstEarn = Carbon::parse($row->first_earn_at);
            $firstSpend = Carbon::parse($row->first_spend_at);
            return [
                'customer_id' => $row->id,
                'first_earn_at' => $firstEarn,
                'first_spend_at' => $firstSpend,
                'days_to_redeem' => $firstEarn->diffInDays($firstSpend),
            ];
        })->sortByDesc('days_to_redeem');

        $avg = $data->isNotEmpty() ? round($data->avg('days_to_redeem'), 1) : 0.0;

        return $this->payload(
            'Time to redeem (points velocity)',
            [
                ['label' => 'Avg days to redeem', 'value' => $avg],
                ['label' => 'Customers analyzed', 'value' => $data->count()],
            ],
            [
                'columns' => ['Customer ID', 'First earn', 'First redeem', 'Days to redeem'],
                'rows' => $data->take($limit)->map(function ($row) {
                    return [
                        $row['customer_id'],
                        $row['first_earn_at']->toDateString(),
                        $row['first_spend_at']->toDateString(),
                        $row['days_to_redeem'],
                    ];
                })->values()->toArray(),
            ],
            [],
            $filters
        );
    }

    private function customersPerTier(array $filters): array
    {
        $base = $this->baseCustomerQuery($filters);
        $tiers = Tier::query()->orderBy('min_points')->get();

        $counts = $base->select('tier_id', DB::raw('COUNT(*) as total'))
            ->groupBy('tier_id')
            ->pluck('total', 'tier_id');

        $rows = [];
        foreach ($tiers as $tier) {
            $rows[] = [$tier->title, (int) ($counts[$tier->id] ?? 0)];
        }
        $rows[] = ['No tier', (int) ($counts[null] ?? 0)];

        $total = array_sum(array_map(fn ($row) => $row[1], $rows));

        return $this->payload(
            'Customers per tier',
            [
                ['label' => 'Total customers', 'value' => $total],
            ],
            [
                'columns' => ['Tier', 'Customers'],
                'rows' => $rows,
            ],
            [],
            $filters
        );
    }

    private function revenuePerTier(array $filters): array
    {
        // Revenue uses customer total_spent as a proxy when orders are not stored separately.
        $base = $this->baseCustomerQuery($filters);
        $tiers = Tier::query()->orderBy('min_points')->get();

        $rows = $base->select('tier_id', DB::raw('SUM(total_spent) as revenue'), DB::raw('SUM(orders_count) as orders'))
            ->groupBy('tier_id')
            ->get()
            ->keyBy('tier_id');

        $table = [];
        foreach ($tiers as $tier) {
            $row = $rows->get($tier->id);
            $revenue = $row ? (float) $row->revenue : 0.0;
            $orders = $row ? (int) $row->orders : 0;
            $aov = $orders > 0 ? round($revenue / $orders, 2) : 0.0;
            $table[] = [$tier->title, round($revenue, 2), $orders, $aov];
        }
        $noTier = $rows->get(null);
        $table[] = ['No tier', round((float) ($noTier?->revenue ?? 0), 2), (int) ($noTier?->orders ?? 0), 0.0];

        return $this->payload(
            'Revenue per tier',
            [
                ['label' => 'Total revenue', 'value' => round($base->sum('total_spent'), 2)],
            ],
            [
                'columns' => ['Tier', 'Revenue', 'Orders', 'AOV'],
                'rows' => $table,
            ],
            [],
            $filters
        );
    }

    private function tierUpgradeFrequency(array $filters): array
    {
        $base = $this->baseCustomerQuery($filters);
        $total = (int) $base->count();
        $baseTierId = Tier::query()->orderBy('min_points')->value('id');

        $upgraded = (int) $base->when($baseTierId, function ($query) use ($baseTierId) {
            $query->whereNotNull('tier_id')->where('tier_id', '!=', $baseTierId);
        }, function ($query) {
            $query->whereNotNull('tier_id');
        })->count();

        $rate = $total > 0 ? round(($upgraded / $total) * 100, 1) : 0.0;

        return $this->payload(
            'Tier upgrade frequency',
            [
                ['label' => 'Upgraded customers', 'value' => $upgraded],
                ['label' => 'Upgrade rate', 'value' => $rate . '%'],
            ],
            [
                'columns' => ['Metric', 'Value'],
                'rows' => [
                    ['Total customers', $total],
                    ['Upgraded customers', $upgraded],
                    ['Upgrade rate', $rate . '%'],
                ],
            ],
            [],
            $filters
        );
    }

    private function gamificationEngagementReport(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $customerIds = $this->baseCustomerQuery($filters)->select('id');

        $sources = [
            SourceType::SOCIAL->value,
            SourceType::PROFILE->value,
            SourceType::BIRTHDAY->value,
            SourceType::REGISTER->value,
            SourceType::MYSTERY_BOX->value,
        ];

        $rows = PointsTransaction::query()
            ->select('source_type', DB::raw('COUNT(DISTINCT customer_id) as customers'), DB::raw('SUM(points) as points'))
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('customer_id', $customerIds)
            ->whereIn('source_type', $sources)
            ->groupBy('source_type')
            ->orderByDesc('customers')
            ->get();

        $totalCustomers = (int) $rows->sum('customers');

        return $this->payload(
            'Gamification engagement report',
            [
                ['label' => 'Engaged customers', 'value' => $totalCustomers],
            ],
            [
                'columns' => ['Engagement type', 'Customers', 'Points'],
                'rows' => $rows->map(function ($row) {
                    return [
                        $row->source_type,
                        (int) $row->customers,
                        (int) $row->points,
                    ];
                })->toArray(),
            ],
            [],
            $filters
        );
    }

    private function customerSegmentationClusterOverview(array $filters): array
    {
        $run = $this->latestClusterRun();
        if (!$run) {
            return $this->emptyReport('Customer segmentation: cluster overview', $filters, 'No AI cluster run found.');
        }

        $clusters = AiCluster::query()
            ->where('ai_cluster_run_id', $run->id)
            ->orderBy('cluster_index')
            ->get();

        return $this->payload(
            'Customer segmentation: cluster overview',
            [
                ['label' => 'Cluster run', 'value' => $run->id],
                ['label' => 'Total clusters', 'value' => $clusters->count()],
            ],
            [
                'columns' => ['Cluster', 'Customers', 'Avg spend', 'Avg orders', 'Avg loyalty points'],
                'rows' => $clusters->map(function (AiCluster $cluster) {
                    $label = $cluster->label ?: ('Cluster ' . $cluster->cluster_index);
                    return [
                        $label,
                        (int) $cluster->customer_count,
                        (float) $cluster->avg_total_spent,
                        (float) $cluster->avg_orders_count,
                        (float) $cluster->avg_loyalty_points,
                    ];
                })->toArray(),
            ],
            [],
            $filters
        );
    }

    private function clusterRevenueComparison(array $filters): array
    {
        $run = $this->latestClusterRun();
        if (!$run) {
            return $this->emptyReport('Cluster revenue comparison', $filters, 'No AI cluster run found.');
        }

        $clusterFilter = Arr::get($filters, 'cluster');
        $rows = AiClusterCustomer::query()
            ->select('ai_cluster_id', DB::raw('SUM(total_spent_snapshot) as revenue'), DB::raw('COUNT(*) as customers'))
            ->where('ai_cluster_run_id', $run->id)
            ->when($clusterFilter, function ($query) use ($clusterFilter) {
                $query->where('ai_cluster_id', $clusterFilter);
            })
            ->groupBy('ai_cluster_id')
            ->orderByDesc('revenue')
            ->get();

        $clusters = AiCluster::query()->where('ai_cluster_run_id', $run->id)->get()->keyBy('id');

        return $this->payload(
            'Cluster revenue comparison',
            [
                ['label' => 'Cluster run', 'value' => $run->id],
            ],
            [
                'columns' => ['Cluster', 'Revenue', 'Customers'],
                'rows' => $rows->map(function ($row) use ($clusters) {
                    $cluster = $clusters->get($row->ai_cluster_id);
                    $label = $cluster?->label ?: ('Cluster ' . ($cluster?->cluster_index ?? $row->ai_cluster_id));
                    return [
                        $label,
                        round((float) $row->revenue, 2),
                        (int) $row->customers,
                    ];
                })->toArray(),
            ],
            [],
            $filters
        );
    }

    private function clusterQualityMetricsSilhouette(array $filters): array
    {
        $run = $this->latestClusterRun();
        if (!$run) {
            return $this->emptyReport('Cluster quality metrics (silhouette)', $filters, 'No AI cluster run found.');
        }

        $scores = collect($run->silhouette_scores ?? []);

        return $this->payload(
            'Cluster quality metrics (silhouette)',
            [
                ['label' => 'Selected K', 'value' => $run->selected_k ?? 'N/A'],
                ['label' => 'Silhouette', 'value' => $run->silhouette_score ?? 'N/A'],
                ['label' => 'Final inertia', 'value' => $run->final_inertia ?? 'N/A'],
            ],
            [
                'columns' => ['K', 'Silhouette score'],
                'rows' => $scores->map(function ($row) {
                    return [
                        $row['k'] ?? 'N/A',
                        $row['score'] ?? 'N/A',
                    ];
                })->toArray(),
            ],
            [
                'labels' => $scores->pluck('k')->values()->toArray(),
                'datasets' => [
                    [
                        'label' => 'Silhouette score',
                        'data' => $scores->pluck('score')->values()->toArray(),
                    ],
                ],
            ],
            $filters
        );
    }

    private function highRiskChurnCustomers(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $customerIds = $this->baseCustomerQuery($filters)->select('id');
        $limit = (int) ($filters['top_n'] ?? 25);
        $limit = $limit > 0 ? $limit : 25;

        $rows = CustomerFeature::query()
            ->with('customer')
            ->whereBetween('computed_at', [$start, $end])
            ->whereIn('customer_id', $customerIds)
            ->orderByDesc('days_since_last_order')
            ->limit($limit)
            ->get();

        $tableRows = $rows->map(function (CustomerFeature $feature) {
            $recency = $feature->days_since_last_order ?? 0;
            $orders = $feature->orders_count ?? 0;
            $risk = 'Low';
            if ($recency >= 90 && $orders <= 1) {
                $risk = 'High';
            } elseif ($recency >= 60 && $orders <= 2) {
                $risk = 'Medium';
            }

            return [
                $feature->customer_id,
                $feature->customer?->email ?? $feature->customer?->shopify_id ?? 'N/A',
                $orders,
                $recency,
                $risk,
            ];
        })->toArray();

        return $this->payload(
            'High risk churn customers',
            [
                ['label' => 'Customers flagged', 'value' => count($tableRows)],
            ],
            [
                'columns' => ['Customer ID', 'Contact', 'Orders', 'Recency (days)', 'Risk'],
                'rows' => $tableRows,
            ],
            [],
            $filters
        );
    }

    private function cohortRetentionAnalysis(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $customerIds = $this->baseCustomerQuery($filters)->select('id');

        $customers = Customer::query()
            ->select('id', 'shopify_created_at')
            ->whereBetween('shopify_created_at', [$start, $end])
            ->whereIn('id', $customerIds)
            ->get();

        $features = CustomerFeature::query()
            ->select('customer_id', 'last_order_at')
            ->whereIn('customer_id', $customers->pluck('id'))
            ->get()
            ->keyBy('customer_id');

        $cohorts = [];
        foreach ($customers as $customer) {
            if (!$customer->shopify_created_at) {
                continue;
            }
            $cohort = Carbon::parse($customer->shopify_created_at)->startOfMonth();
            $key = $cohort->format('Y-m');
            $cohorts[$key] ??= [
                'month' => $cohort,
                'customers' => 0,
                'm1' => 0,
                'm2' => 0,
                'm3' => 0,
            ];
            $cohorts[$key]['customers']++;

            $lastOrder = $features->get($customer->id)?->last_order_at;
            if ($lastOrder) {
                $lastOrder = Carbon::parse($lastOrder);
                foreach ([1, 2, 3] as $offset) {
                    $windowStart = $cohort->copy()->addMonths($offset);
                    $windowEnd = $windowStart->copy()->addMonth();
                    if ($lastOrder->between($windowStart, $windowEnd)) {
                        $cohorts[$key]['m' . $offset]++;
                    }
                }
            }
        }

        $rows = collect($cohorts)->sortByDesc('month')->map(function ($cohort) {
            $customers = $cohort['customers'];
            $m1 = $customers > 0 ? round(($cohort['m1'] / $customers) * 100, 1) . '%' : '0%';
            $m2 = $customers > 0 ? round(($cohort['m2'] / $customers) * 100, 1) . '%' : '0%';
            $m3 = $customers > 0 ? round(($cohort['m3'] / $customers) * 100, 1) . '%' : '0%';
            return [
                $cohort['month']->format('Y-m'),
                $customers,
                $m1,
                $m2,
                $m3,
            ];
        })->values()->toArray();

        return $this->payload(
            'Cohort retention analysis',
            [
                ['label' => 'Cohorts analyzed', 'value' => count($rows)],
            ],
            [
                'columns' => ['Cohort month', 'Customers', 'M+1 retention', 'M+2 retention', 'M+3 retention'],
                'rows' => $rows,
            ],
            [],
            $filters
        );
    }

    private function loyaltyGrowthOverTime(array $filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $groupBy = $filters['group_by'] ?? 'day';

        $customers = $this->applyLoyaltyCondition(Customer::query(), true)
            ->whereBetween('shopify_created_at', [$start, $end])
            ->get(['shopify_created_at']);

        $series = $this->buildTimeSeries($customers, $start, $end, $groupBy, 'shopify_created_at');
        $total = array_sum($series['data']);

        return $this->payload(
            'Loyalty growth over time',
            [
                ['label' => 'New loyalty members', 'value' => $total],
            ],
            [
                'columns' => ['Period', 'Customers'],
                'rows' => collect($series['labels'])->zip($series['data'])->map(function ($row) {
                    return [$row[0], $row[1]];
                })->toArray(),
            ],
            [
                'labels' => $series['labels'],
                'datasets' => [
                    [
                        'label' => 'New loyalty members',
                        'data' => $series['data'],
                    ],
                ],
            ],
            $filters
        );
    }

    private function baseCustomerQuery(array $filters, bool $applyCustomerType = true): Builder
    {
        $query = Customer::query();

        if ($applyCustomerType && !empty($filters['customer_type']) && $filters['customer_type'] !== 'all') {
            if ($filters['customer_type'] === 'loyalty') {
                $this->applyLoyaltyCondition($query, true);
            } elseif ($filters['customer_type'] === 'non_loyalty') {
                $this->applyLoyaltyCondition($query, false);
            }
        }

        if (!empty($filters['tier'])) {
            $query->where('tier_id', $filters['tier']);
        }

        if (!empty($filters['min_total_spent'])) {
            $query->where('total_spent', '>=', $filters['min_total_spent']);
        }

        if (!empty($filters['min_orders_count'])) {
            $query->where('orders_count', '>=', $filters['min_orders_count']);
        }

        if (!empty($filters['cluster'])) {
            $clusterId = $filters['cluster'];
            $query->whereIn('id', function ($sub) use ($clusterId) {
                $sub->select('customer_id')
                    ->from('ai_cluster_customers')
                    ->where('ai_cluster_id', $clusterId);
            });
        }

        return $query;
    }

    private function applyLoyaltyCondition(Builder $query, bool $isLoyalty): Builder
    {
        if ($isLoyalty) {
            return $query->where(function ($builder) {
                $builder->where('loyalty_points', '>', 0)
                    ->orWhere('points_pending', '>', 0)
                    ->orWhereNotNull('tier_id');
            });
        }

        return $query->where(function ($builder) {
            $builder->where(function ($nested) {
                $nested->whereNull('tier_id');
            })->where('loyalty_points', '<=', 0)
                ->where('points_pending', '<=', 0);
        });
    }

    private function resolveDateRange(array $filters): array
    {
        $start = Carbon::parse($filters['start_date'])->startOfDay();
        $end = Carbon::parse($filters['end_date'])->endOfDay();

        return [$start, $end];
    }

    private function buildTimeSeries(Collection $rows, Carbon $start, Carbon $end, string $groupBy, string $dateField): array
    {
        $labels = [];
        $data = [];

        $cursor = $start->copy();
        while ($cursor <= $end) {
            if ($groupBy === 'week') {
                $label = $cursor->copy()->startOfWeek()->format('Y-m-d');
                $cursor = $cursor->copy()->addWeek();
            } elseif ($groupBy === 'month') {
                $label = $cursor->copy()->startOfMonth()->format('Y-m');
                $cursor = $cursor->copy()->addMonth();
            } else {
                $label = $cursor->format('Y-m-d');
                $cursor = $cursor->copy()->addDay();
            }

            if (in_array($label, $labels, true)) {
                continue;
            }
            $labels[] = $label;
        }

        $grouped = $rows->groupBy(function ($row) use ($groupBy, $dateField) {
            $date = Carbon::parse($row->{$dateField});
            if ($groupBy === 'week') {
                return $date->startOfWeek()->format('Y-m-d');
            }
            if ($groupBy === 'month') {
                return $date->startOfMonth()->format('Y-m');
            }
            return $date->format('Y-m-d');
        });

        foreach ($labels as $label) {
            $data[] = $grouped->get($label)?->count() ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function latestClusterRun(): ?AiClusterRun
    {
        $run = AiClusterRun::query()
            ->where('status', AiRunStatus::COMPLETED->value)
            ->orderByDesc('completed_at')
            ->first();

        if (!$run) {
            $run = AiClusterRun::query()->orderByDesc('id')->first();
        }

        return $run;
    }

    private function payload(string $title, array $kpis, array $table, array $chart, array $filters): array
    {
        $sections = $this->buildSections($title, $kpis, $table, $chart, $filters);

        return [
            'title' => $title,
            'kpis' => $kpis,
            'table' => $table,
            'chart' => $chart,
            'sections' => $sections,
            'meta' => [
                'filters' => $filters,
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }

    private function buildSections(string $title, array $kpis, array $table, array $chart, array $filters): array
    {
        $reportKey = $filters['_report_key'] ?? null;
        $context = $this->reportContext($reportKey);
        $filtersSummary = $this->filterSummary($filters);
        $rowCount = is_array($table['rows'] ?? null) ? count($table['rows']) : 0;
        $dateRange = ($filters['start_date'] ?? null) && ($filters['end_date'] ?? null)
            ? ($filters['start_date'] . ' to ' . $filters['end_date'])
            : 'the selected period';

        $summary = $context['summary'] ?? sprintf(
            '%s covering %s. %s records included. Filters: %s.',
            $title,
            $dateRange,
            $rowCount,
            $filtersSummary
        );

        $insights = $context['insights'] ?? $this->buildInsights($kpis, $table);
        $methodology = array_merge($this->defaultMethodology(), $context['methodology'] ?? []);
        $sources = $context['sources'] ?? $this->defaultSources();
        $evidence = $context['evidence'] ?? [
            'columns' => $table['columns'] ?? [],
            'rows' => array_slice($table['rows'] ?? [], 0, 10),
        ];

        return [
            'summary' => $summary,
            'insights' => $insights,
            'methodology' => $methodology,
            'sources' => $sources,
            'evidence' => $evidence,
        ];
    }

    private function buildInsights(array $kpis, array $table): array
    {
        $insights = [];
        if (!empty($kpis)) {
            $primary = $kpis[0];
            $insights[] = sprintf('Primary KPI: %s = %s.', $primary['label'] ?? 'Metric', $primary['value'] ?? 'N/A');
            if (isset($kpis[1])) {
                $secondary = $kpis[1];
                $insights[] = sprintf('Secondary KPI: %s = %s.', $secondary['label'] ?? 'Metric', $secondary['value'] ?? 'N/A');
            }
        }

        $rows = $table['rows'] ?? [];
        if (!empty($rows) && is_array($rows[0])) {
            $topRow = $rows[0];
            $columns = $table['columns'] ?? [];
            if (!empty($columns)) {
                $label = $columns[0] ?? 'Top record';
                $value = $topRow[0] ?? 'N/A';
                $insights[] = sprintf('Top record by %s: %s.', $label, $value);
            }
        }

        return $insights ?: ['No insights available for the selected filters.'];
    }

    private function defaultMethodology(): array
    {
        return [
            'Loyalty member definition: customers with loyalty_points > 0, points_pending > 0, or an assigned tier.',
            'Revenue metrics use customers.total_spent as the revenue proxy when order records are unavailable.',
            'Activity/retention uses points transactions as the engagement proxy in absence of order events.',
        ];
    }

    private function defaultSources(): array
    {
        return [
            'customers',
            'points_transactions',
            'customer_coupons',
            'customer_features',
            'tiers',
            'ai_cluster_runs',
            'ai_clusters',
            'ai_cluster_customers',
        ];
    }

    private function reportContext(?string $reportKey): array
    {
        return match ($reportKey) {
            'loyalty_participation_rate' => [
                'summary' => 'Measures adoption of the loyalty program across the customer base.',
                'methodology' => ['Participation rate = loyalty members / total customers.'],
            ],
            'loyalty_driven_revenue' => [
                'summary' => 'Compares revenue generated by loyalty members vs non-loyalty customers.',
                'methodology' => ['Revenue share = loyalty revenue / total revenue.'],
            ],
            'aov_loyalty_vs_non' => [
                'summary' => 'Compares average order value for loyalty and non-loyalty segments.',
                'methodology' => ['AOV = total_spent / orders_count.'],
            ],
            'repeat_purchase_rate_loyalty_vs_non' => [
                'summary' => 'Measures repeat purchasing behavior across loyalty segments.',
                'methodology' => ['Repeat rate = customers with orders_count > 1 / total customers.'],
            ],
            'customer_retention_rate' => [
                'summary' => 'Tracks retention by comparing activity across consecutive periods.',
                'methodology' => ['Retention = customers active in both current and previous period.'],
            ],
            'points_issued_redeemed_expired' => [
                'summary' => 'Tracks issuance, redemption, and expired points within the period.',
                'methodology' => ['Issued = EARN points, Redeemed = SPEND points. Expired not tracked in schema.'],
            ],
            'points_liability_outstanding' => [
                'summary' => 'Shows outstanding points liability based on issued minus redeemed points.',
                'methodology' => ['Liability = issued - redeemed - expired.'],
            ],
            'redemption_rate' => [
                'summary' => 'Measures how effectively points are redeemed by members.',
                'methodology' => ['Redemption rate = redeemed / issued within period.'],
            ],
            'reward_effectiveness' => [
                'summary' => 'Ranks rewards by redemption volume and points spent.',
                'methodology' => ['Effectiveness = total redemptions per reward.'],
            ],
            'time_to_redeem_points_velocity' => [
                'summary' => 'Measures average time from first earning points to first redemption.',
                'methodology' => ['Velocity = days between first EARN and first SPEND.'],
            ],
            'customers_per_tier' => [
                'summary' => 'Shows distribution of customers across loyalty tiers.',
            ],
            'revenue_per_tier' => [
                'summary' => 'Compares revenue, orders, and AOV by tier.',
                'methodology' => ['AOV = total_spent / orders_count.'],
            ],
            'tier_upgrade_frequency' => [
                'summary' => 'Shows how many customers have upgraded beyond the base tier.',
            ],
            'gamification_engagement_report' => [
                'summary' => 'Tracks engagement across non-purchase activities (social, profile, birthday, etc.).',
            ],
            'customer_segmentation_cluster_overview' => [
                'summary' => 'Provides a high-level overview of AI cluster segments.',
            ],
            'cluster_revenue_comparison' => [
                'summary' => 'Compares revenue contribution by AI cluster.',
            ],
            'cluster_quality_metrics_silhouette' => [
                'summary' => 'Shows clustering quality metrics for model evaluation.',
            ],
            'high_risk_churn_customers' => [
                'summary' => 'Flags customers with high recency and low purchase frequency.',
            ],
            'cohort_retention_analysis' => [
                'summary' => 'Tracks retention by signup cohort month and subsequent activity.',
                'methodology' => ['Cohort month based on customer shopify_created_at.'],
            ],
            'loyalty_growth_over_time' => [
                'summary' => 'Tracks growth of loyalty members over time.',
            ],
            default => [],
        };
    }

    private function filterSummary(array $filters): string
    {
        return collect($filters)
            ->reject(fn ($value, $key) => str_starts_with((string) $key, '_'))
            ->map(function ($value, $key) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                if ($value === null || $value === '') {
                    $value = 'n/a';
                }
                return $key . ': ' . $value;
            })->implode('; ');
    }

    private function emptyReport(string $title, array $filters, string $message = 'No data available.'): array
    {
        return $this->payload(
            $title,
            [
                ['label' => 'Status', 'value' => $message],
            ],
            [
                'columns' => ['Message'],
                'rows' => [[$message]],
            ],
            [],
            $filters
        );
    }
}
