<?php

namespace App\Services;

use App\Models\AiImportBatch;
use Illuminate\Support\Facades\DB;

// This service encapsulates AI import reset logic so it can run safely in queue jobs.
class AiImportResetService
{
    // This performs reset steps and returns a summary for logs/UI.
    public function runReset(bool $deleteCustomersIfSafe, ?callable $progress = null): array
    {
        $batch = AiImportBatch::query()->latest('id')->first();
        $batchId = $batch?->id;
        $deletedCustomers = false;
        $customersDeletedCount = 0;

        DB::transaction(function () use ($batch, $batchId, $deleteCustomersIfSafe, &$deletedCustomers, &$customersDeletedCount, $progress): void {
            $progress && $progress('Clearing derived AI clustering data.', 'clear_ai_data', 25);
            $this->clearAiDerivedData($progress);

            if ($batchId) {
                $progress && $progress("Removing batch-linked records for batch #{$batchId}.", 'clear_batch_rows', 45);
                $deletedCoupons = DB::table('customer_coupons')
                    ->where('ai_import_batch_id', $batchId)
                    ->delete();
                $progress && $progress("Deleted {$deletedCoupons} customer_coupons rows linked to batch #{$batchId}.", 'clear_batch_rows', 50);

                $deletedPoints = DB::table('points_transactions')
                    ->where('ai_import_batch_id', $batchId)
                    ->delete();
                $progress && $progress("Deleted {$deletedPoints} points_transactions rows linked to batch #{$batchId}.", 'clear_batch_rows', 55);

                $progress && $progress("Restoring customers affected by batch #{$batchId}.", 'restore_customers', 65);
                $restoreSummary = $this->restoreCustomersFromBatch($batchId);
                $progress && $progress(
                    "Customer restore completed for batch #{$batchId}: restored {$restoreSummary['restored']}, removed {$restoreSummary['removed']}.",
                    'restore_customers',
                    72
                );

                $batch->update([
                    'status' => 'rolled_back',
                    'rolled_back_at' => now(),
                    'completed_at' => now(),
                ]);
            }

            if ($deleteCustomersIfSafe) {
                $progress && $progress('Evaluating strict legacy AI-import-only customer safety check.', 'safety_check', 80);
                $assessment = $this->assessCustomerTableForImportReset();
                if ($assessment['can_delete_all_customers_safely']) {
                    $progress && $progress('Safety check passed. Removing legacy AI-import-only customers.', 'delete_customers', 92);
                    $customersDeletedCount = DB::table('customers')->delete();
                    $deletedCustomers = true;
                }
            }
        });

        return [
            'batch_id' => $batchId,
            'deleted_customers' => $deletedCustomers,
            'deleted_customers_count' => $customersDeletedCount,
            'assessment' => $this->assessCustomerTableForImportReset(),
        ];
    }

    // This evaluates whether the customer table is likely legacy AI-import-only and safe to delete wholesale.
    public function assessCustomerTableForImportReset(): array
    {
        $totalCustomers = (int) DB::table('customers')->count();
        $rowsWithNativeSignals = (int) DB::table('customers')
            ->where(function ($query): void {
                $query->whereNotNull('first_name')
                    ->orWhereNotNull('last_name')
                    ->orWhereNotNull('phone')
                    ->orWhereNotNull('status')
                    ->orWhereNotNull('currency')
                    ->orWhereNotNull('shopify_created_at')
                    ->orWhereNotNull('tier_id')
                    ->orWhereNotNull('birthday')
                    ->orWhereNotNull('profile_completed_at')
                    ->orWhereNotNull('birthday_rewarded_at');
            })
            ->count();

        $remainingPoints = (int) DB::table('points_transactions')->count();
        $remainingCoupons = (int) DB::table('customer_coupons')->count();
        $remainingFeatures = (int) DB::table('customer_features')->count();
        $batchLinkedCustomers = (int) DB::table('customers')->whereNotNull('ai_import_batch_id')->count();

        $canDeleteAllCustomersSafely = $totalCustomers > 0
            && $batchLinkedCustomers === 0
            && $rowsWithNativeSignals <= 5
            && $remainingPoints === 0
            && $remainingCoupons === 0
            && $remainingFeatures === 0;

        return [
            'total_customers' => $totalCustomers,
            'rows_with_native_signals' => $rowsWithNativeSignals,
            'remaining_points_transactions' => $remainingPoints,
            'remaining_customer_coupons' => $remainingCoupons,
            'remaining_customer_features' => $remainingFeatures,
            'batch_linked_customers' => $batchLinkedCustomers,
            'can_delete_all_customers_safely' => $canDeleteAllCustomersSafely,
            'message' => $canDeleteAllCustomersSafely
                ? 'The current customers table looks like legacy AI-import-only data and can be removed safely.'
                : 'The current customers table is not fully attributable to a legacy AI import.',
        ];
    }

    // This removes all derived AI outputs so the next import starts from a clean AI state.
    private function clearAiDerivedData(?callable $progress = null): void
    {
        $tables = [
            'ai_award_issuances',
            'ai_cluster_award_customers',
            'ai_cluster_awards',
            'ai_cluster_customers',
            'ai_clusters',
            'ai_cluster_runs',
            'customer_features',
        ];

        foreach ($tables as $index => $table) {
            $deleted = DB::table($table)->delete();
            if ($progress) {
                $stepProgress = 28 + (int) floor((($index + 1) / max(1, count($tables))) * 14);
                $progress("Deleted {$deleted} rows from {$table}.", 'clear_ai_data', $stepProgress);
            }
        }
    }

    // This restores customers touched by a tracked batch back to their pre-import state.
    private function restoreCustomersFromBatch(int $batchId): array
    {
        $removed = 0;
        $restored = 0;

        DB::table('ai_import_batch_customer_snapshots')
            ->where('ai_import_batch_id', $batchId)
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($batchId, &$removed, &$restored): void {
                foreach ($rows as $row) {
                    if (!($row->existed_before ?? false)) {
                        $removed += DB::table('customers')
                            ->where('shopify_id', $row->shopify_id)
                            ->where('ai_import_batch_id', $batchId)
                            ->delete();
                        continue;
                    }

                    $snapshot = json_decode((string) ($row->snapshot ?? 'null'), true);
                    if (!is_array($snapshot)) {
                        continue;
                    }

                    unset($snapshot['id']);

                    $restored += DB::table('customers')
                        ->where('shopify_id', $row->shopify_id)
                        ->update($snapshot);
                }
            });

        return [
            'removed' => $removed,
            'restored' => $restored,
        ];
    }
}
