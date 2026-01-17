<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointsTransaction;
use App\Models\PointRule;
use App\Services\ShopifyDiscountService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ShopifyWebhookController extends Controller
{
    public function handleCustomers(Request $request): Response
    {
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) {
            return response('Webhook secret not set', 500);
        }

        $payload = $request->getContent();
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (!$hmac || !hash_equals($computed, $hmac)) {
            return response('Invalid signature', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        if (!in_array($topic, ['customers/create', 'customers/update', 'customers/delete'], true)) {
            return response('Ignored', 202);
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['id'])) {
            return response('Invalid payload', 400);
        }

        if ($topic === 'customers/delete') {
            Customer::where('shopify_id', (string) $data['id'])->delete();
            return response('OK', 200);
        }

        $customer = Customer::updateOrCreate(
            ['shopify_id' => (string) $data['id']],
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

        if ($topic === 'customers/create') {
            $this->awardWelcomePoints($customer);
        }

        return response('OK', 200);
    }

    public function handleOrders(Request $request, ShopifyDiscountService $shopifyDiscounts): Response
    {
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) {
            return response('Webhook secret not set', 500);
        }

        $payload = $request->getContent();
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (!$hmac || !hash_equals($computed, $hmac)) {
            return response('Invalid signature', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        if (!in_array($topic, ['orders/paid', 'orders/create', 'orders/fulfilled', 'orders/refunded', 'orders/cancelled'], true)) {
            return response('Ignored', 202);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return response('Invalid payload', 400);
        }

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
                                $shopifyDiscounts->disableDiscountCode($priceRuleId, $record->code);
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
        if ($orderId && $customerId) {
            $customer = Customer::query()->where('shopify_id', (string) $customerId)->first();
            if ($customer) {
                $rule = PointRule::query()->first();
                $amountPerPoint = max(1, (int) ($rule?->amount_per_point ?? 100));
                $eligibleAmount = (float) (data_get($data, 'current_total_price')
                    ?? data_get($data, 'total_price')
                    ?? 0);
                $earnedPoints = (int) round($eligibleAmount / $amountPerPoint);

                if ($earnedPoints > 0) {
                    if (in_array($topic, ['orders/paid', 'orders/create'], true)) {
                        $eventKey = "order_earn_pending:{$orderId}";
                        DB::transaction(function () use ($customer, $eventKey, $orderId, $earnedPoints, $eligibleAmount, $amountPerPoint, $data): void {
                            $lockedCustomer = Customer::query()
                                ->whereKey($customer->id)
                                ->lockForUpdate()
                                ->first();

                            if (!$lockedCustomer) {
                                return;
                            }

                            $exists = PointsTransaction::query()
                                ->where('customer_id', $lockedCustomer->id)
                                ->where('event_key', $eventKey)
                                ->exists();

                            if ($exists) {
                                return;
                            }

                            PointsTransaction::create([
                                'customer_id' => $lockedCustomer->id,
                                'points' => $earnedPoints,
                                'status' => 'PENDING',
                                'source' => 'ORDER',
                                'source_type' => 'ORDER',
                                'type' => 'EARN',
                                'order_id' => (int) $orderId,
                                'event_key' => $eventKey,
                                'reason' => 'Order pending points',
                                'reference_type' => 'Order',
                                'reference_id' => (string) $orderId,
                                'meta' => [
                                    'eligible_amount' => $eligibleAmount,
                                    'amount_per_point' => $amountPerPoint,
                                    'order_number' => data_get($data, 'order_number') ?? data_get($data, 'name'),
                                ],
                            ]);

                            $lockedCustomer->points_pending = (int) ($lockedCustomer->points_pending ?? 0) + $earnedPoints;
                            $lockedCustomer->save();
                        });
                    } elseif ($topic === 'orders/fulfilled') {
                        DB::transaction(function () use ($customer, $orderId): void {
                            $lockedCustomer = Customer::query()
                                ->whereKey($customer->id)
                                ->lockForUpdate()
                                ->first();

                            if (!$lockedCustomer) {
                                return;
                            }

                            $transaction = PointsTransaction::query()
                                ->where('customer_id', $lockedCustomer->id)
                                ->where('order_id', (int) $orderId)
                                ->where('status', 'PENDING')
                                ->first();

                            if (!$transaction) {
                                return;
                            }

                            $points = (int) $transaction->points;
                            $transaction->status = 'APPROVED';
                            $transaction->save();

                            $lockedCustomer->points_pending = max(0, (int) ($lockedCustomer->points_pending ?? 0) - $points);
                            $lockedCustomer->loyalty_points = (int) ($lockedCustomer->loyalty_points ?? 0) + $points;
                            $lockedCustomer->save();
                        });
                    } elseif (in_array($topic, ['orders/refunded', 'orders/cancelled'], true)) {
                        $refunds = data_get($data, 'refunds', []);
                        if (!is_array($refunds)) {
                            $refunds = [];
                        }
                        if (!$refunds) {
                            $refunds = [['id' => 'refund']];
                        }

                        foreach ($refunds as $refund) {
                            $refundId = (string) (data_get($refund, 'id') ?? 'refund');
                            $eventKey = "order_refund_adjust:{$orderId}:{$refundId}";

                            DB::transaction(function () use ($customer, $orderId, $eventKey): void {
                                $lockedCustomer = Customer::query()
                                    ->whereKey($customer->id)
                                    ->lockForUpdate()
                                    ->first();

                                if (!$lockedCustomer) {
                                    return;
                                }

                                $exists = PointsTransaction::query()
                                    ->where('customer_id', $lockedCustomer->id)
                                    ->where('event_key', $eventKey)
                                    ->exists();

                                if ($exists) {
                                    return;
                                }

                                $transaction = PointsTransaction::query()
                                    ->where('customer_id', $lockedCustomer->id)
                                    ->where('order_id', (int) $orderId)
                                    ->whereIn('status', ['PENDING', 'APPROVED'])
                                    ->first();

                                if (!$transaction) {
                                    return;
                                }

                                $points = (int) $transaction->points;
                                $pending = (int) ($lockedCustomer->points_pending ?? 0);
                                $available = (int) ($lockedCustomer->loyalty_points ?? 0);
                                $reversePoints = min($points, $transaction->status === 'PENDING' ? $pending : $available);

                                if ($reversePoints <= 0) {
                                    return;
                                }

                                PointsTransaction::create([
                                    'customer_id' => $lockedCustomer->id,
                                    'points' => -$reversePoints,
                                    'status' => 'REVERSED',
                                    'source' => 'ORDER',
                                    'source_type' => 'ORDER',
                                    'type' => 'EARN',
                                    'order_id' => (int) $orderId,
                                    'event_key' => $eventKey,
                                    'reason' => 'Order refund adjustment',
                                    'reference_type' => 'Order',
                                    'reference_id' => (string) $orderId,
                                ]);

                                if ($transaction->status === 'PENDING') {
                                    $lockedCustomer->points_pending = max(0, $pending - $reversePoints);
                                } else {
                                    $lockedCustomer->loyalty_points = max(0, $available - $reversePoints);
                                }

                                $lockedCustomer->save();
                            });
                        }
                    }
                }
            }
        }

        return response('OK', 200);
    }

    private function awardWelcomePoints(Customer $customer): void
    {
        $rule = PointRule::query()->first();
        $points = (int) ($rule?->welcome_points ?? 0);
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $points): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            $exists = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('event_key', 'welcome_bonus')
                ->exists();

            if ($exists) {
                return;
            }

            PointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'points' => $points,
                'status' => 'APPROVED',
                'source' => 'RULE',
                'source_type' => 'REGISTER',
                'type' => 'EARN',
                'event_key' => 'welcome_bonus',
                'reason' => 'Welcome bonus',
            ]);

            $lockedCustomer->loyalty_points = (int) ($lockedCustomer->loyalty_points ?? 0) + $points;
            $lockedCustomer->save();
        });
    }
}
