<?php

// This import class ingests points transaction rows from CSV exports.
namespace App\Imports;

use App\Enums\PointsTransactionType;
use App\Enums\SourceType;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// This class normalizes transaction data and inserts missing points transactions.
class PointsTransactionsCsvImport
{
    public int $imported = 0;
    public int $skipped = 0;
    public array $skippedRows = [];

    // This processes each CSV row and inserts unique points transactions.
    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            // This links the row to a customer using Shopify's ID.
            $shopifyId = trim((string) ($row['customer_shopify_id'] ?? ''));
            if ($shopifyId === '') {
                $this->pushSkipped($row, 'missing_customer_shopify_id');
                continue;
            }

            // This ensures the customer exists locally before inserting a transaction.
            $customer = Customer::query()->where('shopify_id', $shopifyId)->first();
            if (!$customer) {
                $this->pushSkipped($row, 'customer_not_found');
                continue;
            }

            // These fields normalize type and source to the known enum values.
            $points = $this->toInt($row['points'] ?? 0);
            $type = strtoupper(trim((string) ($row['type'] ?? PointsTransactionType::EARN->value)));
            if (!in_array($type, [PointsTransactionType::EARN->value, PointsTransactionType::SPEND->value], true)) {
                $type = PointsTransactionType::EARN->value;
            }

            $sourceType = strtoupper(trim((string) ($row['source_type'] ?? SourceType::RULE->value)));
            if (!in_array($sourceType, [SourceType::ORDER->value, SourceType::RULE->value], true)) {
                $sourceType = SourceType::RULE->value;
            }

            // This captures an order ID if present in the import file.
            $orderIdRaw = trim((string) ($row['order_id'] ?? ''));
            $orderId = $orderIdRaw !== '' ? (int) $orderIdRaw : null;

            // This requires a timestamp to preserve transaction history accuracy.
            $createdAt = $this->toDate($row['created_at'] ?? null);
            if (!$createdAt) {
                $this->pushSkipped($row, 'missing_created_at');
                continue;
            }

            // This creates a deterministic event key so duplicates are skipped.
            $rawEventKey = trim((string) ($row['event_key'] ?? ''));
            $signature = implode('|', [
                $customer->id,
                $type,
                $sourceType,
                (string) ($orderId ?? ''),
                $rawEventKey,
                $createdAt->toIso8601String(),
                $points,
            ]);
            $eventKey = $rawEventKey !== '' ? $rawEventKey : 'import:' . sha1($signature);

            // This avoids inserting duplicate transactions for the same event.
            $exists = DB::table('points_transactions')
                ->where('customer_id', $customer->id)
                ->where('event_key', $eventKey)
                ->exists();

            if ($exists) {
                continue;
            }

            // This inserts the transaction in an approved state to reflect historical data.
            DB::table('points_transactions')->insert([
                'customer_id' => $customer->id,
                'points' => $points,
                'status' => 'APPROVED',
                'source' => $sourceType,
                'source_type' => $sourceType,
                'type' => $type,
                'order_id' => $orderId,
                'event_key' => $eventKey,
                'reason' => 'CSV import',
                'title' => 'CSV import',
                'reference_type' => 'IMPORT',
                'reference_id' => Str::limit($eventKey, 64, ''),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->imported++;
        }
    }

    // This safely converts a mixed input into an integer value.
    private function toInt($value): int
    {
        $clean = $this->normalizeNumber($value);
        return (int) round((float) $clean);
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

        // These formats cover common CSV export patterns.
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
