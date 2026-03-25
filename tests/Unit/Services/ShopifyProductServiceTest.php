<?php

use App\Services\ShopifyProductService;
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

test('it lists and normalizes shopify products', function () {
    Http::fake([
        'https://example.myshopify.com/admin/api/2026-01/products.json*' => Http::response([
            'products' => [
                ['id' => 11, 'title' => 'Alpha'],
                ['id' => 12, 'title' => 'Beta'],
                ['title' => 'Ignored missing id'],
            ],
        ], 200),
    ]);

    $service = new ShopifyProductService();
    $products = $service->listProducts();

    expect($products)->toBe([
        ['id' => 11, 'title' => 'Alpha'],
        ['id' => 12, 'title' => 'Beta'],
    ]);
});

test('it throws when product fetch fails', function () {
    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $service = new ShopifyProductService();

    expect(fn () => $service->listProducts())
        ->toThrow(RuntimeException::class, 'Shopify products fetch failed.');
});

test('it returns an empty list when the product payload is malformed', function () {
    Http::fake([
        '*' => Http::response([
            'unexpected' => [],
        ], 200),
    ]);

    $service = new ShopifyProductService();

    expect($service->listProducts())->toBe([]);
});
