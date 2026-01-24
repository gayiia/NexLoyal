<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointRule;
use Illuminate\Support\Facades\DB;

class ShopifySyncService
{
    public function __construct(
        private ShopifyDiscountService $shopifyDiscounts,
        private LoyaltyRulesEngine $rulesEngine
    ) {
    }

    public function syncCustomer(array $data, string $topic): ?Customer
    {
        if ($topic === 'customers/delete') {
            Customer::where('shopify_id', (string) ($data['id'] ?? ''))->delete();
            return null;
        }

        $customer = Customer::updateOrCreate(
            ['shopify_id' => (string) ($data['id'] ?? '')],
            [
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => $data['state'] ?? null,
                'orders_count' => (int) ($data['orders_count'] ?? 0),
                'total_spent' => (float) ($data['total_spent'] ?? 0),
                'currency' => $data['currency'] ?? null,
                'shopify_created_at' => $data['created_at'] ?? null,
            ]
        );

        if ($topic === 'customers/create' && $customer) {
            $this->rulesEngine->awardWelcomePoints($customer);
        }

        return $customer;
    }

    public function syncOrder(array $data, string $topic): void
    {
        $discounts = $data['discount_codes'] ?? [];
        if (is_array($discounts) && $discounts) {
            $codes = [];
            foreach ($discounts as $discount) {
                if (!empty($discount['code'])) {
                    $codes[] = strtoupper((string) $discount['code']);
                }
            }

            if ($codes) {
                $records = CustomerCoupon::query()
                    ->with('coupon')
                    ->whereIn(DB::raw('upper(code)'), $codes)
                    ->get();

                $codeQuery = CustomerCoupon::query()->whereKey($records->pluck('id')->all());

                if ($topic === 'orders/fulfilled') {
                    $codeQuery->update([
                        'status' => 'used',
                        'used_at' => now(),
                    ]);
                } elseif (in_array($topic, ['orders/paid', 'orders/create'], true)) {
                    foreach ($records as $record) {
                        $priceRuleId = (int) ($record->coupon?->shopify_price_rule_id ?? 0);
                        if ($priceRuleId > 0 && $record->code) {
                            try {
                                $this->shopifyDiscounts->disableDiscountCode($priceRuleId, $record->code);
                            } catch (\Throwable $exception) {
                                // Ignore disable failures; status update still applies.
                            }
                        }
                    }
                    $codeQuery->update([
                        'status' => 'in_progress',
                        'used_at' => null,
                    ]);
                }
            }
        }

        $orderId = data_get($data, 'id');
        $customerId = data_get($data, 'customer.id');
        if (!$orderId || !$customerId) {
            return;
        }

        $customer = Customer::query()->where('shopify_id', (string) $customerId)->first();
        if (!$customer) {
            return;
        }

        $rule = PointRule::query()->first();
        $amountPerPoint = max(1, (int) ($rule?->amount_per_point ?? 100));
        $eligibleAmount = (float) (data_get($data, 'current_total_price')
            ?? data_get($data, 'total_price')
            ?? 0);
        $earnedPoints = (int) round($eligibleAmount / $amountPerPoint);

        if ($earnedPoints <= 0) {
            return;
        }

        if (in_array($topic, ['orders/paid', 'orders/create'], true)) {
            $this->rulesEngine->applyOrderPending(
                $customer,
                (int) $orderId,
                $earnedPoints,
                $eligibleAmount,
                $amountPerPoint,
                $data
            );
        } elseif ($topic === 'orders/fulfilled') {
            $this->rulesEngine->approveOrderPoints($customer, (int) $orderId);
        } elseif (in_array($topic, ['orders/refunded', 'orders/cancelled'], true)) {
            $refunds = data_get($data, 'refunds', []);
            if (!is_array($refunds)) {
                $refunds = [];
            }
            $this->rulesEngine->reverseOrderPoints($customer, (int) $orderId, $refunds);
        }
    }
}
