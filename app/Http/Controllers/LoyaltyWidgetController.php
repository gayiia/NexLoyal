<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Models\ChatPollVote;
use App\Models\ChatSetting;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\MysteryBox;
use App\Models\PointsTransaction;
use App\Models\PointRule;
use App\Models\Tier;
use App\Services\ShopifyCustomerService;
use App\Services\ShopifyDiscountService;
use App\Support\PointsHistoryFormatter;
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

        $this->awardWelcomePoints($customer);

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

    public function mysteryBoxPage(Request $request)
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return view('loyalty.mystery-box', ['error' => $error ?? 'Unauthorized.']);
        }

        return view('loyalty.mystery-box', [
            'customer' => $customer,
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
        $pendingPoints = (int) ($customer->points_pending ?? 0);
        $tier = $customer->tier ?? $this->resolveTier($points);

        return $this->corsResponse(
            $request,
            response()->json([
                'name' => $customer->full_name ?: 'Customer',
                'email' => $customer->email,
                'points' => $points,
                'points_pending' => $pendingPoints,
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
                PointsTransaction::create([
                    'customer_id' => $customer->id,
                    'points' => $profilePoints,
                    'status' => 'APPROVED',
                    'source' => 'RULE',
                    'source_type' => 'PROFILE',
                    'type' => 'EARN',
                    'event_key' => 'profile_completion',
                    'reason' => 'Profile completed',
                ]);
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
                    PointsTransaction::create([
                        'customer_id' => $customer->id,
                        'points' => $birthdayPoints,
                        'status' => 'APPROVED',
                        'source' => 'RULE',
                        'source_type' => 'BIRTHDAY',
                        'type' => 'EARN',
                        'event_key' => 'birthday_reward:'.now()->format('Y'),
                        'reason' => 'Birthday reward',
                    ]);
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
            ->where('is_mystery_box_coupon', false)
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

    public function earnSocialOptions(Request $request): Response
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
                'source' => 'REDEEM',
                'redeemed_at' => now(),
                'expires_at' => $coupon->end_date,
            ]);

            PointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'points' => $pointsCost,
                'status' => 'APPROVED',
                'source' => 'COUPON',
                'source_type' => 'COUPON',
                'type' => 'SPEND',
                'event_key' => 'coupon_redeem:'.$record->id,
                'reason' => 'Coupon redeemed',
                'reference_type' => 'CustomerCoupon',
                'reference_id' => (string) $record->id,
                'meta' => [
                    'coupon_title' => $coupon->title,
                    'coupon_id' => $coupon->id,
                ],
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

    public function mysteryBoxActive(Request $request): Response
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
        $tierId = $tier?->id;

        if (!$tierId) {
            return $this->corsResponse(
                $request,
                response()->json(['box' => null, 'message' => 'No tier available for this customer.'])
            );
        }

        $box = $this->findActiveMysteryBoxForTier($tierId);
        if (!$box) {
            return $this->corsResponse(
                $request,
                response()->json(['box' => null, 'message' => 'No active mystery box available.'])
            );
        }

        $eligibility = $this->mysteryBoxEligibility($customer->id, $box);
        $items = $box->items()->with('coupon')->get();

        return $this->corsResponse(
            $request,
            response()->json([
                'box' => [
                    'id' => $box->id,
                    'name' => $box->name,
                    'claim_rule' => $box->claim_rule,
                    'can_claim' => $eligibility['can_claim'],
                    'next_claim_at' => $eligibility['next_claim_at']
                        ? $eligibility['next_claim_at']->toIso8601String()
                        : null,
                ],
                'wheel_items' => $items->map(function ($item) {
                    return [
                        'title' => $item->coupon?->title ?? 'Reward',
                    ];
                })->values(),
            ])
        );
    }

    public function mysteryBoxClaim(Request $request, MysteryBox $mysteryBox, ShopifyDiscountService $shopifyDiscounts): Response
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
        $tierId = $tier?->id;

        if (!$tierId) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'No tier available for this customer.'], 422)
            );
        }

        if (!$this->isMysteryBoxActive($mysteryBox, $tierId)) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Mystery box not available.'], 422)
            );
        }

        $eligibility = $this->mysteryBoxEligibility($customer->id, $mysteryBox);
        if (!$eligibility['can_claim']) {
            return $this->corsResponse(
                $request,
                response()->json([
                    'message' => 'Already claimed.',
                    'next_claim_at' => $eligibility['next_claim_at']
                        ? $eligibility['next_claim_at']->toIso8601String()
                        : null,
                ], 409)
            );
        }

        $items = $mysteryBox->items()->with('coupon')->get();
        if ($items->isEmpty()) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Mystery box has no rewards.'], 422)
            );
        }

        $selectedItem = $this->pickMysteryBoxItem($items);
        $coupon = $selectedItem?->coupon;
        if (!$coupon || !$coupon->shopify_price_rule_id) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Reward is not ready.'], 422)
            );
        }

        $code = $this->generateRedeemCode($coupon);

        try {
            $shopifyDiscounts->createDiscountCode((int) $coupon->shopify_price_rule_id, $code);
        } catch (\Throwable $exception) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $exception->getMessage()], 502)
            );
        }

        $record = CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'points_spent' => 0,
            'code' => $code,
            'status' => 'active',
            'source' => 'MYSTERY_BOX',
            'mystery_box_id' => $mysteryBox->id,
            'redeemed_at' => now(),
            'expires_at' => $coupon->end_date,
        ]);

        return $this->corsResponse(
            $request,
            response()->json([
                'won' => [
                    'coupon_id' => $coupon->id,
                    'title' => $coupon->title,
                    'code' => $code,
                    'expires_at' => optional($coupon->end_date)->toIso8601String(),
                    'redemption_id' => $record->id,
                ],
                'wheel_items' => $items->map(function ($item) {
                    return [
                        'title' => $item->coupon?->title ?? 'Reward',
                    ];
                })->values(),
            ])
        );
    }

    public function mysteryBoxClaimOptions(Request $request): Response
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
            'welcome_points' => 0,
            'birthday_points' => 0,
            'profile_completion_points' => 0,
            'amount_per_point' => 100,
        ]);
    }

    private function awardWelcomePoints(Customer $customer): void
    {
        $rule = $this->pointRule();
        $points = (int) ($rule->welcome_points ?? 0);
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $points): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            $exists = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('event_key', 'welcome_bonus')
                ->exists();

            if ($exists) {
                return;
            }

            PointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'points' => $points,
                'status' => 'APPROVED',
                'source' => 'RULE',
                'source_type' => 'REGISTER',
                'type' => 'EARN',
                'event_key' => 'welcome_bonus',
                'reason' => 'Welcome bonus',
            ]);

            $lockedCustomer->loyalty_points = (int) ($lockedCustomer->loyalty_points ?? 0) + $points;
            $lockedCustomer->save();
        });
    }

    public function earnRules(Request $request): Response
    {
        $rule = $this->pointRule();

        return $this->corsResponse(
            $request,
            response()->json([
                'general' => [
                    'welcome_points' => (int) ($rule->welcome_points ?? 0),
                    'birthday_points' => (int) ($rule->birthday_points ?? 0),
                    'profile_completion_points' => (int) ($rule->profile_completion_points ?? 0),
                    'amount_per_point' => (int) ($rule->amount_per_point ?? 100),
                ],
                'social' => [
                    'linkedin' => [
                        'url' => (string) ($rule->social_linkedin_url ?? ''),
                        'points' => (int) ($rule->social_linkedin_points ?? 0),
                    ],
                    'tiktok' => [
                        'url' => (string) ($rule->social_tiktok_url ?? ''),
                        'points' => (int) ($rule->social_tiktok_points ?? 0),
                    ],
                    'facebook' => [
                        'url' => (string) ($rule->social_facebook_url ?? ''),
                        'points' => (int) ($rule->social_facebook_points ?? 0),
                    ],
                    'x' => [
                        'url' => (string) ($rule->social_x_url ?? ''),
                        'points' => (int) ($rule->social_x_points ?? 0),
                    ],
                    'instagram' => [
                        'url' => (string) ($rule->social_instagram_url ?? ''),
                        'points' => (int) ($rule->social_instagram_points ?? 0),
                    ],
                    'youtube' => [
                        'url' => (string) ($rule->social_youtube_url ?? ''),
                        'points' => (int) ($rule->social_youtube_points ?? 0),
                    ],
                ],
            ])
        );
    }

    public function earnStatus(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        $socialPlatforms = ['linkedin', 'tiktok', 'facebook', 'x', 'instagram', 'youtube'];
        $socialAwarded = [];
        foreach ($socialPlatforms as $platform) {
            $eventKey = "social:{$platform}";
            $socialAwarded[$platform] = PointsTransaction::query()
                ->where('customer_id', $customer->id)
                ->where('event_key', $eventKey)
                ->exists();
        }

        $welcomeAwarded = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('event_key', 'welcome_bonus')
            ->exists();

        $birthdayAwarded = false;
        if ($customer->birthday_rewarded_at) {
            $birthdayAwarded = $customer->birthday_rewarded_at->format('Y') === now()->format('Y');
        }

        return $this->corsResponse(
            $request,
            response()->json([
                'welcome_awarded' => $welcomeAwarded,
                'birthday_awarded_this_year' => $birthdayAwarded,
                'profile_completion_awarded' => (bool) $customer->profile_completed_at,
                'social_awarded' => $socialAwarded,
                'points_available' => (int) ($customer->loyalty_points ?? 0),
                'points_pending' => (int) ($customer->points_pending ?? 0),
            ])
        );
    }

    public function earnSocial(Request $request): Response
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
            'platform' => ['required', 'in:linkedin,tiktok,facebook,x,instagram,youtube'],
        ]);

        $rule = $this->pointRule();
        $platform = $validated['platform'];

        $platformConfig = [
            'linkedin' => ['url' => $rule->social_linkedin_url, 'points' => $rule->social_linkedin_points],
            'tiktok' => ['url' => $rule->social_tiktok_url, 'points' => $rule->social_tiktok_points],
            'facebook' => ['url' => $rule->social_facebook_url, 'points' => $rule->social_facebook_points],
            'x' => ['url' => $rule->social_x_url, 'points' => $rule->social_x_points],
            'instagram' => ['url' => $rule->social_instagram_url, 'points' => $rule->social_instagram_points],
            'youtube' => ['url' => $rule->social_youtube_url, 'points' => $rule->social_youtube_points],
        ];

        $config = $platformConfig[$platform] ?? null;
        $points = (int) ($config['points'] ?? 0);
        $url = trim((string) ($config['url'] ?? ''));

        if ($points <= 0 || $url === '') {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Reward not configured.'], 422)
            );
        }

        $eventKey = "social:{$platform}";

        $awarded = false;
        DB::transaction(function () use ($customer, $eventKey, $points, $platform, &$awarded) {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            $exists = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('event_key', $eventKey)
                ->exists();

            if ($exists) {
                return;
            }

            PointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'points' => $points,
                'status' => 'APPROVED',
                'source' => 'RULE',
                'source_type' => 'SOCIAL',
                'type' => 'EARN',
                'event_key' => $eventKey,
                'reason' => "Social reward: {$platform}",
                'meta' => [
                    'platform' => ucfirst($platform),
                ],
            ]);

            $lockedCustomer->loyalty_points += $points;
            $lockedCustomer->save();
            $awarded = true;
        });

        $customer->refresh();

        return $this->corsResponse(
            $request,
            response()->json([
                'awarded' => $awarded,
                'points' => (int) ($customer->loyalty_points ?? 0),
                'pending_points' => (int) ($customer->points_pending ?? 0),
            ])
        );
    }

    public function pointsHistory(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        $filter = strtolower((string) $request->query('filter', 'all'));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 50) : 20;

        $query = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        if ($filter === 'earned') {
            $query->where('type', 'EARN');
        } elseif ($filter === 'redeemed') {
            $query->where('type', 'SPEND');
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $data = $paginator->getCollection()->map(function (PointsTransaction $transaction) {
            return PointsHistoryFormatter::format($transaction);
        })->values();

        return $this->corsResponse(
            $request,
            response()->json([
                'data' => $data,
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ])
        );
    }

    public function chatMessages(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        [$customer, $error] = $this->customerFromToken($token);

        if (!$customer) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $error ?? 'Unauthorized.'], 401)
            );
        }

        $settings = ChatSetting::firstOrCreate(
            ['store_id' => null],
            ['enabled' => false, 'allowed_tiers' => []]
        );

        if (!$settings->enabled) {
            return $this->corsResponse(
                $request,
                response()->json([
                    'data' => [],
                    'meta' => ['enabled' => false, 'allowed' => false],
                ])
            );
        }

        $points = (int) ($customer->loyalty_points ?? 0);
        $tier = $customer->tier ?? $this->resolveTier($points);
        $tierId = (int) ($tier?->id ?? 0);

        $allowedTiers = array_map('intval', $settings->allowed_tiers ?? []);
        if (!$tierId || ($allowedTiers && !in_array($tierId, $allowedTiers, true))) {
            return $this->corsResponse(
                $request,
                response()->json([
                    'data' => [],
                    'meta' => ['enabled' => true, 'allowed' => false],
                ])
            );
        }

        $limit = (int) $request->query('limit', 30);
        $limit = $limit > 0 ? min($limit, 50) : 30;
        $cursor = (int) $request->query('cursor', 0);

        $query = ChatMessage::query()
            ->whereNotNull('sent_at')
            ->where(function ($query) use ($tierId) {
                $query->whereNull('tier_visibility')
                    ->orWhereJsonLength('tier_visibility', 0)
                    ->orWhereJsonContains('tier_visibility', $tierId);
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('id');

        if ($cursor > 0) {
            $query->where('id', '<', $cursor);
        }

        $messages = $query->with(['attachments', 'poll.options'])->limit($limit)->get();
        $pollIds = $messages->pluck('poll.id')->filter()->values()->all();

        $votes = [];
        if ($pollIds) {
            $votes = ChatPollVote::query()
                ->where('customer_id', $customer->id)
                ->whereIn('chat_poll_id', $pollIds)
                ->get()
                ->keyBy('chat_poll_id');
        }

        $data = $messages->map(function (ChatMessage $message) use ($votes) {
            $payload = [
                'id' => $message->id,
                'type' => $message->type,
                'title' => $message->title,
                'body' => $message->body,
                'sent_at' => $message->sent_at?->toIso8601String(),
                'attachments' => $message->attachments
                    ->map(function ($attachment) {
                        return [
                            'url' => $attachment->resolved_url,
                            'type' => $attachment->file_type,
                        ];
                    })
                    ->filter(function ($attachment) {
                        return !empty($attachment['url']);
                    })
                    ->values(),
            ];

            if ($message->poll) {
                $vote = $votes[$message->poll->id] ?? null;
                $payload['poll'] = [
                    'poll_id' => $message->poll->id,
                    'closes_at' => $message->poll->closes_at?->toIso8601String(),
                    'options' => $message->poll->options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'label' => $option->label,
                        ];
                    })->values(),
                    'my_vote_option_id' => $vote?->option_id,
                ];
            }

            return $payload;
        })->values();

        $nextCursor = $messages->count() === $limit ? $messages->last()?->id : null;

        return $this->corsResponse(
            $request,
            response()->json([
                'data' => $data,
                'meta' => [
                    'next_cursor' => $nextCursor,
                    'enabled' => true,
                    'allowed' => true,
                ],
            ])
        );
    }

    public function chatPollVote(Request $request, ChatPoll $poll): Response
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
            'option_id' => ['required', 'integer', 'exists:chat_poll_options,id'],
        ]);

        $poll->load('message');
        if (!$poll->message) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Poll not found.'], 404)
            );
        }

        $settings = ChatSetting::firstOrCreate(
            ['store_id' => null],
            ['enabled' => false, 'allowed_tiers' => []]
        );
        if (!$settings->enabled) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Chat is disabled.'], 403)
            );
        }

        $points = (int) ($customer->loyalty_points ?? 0);
        $tier = $customer->tier ?? $this->resolveTier($points);
        $tierId = (int) ($tier?->id ?? 0);
        $allowedTiers = array_map('intval', $settings->allowed_tiers ?? []);
        if (!$tierId || ($allowedTiers && !in_array($tierId, $allowedTiers, true))) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Not eligible.'], 403)
            );
        }

        $visibility = array_map('intval', $poll->message->tier_visibility ?? []);
        if ($visibility && !in_array($tierId, $visibility, true)) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Not eligible.'], 403)
            );
        }

        if ($poll->closes_at && $poll->closes_at->isPast()) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Poll closed.'], 422)
            );
        }

        $existing = ChatPollVote::query()
            ->where('chat_poll_id', $poll->id)
            ->where('customer_id', $customer->id)
            ->first();

        if ($existing) {
            return $this->corsResponse(
                $request,
                response()->json([
                    'success' => true,
                    'my_vote_option_id' => (int) $existing->option_id,
                ])
            );
        }

        $optionId = (int) $validated['option_id'];
        if (!$poll->options()->where('id', $optionId)->exists()) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Invalid option.'], 422)
            );
        }

        ChatPollVote::create([
            'store_id' => null,
            'chat_poll_id' => $poll->id,
            'option_id' => $optionId,
            'customer_id' => $customer->id,
            'voted_at' => now(),
        ]);

        return $this->corsResponse(
            $request,
            response()->json([
                'success' => true,
                'my_vote_option_id' => $optionId,
            ])
        );
    }

    public function chatPollVoteOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
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

    private function findActiveMysteryBoxForTier(int $tierId): ?MysteryBox
    {
        $now = now();

        return MysteryBox::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->whereJsonContains('tiers', $tierId)
            ->orderByDesc('id')
            ->first();
    }

    private function isMysteryBoxActive(MysteryBox $box, int $tierId): bool
    {
        if (!$box->is_active) {
            return false;
        }

        $now = now();
        if ($box->starts_at && $box->starts_at->gt($now)) {
            return false;
        }
        if ($box->ends_at && $box->ends_at->lt($now)) {
            return false;
        }

        $tierIds = array_map('intval', $box->tiers ?? []);
        if ($tierIds && !in_array($tierId, $tierIds, true)) {
            return false;
        }

        return true;
    }

    private function mysteryBoxEligibility(int $customerId, MysteryBox $box): array
    {
        $lastClaim = CustomerCoupon::query()
            ->where('customer_id', $customerId)
            ->where('source', 'MYSTERY_BOX')
            ->where('mystery_box_id', $box->id)
            ->orderByDesc('redeemed_at')
            ->first();

        if (!$lastClaim || !$lastClaim->redeemed_at) {
            return ['can_claim' => true, 'next_claim_at' => null];
        }

        $lastClaimAt = Carbon::parse($lastClaim->redeemed_at);
        $rule = strtoupper((string) $box->claim_rule);
        if ($rule === 'ONCE_EVER') {
            return ['can_claim' => false, 'next_claim_at' => null];
        }

        if ($rule === 'ONCE_PER_WEEK') {
            $next = $lastClaimAt->copy()->addWeek()->startOfDay();
            return [
                'can_claim' => now()->gte($next),
                'next_claim_at' => $next,
            ];
        }

        $next = $lastClaimAt->copy()->addDay()->startOfDay();
        return [
            'can_claim' => now()->gte($next),
            'next_claim_at' => $next,
        ];
    }

    private function pickMysteryBoxItem($items)
    {
        $totalWeight = $items->sum(function ($item) {
            return (int) ($item->weight ?? 1);
        });

        if ($totalWeight <= 0) {
            return $items->random();
        }

        $target = random_int(1, $totalWeight);
        $running = 0;
        foreach ($items as $item) {
            $running += (int) ($item->weight ?? 1);
            if ($target <= $running) {
                return $item;
            }
        }

        return $items->first();
    }
}
