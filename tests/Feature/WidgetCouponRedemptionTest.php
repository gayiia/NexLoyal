<?php

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointsTransaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

function widgetToken(Customer $customer): string
{
    return Crypt::encryptString(json_encode([
        'shopify_id' => $customer->shopify_id,
        'email' => $customer->email,
        'issued_at' => now()->timestamp,
        'expires_at' => now()->addMinutes(30)->timestamp,
    ]));
}

test('widget coupon redemption deducts points and creates redemption records', function () {
    config([
        'services.shopify.shop_domain' => 'example.myshopify.com',
        'services.shopify.admin_token' => 'token-123',
        'services.shopify.api_version' => '2026-01',
    ]);

    Http::fake([
        'https://example.myshopify.com/admin/api/2026-01/price_rules/123/discount_codes.json' => Http::response([
            'discount_code' => [
                'id' => 9001,
                'code' => 'LYL-TEST-001',
            ],
        ], 201),
    ]);

    $customer = Customer::create([
        'shopify_id' => 'redeem-1',
        'email' => 'redeem@example.com',
        'loyalty_points' => 500,
    ]);

    $coupon = Coupon::create([
        'title' => '10% Off',
        'type' => 'amount-order',
        'value_type' => 'percentage',
        'value' => 10,
        'points_value' => 150,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'active',
        'shopify_price_rule_id' => '123',
    ]);

    $response = $this->postJson('/loyalty/coupons/'.$coupon->id.'/redeem?token='.widgetToken($customer));

    $response->assertOk();
    $response->assertJsonFragment(['message' => 'Coupon redeemed.']);

    $customer->refresh();
    expect($customer->loyalty_points)->toBe(350);
    expect(CustomerCoupon::count())->toBe(1);
    expect(PointsTransaction::query()->where('type', 'SPEND')->count())->toBe(1);
});

test('widget coupon redemption rejects expired coupons', function () {
    $customer = Customer::create([
        'shopify_id' => 'redeem-2',
        'email' => 'expired@example.com',
        'loyalty_points' => 500,
    ]);

    $coupon = Coupon::create([
        'title' => 'Expired Deal',
        'type' => 'amount-order',
        'value_type' => 'percentage',
        'value' => 10,
        'points_value' => 100,
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'status' => 'active',
        'shopify_price_rule_id' => '123',
    ]);

    $response = $this->postJson('/loyalty/coupons/'.$coupon->id.'/redeem?token='.widgetToken($customer));

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Coupon has expired.']);
    expect(CustomerCoupon::count())->toBe(0);
});
