<?php

// This service prepares thesis-ready benchmarking tables from the feature dataset and latest AI run.
namespace App\Services;

use App\Models\AiClusterRun;
use App\Models\CustomerFeature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

// This class builds baseline and AI benchmarking outputs without retraining the model.
class AiBenchmarkingService
{
    // This assembles the requested benchmarking tables from the current feature dataset and latest AI run.
    public function build(?AiClusterRun $latestRun): array
    {
        $featureRows = $this->loadFeatureRows();
        $thresholds = $this->resolveThresholds($featureRows);
        $baselineDefinition = $this->buildBaselineDefinitionTable($thresholds);
        $baselineResults = $this->buildBaselineResultsTable($featureRows, $thresholds);
        $aiClusterResults = $this->buildAiClusterResultsTable($latestRun, $thresholds);

        return [
            'meta' => [
                'dataset_customer_count' => $featureRows->count(),
                'thresholds' => $thresholds,
                'threshold_summary' => sprintf(
                    'Percentile thresholds from the current eligible feature dataset: spend P75 = %s, points earned P75 = %s, days since last order P75 = %s, orders count P25 = %s.',
                    number_format((float) $thresholds['total_spent_p75'], 2),
                    number_format((float) $thresholds['points_earned_p75'], 2),
                    number_format((float) $thresholds['days_since_last_order_p75'], 1),
                    number_format((float) $thresholds['orders_count_p25'], 1)
                ),
            ],
            'baseline_definition' => $baselineDefinition,
            'baseline_results' => $baselineResults,
            'ai_cluster_results' => $aiClusterResults,
        ];
    }

    // This writes all benchmarking tables to CSV files inside storage/app/exports.
    public function export(array $tables): array
    {
        $directory = storage_path('app/exports/ai-benchmarking/' . now()->format('Ymd_His'));
        File::ensureDirectoryExists($directory);

        $files = [
            'baseline-definition.csv' => $tables['baseline_definition'],
            'baseline-results.csv' => $tables['baseline_results'],
            'ai-cluster-results.csv' => $tables['ai_cluster_results'],
        ];

        $writtenFiles = [];
        foreach ($files as $fileName => $table) {
            $path = $directory . DIRECTORY_SEPARATOR . $fileName;
            $this->writeCsv($path, $table['columns'] ?? [], $table['rows'] ?? []);
            $writtenFiles[] = $path;
        }

        return [
            'directory' => $directory,
            'files' => $writtenFiles,
        ];
    }

    // This loads the current eligible feature dataset used as the baseline benchmark population.
    private function loadFeatureRows(): Collection
    {
        return CustomerFeature::query()
            ->where('is_excluded', false)
            ->get([
                'orders_count',
                'total_spent',
                'points_earned',
                'days_since_last_order',
            ])
            ->map(function (CustomerFeature $feature): array {
                return [
                    'orders_count' => (float) ($feature->orders_count ?? 0),
                    'total_spent' => (float) ($feature->total_spent ?? 0),
                    'points_earned' => (float) ($feature->points_earned ?? 0),
                    'days_since_last_order' => (float) ($feature->days_since_last_order ?? 0),
                ];
            })
            ->values();
    }

    // This computes the percentile thresholds required by the rule-based baseline.
    private function resolveThresholds(Collection $rows): array
    {
        return [
            'total_spent_p75' => $this->percentile($rows->pluck('total_spent')->all(), 0.75),
            'points_earned_p75' => $this->percentile($rows->pluck('points_earned')->all(), 0.75),
            'days_since_last_order_p75' => $this->percentile($rows->pluck('days_since_last_order')->all(), 0.75),
            'orders_count_p25' => $this->percentile($rows->pluck('orders_count')->all(), 0.25),
        ];
    }

    // This defines the baseline rules using the actual percentile cutoffs from the current dataset.
    private function buildBaselineDefinitionTable(array $thresholds): array
    {
        $labels = $this->baselineClusterLabels();

        return [
            'columns' => ['Cluster', 'Rule'],
            'rows' => [
                [
                    'Cluster' => $labels['High-value'],
                    'Rule' => sprintf(
                        'total_spent >= %.2f (dataset P75) OR points_earned >= %.2f (dataset P75)',
                        (float) $thresholds['total_spent_p75'],
                        (float) $thresholds['points_earned_p75']
                    ),
                ],
                [
                    'Cluster' => $labels['Medium-value'],
                    'Rule' => 'Customers not meeting the high-value or low-value / at-risk thresholds.',
                ],
                [
                    'Cluster' => $labels['Low-value / At-risk'],
                    'Rule' => sprintf(
                        'days_since_last_order >= %.2f (dataset P75) OR orders_count <= %.2f (dataset P25)',
                        (float) $thresholds['days_since_last_order_p75'],
                        (float) $thresholds['orders_count_p25']
                    ),
                ],
            ],
        ];
    }

    // This applies the baseline rules to the current feature dataset and summarizes each resulting group.
    private function buildBaselineResultsTable(Collection $rows, array $thresholds): array
    {
        $labels = $this->baselineClusterLabels();
        $groups = [
            'High-value' => collect(),
            'Medium-value' => collect(),
            'Low-value / At-risk' => collect(),
        ];

        foreach ($rows as $row) {
            $groups[$this->classifyBaselineGroup($row, $thresholds)]->push($row);
        }

        $resultRows = [];
        foreach (array_keys($groups) as $group) {
            $groupRows = $groups[$group];
            $resultRows[] = [
                'Cluster' => $labels[$group] ?? $group,
                'Customer Count' => (int) $groupRows->count(),
                'Avg Spend' => round((float) $groupRows->avg('total_spent'), 2),
                'Avg Points Earned' => round((float) $groupRows->avg('points_earned'), 2),
                'Avg Orders' => round((float) $groupRows->avg('orders_count'), 2),
                'Avg Days Since Last Order' => round((float) $groupRows->avg('days_since_last_order'), 2),
            ];
        }

        return [
            'columns' => [
                'Cluster',
                'Customer Count',
                'Avg Spend',
                'Avg Points Earned',
                'Avg Orders',
                'Avg Days Since Last Order',
            ],
            'rows' => $resultRows,
        ];
    }

    // This summarizes the latest persisted AI cluster assignments into the requested benchmarking table.
    private function buildAiClusterResultsTable(?AiClusterRun $latestRun, array $thresholds): array
    {
        $rows = [];

        if ($latestRun) {
            $rows = DB::table('ai_cluster_customers as cluster_customers')
                ->join('ai_clusters as clusters', 'clusters.id', '=', 'cluster_customers.ai_cluster_id')
                ->where('cluster_customers.ai_cluster_run_id', $latestRun->id)
                ->select([
                    'clusters.label',
                    'clusters.cluster_index',
                    DB::raw('COUNT(*) as customer_count'),
                    DB::raw('AVG(cluster_customers.total_spent_snapshot) as avg_spend'),
                    DB::raw('AVG(cluster_customers.points_earned_snapshot) as avg_points_earned'),
                    DB::raw('AVG(cluster_customers.orders_count_snapshot) as avg_orders'),
                    DB::raw('AVG(cluster_customers.days_since_last_order_snapshot) as avg_days_since_last_order'),
                ])
                ->groupBy('clusters.label', 'clusters.cluster_index')
                ->orderByRaw('AVG(cluster_customers.total_spent_snapshot) ASC')
                ->get()
                ->map(function (object $row) use ($thresholds): array {
                    return [
                        'Cluster' => $row->label ?: 'Cluster ' . ((int) $row->cluster_index + 1),
                        'Customer Count' => (int) ($row->customer_count ?? 0),
                        'Avg Spend' => round((float) ($row->avg_spend ?? 0), 2),
                        'Avg Points Earned' => round((float) ($row->avg_points_earned ?? 0), 2),
                        'Avg Orders' => round((float) ($row->avg_orders ?? 0), 2),
                        'Avg Days Since Last Order' => round((float) ($row->avg_days_since_last_order ?? 0), 2),
                    ];
                })
                ->values()
                ->all();
        }

        return [
            'columns' => [
                'Cluster',
                'Customer Count',
                'Avg Spend',
                'Avg Points Earned',
                'Avg Orders',
                'Avg Days Since Last Order',
            ],
            'rows' => $rows,
        ];
    }

    // This applies the baseline rules in a deterministic order so each customer belongs to one group.
    private function classifyBaselineGroup(array $row, array $thresholds): string
    {
        $isAtRisk = (float) $row['days_since_last_order'] >= (float) $thresholds['days_since_last_order_p75']
            || (float) $row['orders_count'] <= (float) $thresholds['orders_count_p25'];
        if ($isAtRisk) {
            return 'Low-value / At-risk';
        }

        $isHighValue = (float) $row['total_spent'] >= (float) $thresholds['total_spent_p75']
            || (float) $row['points_earned'] >= (float) $thresholds['points_earned_p75'];

        return $isHighValue ? 'High-value' : 'Medium-value';
    }

    // This maps internal rule-group names to named benchmark cluster labels for reporting.
    private function baselineClusterLabels(): array
    {
        return [
            'High-value' => 'Value Seekers',
            'Medium-value' => 'Budget Buyers',
            'Low-value / At-risk' => 'Growing Shoppers',
        ];
    }

    // This computes a linear-interpolated percentile for the baseline thresholds.
    private function percentile(array $values, float $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        if ($count === 1) {
            return (float) $values[0];
        }

        $position = max(0, min($count - 1, $percentile * ($count - 1)));
        $lowerIndex = (int) floor($position);
        $upperIndex = (int) ceil($position);

        if ($lowerIndex === $upperIndex) {
            return (float) $values[$lowerIndex];
        }

        $weight = $position - $lowerIndex;

        return ((float) $values[$lowerIndex] * (1 - $weight)) + ((float) $values[$upperIndex] * $weight);
    }

    // This writes a single CSV file with the provided header and rows.
    private function writeCsv(string $path, array $columns, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create benchmark CSV export.');
        }

        try {
            fputcsv($handle, $columns);
            foreach ($rows as $row) {
                $record = [];
                foreach ($columns as $column) {
                    $record[] = $row[$column] ?? null;
                }
                fputcsv($handle, $record);
            }
        } finally {
            fclose($handle);
        }
    }
}
