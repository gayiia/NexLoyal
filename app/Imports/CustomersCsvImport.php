<?php

// This import class parses customer CSV rows into local customer records.
namespace App\Imports;

use App\Models\Customer;
use Illuminate\Support\Carbon;

// This class tracks import stats while normalizing customer data from CSV exports.
class CustomersCsvImport
{
    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $skippedRows = [];
    public array $lastOrderAtRows = [];

    // This processes each CSV row, upserting customers and collecting any last-order timestamps.
    public function import(array $rows): void
    {
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

            // This updates or creates the customer record using Shopify ID as the key.
            $customer = Customer::query()->updateOrCreate(
                ['shopify_id' => $shopifyId],
                [
                    'email' => $email !== '' ? $email : null,
                    'orders_count' => $ordersCount,
                    'total_spent' => $totalSpent,
                    'loyalty_points' => $loyaltyPoints,
                    'points_pending' => $pointsPending,
                ]
            );

            // These counters help the UI summarize what the import changed.
            if ($customer->wasRecentlyCreated) {
                $this->imported++;
            } else {
                $this->updated++;
            }

            // This defers last-order updates to a later batch update step.
            if ($lastOrderAt) {
                $this->lastOrderAtRows[] = [
                    'customer_id' => $customer->id,
                    'last_order_at' => $lastOrderAt,
                ];
            }
        }
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
