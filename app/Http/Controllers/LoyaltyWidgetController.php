<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Tier;
use App\Services\ShopifyCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LoyaltyWidgetController extends Controller
{
    public function token(Request $request, ShopifyCustomerService $shopify): Response
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'shop_domain' => ['required', 'string'],
        ]);

        $configuredDomain = strtolower((string) config('services.shopify.shop_domain'));
        if ($configuredDomain !== '' && strtolower($validated['shop_domain']) !== $configuredDomain) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Invalid shop domain.'], 403)
            );
        }

        $shopifyCustomer = $shopify->getCustomer($validated['customer_id']);
        $shopifyEmail = strtolower((string) ($shopifyCustomer['email'] ?? ''));

        if ($shopifyEmail === '' || $shopifyEmail !== strtolower($validated['email'])) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Customer verification failed.'], 403)
            );
        }

        $customer = Customer::updateOrCreate(
            ['shopify_id' => (string) $shopifyCustomer['id']],
            [
                'first_name' => $shopifyCustomer['first_name'] ?? null,
                'last_name' => $shopifyCustomer['last_name'] ?? null,
                'email' => $shopifyCustomer['email'] ?? null,
                'phone' => $shopifyCustomer['phone'] ?? null,
                'status' => $shopifyCustomer['state'] ?? null,
                'orders_count' => $shopifyCustomer['orders_count'] ?? 0,
                'total_spent' => $shopifyCustomer['total_spent'] ?? 0,
                'currency' => $shopifyCustomer['currency'] ?? null,
                'shopify_created_at' => $shopifyCustomer['created_at'] ?? null,
            ]
        );

        $expiresAt = now()->addMinutes(30);
        $payload = [
            'shopify_id' => $customer->shopify_id,
            'email' => $customer->email,
            'issued_at' => now()->timestamp,
            'expires_at' => $expiresAt->timestamp,
        ];

        $token = Crypt::encryptString(json_encode($payload));

        return $this->corsResponse(
            $request,
            response()->json([
                'token' => $token,
                'expires_at' => $expiresAt->toIso8601String(),
            ])
        );
    }

    public function tokenOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    public function dashboard(Request $request)
    {
        $token = (string) $request->query('token', '');

        [$customer, $error] = $this->customerFromToken($token);
        if (!$customer) {
            return view('loyalty.dashboard', ['error' => $error ?? 'Unauthorized.']);
        }

        $points = $customer->loyalty_points ?? 0;
        $tier = $customer->tier ?? $this->resolveTier($points);

        return view('loyalty.dashboard', [
            'customer' => $customer,
            'points' => $points,
            'tier' => $tier,
        ]);
    }

    public function data(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        $points = (int) ($customer->loyalty_points ?? 0);
        $tier = $customer->tier ?? $this->resolveTier($points);

        return $this->corsResponse(
            $request,
            response()->json([
                'name' => $customer->full_name ?: 'Customer',
                'email' => $customer->email,
                'points' => $points,
                'tier' => $tier?->title,
                'total_spent' => (float) $customer->total_spent,
                'currency' => $customer->currency,
            ])
        );
    }

    public function dataOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    private function resolveTier(int $points): ?Tier
    {
        return Tier::query()
            ->where('status', 'active')
            ->where('min_points', '<=', $points)
            ->where('max_points', '>=', $points)
            ->orderBy('min_points')
            ->first();
    }

    private function customerFromToken(string $token): array
    {
        if ($token === '') {
            return [null, 'Missing token.'];
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return [null, 'Invalid or expired token.'];
        }

        if (!is_array($payload) || empty($payload['shopify_id'])) {
            return [null, 'Invalid token payload.'];
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt < now()->timestamp) {
            return [null, 'Token expired. Please refresh the widget.'];
        }

        $customer = Customer::where('shopify_id', (string) $payload['shopify_id'])->first();
        if (!$customer) {
            return [null, 'Customer not found.'];
        }

        return [$customer, null];
    }

    private function corsResponse(Request $request, Response $response): Response
    {
        $origin = $this->allowedOrigin($request);

        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
            $response->headers->set('Access-Control-Max-Age', '600');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }

    private function allowedOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if (!$origin) {
            return null;
        }

        $configuredDomain = trim((string) config('services.shopify.shop_domain'));
        if ($configuredDomain === '') {
            return null;
        }

        $configuredDomain = Str::after($configuredDomain, 'https://');
        $configuredDomain = Str::after($configuredDomain, 'http://');
        $allowedOrigin = 'https://'.$configuredDomain;

        return strcasecmp($origin, $allowedOrigin) === 0 ? $origin : null;
    }
}
