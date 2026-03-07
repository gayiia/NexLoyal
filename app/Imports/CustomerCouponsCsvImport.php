<?php

// This import class maps CSV rows to customer coupon redemptions.
namespace App\Imports;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// This class upserts customer coupon records and tracks import stats.
class CustomerCouponsCsvImport
{
    public function __construct(private ?int $batchId = null)
    {
    }

    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $skippedRows = [];

    // This processes each CSV row, linking customers to coupons by code.
    public function import(array $rows): void
    {
        $shopifyIds = [];
        $couponCodes = [];
        foreach ($rows as $row) {
            $shopifyId = trim((string) ($row['customer_shopify_id'] ?? ''));
            $couponCode = trim((string) ($row['coupon_code'] ?? ''));
            if ($shopifyId !== '') {
                $shopifyIds[] = $shopifyId;
            }
            if ($couponCode !== '') {
                $couponCodes[] = $couponCode;
            }
        }

        $customers = Customer::query()
            ->whereIn('shopify_id', array_values(array_unique($shopifyIds)))
            ->get(['id', 'shopify_id'])
            ->keyBy('shopify_id');

        $coupons = Coupon::query()
            ->whereIn('code', array_values(array_unique($couponCodes)))
            ->get(['id', 'code', 'points_value'])
            ->keyBy('code');

        $existing = CustomerCoupon::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->whereIn('code', array_values(array_unique($couponCodes)))
            ->get(['id', 'customer_id', 'code'])
            ->keyBy(fn (CustomerCoupon $coupon) => $coupon->customer_id . '|' . $coupon->code);

        $timestamp = now();
        $inserts = [];
        $updates = [];
        $pendingInsertIndexes = [];

        foreach ($rows as $row) {
            // Both a Shopify customer ID and coupon code are required to link records.
            $shopifyId = trim((string) ($row['customer_shopify_id'] ?? ''));
            $couponCode = trim((string) ($row['coupon_code'] ?? ''));

            if ($shopifyId === '' || $couponCode === '') {
                $this->pushSkipped($row, 'missing_customer_or_coupon');
                continue;
            }

            // This resolves the local customer and coupon before creating a join record.
            $customer = $customers->get($shopifyId);
            if (!$customer) {
                $this->pushSkipped($row, 'customer_not_found');
                continue;
            }

            $coupon = $coupons->get($couponCode);
            if (!$coupon) {
                $this->pushSkipped($row, 'coupon_not_found');
                continue;
            }

            // This parses redemption timestamps if the CSV provides them.
            $redeemedAt = $this->toDate($row['redeemed_at'] ?? null);

            $lookupKey = $customer->id . '|' . $couponCode;
            $payload = [
                'customer_id' => $customer->id,
                'coupon_id' => $coupon->id,
                'points_spent' => (int) ($coupon->points_value ?? 0),
                'code' => $couponCode,
                'status' => 'active',
                'source' => 'IMPORT',
                'ai_import_batch_id' => $this->batchId,
                'redeemed_at' => $redeemedAt,
                'updated_at' => $timestamp,
            ];

            // These counters help the UI summarize what the import changed.
            if (!$existing->has($lookupKey) && !array_key_exists($lookupKey, $pendingInsertIndexes)) {
                $this->imported++;
                $payload['created_at'] = $timestamp;
                $pendingInsertIndexes[$lookupKey] = count($inserts);
                $inserts[] = $payload;
                continue;
            }

            if (array_key_exists($lookupKey, $pendingInsertIndexes)) {
                $this->updated++;
                $insertIndex = $pendingInsertIndexes[$lookupKey];
                $payload['created_at'] = $inserts[$insertIndex]['created_at'];
                $inserts[$insertIndex] = $payload;
            } else {
                $this->updated++;
                $payload['id'] = $existing->get($lookupKey)->id;
                $updates[] = $payload;
            }
        }

        if ($inserts !== []) {
            DB::table('customer_coupons')->insert($inserts);
        }

        if ($updates !== []) {
            DB::table('customer_coupons')->upsert(
                $updates,
                ['id'],
                ['coupon_id', 'points_spent', 'status', 'source', 'ai_import_batch_id', 'redeemed_at', 'updated_at']
            );
        }
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
