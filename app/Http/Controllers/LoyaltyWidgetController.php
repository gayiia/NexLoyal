<?php

// This controller powers the customer-facing loyalty widget API and pages.
namespace App\Http\Controllers;

use App\Enums\PointsTransactionType;
use App\Enums\SourceType;
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
use App\Services\LoyaltyRulesEngine;
use App\Services\ShopifyCustomerService;
use App\Services\ShopifyDiscountService;
use App\Support\PointsHistoryFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

// This class issues widget tokens, serves customer data, and handles redemptions.
class LoyaltyWidgetController extends Controller
{
    // This injects the rules engine so widget actions can award points.
    public function __construct(private LoyaltyRulesEngine $rulesEngine)
    {
    }

    // This validates a Shopify customer and returns a signed widget token.
    public function token(Request $request, ShopifyCustomerService $shopify): Response
    {
        // These fields identify the Shopify customer and shop domain.
        // These validations ensure the profile fields are safe to store.
        $validated = $request->validate([
            'customer_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'shop_domain' => ['required', 'string'],
        ]);

        // This ensures the request comes from the configured Shopify store.
        $configuredDomain = strtolower((string) config('services.shopify.shop_domain'));
        if ($configuredDomain !== '' && strtolower($validated['shop_domain']) !== $configuredDomain) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Invalid shop domain.'], 403)
            );
        }

        // This fetches the Shopify customer to verify identity.
        $shopifyCustomer = $shopify->getCustomer($validated['customer_id']);
        $shopifyEmail = strtolower((string) ($shopifyCustomer['email'] ?? ''));

        // This verifies the email matches Shopify to prevent impersonation.
        if ($shopifyEmail === '' || $shopifyEmail !== strtolower($validated['email'])) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Customer verification failed.'], 403)
            );
        }

        // This mirrors the Shopify customer into the local database.
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

        // This awards welcome points if the customer is new.
        $this->rulesEngine->awardWelcomePoints($customer);

        // This issues a short-lived token the widget can use for API calls.
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

    // This handles CORS preflight for the token endpoint.
    public function tokenOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    // This renders the embedded loyalty dashboard for a verified customer.
    public function dashboard(Request $request)
    {
        $token = (string) $request->query('token', '');

        [$customer, $error] = $this->customerFromToken($token);
        if (!$customer) {
            return view('loyalty.dashboard', ['error' => $error ?? 'Unauthorized.']);
        }

        $points = $customer->loyalty_points ?? 0;
        $tier = $customer->tier ?? $this->rulesEngine->resolveTier($points);

        return view('loyalty.dashboard', [
            'customer' => $customer,
            'points' => $points,
            'tier' => $tier,
            'token' => $token,
        ]);
    }

    // This renders the mystery box page for a verified customer.
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

    // This returns core loyalty data for the widget UI.
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
        $tier = $customer->tier ?? $this->rulesEngine->resolveTier($points);

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

    // This handles CORS preflight for the data endpoint.
    public function dataOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    // This returns the customer's profile fields for the widget form.
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

    // This updates the customer profile in Shopify and locally, awarding profile points.
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

        // This merges provided fields with existing values.
        $updates = [
            'first_name' => $validated['first_name'] ?? $customer->first_name,
            'last_name' => $validated['last_name'] ?? $customer->last_name,
            'email' => $validated['email'],
            'birthday' => $validated['birthday'] ?? null,
            'phone' => $validated['phone'] ?? $customer->phone,
        ];

        // This normalizes blank phone values to null.
        if (array_key_exists('phone', $validated) && $updates['phone'] !== null) {
            $updates['phone'] = trim((string) $updates['phone']);
            if ($updates['phone'] === '') {
                $updates['phone'] = null;
            }
        }

        // This payload updates the Shopify customer record.
        $shopifyPayload = [
            'first_name' => $updates['first_name'],
            'last_name' => $updates['last_name'],
            'email' => $updates['email'],
        ];

        if (array_key_exists('phone', $validated)) {
            $shopifyPayload['phone'] = $updates['phone'];
        }

        try {
            // This syncs the profile change back to Shopify.
            $shopify->updateCustomer((string) $customer->shopify_id, $shopifyPayload);
        } catch (\Throwable $exception) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $exception->getMessage()], 502)
            );
        }

        // This updates the local customer record and applies any reward rules.
        $customer->fill($updates);

        $rule = $this->pointRule();
        $awardStatus = $this->rulesEngine->awardProfileAndBirthday($customer, $rule);
        $awardedProfilePoints = (bool) ($awardStatus['awarded_profile_points'] ?? false);
        $awardedBirthdayPoints = (bool) ($awardStatus['awarded_birthday_points'] ?? false);

        return $this->corsResponse(
            $request,
            response()->json([
                'message' => 'Profile updated.',
                'awarded_profile_points' => $awardedProfilePoints,
                'awarded_birthday_points' => $awardedBirthdayPoints,
            ])
        );
    }

    // This handles CORS preflight for the profile endpoint.
    public function profileOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    // This returns available coupons the customer can redeem.
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
        $tier = $customer->tier ?? $this->rulesEngine->resolveTier($points);
        $today = now()->toDateString();

        // This selects only active, non-mystery coupons available today and for the tier.
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

    // This handles CORS preflight for the coupons endpoint.
    public function couponsOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    // This returns coupons already redeemed by the customer.
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

    // This handles CORS preflight for the my-coupons endpoint.
    public function myCouponsOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    // This handles CORS preflight for social earning endpoints.
    public function earnSocialOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    // This redeems a coupon by spending points and creating a Shopify discount code.
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
        $tier = $customer->tier ?? $this->rulesEngine->resolveTier((int) ($customer->loyalty_points ?? 0));
        $tierId = $tier?->id;

        // These checks enforce coupon availability and eligibility.
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

        // This transaction spends points and creates the coupon redemption.
        $updated = DB::transaction(function () use ($customer, $pointsCost, $coupon, $shopifyDiscounts) {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return [null, 'Customer not found.', null];
            }

            // This prevents redemptions when points are insufficient.
            $currentPoints = (int) ($lockedCustomer->loyalty_points ?? 0);
            if ($currentPoints < $pointsCost) {
                return [null, 'Not enough points to redeem this coupon.', null];
            }

            // This generates a unique code and creates it in Shopify.
            $code = $this->generateRedeemCode($coupon);

            try {
                $shopifyDiscounts->createDiscountCode((int) $coupon->shopify_price_rule_id, $code);
            } catch (\Throwable $exception) {
                return [null, $exception->getMessage(), null];
            }

            // This updates the customer's points balance after redemption.
            $lockedCustomer->loyalty_points = $currentPoints - $pointsCost;
            $lockedCustomer->save();

            // This records the redemption locally for future use.
            $record = CustomerCoupon::create([
                'customer_id' => $lockedCustomer->id,
                'coupon_id' => $coupon->id,
                'points_spent' => $pointsCost,
                'code' => $code,
                'status' => 'active',
                'source' => SourceType::REDEEM->value,
                'redeemed_at' => now(),
                'expires_at' => $coupon->end_date,
            ]);

            // This logs the points spend in the transaction ledger.
            PointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'points' => $pointsCost,
                'status' => 'APPROVED',
                'source' => SourceType::COUPON->value,
                'source_type' => SourceType::COUPON->value,
                'type' => PointsTransactionType::SPEND->value,
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

    // This returns the customer's coupons for the embedded widget.
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

    // This returns details for a single redemption, scoped to the customer.
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

    // This returns the active mystery box status for the customer tier.
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
        $tier = $customer->tier ?? $this->rulesEngine->resolveTier($points);
        $tierId = $tier?->id;

        // A tier is required to determine which mystery box applies.
        if (!$tierId) {
            return $this->corsResponse(
                $request,
                response()->json(['box' => null, 'message' => 'No tier available for this customer.'])
            );
        }

        // This selects the latest active mystery box for the tier.
        $box = $this->findActiveMysteryBoxForTier($tierId);
        if (!$box) {
            return $this->corsResponse(
                $request,
                response()->json(['box' => null, 'message' => 'No active mystery box available.'])
            );
        }

        // This checks claim eligibility based on prior redemptions.
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

    // This claims a mystery box reward and issues a coupon or points award.
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
        $tier = $customer->tier ?? $this->rulesEngine->resolveTier($points);
        $tierId = $tier?->id;

        // The customer must have a tier to claim a mystery box.
        if (!$tierId) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'No tier available for this customer.'], 422)
            );
        }

        // This enforces that the selected box is currently active and allowed.
        if (!$this->isMysteryBoxActive($mysteryBox, $tierId)) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Mystery box not available.'], 422)
            );
        }

        // This enforces claim limits such as once per day or once ever.
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

        // A mystery box without rewards cannot be claimed.
        $items = $mysteryBox->items()->with('coupon')->get();
        if ($items->isEmpty()) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Mystery box has no rewards.'], 422)
            );
        }

        // This randomly selects a reward item using the configured weights.
        $selectedItem = $this->pickMysteryBoxItem($items);
        $coupon = $selectedItem?->coupon;
        if (!$coupon || !$coupon->shopify_price_rule_id) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Reward is not ready.'], 422)
            );
        }

        // This creates a unique discount code in Shopify for the reward.
        $code = $this->generateRedeemCode($coupon);

        try {
            $shopifyDiscounts->createDiscountCode((int) $coupon->shopify_price_rule_id, $code);
        } catch (\Throwable $exception) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => $exception->getMessage()], 502)
            );
        }

        // This records the reward redemption locally for tracking.
        $record = CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'points_spent' => 0,
            'code' => $code,
            'status' => 'active',
            'source' => SourceType::MYSTERY_BOX->value,
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

    // This handles CORS preflight for the mystery box claim endpoint.
    public function mysteryBoxClaimOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    // This decrypts the widget token and returns the matching customer or an error.
    private function customerFromToken(string $token): array
    {
        // A missing token cannot be authenticated.
        if ($token === '') {
            return [null, 'Missing token.'];
        }

        try {
            // This decrypts and parses the token payload.
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return [null, 'Invalid or expired token.'];
        }

        // This ensures the payload has the minimum required fields.
        if (!is_array($payload) || empty($payload['shopify_id'])) {
            return [null, 'Invalid token payload.'];
        }

        // This rejects expired tokens.
        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt < now()->timestamp) {
            return [null, 'Token expired. Please refresh the widget.'];
        }

        // This resolves the customer record for the widget session.
        $customer = Customer::where('shopify_id', (string) $payload['shopify_id'])->first();
        if (!$customer) {
            return [null, 'Customer not found.'];
        }

        return [$customer, null];
    }

    // This applies CORS headers when the request comes from the configured store.
    private function corsResponse(Request $request, Response $response): Response
    {
        $origin = $this->allowedOrigin($request);

        // These headers allow the widget to call the API from the storefront.
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
            $response->headers->set('Access-Control-Max-Age', '600');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }

    // This fetches the point rule configuration, creating defaults if missing.
    private function pointRule(): PointRule
    {
        return PointRule::query()->firstOrCreate([], [
            'welcome_points' => 0,
            'birthday_points' => 0,
            'profile_completion_points' => 0,
            'amount_per_point' => 100,
        ]);
    }

    // This returns current earning rules and social link settings.
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

    // This returns the customer's current earning status and history flags.
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

        // This checks which social rewards were already claimed.
        $socialPlatforms = ['linkedin', 'tiktok', 'facebook', 'x', 'instagram', 'youtube'];
        $socialAwarded = [];
        foreach ($socialPlatforms as $platform) {
            $eventKey = "social:{$platform}";
            $socialAwarded[$platform] = PointsTransaction::query()
                ->where('customer_id', $customer->id)
                ->where('event_key', $eventKey)
                ->exists();
        }

        // This checks whether the welcome bonus was already awarded.
        $welcomeAwarded = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('event_key', 'welcome_bonus')
            ->exists();

        // This checks whether a birthday reward was given in the current year.
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

    // This awards points for a social platform visit when configured.
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
        $result = $this->rulesEngine->awardSocialVisit($customer, $platform, $rule);
        if (($result['awarded'] ?? false) === false && ($result['message'] ?? '') === 'Social reward not configured.') {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Reward not configured.'], 422)
            );
        }

        $customer->refresh();

        return $this->corsResponse(
            $request,
            response()->json([
                'awarded' => (bool) ($result['awarded'] ?? false),
                'points' => (int) ($customer->loyalty_points ?? 0),
                'pending_points' => (int) ($customer->points_pending ?? 0),
            ])
        );
    }

    // This returns paginated points history entries for the customer.
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
            $query->where('type', PointsTransactionType::EARN->value);
        } elseif ($filter === 'redeemed') {
            $query->where('type', PointsTransactionType::SPEND->value);
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

    // This returns eligible exclusive chat messages for the customer.
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

        // This short-circuits when the chat feature is disabled.
        if (!$settings->enabled) {
            return $this->corsResponse(
                $request,
                response()->json([
                    'data' => [],
                    'meta' => ['enabled' => false, 'allowed' => false],
                ])
            );
        }

        // This enforces tier eligibility for exclusive chat.
        $points = (int) ($customer->loyalty_points ?? 0);
        $tier = $customer->tier ?? $this->rulesEngine->resolveTier($points);
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

        // These pagination controls limit payload size for the widget.
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

        // This cursor paginates backwards by message ID.
        if ($cursor > 0) {
            $query->where('id', '<', $cursor);
        }

        $messages = $query->with(['attachments', 'poll.options'])->limit($limit)->get();
        $pollIds = $messages->pluck('poll.id')->filter()->values()->all();

        $votes = [];
        if ($pollIds) {
            // This preloads the customer's votes for the returned polls.
            $votes = ChatPollVote::query()
                ->where('customer_id', $customer->id)
                ->whereIn('chat_poll_id', $pollIds)
                ->get()
                ->keyBy('chat_poll_id');
        }

        $data = $messages->map(function (ChatMessage $message) use ($votes) {
            // This formats messages into the payload expected by the widget.
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
                // This adds poll details and the customer's existing vote if any.
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

        // This uses the last ID as the next cursor when the page is full.
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

    // This records a poll vote if the customer is eligible.
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
        // This blocks voting when the chat feature is disabled.
        if (!$settings->enabled) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Chat is disabled.'], 403)
            );
        }

        $points = (int) ($customer->loyalty_points ?? 0);
        $tier = $customer->tier ?? $this->rulesEngine->resolveTier($points);
        $tierId = (int) ($tier?->id ?? 0);
        $allowedTiers = array_map('intval', $settings->allowed_tiers ?? []);
        // This checks tier eligibility for voting.
        if (!$tierId || ($allowedTiers && !in_array($tierId, $allowedTiers, true))) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Not eligible.'], 403)
            );
        }

        $visibility = array_map('intval', $poll->message->tier_visibility ?? []);
        // This enforces message-specific visibility rules.
        if ($visibility && !in_array($tierId, $visibility, true)) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Not eligible.'], 403)
            );
        }

        // This prevents voting on closed polls.
        if ($poll->closes_at && $poll->closes_at->isPast()) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Poll closed.'], 422)
            );
        }

        // This prevents duplicate votes for the same poll.
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
        // This confirms the selected option belongs to this poll.
        if (!$poll->options()->where('id', $optionId)->exists()) {
            return $this->corsResponse(
                $request,
                response()->json(['message' => 'Invalid option.'], 422)
            );
        }

        // This creates the vote record.
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

    // This handles CORS preflight for poll voting.
    public function chatPollVoteOptions(Request $request): Response
    {
        return $this->corsResponse($request, response()->noContent());
    }

    // This returns the allowed CORS origin if it matches the configured shop domain.
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

    // This determines a normalized redemption status for a coupon record.
    private function resolveRedemptionStatus(CustomerCoupon $record, $expiresAt = null): string
    {
        $status = strtolower((string) $record->status);
        // Used takes precedence when either status or timestamp indicates usage.
        if ($status === 'used' || $record->used_at) {
            return 'used';
        }

        if ($status === 'expired') {
            return 'expired';
        }

        // This treats past expiration as expired even if status was not updated.
        $expiry = $expiresAt ? Carbon::parse($expiresAt) : null;
        if ($expiry && $expiry->isPast()) {
            return 'expired';
        }

        if ($status === 'in_progress') {
            return 'in_progress';
        }

        return 'unused';
    }

    // This formats a coupon redemption into the widget-friendly payload.
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

    // This builds a human-readable label for the coupon's value.
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

        // This formats fixed and percentage values into a readable label.
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

    // This generates a unique, readable coupon code for Shopify.
    private function generateRedeemCode(Coupon $coupon): string
    {
        $prefix = strtoupper(Str::slug($coupon->title));
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', $prefix), 0, 8);

        // This retries to avoid collisions with existing codes.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = trim($prefix.'-'.strtoupper(Str::random(8)), '-');
            $exists = Coupon::where('code', $code)->exists()
                || CustomerCoupon::where('code', $code)->exists();
            if (!$exists) {
                return $code;
            }
        }

        // This fallback uses a fully random code if all attempts collide.
        return strtoupper(Str::random(12));
    }

    // This finds the most recent active mystery box for a specific tier.
    private function findActiveMysteryBoxForTier(int $tierId): ?MysteryBox
    {
        $now = now();

        // This picks the newest active box within the date window and tier list.
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

    // This checks if a mystery box is active and available to the given tier.
    private function isMysteryBoxActive(MysteryBox $box, int $tierId): bool
    {
        // The box must be flagged as active to be available.
        if (!$box->is_active) {
            return false;
        }

        $now = now();
        // This enforces the start and end windows if configured.
        if ($box->starts_at && $box->starts_at->gt($now)) {
            return false;
        }
        if ($box->ends_at && $box->ends_at->lt($now)) {
            return false;
        }

        // This enforces tier eligibility based on the box configuration.
        $tierIds = array_map('intval', $box->tiers ?? []);
        if ($tierIds && !in_array($tierId, $tierIds, true)) {
            return false;
        }

        return true;
    }

    // This evaluates claim limits and returns whether the customer can claim now.
    private function mysteryBoxEligibility(int $customerId, MysteryBox $box): array
    {
        // This finds the most recent claim for this customer and box.
        $lastClaim = CustomerCoupon::query()
            ->where('customer_id', $customerId)
            ->where('source', SourceType::MYSTERY_BOX->value)
            ->where('mystery_box_id', $box->id)
            ->orderByDesc('redeemed_at')
            ->first();

        if (!$lastClaim || !$lastClaim->redeemed_at) {
            return ['can_claim' => true, 'next_claim_at' => null];
        }

        $lastClaimAt = Carbon::parse($lastClaim->redeemed_at);
        $rule = strtoupper((string) $box->claim_rule);
        // This enforces the claim rule configured on the box.
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

    // This selects a mystery box item based on weight, with safe fallbacks.
    private function pickMysteryBoxItem($items)
    {
        // This computes total weight to drive weighted randomness.
        $totalWeight = $items->sum(function ($item) {
            return (int) ($item->weight ?? 1);
        });

        if ($totalWeight <= 0) {
            return $items->random();
        }

        // This chooses a weighted random item based on cumulative weights.
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
