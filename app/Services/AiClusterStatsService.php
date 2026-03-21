<?php

namespace App\Services;

use App\Models\AiCluster;
use App\Models\AiClusterRun;
use Illuminate\Support\Facades\DB;

class AiClusterStatsService
{
    // This refreshes persisted cluster and run aggregates from the current cluster-customer snapshots.
    public function refreshRun(AiClusterRun $run): void
    {
        $aggregates = DB::table('ai_cluster_customers')
            ->select([
                'ai_cluster_id',
                DB::raw('COUNT(*) as customer_count'),
                DB::raw('AVG(total_spent_snapshot) as avg_total_spent'),
                DB::raw('AVG(orders_count_snapshot) as avg_orders_count'),
                DB::raw('AVG(loyalty_points_snapshot) as avg_loyalty_points'),
                DB::raw('AVG(points_spent_snapshot) as avg_points_spent'),
            ])
            ->where('ai_cluster_run_id', $run->id)
            ->groupBy('ai_cluster_id')
            ->get()
            ->keyBy('ai_cluster_id');

        $clusters = $run->clusters()->get();
        foreach ($clusters as $cluster) {
            $aggregate = $aggregates->get($cluster->id);
            $cluster->update([
                'customer_count' => (int) ($aggregate->customer_count ?? 0),
                'avg_total_spent' => round((float) ($aggregate->avg_total_spent ?? 0), 2),
                'avg_orders_count' => round((float) ($aggregate->avg_orders_count ?? 0), 2),
                'avg_loyalty_points' => round((float) ($aggregate->avg_loyalty_points ?? 0), 2),
                'avg_points_spent' => round((float) ($aggregate->avg_points_spent ?? 0), 2),
            ]);
        }

        $run->update([
            'total_customers' => (int) DB::table('ai_cluster_customers')
                ->where('ai_cluster_run_id', $run->id)
                ->count(),
            'total_clusters' => (int) AiCluster::query()
                ->where('ai_cluster_run_id', $run->id)
                ->count(),
        ]);
    }

    // This refreshes the latest completed clustering run if one exists.
    public function refreshLatestCompletedRun(): void
    {
        $run = AiClusterRun::query()
            ->where('status', 'completed')
            ->whereHas('clusters')
            ->orderByDesc('id')
            ->first();

        if ($run) {
            $this->refreshRun($run);
        }
    }
}
