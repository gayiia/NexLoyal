<?php

// This controller handles CSV uploads that seed AI-related customer data.
namespace App\Http\Controllers;

use App\Imports\CustomerCouponsCsvImport;
use App\Imports\CustomersCsvImport;
use App\Imports\PointsTransactionsCsvImport;
use App\Jobs\ComputeCustomerFeaturesJob;
use App\Jobs\RunAIClusteringJob;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

// This class validates imports, runs them in a transaction, and triggers clustering when requested.
class AiDataImportController extends Controller
{
    // This shows the AI data import form.
    public function index()
    {
        return view('ai-data-import');
    }

    // This validates uploaded CSV files and performs the import.
    public function store(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // These validations ensure files are CSV-like and within size limits.
        $request->validate([
            'customers_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'points_transactions_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'customer_coupons_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'run_clustering' => ['nullable', 'boolean'],
        ]);

        $customersFile = $request->file('customers_file');
        $pointsFile = $request->file('points_transactions_file');
        $couponsFile = $request->file('customer_coupons_file');
        $runClustering = $request->boolean('run_clustering', true);

        // This enforces required columns for each CSV type before importing.
        $this->ensureHeaders($customersFile, 'customers.csv', [
            'shopify_id',
            'email',
            'orders_count',
            'total_spent',
            'loyalty_points',
            'points_pending',
            'last_order_at',
        ], 'customers_file');

        if ($pointsFile) {
            $this->ensureHeaders($pointsFile, 'points_transactions.csv', [
                'customer_shopify_id',
                'points',
                'type',
                'source_type',
                'order_id',
                'event_key',
                'created_at',
            ], 'points_transactions_file');
        }

        if ($couponsFile) {
            $this->ensureHeaders($couponsFile, 'customer_coupons.csv', [
                'customer_shopify_id',
                'coupon_code',
                'redeemed_at',
            ], 'customer_coupons_file');
        }

        // This summary object is stored in session to show import results.
        $summary = [
            'customers' => [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
            ],
            'points_transactions' => [
                'imported' => 0,
                'skipped' => 0,
            ],
            'customer_coupons' => [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
            ],
            'skipped_rows' => [],
            'warnings' => [],
            'run_clustering' => $runClustering,
        ];

        // This tracks synthetic order events when points transactions are missing.
        $syntheticOrders = 0;

        try {
            // This keeps the import atomic across related tables.
            DB::transaction(function () use (
                $customersFile,
                $pointsFile,
                $couponsFile,
                &$summary,
                &$syntheticOrders
            ) {
                // This upserts customers and optionally creates synthetic order history in chunks.
                $customersImport = new CustomersCsvImport();
                $this->processRows($customersFile, function (array $rows) use ($customersImport, $pointsFile, &$syntheticOrders): void {
                    $customersImport->import($rows);

                    if ($pointsFile) {
                        $customersImport->releaseLastOrderAtRows();
                        return;
                    }

                    $syntheticOrders += $this->insertSyntheticOrders($customersImport->releaseLastOrderAtRows());
                });

                $summary['customers'] = [
                    'imported' => $customersImport->imported,
                    'updated' => $customersImport->updated,
                    'skipped' => $customersImport->skipped,
                ];
                if ($customersImport->skippedRows) {
                    $summary['skipped_rows']['customers'] = $customersImport->skippedRows;
                }

                if ($pointsFile) {
                    // This imports points transactions when provided.
                    $pointsImport = new PointsTransactionsCsvImport();
                    $this->processRows($pointsFile, fn (array $rows) => $pointsImport->import($rows));

                    $summary['points_transactions'] = [
                        'imported' => $pointsImport->imported,
                        'skipped' => $pointsImport->skipped,
                    ];
                    if ($pointsImport->skippedRows) {
                        $summary['skipped_rows']['points_transactions'] = $pointsImport->skippedRows;
                    }
                }

                if ($couponsFile) {
                    // This imports coupon redemption history if provided.
                    $couponsImport = new CustomerCouponsCsvImport();
                    $this->processRows($couponsFile, fn (array $rows) => $couponsImport->import($rows));

                    $summary['customer_coupons'] = [
                        'imported' => $couponsImport->imported,
                        'updated' => $couponsImport->updated,
                        'skipped' => $couponsImport->skipped,
                    ];
                    if ($couponsImport->skippedRows) {
                        $summary['skipped_rows']['customer_coupons'] = $couponsImport->skippedRows;
                    }
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The import failed before completion.',
                    'errors' => [
                        'import' => [$exception->getMessage()],
                    ],
                ], 500);
            }

            throw ValidationException::withMessages([
                'customers_file' => 'The import failed before completion: ' . $exception->getMessage(),
            ]);
        }

        // This adds synthetic order count to the summary for transparency.
        if ($syntheticOrders > 0) {
            $summary['customers']['synthetic_orders'] = $syntheticOrders;
        }

        // This prevents clustering if the dataset is below the minimum threshold.
        $processedCustomers = (int) ($summary['customers']['imported'] + $summary['customers']['updated']);
        $minCustomers = (int) config('ai.min_customers_for_training', 20);
        if ($processedCustomers < $minCustomers) {
            $summary['warnings'][] = "Imported customers below minimum ({$minCustomers}). Clustering will not run.";
            $runClustering = false;
        }

        if ($runClustering) {
            // This chains feature computation and clustering after the import.
            Bus::chain([
                new ComputeCustomerFeaturesJob(),
                new RunAIClusteringJob(),
            ])->dispatch();
            $summary['status'] = 'Clustering queued.';
        } else {
            $summary['status'] = 'Import completed.';
        }

        // This returns to the import page with a summary of what happened.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $summary['status'],
                'summary' => $summary,
                'redirect' => route('ai-data-import'),
            ]);
        }

        return redirect()
            ->route('ai-data-import')
            ->with('import_summary', $summary);
    }

    // This ensures each CSV file includes required column headers.
    private function ensureHeaders(UploadedFile $file, string $label, array $required, string $errorKey): void
    {
        $headers = $this->readHeaders($file);
        foreach ($required as $column) {
            if (!in_array($column, $headers, true)) {
                throw ValidationException::withMessages([
                    $errorKey => $label . ' missing column: ' . $column,
                ]);
            }
        }
    }

    // This reads and normalizes the first row of a CSV file into lowercase headers.
    private function readHeaders(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return [];
        }
        $headers = fgetcsv($handle) ?: [];
        fclose($handle);

        $headers = array_map(function ($header) {
            $value = trim((string) $header);
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
            return strtolower($value);
        }, $headers);

        return array_values(array_filter($headers, fn ($value) => $value !== ''));
    }

    // This streams rows in chunks so large imports do not exhaust memory or time.
    private function processRows(UploadedFile $file, callable $callback, int $chunkSize = 500): void
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return;
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(function ($header) {
            $value = trim((string) $header);
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
            return strtolower($value);
        }, $headers);

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            // This skips empty lines to avoid creating blank records.
            if (!$data || count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $data[$index] ?? null;
            }
            if ($row) {
                $rows[] = $row;
                if (count($rows) >= $chunkSize) {
                    $callback($rows);
                    $rows = [];
                }
            }
        }
        fclose($handle);

        if ($rows) {
            $callback($rows);
        }
    }

    // This bulk-inserts synthetic order events without per-row existence queries.
    private function insertSyntheticOrders(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $records = [];
        foreach ($rows as $row) {
            $eventKey = 'import:last_order:' . $row['customer_id'];
            $records[] = [
                'customer_id' => $row['customer_id'],
                'points' => 0,
                'status' => 'APPROVED',
                'source' => \App\Enums\SourceType::ORDER->value,
                'source_type' => \App\Enums\SourceType::ORDER->value,
                'type' => \App\Enums\PointsTransactionType::EARN->value,
                'order_id' => null,
                'event_key' => $eventKey,
                'reason' => 'CSV import last_order_at',
                'title' => 'CSV import last_order_at',
                'reference_type' => 'IMPORT',
                'reference_id' => $eventKey,
                'created_at' => $row['last_order_at'],
                'updated_at' => $row['last_order_at'],
            ];
        }

        return DB::table('points_transactions')->insertOrIgnore($records);
    }
}
