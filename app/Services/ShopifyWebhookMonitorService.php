<?php

namespace App\Services;

// This service defines the webhook topics shown in the admin monitor.
class ShopifyWebhookMonitorService
{
    public function definitions(): array
    {
        $baseAddress = rtrim(
            config('services.shopify.webhook_address') ?: rtrim((string) config('app.url'), '/').'/webhooks/shopify',
            '/'
        );

        return [
            [
                'topic' => 'customers/create',
                'label' => 'Customer creation',
                'webhook_key' => 'customers',
                'address' => "{$baseAddress}/customers",
            ],
            [
                'topic' => 'customers/update',
                'label' => 'Customer update',
                'webhook_key' => 'customers',
                'address' => "{$baseAddress}/customers",
            ],
            [
                'topic' => 'orders/create',
                'label' => 'Order creation',
                'webhook_key' => 'orders/create',
                'address' => "{$baseAddress}/orders/create",
            ],
            [
                'topic' => 'orders/paid',
                'label' => 'Order payment',
                'webhook_key' => 'orders/paid',
                'address' => "{$baseAddress}/orders/paid",
            ],
            [
                'topic' => 'orders/fulfilled',
                'label' => 'Order fulfilment',
                'webhook_key' => 'orders/fulfilled',
                'address' => "{$baseAddress}/orders/fulfilled",
            ],
            [
                'topic' => 'refunds/create',
                'label' => 'Refund create',
                'webhook_key' => 'refunds/create',
                'address' => "{$baseAddress}/refunds/create",
            ],
            [
                'topic' => 'orders/cancelled',
                'label' => 'Order cancellation',
                'webhook_key' => 'orders/cancelled',
                'address' => "{$baseAddress}/orders/cancelled",
            ],
        ];
    }
}
