<?php

// This controller renders AI insights and manages clustering runs.
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

// This class powers the AI insights dashboard and export endpoints.
class AiInsightsController extends Controller
{
    // This loads the latest clustering data and renders the AI insights page.
    public function index(Request $request)
    {
        // This fetches the most recent clustering run if one exists.
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        $clusters = $latestRun
            ? AiCluster::query()
                ->where('ai_cluster_run_id', $latestRun->id)
                ->orderBy('label')
                ->get()
            : collect();

        // This groups cluster customers so the UI can show members per cluster.
        $clusterCustomers = $latestRun
            ? AiClusterCustomer::query()
                ->with('customer')
                ->where('ai_cluster_run_id', $latestRun->id)
                ->get()
                ->groupBy('ai_cluster_id')
            : collect();

        // This loads configured AI awards and their linked coupon data.
        $awards = AiClusterAward::query()
            ->with(['cluster', 'coupon'])
            ->orderByDesc('id')
            ->get();

        // This aggregates award issuance counts by type for summary charts.
        $awardIssuanceCounts = AiAwardIssuance::query()
            ->select('ai_cluster_awards.type', DB::raw('COUNT(*) as total'))
            ->join('ai_cluster_awards', 'ai_cluster_awards.id', '=', 'ai_award_issuances.ai_cluster_award_id')
            ->groupBy('ai_cluster_awards.type')
            ->pluck('total', 'type')
            ->toArray();

        // These arrays are used to build charts in the Blade template.
        $chartLabels = $clusters->pluck('label')->values()->all();
        $chartDistribution = $clusters->pluck('customer_count')->values()->all();
        $chartAvgSpend = $clusters->pluck('avg_total_spent')->map(fn ($value) => (float) $value)->values()->all();
        $awardMix = [
            'points' => (int) ($awardIssuanceCounts['points'] ?? 0),
            'coupon' => (int) ($awardIssuanceCounts['coupon'] ?? 0),
        ];

        // This renders the dashboard view with all derived data.
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

    // This starts a new AI clustering run as a chained background job.
    public function run(Request $request)
    {
        // This ensures feature computation happens before clustering.
        Bus::chain([
            new ComputeCustomerFeaturesJob(),
            new RunAIClusteringJob(),
        ])->dispatch();

        return redirect()->route('ai-insights')->with('status', 'AI clustering started.');
    }

    // This streams a CSV export of customers in a selected cluster.
    public function exportCluster(AiCluster $cluster)
    {
        // This builds the query used for chunked CSV export.
        $query = AiClusterCustomer::query()
            ->with('customer')
            ->where('ai_cluster_id', $cluster->id)
            ->orderByDesc('total_spent_snapshot');

        // This names the file with the cluster ID and timestamp.
        $fileName = 'ai-cluster-' . $cluster->id . '-customers-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // This streams the CSV content to avoid loading all rows into memory.
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // This writes the header row expected by the export.
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
                    // This falls back to email when name fields are missing.
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

    // This returns the status of the latest clustering run for polling.
    public function status()
    {
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        if (!$latestRun) {
            return response()->json([
                'status' => 'none',
            ]);
        }

        // This exposes run metrics used by the front-end progress UI.
        return response()->json([
            'status' => $latestRun->status,
            'total_customers' => (int) $latestRun->total_customers,
            'total_clusters' => (int) $latestRun->total_clusters,
            'silhouette_score' => $latestRun->silhouette_score,
            'selected_k' => $latestRun->selected_k,
            'final_inertia' => $latestRun->final_inertia,
            'silhouette_scores' => $latestRun->silhouette_scores,
            'inertia_scores' => $latestRun->inertia_scores,
            'error_message' => $latestRun->error_message,
            'started_at' => optional($latestRun->started_at)->toIso8601String(),
            'completed_at' => optional($latestRun->completed_at)->toIso8601String(),
        ]);
    }
}
