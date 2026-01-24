<?php

namespace App\Http\Controllers;

use App\Services\ShopifySyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShopifyWebhookController extends Controller
{
    public function handleCustomers(Request $request, ShopifySyncService $sync): Response
    {
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) {
            return response('Webhook secret not set', 500);
        }

        $payload = $request->getContent();
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (!$hmac || !hash_equals($computed, $hmac)) {
            return response('Invalid signature', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        if (!in_array($topic, ['customers/create', 'customers/update', 'customers/delete'], true)) {
            return response('Ignored', 202);
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['id'])) {
            return response('Invalid payload', 400);
        }

        $sync->syncCustomer($data, $topic);

        return response('OK', 200);
    }

    public function handleOrders(Request $request, ShopifySyncService $sync): Response
    {
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) {
            return response('Webhook secret not set', 500);
        }

        $payload = $request->getContent();
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (!$hmac || !hash_equals($computed, $hmac)) {
            return response('Invalid signature', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        if (!in_array($topic, ['orders/paid', 'orders/create', 'orders/fulfilled', 'orders/refunded', 'orders/cancelled'], true)) {
            return response('Ignored', 202);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return response('Invalid payload', 400);
        }

        $sync->syncOrder($data, $topic);

        return response('OK', 200);
    }
}
