<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShopifyProductService
{
    public function listProducts(int $limit = 100): array
    {
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

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
