<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PointRule;
use App\Models\PointsTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifyCustomerWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function webhookHeaders(string $payload, string $topic): array
    {
        $hmac = base64_encode(hash_hmac('sha256', $payload, 'secret', true));

        return [
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_TOPIC' => $topic,
        ];
    }

    public function test_customer_create_and_update_webhooks_sync_customer_and_award_welcome_points_once(): void
    {
        config(['services.shopify.webhook_secret' => 'secret']);

        PointRule::create([
            'welcome_points' => 25,
            'birthday_points' => 0,
            'profile_completion_points' => 0,
            'amount_per_point' => 100,
        ]);

        $createPayload = json_encode([
            'id' => 4001,
            'first_name' => 'Ava',
            'last_name' => 'Stone',
            'email' => 'ava@example.com',
            'phone' => '+94112223344',
            'state' => 'enabled',
            'orders_count' => 1,
            'total_spent' => '149.90',
            'currency' => 'USD',
            'created_at' => now()->toIso8601String(),
        ]);

        $this->call(
            'POST',
            '/webhooks/shopify/customers',
            [],
            [],
            [],
            $this->webhookHeaders($createPayload, 'customers/create'),
            $createPayload
        )->assertOk();

        $customer = Customer::query()->where('shopify_id', '4001')->first();

        $this->assertNotNull($customer);
        $this->assertSame('Ava', $customer->first_name);
        $this->assertSame(25, $customer->loyalty_points);
        $this->assertSame(1, PointsTransaction::where('event_key', 'welcome_bonus')->count());

        $updatePayload = json_encode([
            'id' => 4001,
            'first_name' => 'Ava',
            'last_name' => 'Stone',
            'email' => 'ava.updated@example.com',
            'phone' => '+94770001122',
            'state' => 'disabled',
            'orders_count' => 3,
            'total_spent' => '249.90',
            'currency' => 'USD',
            'created_at' => now()->subDay()->toIso8601String(),
        ]);

        $this->call(
            'POST',
            '/webhooks/shopify/customers',
            [],
            [],
            [],
            $this->webhookHeaders($updatePayload, 'customers/update'),
            $updatePayload
        )->assertOk();

        $customer->refresh();

        $this->assertSame('ava.updated@example.com', $customer->email);
        $this->assertSame('+94770001122', $customer->phone);
        $this->assertSame(3, $customer->orders_count);
        $this->assertSame(25, $customer->loyalty_points);
        $this->assertSame(1, PointsTransaction::where('event_key', 'welcome_bonus')->count());
    }

    public function test_customer_delete_webhook_removes_the_customer(): void
    {
        config(['services.shopify.webhook_secret' => 'secret']);

        $customer = Customer::create([
            'shopify_id' => '4999',
            'email' => 'delete-me@example.com',
        ]);

        $payload = json_encode([
            'id' => 4999,
        ]);

        $this->call(
            'POST',
            '/webhooks/shopify/customers',
            [],
            [],
            [],
            $this->webhookHeaders($payload, 'customers/delete'),
            $payload
        )->assertOk();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
