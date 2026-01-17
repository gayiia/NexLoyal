<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShopifyDiscountService
{
    public function createPriceRule(array $payload): array
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
        ])->post("https://{$domain}/admin/api/{$version}/price_rules.json", [
            'price_rule' => $payload,
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

            throw new \RuntimeException("Shopify price rule creation failed ({$status}): {$message}");
        }

        $data = $response->json();
        $rule = is_array($data) ? ($data['price_rule'] ?? null) : null;

        if (!is_array($rule) || empty($rule['id'])) {
            throw new \RuntimeException('Shopify response missing price rule data.');
        }

        return $rule;
    }

    public function createDiscountCode(int $priceRuleId, string $code): array
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
        ])->post("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}/discount_codes.json", [
            'discount_code' => [
                'code' => $code,
            ],
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

            throw new \RuntimeException("Shopify discount code creation failed ({$status}): {$message}");
        }

        $data = $response->json();
        $discount = is_array($data) ? ($data['discount_code'] ?? null) : null;

        if (!is_array($discount) || empty($discount['id'])) {
            throw new \RuntimeException('Shopify response missing discount code data.');
        }

        return $discount;
    }

    public function updatePriceRule(int $priceRuleId, array $payload): array
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
        ])->put("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}.json", [
            'price_rule' => $payload,
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

            throw new \RuntimeException("Shopify price rule update failed ({$status}): {$message}");
        }

        $data = $response->json();
        $rule = is_array($data) ? ($data['price_rule'] ?? null) : null;

        if (!is_array($rule) || empty($rule['id'])) {
            throw new \RuntimeException('Shopify response missing price rule data.');
        }

        return $rule;
    }

    public function deletePriceRule(int $priceRuleId): void
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
        ])->delete("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}.json");

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

            throw new \RuntimeException("Shopify price rule delete failed ({$status}): {$message}");
        }
    }

    public function lookupDiscountCodeId(int $priceRuleId, string $code): ?int
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
        ])->get("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}/discount_codes/lookup.json", [
            'code' => $code,
        ]);

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

    public function deleteDiscountCode(int $priceRuleId, int $discountCodeId): void
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
        ])->delete("https://{$domain}/admin/api/{$version}/price_rules/{$priceRuleId}/discount_codes/{$discountCodeId}.json");

        if (!$response->successful()) {
            $status = $response->status();
            $message = $response->body();
            throw new \RuntimeException("Shopify discount code delete failed ({$status}): {$message}");
        }
    }

    public function disableDiscountCode(int $priceRuleId, string $code): void
    {
        $discountCodeId = $this->lookupDiscountCodeId($priceRuleId, $code);
        if (!$discountCodeId) {
            return;
        }

        $this->deleteDiscountCode($priceRuleId, $discountCodeId);
    }
}
