<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointRule;
use App\Models\Tier;
use App\Services\ShopifyCustomerService;
use App\Services\ShopifyDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
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
            'token' => $token,
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
                'birthday' => $customer->birthday?->format('Y-m-d'),
            ])
        );
    }

    public function dataOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    public function profile(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        return $this->corsResponse(
            $request,
            response()->json([
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'birthday' => $customer->birthday?->format('Y-m-d'),
            ])
        );
    }

    public function updateProfile(Request $request, ShopifyCustomerService $shopify): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'birthday' => ['nullable', 'date_format:Y-m-d'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $updates = [
            'first_name' => $validated['first_name'] ?? $customer->first_name,
            'last_name' => $validated['last_name'] ?? $customer->last_name,
            'email' => $validated['email'],
            'birthday' => $validated['birthday'] ?? null,
            'phone' => $validated['phone'] ?? $customer->phone,
        ];

        if (array_key_exists('phone', $validated) && $updates['phone'] !== null) {
            $updates['phone'] = trim((string) $updates['phone']);
            if ($updates['phone'] === '') {
                $updates['phone'] = null;
            }
        }

        $shopifyPayload = [
            'first_name' => $updates['first_name'],
            'last_name' => $updates['last_name'],
            'email' => $updates['email'],
        ];

        if (array_key_exists('phone', $validated)) {
            $shopifyPayload['phone'] = $updates['phone'];
        }

        try {
            $shopify->updateCustomer((string) $customer->shopify_id, $shopifyPayload);
        } catch (\Throwable $exception) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $exception->getMessage()], 502)
            );
        }

        $customer->fill($updates);

        $awardedProfilePoints = false;
        $rule = $this->pointRule();
        $profilePoints = (int) ($rule?->profile_completion_points ?? 0);

        if (!$customer->profile_completed_at
            && $customer->first_name
            && $customer->last_name
            && $customer->email
            && $customer->birthday
        ) {
            if ($profilePoints > 0) {
                $customer->loyalty_points += $profilePoints;
                $awardedProfilePoints = true;
            }
            $customer->profile_completed_at = now();
        }

        $awardedBirthdayPoints = false;
        $birthdayPoints = (int) ($rule?->birthday_points ?? 0);
        if ($birthdayPoints > 0 && $customer->birthday) {
            $today = now()->toDateString();
            if ($customer->birthday->format('m-d') === now()->format('m-d')) {
                $lastReward = $customer->birthday_rewarded_at?->format('Y');
                if ($lastReward !== now()->format('Y')) {
                    $customer->loyalty_points += $birthdayPoints;
                    $customer->birthday_rewarded_at = $today;
                    $awardedBirthdayPoints = true;
                    $this->sendBirthdayEmail($customer, $birthdayPoints);
                }
            }
        }

        $customer->save();

        return $this->corsResponse(
            $request,
            response()->json([
                'message' => 'Profile updated.',
                'awarded_profile_points' => $awardedProfilePoints,
                'awarded_birthday_points' => $awardedBirthdayPoints,
            ])
        );
    }

    public function profileOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    public function coupons(Request $request): Response
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
        $today = now()->toDateString();

        $coupons = Coupon::query()
            ->where('status', 'active')
            ->where(function ($query) use ($today) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->where(function ($query) use ($tier) {
                if ($tier) {
                    $query->whereNull('tier_id')
                        ->orWhere('tier_id', $tier->id);
                } else {
                    $query->whereNull('tier_id');
                }
            })
            ->orderBy('points_value')
            ->get()
            ->map(function (Coupon $coupon) {
                return [
                    'id' => $coupon->id,
                    'title' => $coupon->title,
                    'points_value' => (int) ($coupon->points_value ?? 0),
                    'description' => (string) ($coupon->description ?? ''),
                ];
            })
            ->values();

        return $this->corsResponse(
            $request,
            response()->json([
                'points' => $points,
                'tier' => $tier?->title,
                'coupons' => $coupons,
            ])
        );
    }

    public function couponsOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    public function myCoupons(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        $records = CustomerCoupon::query()
            ->with('coupon')
            ->where('customer_id', $customer->id)
            ->orderByDesc('redeemed_at')
            ->get();

        $coupons = $records->map(function (CustomerCoupon $record) {
            return $this->formatRedemption($record);
        })->values();

        return $this->corsResponse(
            $request,
            response()->json([
                'coupons' => $coupons,
            ])
        );
    }

    public function myCouponsOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    public function redeemCoupon(Request $request, Coupon $coupon, ShopifyDiscountService $shopifyDiscounts): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        $today = now()->toDateString();
        $pointsCost = (int) ($coupon->points_value ?? 0);
        $tier = $customer->tier ?? $this->resolveTier((int) ($customer->loyalty_points ?? 0));
        $tierId = $tier?->id;

        if ($coupon->status !== 'active') {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Coupon is not active.'], 422)
            );
        }

        if ($coupon->start_date && $coupon->start_date->format('Y-m-d') > $today) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Coupon is not available yet.'], 422)
            );
        }

        if ($coupon->end_date && $coupon->end_date->format('Y-m-d') < $today) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Coupon has expired.'], 422)
            );
        }

        if ($coupon->tier_id && $coupon->tier_id !== $tierId) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Coupon is not available for your tier.'], 403)
            );
        }

        if (!$coupon->shopify_price_rule_id) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Coupon is not ready for redemption.'], 422)
            );
        }

        $updated = DB::transaction(function () use ($customer, $pointsCost, $coupon, $shopifyDiscounts) {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return [null, 'Customer not found.', null];
            }

            $currentPoints = (int) ($lockedCustomer->loyalty_points ?? 0);
            if ($currentPoints < $pointsCost) {
                return [null, 'Not enough points to redeem this coupon.', null];
            }

            $code = $this->generateRedeemCode($coupon);

            try {
                $shopifyDiscounts->createDiscountCode((int) $coupon->shopify_price_rule_id, $code);
            } catch (\Throwable $exception) {
                return [null, $exception->getMessage(), null];
            }

            $lockedCustomer->loyalty_points = $currentPoints - $pointsCost;
            $lockedCustomer->save();

            $record = CustomerCoupon::create([
                'customer_id' => $lockedCustomer->id,
                'coupon_id' => $coupon->id,
                'points_spent' => $pointsCost,
                'code' => $code,
                'status' => 'active',
                'redeemed_at' => now(),
                'expires_at' => $coupon->end_date,
            ]);

            return [$lockedCustomer, null, $record];
        });

        [$updatedCustomer, $message, $record] = $updated;
        if (!$updatedCustomer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $message ?? 'Unable to redeem coupon.'], 422)
            );
        }

        return $this->corsResponse(
            $request,
            response()->json([
                'message' => 'Coupon redeemed.',
                'code' => $record?->code,
                'points' => (int) ($updatedCustomer->loyalty_points ?? 0),
            ])
        );
    }

    public function widgetMyCoupons(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        $records = CustomerCoupon::query()
            ->with('coupon')
            ->where('customer_id', $customer->id)
            ->orderByDesc('redeemed_at')
            ->get();

        $coupons = $records->map(function (CustomerCoupon $record) {
            return $this->formatRedemption($record);
        })->values();

        return $this->corsResponse(
            $request,
            response()->json(['coupons' => $coupons])
        );
    }

    public function widgetMyCouponDetail(Request $request, CustomerCoupon $redemption): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        if ($redemption->customer_id !== $customer->id) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Unauthorized.'], 403)
            );
        }

        $redemption->loadMissing('coupon');

        return $this->corsResponse(
            $request,
            response()->json([
                'coupon' => $this->formatRedemption($redemption),
            ])
        );
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
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
            $response->headers->set('Access-Control-Max-Age', '600');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }

    private function pointRule(): PointRule
    {
        return PointRule::query()->firstOrCreate([], [
            'birthday_points' => 0,
            'profile_completion_points' => 0,
        ]);
    }

    private function sendBirthdayEmail(Customer $customer, int $points): void
    {
        if (!$customer->email) {
            return;
        }

        Mail::send('emails.birthday', [
            'customer' => $customer,
            'points' => $points,
        ], function ($message) use ($customer): void {
            $message->to($customer->email, $customer->full_name ?: $customer->email)
                ->subject('Happy Birthday from NexLoyal');
        });
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

    private function resolveRedemptionStatus(CustomerCoupon $record, $expiresAt = null): string
    {
        $status = strtolower((string) $record->status);
        if ($status === 'used' || $record->used_at) {
            return 'used';
        }

        if ($status === 'expired') {
            return 'expired';
        }

        $expiry = $expiresAt ? Carbon::parse($expiresAt) : null;
        if ($expiry && $expiry->isPast()) {
            return 'expired';
        }

        if ($status === 'in_progress') {
            return 'in_progress';
        }

        return 'unused';
    }

    private function formatRedemption(CustomerCoupon $record): array
    {
        $coupon = $record->coupon;
        $expiresAt = $record->expires_at ?? $coupon?->end_date;
        $status = $this->resolveRedemptionStatus($record, $expiresAt);

        return [
            'redemption_id' => $record->id,
            'coupon_id' => $record->coupon_id,
            'coupon_title' => $coupon?->title ?? 'Coupon',
            'coupon_value_label' => $coupon ? $this->formatCouponValueLabel($coupon) : null,
            'coupon_points' => (int) ($coupon?->points_value ?? 0),
            'shopify_discount_code' => $record->code,
            'status' => strtoupper($status),
            'purchased_at' => optional($record->redeemed_at)->toIso8601String(),
            'expires_at' => optional($expiresAt)->toIso8601String(),
            'used_at' => optional($record->used_at)->toIso8601String(),
        ];
    }

    private function formatCouponValueLabel(Coupon $coupon): string
    {
        if ($coupon->type === 'free-shipping') {
            return 'Free shipping';
        }

        if ($coupon->type === 'buy-x-get-y') {
            $buyQty = $coupon->buy_quantity ?: 1;
            $getQty = $coupon->get_quantity ?: 1;
            return "Buy {$buyQty} get {$getQty}";
        }

        $value = (float) ($coupon->value ?? 0);
        if ($coupon->value_type === 'percentage') {
            $label = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
        } elseif ($coupon->value_type === 'fixed') {
            $label = '$'.number_format($value, 2);
        } else {
            $label = 'No value';
        }

        $suffix = $coupon->type === 'amount-product' ? ' off product' : ' off order';

        return $label.$suffix;
    }

    private function generateRedeemCode(Coupon $coupon): string
    {
        $prefix = strtoupper(Str::slug($coupon->title));
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', $prefix), 0, 8);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = trim($prefix.'-'.strtoupper(Str::random(8)), '-');
            $exists = Coupon::where('code', $code)->exists()
                || CustomerCoupon::where('code', $code)->exists();
            if (!$exists) {
                return $code;
            }
        }

        return strtoupper(Str::random(12));
    }
}
