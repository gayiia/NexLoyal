<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// This service registers Shopify webhook topics against the configured admin API.
class ShopifyWebhookRegistrationService
{
    public function inspect(array $definitions): array
    {
        [$baseApiUrl, $client] = $this->shopifyApi();
        $definitions = $this->normalizeDefinitions($definitions);
        $existingWebhooks = $this->fetchExistingWebhooks($client, "{$baseApiUrl}/webhooks.json");
        $checkedAt = now();
        $results = [];
        $connectedCount = 0;
        $disconnectedCount = 0;

        foreach ($definitions as $definition) {
            $existing = $existingWebhooks->get($this->signature($definition['topic'], $definition['address']));

            if (is_array($existing) && data_get($existing, 'id')) {
                $connectedCount++;
                $results[] = [
                    ...$definition,
                    'status' => 'connected',
                    'status_label' => 'Connected',
                    'shopify_webhook_id' => data_get($existing, 'id'),
                    'connection_message' => 'Webhook is registered in Shopify.',
                    'checked_at_label' => $checkedAt->format('M d, Y H:i:s'),
                ];
                continue;
            }

            $disconnectedCount++;
            $results[] = [
                ...$definition,
                'status' => 'waiting',
                'status_label' => 'Not connected',
                'shopify_webhook_id' => null,
                'connection_message' => 'Webhook is not registered in Shopify.',
                'checked_at_label' => $checkedAt->format('M d, Y H:i:s'),
            ];
        }

        return [
            'connected_count' => $connectedCount,
            'disconnected_count' => $disconnectedCount,
            'results' => $results,
        ];
    }

    public function register(array $definitions): array
    {
        [$baseApiUrl, $client] = $this->shopifyApi();
        $definitions = $this->normalizeDefinitions($definitions);
        $endpoint = "{$baseApiUrl}/webhooks.json";
        $existingWebhooks = $this->fetchExistingWebhooks($client, $endpoint);

        $results = [];
        $createdCount = 0;
        $existingCount = 0;
        $failedCount = 0;

        foreach ($definitions as $definition) {
            $signature = $this->signature($definition['topic'], $definition['address']);
            $existing = $existingWebhooks->get($signature);

            if (is_array($existing)) {
                $existingCount++;
                $results[] = [
                    ...$definition,
                    'status' => 'existing',
                    'id' => data_get($existing, 'id'),
                    'message' => 'Webhook already exists in Shopify.',
                ];
                continue;
            }

            $response = $client->post($endpoint, [
                'webhook' => [
                    'topic' => $definition['topic'],
                    'address' => $definition['address'],
                    'format' => 'json',
                ],
            ]);

            if (! $response->successful()) {
                $failedCount++;
                $results[] = [
                    ...$definition,
                    'status' => 'failed',
                    'id' => null,
                    'message' => $this->responseMessage($response),
                ];
                continue;
            }

            $created = $response->json('webhook', []);
            $existingWebhooks->put($signature, [
                'id' => data_get($created, 'id'),
                'topic' => $definition['topic'],
                'address' => $definition['address'],
            ]);

            $createdCount++;
            $results[] = [
                ...$definition,
                'status' => 'created',
                'id' => data_get($created, 'id'),
                'message' => 'Webhook created successfully.',
            ];
        }

        return [
            'created_count' => $createdCount,
            'existing_count' => $existingCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    public function delete(array $definitions): array
    {
        [$baseApiUrl, $client] = $this->shopifyApi();
        $definitions = $this->normalizeDefinitions($definitions);
        $endpoint = "{$baseApiUrl}/webhooks.json";
        $existingWebhooks = $this->fetchExistingWebhooks($client, $endpoint);

        $results = [];
        $deletedCount = 0;
        $missingCount = 0;
        $failedCount = 0;

        foreach ($definitions as $definition) {
            $signature = $this->signature($definition['topic'], $definition['address']);
            $existing = $existingWebhooks->get($signature);

            if (! is_array($existing) || ! data_get($existing, 'id')) {
                $missingCount++;
                $results[] = [
                    ...$definition,
                    'status' => 'missing',
                    'id' => null,
                    'message' => 'Webhook was not found in Shopify.',
                ];
                continue;
            }

            $id = data_get($existing, 'id');
            $response = $client->delete("{$baseApiUrl}/webhooks/{$id}.json");

            if (! $response->successful()) {
                $failedCount++;
                $results[] = [
                    ...$definition,
                    'status' => 'failed',
                    'id' => $id,
                    'message' => $this->responseMessage($response),
                ];
                continue;
            }

            $existingWebhooks->forget($signature);
            $deletedCount++;
            $results[] = [
                ...$definition,
                'status' => 'deleted',
                'id' => $id,
                'message' => 'Webhook deleted successfully.',
            ];
        }

        return [
            'deleted_count' => $deletedCount,
            'missing_count' => $missingCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    private function shopifyApi(): array
    {
        $shopDomain = trim((string) config('services.shopify.shop_domain', ''));
        $token = trim((string) config('services.shopify.admin_token', ''));
        $apiVersion = trim((string) config('services.shopify.api_version', '2024-01'));

        if ($shopDomain === '' || $token === '') {
            throw new RuntimeException('Missing Shopify credentials. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN.');
        }

        return [
            "https://{$shopDomain}/admin/api/{$apiVersion}",
            Http::withHeaders([
                'Accept' => 'application/json',
                'X-Shopify-Access-Token' => $token,
            ]),
        ];
    }

    private function normalizeDefinitions(array $definitions): array
    {
        $definitions = collect($definitions)
            ->filter(fn ($definition) => is_array($definition) && ! empty($definition['topic']))
            ->map(function (array $definition): array {
                return [
                    'topic' => (string) $definition['topic'],
                    'label' => (string) ($definition['label'] ?? $definition['topic']),
                    'webhook_key' => (string) ($definition['webhook_key'] ?? $definition['topic']),
                    'address' => rtrim((string) ($definition['address'] ?? ''), '/'),
                ];
            })
            ->unique(fn (array $definition) => $this->signature($definition['topic'], $definition['address']))
            ->values()
            ->all();

        if ($definitions === []) {
            throw new RuntimeException('No webhook topics are configured for registration.');
        }

        foreach ($definitions as $definition) {
            if ($definition['address'] === '' || ! filter_var($definition['address'], FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Missing webhook address. Set SHOPIFY_WEBHOOK_ADDRESS or APP_URL to a valid absolute URL.');
            }
        }

        return $definitions;
    }

    private function fetchExistingWebhooks($client, string $endpoint)
    {
        $existingResponse = $client->get($endpoint, [
            'limit' => 250,
        ]);

        if (! $existingResponse->successful()) {
            throw new RuntimeException(
                "Failed to load existing Shopify webhooks ({$existingResponse->status()}): ".$this->responseMessage($existingResponse)
            );
        }

        return collect($existingResponse->json('webhooks', []))
            ->filter(fn ($webhook) => is_array($webhook) && ! empty($webhook['topic']) && ! empty($webhook['address']))
            ->keyBy(fn (array $webhook) => $this->signature((string) $webhook['topic'], (string) $webhook['address']));
    }

    private function signature(string $topic, string $address): string
    {
        return $topic.'|'.rtrim($address, '/');
    }

    private function responseMessage(Response $response): string
    {
        $body = trim($response->body());
        $json = $response->json();

        if (is_array($json)) {
            $errors = data_get($json, 'errors');

            if (is_string($errors) && $errors !== '') {
                return $errors;
            }

            if (is_array($errors) && $errors !== []) {
                return json_encode($errors, JSON_UNESCAPED_SLASHES) ?: $body;
            }

            return json_encode($json, JSON_UNESCAPED_SLASHES) ?: ($body !== '' ? $body : 'Unexpected Shopify response.');
        }

        return $body !== '' ? $body : 'Unexpected Shopify response.';
    }
}
