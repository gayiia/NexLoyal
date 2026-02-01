<?php

// This service wraps Shopify discount and price rule operations used by coupon workflows.
namespace App\Services;

use Illuminate\Support\Facades\Http;

// This class centralizes Shopify Admin API calls for creating, updating, and deleting discounts.
class ShopifyDiscountService
{
    // This creates a Shopify price rule and returns the created rule data.
    public function createPriceRule(array $payload): array
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This sends the price rule payload in Shopify's expected wrapper structure.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->post("https://{$domain}/admin/api/{$version}/price_rules.json", [
            'price_rule' => $payload,
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

            throw new \RuntimeException("Shopify price rule creation failed ({$status}): {$message}");
        }

        // This validates the expected response shape before returning it.
        $data = $response->json();
        $rule = is_array($data) ? ($data['price_rule'] ?? null) : null;

        if (!is_array($rule) || empty($rule['id'])) {
            throw new \RuntimeException('Shopify response missing price rule data.');
        }

        return $rule;
    }

    // This creates a discount code tied to an existing price rule.
    public function createDiscountCode(int $priceRuleId, string $code): array
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This posts the discount code under the specific price rule.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->post("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}/discount_codes.json", [
            'discount_code' => [
                'code' => $code,
            ],
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

            throw new \RuntimeException("Shopify discount code creation failed ({$status}): {$message}");
        }

        // This validates the expected response shape before returning it.
        $data = $response->json();
        $discount = is_array($data) ? ($data['discount_code'] ?? null) : null;

        if (!is_array($discount) || empty($discount['id'])) {
            throw new \RuntimeException('Shopify response missing discount code data.');
        }

        return $discount;
    }

    // This updates an existing price rule with new settings.
    public function updatePriceRule(int $priceRuleId, array $payload): array
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This sends updated price rule data in Shopify's expected wrapper structure.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->put("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}.json", [
            'price_rule' => $payload,
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

            throw new \RuntimeException("Shopify price rule update failed ({$status}): {$message}");
        }

        // This validates the expected response shape before returning it.
        $data = $response->json();
        $rule = is_array($data) ? ($data['price_rule'] ?? null) : null;

        if (!is_array($rule) || empty($rule['id'])) {
            throw new \RuntimeException('Shopify response missing price rule data.');
        }

        return $rule;
    }

    // This deletes a price rule from Shopify.
    public function deletePriceRule(int $priceRuleId): void
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This issues a delete request for the price rule ID.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->delete("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}.json");

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

            throw new \RuntimeException("Shopify price rule delete failed ({$status}): {$message}");
        }
    }

    // This looks up a discount code ID for a price rule so it can be deleted later.
    public function lookupDiscountCodeId(int $priceRuleId, string $code): ?int
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This calls Shopify's lookup endpoint to translate a code into its internal ID.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->get("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}/discount_codes/lookup.json", [
            'code' => $code,
        ]);

        // This uses null when the code does not exist in Shopify.
        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            $status = $response->status();
            $message = $response->body();
            throw new \RuntimeException("Shopify discount code lookup failed ({$status}): {$message}");
        }

        $data = $response->json();
        $discount = is_array($data) ? ($data['discount_code'] ?? null) : null;
        if (!is_array($discount) || empty($discount['id'])) {
            return null;
        }

        return (int) $discount['id'];
    }

    // This deletes a specific discount code under a price rule.
    public function deleteDiscountCode(int $priceRuleId, int $discountCodeId): void
    {
        // These credentials are required for Shopify Admin API access.
        $domain = config('services.shopify.shop_domain');
        $token = config('services.shopify.admin_token');
        $version = config('services.shopify.api_version', '2024-01');

        if (!$domain || !$token) {
            throw new \RuntimeException('Shopify credentials are not configured.');
        }

        // This issues a delete request for the discount code ID.
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->delete("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}/discount_codes/{$discountCodeId}.json");

        if (!$response->successful()) {
            $status = $response->status();
            $message = $response->body();
            throw new \RuntimeException("Shopify discount code delete failed ({$status}): {$message}");
        }
    }

    // This disables a discount code by resolving its ID and then deleting it.
    public function disableDiscountCode(int $priceRuleId, string $code): void
    {
        $discountCodeId = $this->lookupDiscountCodeId($priceRuleId, $code);
        if (!$discountCodeId) {
            return;
        }

        $this->deleteDiscountCode($priceRuleId, $discountCodeId);
    }
}
