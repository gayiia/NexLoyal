<?php

// This import class maps CSV rows to customer coupon redemptions.
namespace App\Imports;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use Illuminate\Support\Carbon;

// This class upserts customer coupon records and tracks import stats.
class CustomerCouponsCsvImport
{
    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $skippedRows = [];

    // This processes each CSV row, linking customers to coupons by code.
    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            // Both a Shopify customer ID and coupon code are required to link records.
            $shopifyId = trim((string) ($row['customer_shopify_id'] ?? ''));
            $couponCode = trim((string) ($row['coupon_code'] ?? ''));

            if ($shopifyId === '' || $couponCode === '') {
                $this->pushSkipped($row, 'missing_customer_or_coupon');
                continue;
            }

            // This resolves the local customer and coupon before creating a join record.
            $customer = Customer::query()->where('shopify_id', $shopifyId)->first();
            if (!$customer) {
                $this->pushSkipped($row, 'customer_not_found');
                continue;
            }

            $coupon = Coupon::query()->where('code', $couponCode)->first();
            if (!$coupon) {
                $this->pushSkipped($row, 'coupon_not_found');
                continue;
            }

            // This parses redemption timestamps if the CSV provides them.
            $redeemedAt = $this->toDate($row['redeemed_at'] ?? null);

            // This upserts the customer coupon and marks it as imported.
            $model = CustomerCoupon::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'code' => $couponCode],
                [
                    'coupon_id' => $coupon->id,
                    'points_spent' => (int) ($coupon->points_value ?? 0),
                    'status' => 'active',
                    'source' => 'IMPORT',
                    'redeemed_at' => $redeemedAt,
                ]
            );

            // These counters help the UI summarize what the import changed.
            if ($model->wasRecentlyCreated) {
                $this->imported++;
            } else {
                $this->updated++;
            }
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
