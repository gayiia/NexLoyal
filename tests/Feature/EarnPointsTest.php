<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PointRule;
use App\Models\PointsTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EarnPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_paid_creates_pending_points_idempotent(): void
    {
        config(['services.shopify.webhook_secret' => 'secret']);

        $rule = PointRule::create([
            'welcome_points' => 0,
            'birthday_points' => 0,
            'profile_completion_points' => 0,
            'amount_per_point' => 100,
        ]);

        $customer = Customer::create([
            'shopify_id' => '1001',
            'email' => 'earn@example.com',
            'loyalty_points' => 0,
            'points_pending' => 0,
        ]);

        $payload = json_encode([
            'id' => 555,
            'customer' => ['id' => 1001],
            'current_total_price' => '200.00',
            'discount_codes' => [],
        ]);
        $hmac = base64_encode(hash_hmac('sha256', $payload, 'secret', true));

        $server = [
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/paid',
        ];

        $this->call('POST', '/webhooks/shopify/orders/paid', [], [], [], $server, $payload)
            ->assertOk();
        $this->call('POST', '/webhooks/shopify/orders/paid', [], [], [], $server, $payload)
            ->assertOk();

        $customer->refresh();
        $this->assertSame(2, $customer->points_pending);
        $this->assertSame(1, PointsTransaction::count());
        $this->assertSame('PENDING', PointsTransaction::first()->status);
    }

    public function test_order_fulfilled_approves_points(): void
    {
        config(['services.shopify.webhook_secret' => 'secret']);

        PointRule::create([
            'welcome_points' => 0,
            'birthday_points' => 0,
            'profile_completion_points' => 0,
            'amount_per_point' => 100,
        ]);

        $customer = Customer::create([
            'shopify_id' => '2001',
            'email' => 'approve@example.com',
            'loyalty_points' => 0,
            'points_pending' => 0,
        ]);

        $payload = json_encode([
            'id' => 777,
            'customer' => ['id' => 2001],
            'current_total_price' => '100.00',
            'discount_codes' => [],
        ]);
        $hmac = base64_encode(hash_hmac('sha256', $payload, 'secret', true));
        $serverPaid = [
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/paid',
        ];
        $serverFulfilled = [
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/fulfilled',
        ];

        $this->call('POST', '/webhooks/shopify/orders/paid', [], [], [], $serverPaid, $payload)
            ->assertOk();
        $this->call('POST', '/webhooks/shopify/orders/fulfilled', [], [], [], $serverFulfilled, $payload)
            ->assertOk();

        $customer->refresh();
        $this->assertSame(0, $customer->points_pending);
        $this->assertSame(1, $customer->loyalty_points);
        $this->assertSame('APPROVED', PointsTransaction::first()->status);
    }

    public function test_order_refunded_reverses_previously_approved_points_once(): void
    {
        config(['services.shopify.webhook_secret' => 'secret']);

        PointRule::create([
            'welcome_points' => 0,
            'birthday_points' => 0,
            'profile_completion_points' => 0,
            'amount_per_point' => 100,
        ]);

        $customer = Customer::create([
            'shopify_id' => '2002',
            'email' => 'refund@example.com',
            'loyalty_points' => 0,
            'points_pending' => 0,
        ]);

        $payload = json_encode([
            'id' => 888,
            'customer' => ['id' => 2002],
            'current_total_price' => '100.00',
            'discount_codes' => [],
            'refunds' => [
                ['id' => 9001],
            ],
        ]);
        $hmac = base64_encode(hash_hmac('sha256', $payload, 'secret', true));
        $serverPaid = [
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/paid',
        ];
        $serverFulfilled = [
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/fulfilled',
        ];
        $serverRefunded = [
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/refunded',
        ];

        $this->call('POST', '/webhooks/shopify/orders/paid', [], [], [], $serverPaid, $payload)
            ->assertOk();
        $this->call('POST', '/webhooks/shopify/orders/fulfilled', [], [], [], $serverFulfilled, $payload)
            ->assertOk();
        $this->call('POST', '/webhooks/shopify/orders/refunded', [], [], [], $serverRefunded, $payload)
            ->assertOk();
        $this->call('POST', '/webhooks/shopify/orders/refunded', [], [], [], $serverRefunded, $payload)
            ->assertOk();

        $customer->refresh();

        $this->assertSame(0, $customer->points_pending);
        $this->assertSame(0, $customer->loyalty_points);
        $this->assertSame(2, PointsTransaction::count());
        $this->assertDatabaseHas('points_transactions', [
            'customer_id' => $customer->id,
            'order_id' => 888,
            'status' => 'APPROVED',
            'points' => 1,
        ]);
        $this->assertDatabaseHas('points_transactions', [
            'customer_id' => $customer->id,
            'order_id' => 888,
            'status' => 'REVERSED',
            'points' => -1,
            'event_key' => 'order_refund_adjust:888:9001',
        ]);
    }

    public function test_social_award_only_once(): void
    {
        $rule = PointRule::create([
            'welcome_points' => 0,
            'birthday_points' => 0,
            'profile_completion_points' => 0,
            'amount_per_point' => 100,
            'social_instagram_url' => 'https://instagram.com/brand',
            'social_instagram_points' => 20,
        ]);

        $customer = Customer::create([
            'shopify_id' => '3001',
            'email' => 'social@example.com',
            'loyalty_points' => 0,
            'points_pending' => 0,
        ]);

        $token = Crypt::encryptString(json_encode([
            'shopify_id' => $customer->shopify_id,
            'email' => $customer->email,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]));

        $response = $this->postJson('/api/widget/earn/social?token='.$token, [
            'platform' => 'instagram',
        ]);
        $response->assertOk();

        $second = $this->postJson('/api/widget/earn/social?token='.$token, [
            'platform' => 'instagram',
        ]);
        $second->assertOk();

        $customer->refresh();
        $this->assertSame(20, $customer->loyalty_points);
        $this->assertSame(1, PointsTransaction::count());
    }
}
