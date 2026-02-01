<?php

// This controller validates Shopify webhooks and delegates sync handling.
namespace App\Http\Controllers;

use App\Services\ShopifySyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// This class exposes webhook endpoints for Shopify customers and orders.
class ShopifyWebhookController extends Controller
{
    // This verifies a customer webhook and syncs the customer record.
    public function handleCustomers(Request $request, ShopifySyncService $sync): Response
    {
        // This secret is used to validate the webhook signature.
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) {
            return response('Webhook secret not set', 500);
        }

        // This computes the HMAC for signature verification.
        $payload = $request->getContent();
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (!$hmac || !hash_equals($computed, $hmac)) {
            return response('Invalid signature', 401);
        }

        // This limits processing to supported customer topics.
        $topic = $request->header('X-Shopify-Topic');
        if (!in_array($topic, ['customers/create', 'customers/update', 'customers/delete'], true)) {
            return response('Ignored', 202);
        }

        // This ensures the payload is valid JSON with an ID.
        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['id'])) {
            return response('Invalid payload', 400);
        }

        // This applies the sync logic for the received event.
        $sync->syncCustomer($data, $topic);

        return response('OK', 200);
    }

    // This verifies an order webhook and syncs loyalty-related order data.
    public function handleOrders(Request $request, ShopifySyncService $sync): Response
    {
        // This secret is used to validate the webhook signature.
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) {
            return response('Webhook secret not set', 500);
        }

        // This computes the HMAC for signature verification.
        $payload = $request->getContent();
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (!$hmac || !hash_equals($computed, $hmac)) {
            return response('Invalid signature', 401);
        }

        // This limits processing to supported order topics.
        $topic = $request->header('X-Shopify-Topic');
        if (!in_array($topic, ['orders/paid', 'orders/create', 'orders/fulfilled', 'orders/refunded', 'orders/cancelled'], true)) {
            return response('Ignored', 202);
        }

        // This ensures the payload is valid JSON before syncing.
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return response('Invalid payload', 400);
        }

        // This applies order sync logic including points and coupons.
        $sync->syncOrder($data, $topic);

        return response('OK', 200);
    }
}
