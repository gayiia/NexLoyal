<?php

// This controller builds the admin dashboard KPIs and charts.
namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatPollVote;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointsTransaction;
use App\Enums\SourceType;
use App\Models\Tier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// This class aggregates loyalty, redemption, and engagement metrics for the dashboard view.
class AdminDashboardController extends Controller
{
    // This gathers all summary statistics and chart series for the admin dashboard.
    public function index()
    {
        // These date anchors define the reporting windows.
        $now = now();
        $start30 = $now->copy()->subDays(29)->startOfDay();
        $start14 = $now->copy()->subDays(13)->startOfDay();
        $start7 = $now->copy()->subDays(6)->startOfDay();

        // These arrays provide consistent date labels for charts.
        $days30 = $this->buildDateRange($start30, $now);
        $days14 = $this->buildDateRange($start14, $now);
        $days7 = $this->buildDateRange($start7, $now);

        // This totals points earned per day for the last 30 days.
        $earnedByDay = PointsTransaction::query()
            ->selectRaw('DATE(created_at) as day, SUM(points) as total')
            ->where('type', 'EARN')
            ->whereBetween('created_at', [$start30, $now])
            ->groupBy('day')
            ->pluck('total', 'day');

        // This totals points spent per day for the last 30 days.
        $spentByDay = PointsTransaction::query()
            ->selectRaw('DATE(created_at) as day, SUM(points) as total')
            ->where('type', 'SPEND')
            ->whereBetween('created_at', [$start30, $now])
            ->groupBy('day')
            ->pluck('total', 'day');

        // This fills in missing days with zeros for chart rendering.
        $earnedSeries = $this->mapSeries($days30, $earnedByDay);
        $spentSeries = $this->mapSeries($days30, $spentByDay);
        $earnedTotal = array_sum($earnedSeries);
        $spentTotal = array_sum($spentSeries);
        $redemptionRate = $earnedTotal > 0 ? round(($spentTotal / $earnedTotal) * 100, 1) : 0;

        // These KPI counters support the top-level dashboard metrics.
        $pointsOutstanding = (int) Customer::query()->sum('loyalty_points');
        $activeMembers = (int) Customer::query()->count();

        // This counts customers in tiers above the base tier.
        $baseTierId = Tier::query()->orderBy('min_points')->value('id');
        $tierUpgrades = Customer::query()
            ->when($baseTierId, function ($query) use ($baseTierId) {
                $query->whereNotNull('tier_id')->where('tier_id', '!=', $baseTierId);
            }, function ($query) {
                $query->whereNotNull('tier_id');
            })
            ->count();

        // This counts mystery box redemptions in the last 30 days.
        $mysteryBoxClaims = CustomerCoupon::query()
            ->where('source', SourceType::MYSTERY_BOX->value)
            ->whereBetween('redeemed_at', [$start30, $now])
            ->count();

        // This counts chat messages for engagement tracking.
        $chatMessages = ChatMessage::query()
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$start30, $now])
            ->count();

        // This builds chat poll vote counts for the last 14 days.
        $chatVotesByDay = ChatPollVote::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$start14, $now])
            ->groupBy('day')
            ->pluck('total', 'day');

        $chatVotesSeries = $this->mapSeries($days14, $chatVotesByDay);

        // This groups redemptions by coupon type for the mix chart.
        $redemptionMixRows = CustomerCoupon::query()
            ->select('coupons.type', 'coupons.value_type', DB::raw('COUNT(*) as total'))
            ->join('coupons', 'coupons.id', '=', 'customer_coupons.coupon_id')
            ->whereNotNull('customer_coupons.redeemed_at')
            ->whereBetween('customer_coupons.redeemed_at', [$start30, $now])
            ->groupBy('coupons.type', 'coupons.value_type')
            ->get();

        // This normalizes coupon types into display-friendly labels.
        $redemptionMix = $redemptionMixRows->groupBy(function ($row) {
            if ($row->type === 'free-shipping') {
                return 'Free shipping';
            }
            if ($row->type === 'buy-x-get-y') {
                return 'Free product';
            }
            if ($row->type === 'gift-card') {
                return 'Gift card';
            }
            if ($row->value_type === 'percentage') {
                return 'Percentage off';
            }
            return 'Amount off';
        })->map(function ($group) {
            return (int) $group->sum('total');
        })->toArray();

        $redemptionMixTotal = array_sum($redemptionMix);
        $redemptionMix = collect($redemptionMix)->map(function ($count) use ($redemptionMixTotal) {
            // This converts counts into percentages for chart labels.
            $percent = $redemptionMixTotal > 0 ? round(($count / $redemptionMixTotal) * 100) : 0;
            return ['count' => $count, 'percent' => $percent];
        })->toArray();

        // This builds a tier distribution list for the dashboard.
        $tierCounts = Customer::query()
            ->select('tier_id', DB::raw('COUNT(*) as total'))
            ->groupBy('tier_id')
            ->pluck('total', 'tier_id');

        $tiers = Tier::query()->orderBy('min_points')->get();
        $tierDistribution = $tiers->map(function ($tier) use ($tierCounts, $activeMembers) {
            $count = (int) ($tierCounts[$tier->id] ?? 0);
            $percent = $activeMembers > 0 ? round(($count / $activeMembers) * 100) : 0;
            return [
                'title' => $tier->title,
                'count' => $count,
                'percent' => $percent,
            ];
        })->values();

        // This builds weekly redemption counts for the last 7 days.
        $redemptionsByDay = CustomerCoupon::query()
            ->selectRaw('DATE(redeemed_at) as day, COUNT(*) as total')
            ->whereNotNull('redeemed_at')
            ->whereBetween('redeemed_at', [$start7, $now])
            ->groupBy('day')
            ->pluck('total', 'day');

        $redemptionsWeekly = $this->mapSeries($days7, $redemptionsByDay);

        // This lists the most popular mystery box outcomes.
        $mysteryBoxOutcomes = CustomerCoupon::query()
            ->select('coupons.title', DB::raw('COUNT(*) as total'))
            ->join('coupons', 'coupons.id', '=', 'customer_coupons.coupon_id')
            ->where('customer_coupons.source', SourceType::MYSTERY_BOX->value)
            ->whereBetween('customer_coupons.redeemed_at', [$start30, $now])
            ->groupBy('coupons.title')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'title' => $row->title ?? 'Reward',
                    'count' => (int) $row->total,
                ];
            });

        // This renders the dashboard view with all computed metrics.
        return view('dashboard', [
            'stats' => [
                'points_outstanding' => $pointsOutstanding,
                'redemption_rate' => $redemptionRate,
                'active_members' => $activeMembers,
                'tier_upgrades' => $tierUpgrades,
                'mystery_box_claims' => $mysteryBoxClaims,
                'chat_messages' => $chatMessages,
            ],
            'series' => [
                'days_30' => $days30,
                'earned' => $earnedSeries,
                'spent' => $spentSeries,
                'days_14' => $days14,
                'chat_votes' => $chatVotesSeries,
                'days_7' => $days7,
                'weekly_redemptions' => $redemptionsWeekly,
            ],
            'redemption_mix' => $redemptionMix,
            'tier_distribution' => $tierDistribution,
            'mystery_box_outcomes' => $mysteryBoxOutcomes,
        ]);
    }

    // This builds an inclusive date range for chart labels.
    private function buildDateRange(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }
        return $dates;
    }

    // This maps totals onto a fixed set of day labels, filling missing days with zeros.
    private function mapSeries(array $days, $totals): array
    {
        $data = [];
        foreach ($days as $day) {
            $data[] = (int) ($totals[$day] ?? 0);
        }
        return $data;
    }
}
