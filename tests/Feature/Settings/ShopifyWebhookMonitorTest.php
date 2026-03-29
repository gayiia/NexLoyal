<?php

namespace Tests\Feature\Settings;

use App\Models\ShopifyWebhookLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifyWebhookMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopify_webhook_monitor_page_shows_registration_and_recent_delivery_status(): void
    {
        config([
            'app.url' => 'https://nexloyal.store',
            'services.shopify.shop_domain' => 'example.myshopify.com',
            'services.shopify.admin_token' => 'shopify-token',
            'services.shopify.webhook_address' => 'https://nexloyal.store/webhooks/shopify',
        ]);

        $processedLog = ShopifyWebhookLog::query()->create([
            'webhook_key' => 'customers',
            'topic' => 'customers/create',
            'request_path' => 'webhooks/shopify/customers',
            'request_url' => 'https://nexloyal.store/webhooks/shopify/customers',
            'delivery_state' => 'processed',
            'response_status' => 200,
            'hmac_valid' => true,
            'shop_domain' => 'example.myshopify.com',
            'request_headers' => ['x-shopify-topic' => 'customers/create'],
            'payload' => json_encode(['id' => 1]),
            'processed_at' => now(),
        ]);

        ShopifyWebhookLog::query()->create([
            'webhook_key' => 'orders/paid',
            'topic' => 'orders/paid',
            'request_path' => 'webhooks/shopify/orders/paid',
            'request_url' => 'https://nexloyal.store/webhooks/shopify/orders/paid',
            'delivery_state' => 'invalid_payload',
            'response_status' => 400,
            'hmac_valid' => true,
            'shop_domain' => 'example.myshopify.com',
            'request_headers' => ['x-shopify-topic' => 'orders/paid'],
            'payload' => '{}',
            'processed_at' => now(),
        ]);

        $this->actingAs(User::factory()->create());

        $response = $this->get(route('shopify-webhooks'));

        $response->assertOk();
        $response->assertSee('Shopify Webhooks');
        $response->assertSee('Webhook Monitor');
        $response->assertSee('Customer creation');
        $response->assertSee('Customer update');
        $response->assertSee('Order creation');
        $response->assertSee('Order payment');
        $response->assertSee('Refund create');
        $response->assertSee('Order cancellation');
        $response->assertSee('Order fulfilment');
        $response->assertSee('customers/create');
        $response->assertSee('orders/paid');
        $response->assertSee('Connected');
        $response->assertSee('Issue');
        $response->assertSee('View logs');
        $response->assertSee('Expected URL');
        $response->assertDontSee('customers/delete');
        $response->assertSee(route('shopify-webhooks.logs.show', $processedLog), false);
    }

    public function test_shopify_webhook_log_detail_page_renders_stored_payload_and_headers(): void
    {
        $log = ShopifyWebhookLog::query()->create([
            'webhook_key' => 'customers',
            'topic' => 'customers/create',
            'request_path' => 'webhooks/shopify/customers',
            'request_url' => 'https://nexloyal.store/webhooks/shopify/customers',
            'delivery_state' => 'processed',
            'response_status' => 200,
            'hmac_valid' => true,
            'shop_domain' => 'example.myshopify.com',
            'request_headers' => ['x-shopify-topic' => 'customers/create'],
            'payload' => json_encode(['id' => 44, 'email' => 'test@example.com']),
            'processed_at' => now(),
        ]);

        $this->actingAs(User::factory()->create());

        $response = $this->get(route('shopify-webhooks.logs.show', $log));

        $response->assertOk();
        $response->assertSee('Webhook Log');
        $response->assertSee('customers/create');
        $response->assertSee('test@example.com');
        $response->assertSee('x-shopify-topic');
    }
}
