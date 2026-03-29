<?php

// This controller validates Shopify webhooks and delegates sync handling.
namespace App\Http\Controllers;

use App\Models\ShopifyWebhookLog;
use App\Services\ShopifySyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// This class exposes webhook endpoints for Shopify customers and orders.
class ShopifyWebhookController extends Controller
{
    // This verifies a customer webhook and syncs the customer record.
    public function handleCustomers(Request $request, ShopifySyncService $sync): Response
    {
        return $this->handleWebhook(
            $request,
            ['customers/create', 'customers/update', 'customers/delete'],
            fn (array $data): bool => !empty($data['id']),
            fn (array $data, string $topic) => $sync->syncCustomer($data, $topic)
        );
    }

    // This verifies an order webhook and syncs loyalty-related order data.
    public function handleOrders(Request $request, ShopifySyncService $sync): Response
    {
        return $this->handleWebhook(
            $request,
            ['orders/paid', 'orders/create', 'orders/fulfilled', 'orders/refunded', 'orders/cancelled'],
            fn (array $data): bool => true,
            fn (array $data, string $topic) => $sync->syncOrder($data, $topic)
        );
    }

    private function handleWebhook(
        Request $request,
        array $supportedTopics,
        callable $payloadValidator,
        callable $processor
    ): Response {
        // This secret is used to validate the webhook signature.
        $secret = config('services.shopify.webhook_secret');
        $payload = $request->getContent();
        $topic = (string) $request->header('X-Shopify-Topic', '');
        $logContext = $this->baseLogContext($request, $payload, $topic);

        if (!$secret) {
            return $this->logAndRespond($logContext, 'Webhook secret not set', 500, 'misconfigured', false);
        }

        // This computes the HMAC for signature verification.
        $hmac = (string) $request->header('X-Shopify-Hmac-Sha256', '');
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        $hmacValid = $hmac !== '' && hash_equals($computed, $hmac);

        if (!$hmacValid) {
            return $this->logAndRespond($logContext, 'Invalid signature', 401, 'invalid_signature', false);
        }

        // This limits processing to supported topics.
        if (!in_array($topic, $supportedTopics, true)) {
            return $this->logAndRespond($logContext, 'Ignored', 202, 'ignored', true);
        }

        // This ensures the payload is valid JSON before syncing.
        $data = json_decode($payload, true);
        if (!is_array($data) || !($payloadValidator)($data)) {
            return $this->logAndRespond($logContext, 'Invalid payload', 400, 'invalid_payload', true);
        }

        try {
            // This applies the business sync logic for the received event.
            $processor($data, $topic);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->logAndRespond(
                $logContext,
                'Webhook processing failed',
                500,
                'error',
                true,
                $exception->getMessage()
            );
        }

        return $this->logAndRespond($logContext, 'OK', 200, 'processed', true);
    }

    private function baseLogContext(Request $request, string $payload, string $topic): array
    {
        return [
            'webhook_key' => $this->webhookKeyFromRequest($request),
            'topic' => $topic !== '' ? $topic : null,
            'request_path' => $request->path(),
            'request_url' => $request->fullUrl(),
            'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
            'shopify_webhook_id' => $request->header('X-Shopify-Webhook-Id'),
            'shopify_event_id' => $request->header('X-Shopify-Event-Id'),
            'request_headers' => $this->filteredHeaders($request),
            'payload' => $payload,
        ];
    }

    private function logAndRespond(
        array $context,
        string $message,
        int $status,
        string $deliveryState,
        ?bool $hmacValid,
        ?string $errorMessage = null
    ): Response {
        ShopifyWebhookLog::query()->create([
            ...$context,
            'delivery_state' => $deliveryState,
            'response_status' => $status,
            'hmac_valid' => $hmacValid,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);

        return response($message, $status);
    }

    private function filteredHeaders(Request $request): array
    {
        $headers = [];
        $allowedHeaders = [
            'content-type',
            'user-agent',
            'x-shopify-topic',
            'x-shopify-shop-domain',
            'x-shopify-hmac-sha256',
            'x-shopify-webhook-id',
            'x-shopify-event-id',
        ];

        foreach ($allowedHeaders as $headerName) {
            $value = $request->headers->get($headerName);

            if ($value === null || $value === '') {
                continue;
            }

            if ($headerName === 'x-shopify-hmac-sha256') {
                $value = substr((string) $value, 0, 12).'...';
            }

            $headers[$headerName] = $value;
        }

        return $headers;
    }

    private function webhookKeyFromRequest(Request $request): string
    {
        $path = trim($request->path(), '/');
        $prefix = 'webhooks/shopify/';

        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }
}
