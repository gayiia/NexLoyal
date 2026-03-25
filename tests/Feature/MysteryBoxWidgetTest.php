<?php

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\MysteryBox;
use App\Models\MysteryBoxItem;
use App\Models\Tier;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

function mysteryBoxToken(Customer $customer): string
{
    return Crypt::encryptString(json_encode([
        'shopify_id' => $customer->shopify_id,
        'email' => $customer->email,
        'issued_at' => now()->timestamp,
        'expires_at' => now()->addMinutes(30)->timestamp,
    ]));
}

test('mystery box active endpoint only returns boxes for eligible tiers', function () {
    $bronze = Tier::create([
        'title' => 'Bronze',
        'color' => '#cd7f32',
        'min_points' => 0,
        'max_points' => 999,
        'single_point_value' => 1,
        'status' => 'active',
    ]);

    $gold = Tier::create([
        'title' => 'Gold',
        'color' => '#facc15',
        'min_points' => 1000,
        'max_points' => 9999,
        'single_point_value' => 1.5,
        'status' => 'active',
    ]);

    $coupon = Coupon::create([
        'title' => 'Gold Reward',
        'type' => 'amount-order',
        'value_type' => 'fixed',
        'value' => 10,
        'points_value' => 0,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'active',
        'is_mystery_box_coupon' => true,
        'shopify_price_rule_id' => '321',
    ]);

    $box = MysteryBox::create([
        'name' => 'Gold Box',
        'tiers' => [$gold->id],
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(3),
        'claim_rule' => 'ONCE_PER_DAY',
    ]);

    MysteryBoxItem::create([
        'mystery_box_id' => $box->id,
        'coupon_id' => $coupon->id,
        'weight' => 1,
    ]);

    $goldCustomer = Customer::create([
        'shopify_id' => 'gold-1',
        'email' => 'gold@example.com',
        'tier_id' => $gold->id,
    ]);

    $bronzeCustomer = Customer::create([
        'shopify_id' => 'bronze-1',
        'email' => 'bronze@example.com',
        'tier_id' => $bronze->id,
    ]);

    $goldResponse = $this->getJson('/api/widget/mystery-box/active?token='.mysteryBoxToken($goldCustomer));
    $goldResponse->assertOk();
    $goldResponse->assertJsonPath('box.id', $box->id);
    $goldResponse->assertJsonPath('box.can_claim', true);

    $bronzeResponse = $this->getJson('/api/widget/mystery-box/active?token='.mysteryBoxToken($bronzeCustomer));
    $bronzeResponse->assertOk();
    $bronzeResponse->assertJsonPath('box', null);
});

test('mystery box claim creates a reward and enforces claim limits', function () {
    config([
        'services.shopify.shop_domain' => 'example.myshopify.com',
        'services.shopify.admin_token' => 'token-123',
        'services.shopify.api_version' => '2026-01',
    ]);

    Http::fake([
        'https://example.myshopify.com/admin/api/2026-01/price_rules/321/discount_codes.json' => Http::response([
            'discount_code' => [
                'id' => 777,
                'code' => 'MYSTERY-001',
            ],
        ], 201),
    ]);

    $gold = Tier::create([
        'title' => 'Gold',
        'color' => '#facc15',
        'min_points' => 1000,
        'max_points' => 9999,
        'single_point_value' => 1.5,
        'status' => 'active',
    ]);

    $coupon = Coupon::create([
        'title' => 'Mystery Reward',
        'type' => 'amount-order',
        'value_type' => 'fixed',
        'value' => 10,
        'points_value' => 0,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'active',
        'is_mystery_box_coupon' => true,
        'shopify_price_rule_id' => '321',
    ]);

    $box = MysteryBox::create([
        'name' => 'Gold Box',
        'tiers' => [$gold->id],
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(3),
        'claim_rule' => 'ONCE_PER_DAY',
    ]);

    MysteryBoxItem::create([
        'mystery_box_id' => $box->id,
        'coupon_id' => $coupon->id,
        'weight' => 1,
    ]);

    $customer = Customer::create([
        'shopify_id' => 'gold-claim-1',
        'email' => 'claim@example.com',
        'tier_id' => $gold->id,
    ]);

    $token = mysteryBoxToken($customer);

    $first = $this->postJson('/api/widget/mystery-box/'.$box->id.'/claim?token='.$token);
    $first->assertOk();
    $first->assertJsonPath('won.coupon_id', $coupon->id);

    expect(CustomerCoupon::query()->where('mystery_box_id', $box->id)->count())->toBe(1);

    $second = $this->postJson('/api/widget/mystery-box/'.$box->id.'/claim?token='.$token);
    $second->assertStatus(409);
    $second->assertJsonFragment(['message' => 'Already claimed.']);
});
