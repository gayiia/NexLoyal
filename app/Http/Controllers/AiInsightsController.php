<?php

namespace App\Http\Controllers;

use App\Jobs\ComputeCustomerFeaturesJob;
use App\Jobs\RunAIClusteringJob;
use App\Models\AiAwardIssuance;
use App\Models\AiCluster;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Models\AiClusterAward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class AiInsightsController extends Controller
{
    public function index(Request $request)
    {
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        $clusters = $latestRun
            ? AiCluster::query()
                ->where('ai_cluster_run_id', $latestRun->id)
                ->orderBy('label')
                ->get()
            : collect();

        $clusterCustomers = $latestRun
            ? AiClusterCustomer::query()
                ->with('customer')
                ->where('ai_cluster_run_id', $latestRun->id)
                ->get()
                ->groupBy('ai_cluster_id')
            : collect();

        $awards = AiClusterAward::query()
            ->with(['cluster', 'coupon'])
            ->orderByDesc('id')
            ->get();

        $awardIssuanceCounts = AiAwardIssuance::query()
            ->select('ai_cluster_awards.type', DB::raw('COUNT(*) as total'))
            ->join('ai_cluster_awards', 'ai_cluster_awards.id', '=', 'ai_award_issuances.ai_cluster_award_id')
            ->groupBy('ai_cluster_awards.type')
            ->pluck('total', 'type')
            ->toArray();

        $chartLabels = $clusters->pluck('label')->values()->all();
        $chartDistribution = $clusters->pluck('customer_count')->values()->all();
        $chartAvgSpend = $clusters->pluck('avg_total_spent')->map(fn ($value) => (float) $value)->values()->all();
        $awardMix = [
            'points' => (int) ($awardIssuanceCounts['points'] ?? 0),
            'coupon' => (int) ($awardIssuanceCounts['coupon'] ?? 0),
        ];

        return view('ai-insights', [
            'latestRun' => $latestRun,
            'clusters' => $clusters,
            'clusterCustomers' => $clusterCustomers,
            'awards' => $awards,
            'charts' => [
                'labels' => $chartLabels,
                'distribution' => $chartDistribution,
                'avg_spend' => $chartAvgSpend,
                'award_mix' => $awardMix,
            ],
        ]);
    }

    public function run(Request $request)
    {
        Bus::chain([
            new ComputeCustomerFeaturesJob(),
            new RunAIClusteringJob(),
        ])->dispatch();

        return redirect()->route('ai-insights')->with('status', 'AI clustering started.');
    }

    public function exportCluster(AiCluster $cluster)
    {
        $query = AiClusterCustomer::query()
            ->with('customer')
            ->where('ai_cluster_id', $cluster->id)
            ->orderByDesc('total_spent_snapshot');

        $fileName = 'ai-cluster-' . $cluster->id . '-customers-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Customer ID',
                'Name',
                'Email',
                'Orders',
                'Total Spent',
                'Loyalty Points',
            ]);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    $customer = $row->customer;
                    $nameParts = array_filter([$customer?->first_name, $customer?->last_name]);
                    $name = $nameParts ? implode(' ', $nameParts) : ($customer?->email ?? 'Customer');
                    fputcsv($handle, [
                        $customer?->id,
                        $name,
                        $customer?->email,
                        $row->orders_count_snapshot,
                        $row->total_spent_snapshot,
                        $row->loyalty_points_snapshot,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, $headers);
    }

    public function status()
    {
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        if (!$latestRun) {
            return response()->json([
                'status' => 'none',
            ]);
        }

        return response()->json([
            'status' => $latestRun->status,
            'total_customers' => (int) $latestRun->total_customers,
            'total_clusters' => (int) $latestRun->total_clusters,
            'silhouette_score' => $latestRun->silhouette_score,
            'error_message' => $latestRun->error_message,
            'started_at' => optional($latestRun->started_at)->toIso8601String(),
            'completed_at' => optional($latestRun->completed_at)->toIso8601String(),
        ]);
    }
}
