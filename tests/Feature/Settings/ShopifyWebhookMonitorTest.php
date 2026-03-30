<?php

namespace Tests\Feature\Settings;

use App\Models\ShopifyWebhookLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyWebhookMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopify_webhook_monitor_page_shows_shopify_connection_status_instead_of_delivery_logs(): void
    {
        config([
            'app.url' => 'https://nexloyal.store',
            'services.shopify.shop_domain' => 'example.myshopify.com',
            'services.shopify.admin_token' => 'shopify-token',
            'services.shopify.api_version' => '2024-01',
            'services.shopify.webhook_address' => 'https://nexloyal.store/webhooks/shopify',
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'webhooks' => [
                        [
                            'id' => 9001,
                            'topic' => 'customers/create',
                            'address' => 'https://nexloyal.store/webhooks/shopify/customers',
                        ],
                        [
                            'id' => 9003,
                            'topic' => 'orders/create',
                            'address' => 'https://nexloyal.store/webhooks/shopify/orders/create',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 500);
        });

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
        $response->assertSee('Not connected');
        $response->assertDontSee('Issue');
        $response->assertSee('View logs');
        $response->assertSee('Expected URL');
        $response->assertSee('Connect');
        $response->assertSee('Delete webhooks');
        $response->assertDontSee('Create webhooks');
        $response->assertDontSee('customers/delete');
        $response->assertSee(route('shopify-webhooks.logs.show', $processedLog), false);

        Http::assertSentCount(1);
    }

    public function test_shopify_webhook_registration_action_registers_monitor_topics_using_shopify_env_credentials(): void
    {
        config([
            'app.url' => 'https://nexloyal.store',
            'services.shopify.shop_domain' => 'example.myshopify.com',
            'services.shopify.admin_token' => 'shopify-token',
            'services.shopify.api_version' => '2024-01',
            'services.shopify.webhook_address' => 'https://nexloyal.store/webhooks/shopify',
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'webhooks' => [
                        [
                            'id' => 9001,
                            'topic' => 'customers/create',
                            'address' => 'https://nexloyal.store/webhooks/shopify/customers',
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'webhook' => [
                    'id' => crc32((string) data_get($request->data(), 'webhook.topic')),
                ],
            ], 201);
        });

        $this->actingAs(User::factory()->create());

        $response = $this->post(route('shopify-webhooks.register'));

        $response->assertRedirect(route('shopify-webhooks'));
        $response->assertSessionHas('shopify_webhook_feedback.level', 'success');
        $response->assertSessionHas('shopify_webhook_feedback.created_count', 6);
        $response->assertSessionHas('shopify_webhook_feedback.existing_count', 1);
        $response->assertSessionHas('shopify_webhook_feedback.failed_count', 0);
        $response->assertSessionHas('shopify_webhook_feedback.results', function (array $results): bool {
            return count($results) === 7
                && collect($results)->contains(fn (array $result) => $result['topic'] === 'customers/create' && $result['status'] === 'existing')
                && collect($results)->contains(fn (array $result) => $result['topic'] === 'orders/cancelled' && $result['status'] === 'created');
        });

        Http::assertSentCount(7);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && data_get($request->data(), 'webhook.topic') === 'refunds/create'
                && data_get($request->data(), 'webhook.address') === 'https://nexloyal.store/webhooks/shopify/refunds/create';
        });
    }

    public function test_shopify_webhook_registration_action_redirects_with_error_when_shopify_credentials_are_missing(): void
    {
        config([
            'services.shopify.shop_domain' => null,
            'services.shopify.admin_token' => null,
        ]);

        Http::fake();

        $this->actingAs(User::factory()->create());

        $response = $this->post(route('shopify-webhooks.register'));

        $response->assertRedirect(route('shopify-webhooks'));
        $response->assertSessionHas('shopify_webhook_feedback.level', 'error');
        $response->assertSessionHas(
            'shopify_webhook_feedback.message',
            'Missing Shopify credentials. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN.'
        );

        Http::assertNothingSent();
    }

    public function test_shopify_webhook_delete_action_removes_monitor_topics_using_shopify_env_credentials(): void
    {
        config([
            'app.url' => 'https://nexloyal.store',
            'services.shopify.shop_domain' => 'example.myshopify.com',
            'services.shopify.admin_token' => 'shopify-token',
            'services.shopify.api_version' => '2024-01',
            'services.shopify.webhook_address' => 'https://nexloyal.store/webhooks/shopify',
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'webhooks' => [
                        ['id' => 9101, 'topic' => 'customers/create', 'address' => 'https://nexloyal.store/webhooks/shopify/customers'],
                        ['id' => 9102, 'topic' => 'customers/update', 'address' => 'https://nexloyal.store/webhooks/shopify/customers'],
                        ['id' => 9103, 'topic' => 'orders/create', 'address' => 'https://nexloyal.store/webhooks/shopify/orders/create'],
                        ['id' => 9104, 'topic' => 'orders/paid', 'address' => 'https://nexloyal.store/webhooks/shopify/orders/paid'],
                        ['id' => 9105, 'topic' => 'orders/fulfilled', 'address' => 'https://nexloyal.store/webhooks/shopify/orders/fulfilled'],
                        ['id' => 9106, 'topic' => 'refunds/create', 'address' => 'https://nexloyal.store/webhooks/shopify/refunds/create'],
                        ['id' => 9107, 'topic' => 'orders/cancelled', 'address' => 'https://nexloyal.store/webhooks/shopify/orders/cancelled'],
                    ],
                ], 200);
            }

            if ($request->method() === 'DELETE') {
                return Http::response('', 200);
            }

            return Http::response([], 500);
        });

        $this->actingAs(User::factory()->create());

        $response = $this->delete(route('shopify-webhooks.destroy'));

        $response->assertRedirect(route('shopify-webhooks'));
        $response->assertSessionHas('shopify_webhook_feedback.level', 'success');
        $response->assertSessionHas('shopify_webhook_feedback.deleted_count', 7);
        $response->assertSessionHas('shopify_webhook_feedback.missing_count', 0);
        $response->assertSessionHas('shopify_webhook_feedback.failed_count', 0);
        $response->assertSessionHas('shopify_webhook_feedback.results', function (array $results): bool {
            return count($results) === 7
                && collect($results)->contains(fn (array $result) => $result['topic'] === 'customers/create' && $result['status'] === 'deleted')
                && collect($results)->contains(fn (array $result) => $result['topic'] === 'orders/cancelled' && $result['status'] === 'deleted');
        });

        Http::assertSentCount(8);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://example.myshopify.com/admin/api/2024-01/webhooks/9106.json';
        });
    }

    public function test_shopify_webhook_delete_action_redirects_with_error_when_shopify_credentials_are_missing(): void
    {
        config([
            'services.shopify.shop_domain' => null,
            'services.shopify.admin_token' => null,
        ]);

        Http::fake();

        $this->actingAs(User::factory()->create());

        $response = $this->delete(route('shopify-webhooks.destroy'));

        $response->assertRedirect(route('shopify-webhooks'));
        $response->assertSessionHas('shopify_webhook_feedback.level', 'error');
        $response->assertSessionHas(
            'shopify_webhook_feedback.message',
            'Missing Shopify credentials. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN.'
        );

        Http::assertNothingSent();
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
