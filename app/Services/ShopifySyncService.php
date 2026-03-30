<?php

// This service syncs Shopify webhook payloads into local customers, orders, and loyalty state.
namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointRule;
use Illuminate\Support\Facades\DB;

// This class updates local records based on Shopify events and triggers loyalty rules.
class ShopifySyncService
{
    // This injects dependencies for discount handling and loyalty rule evaluation.
    public function __construct(
        private ShopifyDiscountService $shopifyDiscounts,
        private LoyaltyRulesEngine $rulesEngine
    ) {
    }

    // This applies a customer create/update/delete webhook and optionally awards welcome points.
    public function syncCustomer(array $data, string $topic): ?Customer
    {
        // This deletes the local record when Shopify sends a delete event.
        if ($topic === 'customers/delete') {
            Customer::where('shopify_id', (string) ($data['id'] ?? ''))->delete();
            return null;
        }

        // This upserts customer data using Shopify's payload fields.
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

        // This awards welcome points only when a customer is newly created.
        if ($topic === 'customers/create' && $customer) {
            $this->rulesEngine->awardWelcomePoints($customer);
        }

        return $customer;
    }

    // This applies order-related webhooks to coupon status and loyalty points.
    public function syncOrder(array $data, string $topic): void
    {
        // This extracts discount codes from the order payload and normalizes them to uppercase.
        $discounts = $data['discount_codes'] ?? [];
        if (is_array($discounts) && $discounts) {
            $codes = [];
            foreach ($discounts as $discount) {
                if (!empty($discount['code'])) {
                    $codes[] = strtoupper((string) $discount['code']);
                }
            }

            if ($codes) {
                // This loads matching customer coupons to update status or disable codes in Shopify.
                $records = CustomerCoupon::query()
                    ->with('coupon')
                    ->whereIn(DB::raw('upper(code)'), $codes)
                    ->get();

                $codeQuery = CustomerCoupon::query()->whereKey($records->pluck('id')->all());

                if ($topic === 'orders/fulfilled') {
                    // This marks coupons as used once the order is fulfilled.
                    $codeQuery->update([
                        'status' => 'used',
                        'used_at' => now(),
                    ]);
                } elseif (in_array($topic, ['orders/paid', 'orders/create'], true)) {
                    // This disables Shopify discount codes for partially used coupons.
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
                    // This marks coupons as in progress while the order is being processed.
                    $codeQuery->update([
                        'status' => 'in_progress',
                        'used_at' => null,
                    ]);
                }
            }
        }

        // This extracts order and customer IDs, stopping if either is missing.
        $orderId = data_get($data, 'id');
        $customerId = data_get($data, 'customer.id');
        if (!$orderId || !$customerId) {
            return;
        }

        // This finds the local customer tied to the Shopify customer ID.
        $customer = Customer::query()->where('shopify_id', (string) $customerId)->first();
        if (!$customer) {
            return;
        }

        // This determines how many points the order is worth based on the configured rule.
        $rule = PointRule::query()->first();
        $amountPerPoint = max(1, (int) ($rule?->amount_per_point ?? 100));
        $eligibleAmount = (float) (data_get($data, 'current_total_price')
            ?? data_get($data, 'total_price')
            ?? 0);
        $earnedPoints = (int) round($eligibleAmount / $amountPerPoint);

        // This skips downstream logic when the order earns no points.
        if ($earnedPoints <= 0) {
            return;
        }

        if (in_array($topic, ['orders/paid', 'orders/create'], true)) {
            // This creates pending points so they can be approved on fulfillment.
            $this->rulesEngine->applyOrderPending(
                $customer,
                (int) $orderId,
                $earnedPoints,
                $eligibleAmount,
                $amountPerPoint,
                $data
            );
        } elseif ($topic === 'orders/fulfilled') {
            // This approves pending points once the order is fulfilled.
            $this->rulesEngine->approveOrderPoints($customer, (int) $orderId);
        } elseif (in_array($topic, ['refunds/create', 'orders/cancelled'], true)) {
            // This reverses points for refunds or cancellations using refund line data.
            $refunds = data_get($data, 'refunds', []);
            if (!is_array($refunds)) {
                $refunds = [];
            }
            $this->rulesEngine->reverseOrderPoints($customer, (int) $orderId, $refunds);
        }
    }
}
