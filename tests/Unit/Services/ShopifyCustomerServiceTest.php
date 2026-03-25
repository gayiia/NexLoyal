<?php

use App\Services\ShopifyCustomerService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.shopify.shop_domain' => 'example.myshopify.com',
        'services.shopify.admin_token' => 'token-123',
        'services.shopify.api_version' => '2026-01',
    ]);
});

test('it fetches a customer successfully', function () {
    Http::fake([
        'https://example.myshopify.com/admin/api/2026-01/customers/123.json' => Http::response([
            'customer' => [
                'id' => 123,
                'email' => 'customer@example.com',
            ],
        ], 200),
    ]);

    $service = new ShopifyCustomerService();
    $customer = $service->getCustomer('123');

    expect($customer)->toMatchArray([
        'id' => 123,
        'email' => 'customer@example.com',
    ]);
});

test('it throws when customer fetch fails', function () {
    Http::fake([
        '*' => Http::response([], 404),
    ]);

    $service = new ShopifyCustomerService();

    expect(fn () => $service->getCustomer('404'))
        ->toThrow(RuntimeException::class, 'Shopify customer fetch failed.');
});

test('it throws when customer response is missing expected data', function () {
    Http::fake([
        '*' => Http::response([
            'customer' => [
                'email' => 'customer@example.com',
            ],
        ], 200),
    ]);

    $service = new ShopifyCustomerService();

    expect(fn () => $service->getCustomer('123'))
        ->toThrow(RuntimeException::class, 'Shopify response missing customer data.');
});
