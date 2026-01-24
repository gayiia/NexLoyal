<?php

namespace App\Imports;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use Illuminate\Support\Carbon;
class CustomerCouponsCsvImport
{
    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $skippedRows = [];

    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            $shopifyId = trim((string) ($row['customer_shopify_id'] ?? ''));
            $couponCode = trim((string) ($row['coupon_code'] ?? ''));

            if ($shopifyId === '' || $couponCode === '') {
                $this->pushSkipped($row, 'missing_customer_or_coupon');
                continue;
            }

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

            $redeemedAt = $this->toDate($row['redeemed_at'] ?? null);

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

            if ($model->wasRecentlyCreated) {
                $this->imported++;
            } else {
                $this->updated++;
            }
        }
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
