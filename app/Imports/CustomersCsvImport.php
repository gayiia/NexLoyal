<?php

namespace App\Imports;

use App\Models\Customer;
use Illuminate\Support\Carbon;
class CustomersCsvImport
{
    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $skippedRows = [];
    public array $lastOrderAtRows = [];

    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            $shopifyId = trim((string) ($row['shopify_id'] ?? ''));
            if ($shopifyId === '') {
                $this->pushSkipped($row, 'missing_shopify_id');
                continue;
            }

            $email = trim((string) ($row['email'] ?? ''));
            $ordersCount = $this->toInt($row['orders_count'] ?? 0);
            $totalSpent = $this->toFloat($row['total_spent'] ?? 0);
            $loyaltyPoints = $this->toInt($row['loyalty_points'] ?? 0);
            $pointsPending = $this->toInt($row['points_pending'] ?? 0);
            $lastOrderAt = $this->toDate($row['last_order_at'] ?? null);

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

            if ($customer->wasRecentlyCreated) {
                $this->imported++;
            } else {
                $this->updated++;
            }

            if ($lastOrderAt) {
                $this->lastOrderAtRows[] = [
                    'customer_id' => $customer->id,
                    'last_order_at' => $lastOrderAt,
                ];
            }
        }
    }

    private function toInt($value): int
    {
        $clean = $this->normalizeNumber($value);
        return (int) round((float) $clean);
    }

    private function toFloat($value): float
    {
        $clean = $this->normalizeNumber($value);
        return (float) $clean;
    }

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

    private function toDate($value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'm/d/Y H:i:s', 'm/d/Y H:i', 'm/d/Y'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $stringValue);
            } catch (\Throwable $exception) {
                // Try the next format.
            }
        }

        try {
            return Carbon::parse($stringValue);
        } catch (\Throwable $exception) {
            return null;
        }
    }

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
