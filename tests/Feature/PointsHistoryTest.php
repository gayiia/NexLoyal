<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PointsTransaction;
use App\Support\PointsHistoryFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PointsHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_earned_filter_includes_pending_orders(): void
    {
        $customer = Customer::create([
            'shopify_id' => '9001',
            'email' => 'history@example.com',
        ]);

        PointsTransaction::create([
            'customer_id' => $customer->id,
            'points' => 5,
            'status' => 'PENDING',
            'source' => 'ORDER',
            'source_type' => 'ORDER',
            'type' => 'EARN',
            'event_key' => 'order_earn_pending:1',
        ]);

        $token = Crypt::encryptString(json_encode([
            'shopify_id' => $customer->shopify_id,
            'email' => $customer->email,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]));

        $response = $this->get('/api/widget/points/history?filter=earned&token='.$token);
        $response->assertOk();
        $response->assertJsonFragment(['status' => 'PENDING']);
    }

    public function test_redeemed_filter_returns_spend(): void
    {
        $customer = Customer::create([
            'shopify_id' => '9002',
            'email' => 'redeem@example.com',
        ]);

        PointsTransaction::create([
            'customer_id' => $customer->id,
            'points' => 30,
            'status' => 'APPROVED',
            'source' => 'COUPON',
            'source_type' => 'COUPON',
            'type' => 'SPEND',
            'event_key' => 'coupon_redeem:1',
        ]);

        $token = Crypt::encryptString(json_encode([
            'shopify_id' => $customer->shopify_id,
            'email' => $customer->email,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]));

        $response = $this->get('/api/widget/points/history?filter=redeemed&token='.$token);
        $response->assertOk();
        $response->assertJsonFragment(['direction' => 'REDEEM']);
    }

    public function test_title_formatting_for_order(): void
    {
        $customer = Customer::create([
            'shopify_id' => '9003',
            'email' => 'order@example.com',
        ]);

        $transaction = PointsTransaction::create([
            'customer_id' => $customer->id,
            'points' => 10,
            'status' => 'APPROVED',
            'source' => 'ORDER',
            'source_type' => 'ORDER',
            'type' => 'EARN',
            'event_key' => 'order_earn_pending:99',
            'reference_id' => '99',
            'meta' => [
                'order_number' => '5063',
            ],
        ]);

        $formatted = PointsHistoryFormatter::format($transaction);
        $this->assertSame('Order ID #5063', $formatted['title']);
    }
}
