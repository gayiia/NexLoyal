<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatPollVote;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointsTransaction;
use App\Models\Tier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $start30 = $now->copy()->subDays(29)->startOfDay();
        $start14 = $now->copy()->subDays(13)->startOfDay();
        $start7 = $now->copy()->subDays(6)->startOfDay();

        $days30 = $this->buildDateRange($start30, $now);
        $days14 = $this->buildDateRange($start14, $now);
        $days7 = $this->buildDateRange($start7, $now);

        $earnedByDay = PointsTransaction::query()
            ->selectRaw('DATE(created_at) as day, SUM(points) as total')
            ->where('type', 'EARN')
            ->whereBetween('created_at', [$start30, $now])
            ->groupBy('day')
            ->pluck('total', 'day');

        $spentByDay = PointsTransaction::query()
            ->selectRaw('DATE(created_at) as day, SUM(points) as total')
            ->where('type', 'SPEND')
            ->whereBetween('created_at', [$start30, $now])
            ->groupBy('day')
            ->pluck('total', 'day');

        $earnedSeries = $this->mapSeries($days30, $earnedByDay);
        $spentSeries = $this->mapSeries($days30, $spentByDay);
        $earnedTotal = array_sum($earnedSeries);
        $spentTotal = array_sum($spentSeries);
        $redemptionRate = $earnedTotal > 0 ? round(($spentTotal / $earnedTotal) * 100, 1) : 0;

        $pointsOutstanding = (int) Customer::query()->sum('loyalty_points');
        $activeMembers = (int) Customer::query()->count();

        $baseTierId = Tier::query()->orderBy('min_points')->value('id');
        $tierUpgrades = Customer::query()
            ->when($baseTierId, function ($query) use ($baseTierId) {
                $query->whereNotNull('tier_id')->where('tier_id', '!=', $baseTierId);
            }, function ($query) {
                $query->whereNotNull('tier_id');
            })
            ->count();

        $mysteryBoxClaims = CustomerCoupon::query()
            ->where('source', 'MYSTERY_BOX')
            ->whereBetween('redeemed_at', [$start30, $now])
            ->count();

        $chatMessages = ChatMessage::query()
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$start30, $now])
            ->count();

        $chatVotesByDay = ChatPollVote::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$start14, $now])
            ->groupBy('day')
            ->pluck('total', 'day');

        $chatVotesSeries = $this->mapSeries($days14, $chatVotesByDay);

        $redemptionMixRows = CustomerCoupon::query()
            ->select('coupons.type', 'coupons.value_type', DB::raw('COUNT(*) as total'))
            ->join('coupons', 'coupons.id', '=', 'customer_coupons.coupon_id')
            ->whereNotNull('customer_coupons.redeemed_at')
            ->whereBetween('customer_coupons.redeemed_at', [$start30, $now])
            ->groupBy('coupons.type', 'coupons.value_type')
            ->get();

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
            $percent = $redemptionMixTotal > 0 ? round(($count / $redemptionMixTotal) * 100) : 0;
            return ['count' => $count, 'percent' => $percent];
        })->toArray();

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

        $redemptionsByDay = CustomerCoupon::query()
            ->selectRaw('DATE(redeemed_at) as day, COUNT(*) as total')
            ->whereNotNull('redeemed_at')
            ->whereBetween('redeemed_at', [$start7, $now])
            ->groupBy('day')
            ->pluck('total', 'day');

        $redemptionsWeekly = $this->mapSeries($days7, $redemptionsByDay);

        $mysteryBoxOutcomes = CustomerCoupon::query()
            ->select('coupons.title', DB::raw('COUNT(*) as total'))
            ->join('coupons', 'coupons.id', '=', 'customer_coupons.coupon_id')
            ->where('customer_coupons.source', 'MYSTERY_BOX')
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

    private function mapSeries(array $days, $totals): array
    {
        $data = [];
        foreach ($days as $day) {
            $data[] = (int) ($totals[$day] ?? 0);
        }
        return $data;
    }
}
