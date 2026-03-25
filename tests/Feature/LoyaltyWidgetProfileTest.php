<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PointRule;
use App\Models\PointsTransaction;
use App\Services\ShopifyCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LoyaltyWidgetProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_token_data_and_profile_update_flow(): void
    {
        config(['services.shopify.shop_domain' => 'example.myshopify.com']);

        PointRule::create([
            'welcome_points' => 15,
            'birthday_points' => 0,
            'profile_completion_points' => 20,
            'amount_per_point' => 100,
        ]);

        $shopify = Mockery::mock(ShopifyCustomerService::class);
        $shopify->shouldReceive('getCustomer')
            ->once()
            ->with('7001')
            ->andReturn([
                'id' => '7001',
                'email' => 'widget@example.com',
                'first_name' => null,
                'last_name' => null,
                'phone' => null,
                'state' => 'enabled',
                'orders_count' => 0,
                'total_spent' => '0.00',
                'currency' => 'USD',
                'created_at' => now()->toIso8601String(),
            ]);
        $shopify->shouldReceive('updateCustomer')
            ->once()
            ->with('7001', [
                'first_name' => 'Nina',
                'last_name' => 'Ross',
                'email' => 'widget@example.com',
                'phone' => '+94771234567',
            ])
            ->andReturn([
                'id' => '7001',
                'email' => 'widget@example.com',
            ]);

        $this->app->instance(ShopifyCustomerService::class, $shopify);

        $tokenResponse = $this->getJson('/loyalty/token?customer_id=7001&email=widget@example.com&shop_domain=example.myshopify.com');
        $tokenResponse->assertOk();

        $token = $tokenResponse->json('token');
        $this->assertNotEmpty($token);

        $customer = Customer::query()->where('shopify_id', '7001')->first();
        $this->assertNotNull($customer);
        $this->assertSame(15, $customer->loyalty_points);
        $this->assertSame(1, PointsTransaction::where('event_key', 'welcome_bonus')->count());

        $dataResponse = $this->getJson('/loyalty/data?token='.$token);
        $dataResponse->assertOk();
        $dataResponse->assertJsonFragment([
            'email' => 'widget@example.com',
            'points' => 15,
            'points_pending' => 0,
        ]);

        $profileResponse = $this->postJson('/loyalty/profile?token='.$token, [
            'first_name' => 'Nina',
            'last_name' => 'Ross',
            'email' => 'widget@example.com',
            'phone' => '+94771234567',
            'birthday' => '1995-08-15',
        ]);

        $profileResponse->assertOk();
        $profileResponse->assertJson([
            'message' => 'Profile updated.',
            'awarded_profile_points' => true,
            'awarded_birthday_points' => false,
        ]);

        $customer->refresh();

        $this->assertSame('Nina', $customer->first_name);
        $this->assertSame('Ross', $customer->last_name);
        $this->assertSame('+94771234567', $customer->phone);
        $this->assertSame(35, $customer->loyalty_points);
        $this->assertNotNull($customer->profile_completed_at);
        $this->assertSame(1, PointsTransaction::where('event_key', 'profile_completion')->count());
    }
}
