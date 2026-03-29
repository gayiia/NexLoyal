<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// This model stores inbound Shopify webhook attempts for delivery monitoring.
class ShopifyWebhookLog extends Model
{
    protected $fillable = [
        'webhook_key',
        'topic',
        'request_path',
        'request_url',
        'delivery_state',
        'response_status',
        'hmac_valid',
        'shop_domain',
        'shopify_webhook_id',
        'shopify_event_id',
        'request_headers',
        'payload',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'hmac_valid' => 'boolean',
        'request_headers' => 'array',
        'processed_at' => 'datetime',
    ];
}
