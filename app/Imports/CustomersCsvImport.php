<?php

// This import class parses customer CSV rows into local customer records.
namespace App\Imports;

use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// This class tracks import stats while normalizing customer data from CSV exports.
class CustomersCsvImport
{
    public function __construct(private ?int $batchId = null)
    {
    }

    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $skippedRows = [];
    public array $lastOrderAtRows = [];

    // This processes each CSV row, upserting customers and collecting any last-order timestamps.
    public function import(array $rows): void
    {
        $payloads = [];
        $shopifyIds = [];
        $newlyImported = [];

        foreach ($rows as $row) {
            // A Shopify ID is required to uniquely map the customer.
            $shopifyId = trim((string) ($row['shopify_id'] ?? ''));
            if ($shopifyId === '') {
                $this->pushSkipped($row, 'missing_shopify_id');
                continue;
            }

            // These values are normalized to keep numeric and date fields consistent.
            $email = trim((string) ($row['email'] ?? ''));
            $ordersCount = $this->toInt($row['orders_count'] ?? 0);
            $totalSpent = $this->toFloat($row['total_spent'] ?? 0);
            $loyaltyPoints = $this->toInt($row['loyalty_points'] ?? 0);
            $pointsPending = $this->toInt($row['points_pending'] ?? 0);
            $lastOrderAt = $this->toDate($row['last_order_at'] ?? null);

            $payloads[] = [
                'shopify_id' => $shopifyId,
                'email' => $email !== '' ? $email : null,
                'orders_count' => $ordersCount,
                'total_spent' => $totalSpent,
                'loyalty_points' => $loyaltyPoints,
                'points_pending' => $pointsPending,
                'last_order_at' => $lastOrderAt,
            ];
            $shopifyIds[] = $shopifyId;
        }

        if ($payloads === []) {
            return;
        }

        $existingIds = Customer::query()
            ->whereIn('shopify_id', array_values(array_unique($shopifyIds)))
            ->get()
            ->keyBy('shopify_id');

        if ($this->batchId) {
            $snapshotRows = [];
            foreach ($existingIds as $shopifyId => $customer) {
                $snapshot = $customer->getAttributes();
                $snapshotRows[] = [
                    'ai_import_batch_id' => $this->batchId,
                    'customer_id' => $customer->id,
                    'shopify_id' => (string) $shopifyId,
                    'existed_before' => true,
                    'snapshot' => json_encode($snapshot),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_values(array_diff(array_unique($shopifyIds), array_keys($existingIds->all()))) as $shopifyId) {
                $snapshotRows[] = [
                    'ai_import_batch_id' => $this->batchId,
                    'customer_id' => null,
                    'shopify_id' => (string) $shopifyId,
                    'existed_before' => false,
                    'snapshot' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($snapshotRows !== []) {
                DB::table('ai_import_batch_customer_snapshots')->upsert(
                    $snapshotRows,
                    ['ai_import_batch_id', 'shopify_id'],
                    ['customer_id', 'existed_before', 'snapshot', 'updated_at']
                );
            }
        }

        $timestamp = now();
        $upsertRows = [];

        foreach ($payloads as $payload) {
            $shopifyId = $payload['shopify_id'];

            // These counters reflect first-seen inserts and subsequent updates within the same file.
            if ($existingIds->has($shopifyId) || isset($newlyImported[$shopifyId])) {
                $this->updated++;
            } else {
                $this->imported++;
                $newlyImported[$shopifyId] = true;
            }

            $upsertRows[] = [
                'shopify_id' => $shopifyId,
                'email' => $payload['email'],
                'orders_count' => $payload['orders_count'],
                'total_spent' => $payload['total_spent'],
                'loyalty_points' => $payload['loyalty_points'],
                'points_pending' => $payload['points_pending'],
                'ai_import_batch_id' => $this->batchId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('customers')->upsert(
            $upsertRows,
            ['shopify_id'],
            ['email', 'orders_count', 'total_spent', 'loyalty_points', 'points_pending', 'ai_import_batch_id', 'updated_at']
        );

        $customerIds = Customer::query()
            ->whereIn('shopify_id', array_values(array_unique($shopifyIds)))
            ->pluck('id', 'shopify_id')
            ->all();

        foreach ($payloads as $payload) {
            if ($payload['last_order_at'] && isset($customerIds[$payload['shopify_id']])) {
                $this->lastOrderAtRows[] = [
                    'customer_id' => $customerIds[$payload['shopify_id']],
                    'last_order_at' => $payload['last_order_at'],
                ];
            }
        }
    }

    // This releases collected last-order timestamps so the controller can process them chunk by chunk.
    public function releaseLastOrderAtRows(): array
    {
        $rows = $this->lastOrderAtRows;
        $this->lastOrderAtRows = [];

        return $rows;
    }

    // This safely converts a mixed input into an integer value.
    private function toInt($value): int
    {
        $clean = $this->normalizeNumber($value);
        return (int) round((float) $clean);
    }

    // This safely converts a mixed input into a float value.
    private function toFloat($value): float
    {
        $clean = $this->normalizeNumber($value);
        return (float) $clean;
    }

    // This strips common formatting characters before numeric casting.
    private function normalizeNumber($value): string
    {
        if ($value === null) {
            return '0';
        }
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return '0';
        }
        return str_replace([',', ' '], '', $stringValue);
    }

    // This attempts multiple date formats and falls back to Carbon parsing.
    private function toDate($value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        // These formats cover common exports from Shopify and spreadsheet tools.
        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'm/d/Y H:i:s', 'm/d/Y H:i', 'm/d/Y'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $stringValue);
            } catch (\Throwable $exception) {
                // Try the next format.
            }
        }

        // This fallback may still fail if the value is not a recognizable date string.
        try {
            return Carbon::parse($stringValue);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    // This tracks a skipped row and stores a small sample for debugging.
    private function pushSkipped($row, string $reason): void
    {
        $this->skipped++;
        if (count($this->skippedRows) < 5) {
            $this->skippedRows[] = [
                'reason' => $reason,
                'row' => $row,
            ];
        }
    }
}
