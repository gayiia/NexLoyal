<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCoupon;
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

        Customer::updateOrCreate(
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
        if (!in_array($topic, ['orders/paid', 'orders/create', 'orders/fulfilled'], true)) {
            return response('Ignored', 202);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return response('Invalid payload', 400);
        }

        $discounts = $data['discount_codes'] ?? [];
        if (!is_array($discounts) || !$discounts) {
            return response('OK', 200);
        }

        $codes = [];
        foreach ($discounts as $discount) {
            if (!empty($discount['code'])) {
                $codes[] = strtoupper((string) $discount['code']);
            }
        }

        if (!$codes) {
            return response('OK', 200);
        }

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
        } else {
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

        return response('OK', 200);
    }
}
