<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PointRule;
use App\Models\PointsTransaction;
use App\Models\ShopifyWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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
        $this->assertDatabaseHas('shopify_webhook_logs', [
            'topic' => 'customers/create',
            'delivery_state' => 'processed',
            'response_status' => 200,
        ]);

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
        $this->assertSame(2, ShopifyWebhookLog::query()->count());
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
        $this->assertDatabaseHas('shopify_webhook_logs', [
            'topic' => 'customers/delete',
            'delivery_state' => 'processed',
            'response_status' => 200,
        ]);
    }

    public function test_invalid_customer_webhook_signature_is_logged(): void
    {
        config(['services.shopify.webhook_secret' => 'secret']);

        $payload = json_encode([
            'id' => 8001,
        ]);

        $loggedMessage = null;
        $loggedContext = null;

        Log::shouldReceive('warning')->once()->withAnyArgs()->andReturnUsing(
            function (string $message, array $context) use (&$loggedMessage, &$loggedContext): void {
                $loggedMessage = $message;
                $loggedContext = $context;
            }
        );
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $this->call(
            'POST',
            '/webhooks/shopify/customers',
            [],
            [],
            [],
            [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => 'bogus',
                'HTTP_X_SHOPIFY_TOPIC' => 'customers/create',
                'HTTP_X_SHOPIFY_WEBHOOK_ID' => 'webhook-123',
                'HTTP_X_SHOPIFY_EVENT_ID' => 'event-456',
                'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'example.myshopify.com',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_USER_AGENT' => 'Shopify-Captain-Hook',
            ],
            $payload
        )->assertUnauthorized();

        $this->assertDatabaseHas('shopify_webhook_logs', [
            'topic' => 'customers/create',
            'delivery_state' => 'invalid_signature',
            'response_status' => 401,
        ]);

        $log = ShopifyWebhookLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->request_headers);
        $this->assertSame('Shopify-Captain-Hook', $log->request_headers['user-agent'] ?? null);
        $this->assertSame('application/json', $log->request_headers['content-type'] ?? null);
        $this->assertSame('example.myshopify.com', $log->request_headers['x-shopify-shop-domain'] ?? null);
        $this->assertSame('webhook-123', $log->request_headers['x-shopify-webhook-id'] ?? null);
        $this->assertSame('event-456', $log->request_headers['x-shopify-event-id'] ?? null);
        $this->assertSame('customers/create', $log->request_headers['x-shopify-topic'] ?? null);
        $this->assertSame('bogus...', $log->request_headers['x-shopify-hmac-sha256'] ?? null);
        $this->assertSame($payload, $log->payload);
        $this->assertNull($log->error_message);

        $diagnostics = $log->request_headers['diagnostics'] ?? null;

        $this->assertIsArray($diagnostics);
        $this->assertSame(5, $diagnostics['provided_hmac_length'] ?? null);
        $this->assertSame('bogus', $diagnostics['provided_hmac_prefix'] ?? null);
        $this->assertFalse($diagnostics['provided_hmac_is_base64'] ?? true);
        $this->assertSame(44, $diagnostics['computed_hmac_length'] ?? null);
        $this->assertSame(6, $diagnostics['secret_length'] ?? null);
        $this->assertSame(strlen($payload), $diagnostics['payload_length'] ?? null);
        $this->assertSame(hash('sha256', $payload), $diagnostics['payload_sha256'] ?? null);
        $this->assertSame(12, strlen($diagnostics['computed_hmac_prefix'] ?? ''));
        $this->assertSame(12, strlen($diagnostics['secret_fingerprint'] ?? ''));
        $this->assertSame('Shopify webhook rejected', $loggedMessage);
        $this->assertIsArray($loggedContext);
        $this->assertSame('customers/create', $loggedContext['topic'] ?? null);
        $this->assertSame('example.myshopify.com', $loggedContext['shop_domain'] ?? null);
        $this->assertSame('webhook-123', $loggedContext['shopify_webhook_id'] ?? null);
        $this->assertSame('event-456', $loggedContext['shopify_event_id'] ?? null);
        $this->assertSame('application/json', $loggedContext['content_type'] ?? null);
        $this->assertSame('Shopify-Captain-Hook', $loggedContext['user_agent'] ?? null);
        $this->assertSame(strlen($payload), $loggedContext['payload_length'] ?? null);
        $this->assertSame(hash('sha256', $payload), $loggedContext['payload_sha256'] ?? null);
        $this->assertSame(5, $loggedContext['provided_hmac_length'] ?? null);
        $this->assertSame('bogus', $loggedContext['provided_hmac_prefix'] ?? null);
        $this->assertFalse($loggedContext['provided_hmac_is_base64'] ?? true);
        $this->assertSame(44, $loggedContext['computed_hmac_length'] ?? null);
        $this->assertSame(6, $loggedContext['secret_length'] ?? null);
        $this->assertSame('invalid_signature', $loggedContext['delivery_state'] ?? null);
        $this->assertSame(401, $loggedContext['response_status'] ?? null);
        $this->assertFalse($loggedContext['hmac_valid'] ?? true);
        $this->assertSame(12, strlen($loggedContext['computed_hmac_prefix'] ?? ''));
        $this->assertSame(12, strlen($loggedContext['secret_fingerprint'] ?? ''));

    }
}
