<?php

namespace App\Imports;

use App\Enums\PointsTransactionType;
use App\Enums\SourceType;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PointsTransactionsCsvImport
{
    public int $imported = 0;
    public int $skipped = 0;
    public array $skippedRows = [];

    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            $shopifyId = trim((string) ($row['customer_shopify_id'] ?? ''));
            if ($shopifyId === '') {
                $this->pushSkipped($row, 'missing_customer_shopify_id');
                continue;
            }

            $customer = Customer::query()->where('shopify_id', $shopifyId)->first();
            if (!$customer) {
                $this->pushSkipped($row, 'customer_not_found');
                continue;
            }

            $points = $this->toInt($row['points'] ?? 0);
            $type = strtoupper(trim((string) ($row['type'] ?? PointsTransactionType::EARN->value)));
            if (!in_array($type, [PointsTransactionType::EARN->value, PointsTransactionType::SPEND->value], true)) {
                $type = PointsTransactionType::EARN->value;
            }

            $sourceType = strtoupper(trim((string) ($row['source_type'] ?? SourceType::RULE->value)));
            if (!in_array($sourceType, [SourceType::ORDER->value, SourceType::RULE->value], true)) {
                $sourceType = SourceType::RULE->value;
            }

            $orderIdRaw = trim((string) ($row['order_id'] ?? ''));
            $orderId = $orderIdRaw !== '' ? (int) $orderIdRaw : null;

            $createdAt = $this->toDate($row['created_at'] ?? null);
            if (!$createdAt) {
                $this->pushSkipped($row, 'missing_created_at');
                continue;
            }

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

            $exists = DB::table('points_transactions')
                ->where('customer_id', $customer->id)
                ->where('event_key', $eventKey)
                ->exists();

            if ($exists) {
                continue;
            }

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

    private function toInt($value): int
    {
        $clean = $this->normalizeNumber($value);
        return (int) round((float) $clean);
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
