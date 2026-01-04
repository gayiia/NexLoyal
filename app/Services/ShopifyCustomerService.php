<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShopifyCustomerService
{
    public function getCustomer(string $shopifyId): array
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
        ])->get("https://{$domain}/admin/api/{$version}/customers/{$shopifyId}.json");

        if (!$response->successful()) {
            throw new \RuntimeException('Shopify customer fetch failed.');
        }

        $data = $response->json();
        $customer = is_array($data) ? ($data['customer'] ?? null) : null;

        if (!is_array($customer) || empty($customer['id'])) {
            throw new \RuntimeException('Shopify response missing customer data.');
        }

        return $customer;
    }

    public function createCustomer(array $payload): array
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
        ])->post("https://{$domain}/admin/api/{$version}/customers.json", [
            'customer' => $payload,
        ]);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->json();
            $message = null;

            if (is_array($body) && isset($body['errors'])) {
                $errors = $body['errors'];
                if (is_string($errors)) {
                    $message = $errors;
                } elseif (is_array($errors)) {
                    $parts = [];
                    foreach ($errors as $field => $detail) {
                        if (is_array($detail)) {
                            foreach ($detail as $entry) {
                                $parts[] = "{$field} {$entry}";
                            }
                        } elseif (is_string($detail)) {
                            $parts[] = "{$field} {$detail}";
                        }
                    }
                    $message = $parts ? implode('. ', $parts) : json_encode($errors);
                }
            }

            if (!$message) {
                $message = $response->body();
            }

            throw new \RuntimeException("Shopify error ({$status}): {$message}");
        }

        $data = $response->json();
        $customer = is_array($data) ? ($data['customer'] ?? null) : null;

        if (!is_array($customer) || empty($customer['id'])) {
            throw new \RuntimeException('Shopify response missing customer data.');
        }

        return $customer;
    }
}
