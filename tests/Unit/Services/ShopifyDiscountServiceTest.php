<?php

use App\Services\ShopifyDiscountService;
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

test('it creates a price rule successfully', function () {
    Http::fake([
        'https://example.myshopify.com/admin/api/2026-01/price_rules.json' => Http::response([
            'price_rule' => [
                'id' => 101,
                'title' => 'Spring Sale',
            ],
        ], 201),
    ]);

    $service = new ShopifyDiscountService();
    $rule = $service->createPriceRule([
        'title' => 'Spring Sale',
    ]);

    expect($rule['id'])->toBe(101);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.myshopify.com/admin/api/2026-01/price_rules.json'
            && $request->method() === 'POST'
            && $request['price_rule']['title'] === 'Spring Sale';
    });
});

test('it creates a discount code successfully', function () {
    Http::fake([
        'https://example.myshopify.com/admin/api/2026-01/price_rules/101/discount_codes.json' => Http::response([
            'discount_code' => [
                'id' => 202,
                'code' => 'VIP-202',
            ],
        ], 201),
    ]);

    $service = new ShopifyDiscountService();
    $discount = $service->createDiscountCode(101, 'VIP-202');

    expect($discount['id'])->toBe(202);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.myshopify.com/admin/api/2026-01/price_rules/101/discount_codes.json'
            && $request->method() === 'POST'
            && $request['discount_code']['code'] === 'VIP-202';
    });
});

test('it surfaces shopify error details when price rule creation fails', function () {
    Http::fake([
        '*' => Http::response([
            'errors' => [
                'title' => ['is invalid'],
            ],
        ], 422),
    ]);

    $service = new ShopifyDiscountService();

    expect(fn () => $service->createPriceRule(['title' => 'Bad Rule']))
        ->toThrow(RuntimeException::class, 'Shopify price rule creation failed (422): title is invalid');
});

test('it throws when credentials are missing for discount operations', function () {
    config([
        'services.shopify.shop_domain' => null,
        'services.shopify.admin_token' => null,
    ]);

    $service = new ShopifyDiscountService();

    expect(fn () => $service->createDiscountCode(1, 'ANY-CODE'))
        ->toThrow(RuntimeException::class, 'Shopify credentials are not configured.');
});

test('it throws when the price rule response is missing expected data', function () {
    Http::fake([
        '*' => Http::response([
            'price_rule' => [
                'title' => 'Missing id',
            ],
        ], 201),
    ]);

    $service = new ShopifyDiscountService();

    expect(fn () => $service->createPriceRule(['title' => 'Missing id']))
        ->toThrow(RuntimeException::class, 'Shopify response missing price rule data.');
});
