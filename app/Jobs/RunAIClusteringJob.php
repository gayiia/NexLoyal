<?php

namespace App\Jobs;

use App\Models\AiCluster;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Models\CustomerFeature;
use App\Services\AiClusterClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RunAIClusteringJob implements ShouldQueue
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

    private const MIN_CUSTOMERS = 20;

    public function handle(AiClusterClient $client): void
    {
        $lock = Cache::lock('ai_cluster_run', 1200);
        if (!$lock->get()) {
            return;
        }

        $run = AiClusterRun::create([
            'status' => 'running',
            'started_at' => now(),
            'params' => [
                'feature_keys' => self::FEATURE_KEYS,
                'min_k' => 2,
                'max_k' => 6,
            ],
        ]);

        try {
            $features = CustomerFeature::query()
                ->whereNotNull('features')
                ->get([
                    'customer_id',
                    'orders_count',
                    'total_spent',
                    'loyalty_points',
                    'points_earned',
                    'points_spent',
                    'redeemed_coupons',
                    'features',
                ]);

            if ($features->count() < self::MIN_CUSTOMERS) {
                $run->update([
                    'status' => 'failed',
                    'error_message' => 'At least 20 customers are required for clustering.',
                    'completed_at' => now(),
                ]);
                return;
            }

            $customerIds = [];
            $vectors = [];
            $snapshots = [];

            foreach ($features as $feature) {
                $values = $feature->features ?? [];
                $vector = [];
                foreach (self::FEATURE_KEYS as $key) {
                    $vector[] = (float) ($values[$key] ?? 0);
                }

                $customerIds[] = $feature->customer_id;
                $vectors[] = $vector;
                $snapshots[$feature->customer_id] = [
                    'orders_count_snapshot' => (int) $feature->orders_count,
                    'total_spent_snapshot' => (float) $feature->total_spent,
                    'loyalty_points_snapshot' => (int) $feature->loyalty_points,
                    'points_earned_snapshot' => (int) $feature->points_earned,
                    'points_spent_snapshot' => (int) $feature->points_spent,
                    'redeemed_coupons_snapshot' => (int) $feature->redeemed_coupons,
                ];
            }

            $response = $client->train([
                'features' => $vectors,
                'feature_keys' => self::FEATURE_KEYS,
                'min_k' => 2,
                'max_k' => 6,
            ]);

            $labels = $response['labels'] ?? [];
            if (count($labels) !== count($customerIds)) {
                throw new \RuntimeException('AI service returned mismatched labels.');
            }

            $clusterGroups = [];
            foreach ($labels as $index => $label) {
                $labelKey = (string) $label;
                $clusterGroups[$labelKey] ??= [];
                $clusterGroups[$labelKey][] = $customerIds[$index];
            }

            $clusterRecords = [];
            foreach ($clusterGroups as $labelKey => $ids) {
                $totals = [
                    'total_spent' => 0,
                    'orders_count' => 0,
                    'loyalty_points' => 0,
                    'points_spent' => 0,
                ];
                foreach ($ids as $customerId) {
                    $snapshot = $snapshots[$customerId] ?? [];
                    $totals['total_spent'] += (float) ($snapshot['total_spent_snapshot'] ?? 0);
                    $totals['orders_count'] += (int) ($snapshot['orders_count_snapshot'] ?? 0);
                    $totals['loyalty_points'] += (int) ($snapshot['loyalty_points_snapshot'] ?? 0);
                    $totals['points_spent'] += (int) ($snapshot['points_spent_snapshot'] ?? 0);
                }

                $count = max(1, count($ids));
                $labelName = is_numeric($labelKey)
                    ? 'Cluster '.((int) $labelKey + 1)
                    : 'Cluster '.$labelKey;

                $clusterRecords[$labelKey] = [
                    'ai_cluster_run_id' => $run->id,
                    'label' => $labelName,
                    'customer_count' => count($ids),
                    'avg_total_spent' => $totals['total_spent'] / $count,
                    'avg_orders_count' => $totals['orders_count'] / $count,
                    'avg_loyalty_points' => $totals['loyalty_points'] / $count,
                    'avg_points_spent' => $totals['points_spent'] / $count,
                    'centroid' => $response['centroids'][$labelKey] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::transaction(function () use ($run, $clusterRecords, $clusterGroups, $snapshots, $response): void {
                $clusterIdMap = [];
                foreach ($clusterRecords as $labelKey => $data) {
                    $cluster = AiCluster::create($data);
                    $clusterIdMap[$labelKey] = $cluster->id;
                }

                $customerRows = [];
                foreach ($clusterGroups as $labelKey => $ids) {
                    $clusterId = $clusterIdMap[$labelKey] ?? null;
                    if (!$clusterId) {
                        continue;
                    }
                    foreach ($ids as $customerId) {
                        $snapshot = $snapshots[$customerId] ?? [];
                        $customerRows[] = array_merge($snapshot, [
                            'ai_cluster_run_id' => $run->id,
                            'ai_cluster_id' => $clusterId,
                            'customer_id' => $customerId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                foreach (array_chunk($customerRows, 500) as $chunk) {
                    AiClusterCustomer::insert($chunk);
                }

                $run->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'total_customers' => count($snapshots),
                    'total_clusters' => count($clusterRecords),
                    'silhouette_score' => $response['silhouette'] ?? null,
                ]);
            });
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
