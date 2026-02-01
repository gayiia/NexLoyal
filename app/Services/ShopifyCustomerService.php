<?php

// This service wraps Shopify customer API calls used by local onboarding and sync flows.
namespace App\Services;

use Illuminate\Support\Facades\Http;

// This class handles reading and writing customer records in Shopify's Admin API.
class ShopifyCustomerService
{
    // This fetches a single Shopify customer and returns the raw API data.
    public function getCustomer(string $shopifyId): array
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This performs the GET request for the given Shopify customer ID.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->get("https://{$domain}/admin/api/{$version}/customers/{$shopifyId}.json");

        if (!$response->successful()) {
            throw new \RuntimeException('Shopify customer fetch failed.');
        }

        // This validates the expected response shape before returning.
        $data = $response->json();
        $customer = is_array($data) ? ($data['customer'] ?? null) : null;

        if (!is_array($customer) || empty($customer['id'])) {
            throw new \RuntimeException('Shopify response missing customer data.');
        }

        return $customer;
    }

    // This creates a new Shopify customer using the provided payload.
    public function createCustomer(array $payload): array
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This posts a customer payload in Shopify's expected wrapper structure.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->post("https://{$domain}/admin/api/{$version}/customers.json", [
            'customer' => $payload,
        ]);

        if (!$response->successful()) {
            // This tries to extract a human-readable error message from Shopify's response.
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

        // This validates the expected response shape before returning.
        $data = $response->json();
        $customer = is_array($data) ? ($data['customer'] ?? null) : null;

        if (!is_array($customer) || empty($customer['id'])) {
            throw new \RuntimeException('Shopify response missing customer data.');
        }

        return $customer;
    }

    // This updates an existing Shopify customer with the provided fields.
    public function updateCustomer(string $shopifyId, array $payload): array
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This sends updated customer data in Shopify's expected wrapper structure.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->put("https://{$domain}/admin/api/{$version}/customers/{$shopifyId}.json", [
            'customer' => $payload,
        ]);

        if (!$response->successful()) {
            // This tries to extract a human-readable error message from Shopify's response.
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

        // This validates the expected response shape before returning.
        $data = $response->json();
        $customer = is_array($data) ? ($data['customer'] ?? null) : null;

        if (!is_array($customer) || empty($customer['id'])) {
            throw new \RuntimeException('Shopify response missing customer data.');
        }

        return $customer;
    }
}
