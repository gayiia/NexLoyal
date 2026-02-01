<?php

// This service fetches product metadata from Shopify for coupon and UI selection flows.
namespace App\Services;

use Illuminate\Support\Facades\Http;

// This class wraps Shopify product listing calls and normalizes the response.
class ShopifyProductService
{
    // This retrieves a limited set of products with id and title for selection lists.
    public function listProducts(int $limit = 100): array
    {
        // These settings are required to authenticate against the Shopify Admin API.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This performs a minimal fields request to keep payloads small.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->get("https://{$domain}/admin/api/{$version}/products.json", [
            'limit' => $limit,
            'fields' => 'id,title',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Shopify products fetch failed.');
        }

        // This sanitizes the response into a simple array of ids and titles.
        $data = $response->json();
        $products = is_array($data) ? ($data['products'] ?? []) : [];

        return collect($products)
            ->filter(fn ($product) => is_array($product) && isset($product['id'], $product['title']))
            ->map(fn ($product) => [
                'id' => (int) $product['id'],
                'title' => (string) $product['title'],
            ])
            ->values()
            ->all();
    }
}
