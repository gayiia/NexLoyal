<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CustomerCoupon;
use App\Models\Tier;
use App\Services\ShopifyDiscountService;
use App\Services\ShopifyProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    public function index(Request $request, ShopifyProductService $shopifyProducts)
    {
        $query = Coupon::query()->with('tier');

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('value_type') && $request->input('value_type') !== 'all') {
            $query->where('value_type', $request->input('value_type'));
        }

        if ($request->filled('tier') && $request->input('tier') !== 'all') {
            $query->where('tier_id', $request->input('tier'));
        }

        if ($request->filled('start_period')) {
            $query->whereDate('start_date', '>=', $request->input('start_period'));
        }

        if ($request->filled('end_period')) {
            $query->whereDate('end_date', '<=', $request->input('end_period'));
        }

        if ($request->filled('points') && $request->input('points') !== 'all') {
            $points = $request->input('points');
            if ($points === 'under-200') {
                $query->where('points_value', '<', 200);
            } elseif ($points === '200-500') {
                $query->whereBetween('points_value', [200, 500]);
            } elseif ($points === '500-1000') {
                $query->whereBetween('points_value', [500, 1000]);
            } elseif ($points === '1000-plus') {
                $query->where('points_value', '>=', 1000);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $coupons = $query->orderByDesc('id')->paginate($perPage)->withQueryString();
        $tiers = Tier::query()->orderBy('min_points')->get();

        $products = [];
        $productError = null;
        try {
            $products = $shopifyProducts->listProducts();
        } catch (\Throwable $exception) {
            $productError = $exception->getMessage();
        }

        return view('coupons', compact('coupons', 'tiers', 'products', 'productError'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:amount-order,amount-product,buy-x-get-y,free-shipping'],
            'value_type' => ['nullable', 'in:percentage,fixed,none'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'points_value' => ['required', 'integer', 'min:0'],
            'is_mystery_box_coupon' => ['nullable', 'boolean'],
            'tier_id' => ['nullable', 'exists:tiers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'product_ids' => ['required_if:type,amount-product', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
            'buy_product_ids' => ['required_if:type,buy-x-get-y', 'array', 'min:1'],
            'buy_product_ids.*' => ['integer'],
            'get_product_ids' => ['required_if:type,buy-x-get-y', 'array', 'min:1'],
            'get_product_ids.*' => ['integer'],
            'buy_quantity' => ['required_if:type,buy-x-get-y', 'integer', 'min:1'],
            'get_quantity' => ['required_if:type,buy-x-get-y', 'integer', 'min:1'],
            'buyx_discount_type' => ['required_if:type,buy-x-get-y', 'in:percentage,amount,free'],
            'buyx_discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['value_type'] = $validated['value_type'] ?? null;
        $validated['value'] = $validated['value'] ?? null;
        $validated['buyx_discount_type'] = $validated['buyx_discount_type'] ?? null;
        $validated['buyx_discount_value'] = $validated['buyx_discount_value'] ?? null;

        if (in_array($validated['type'], ['amount-order', 'amount-product'], true)) {
            if (!$request->filled('value_type') || $validated['value_type'] === 'none') {
                return back()
                    ->withErrors(['value_type' => 'Value type is required for this coupon type.'])
                    ->withInput();
            }
            if (!$request->filled('value')) {
                return back()
                    ->withErrors(['value' => 'Value is required for this coupon type.'])
                    ->withInput();
            }
        }

        if ($validated['type'] === 'buy-x-get-y') {
            if (($validated['buyx_discount_type'] ?? '') !== 'free' && !$request->filled('buyx_discount_value')) {
                return back()
                    ->withErrors(['buyx_discount_value' => 'Discount value is required for this option.'])
                    ->withInput();
            }

            $validated['value_type'] = $validated['buyx_discount_type'] === 'amount' ? 'fixed' : 'percentage';
            $validated['value'] = $validated['buyx_discount_type'] === 'free'
                ? 100
                : (float) $validated['buyx_discount_value'];
        }

        if (in_array($validated['type'], ['amount-order', 'amount-product'], true) && $validated['value_type'] === 'none') {
            return back()
                ->withErrors(['value_type' => 'Value type is required for this coupon type.'])
                ->withInput();
        }

        if ($validated['value_type'] === 'none') {
            $validated['value'] = null;
        }

        if ($validated['type'] === 'free-shipping') {
            $validated['value_type'] = 'percentage';
            $validated['value'] = 100;
        }

        $productIds = [];
        $buyProductIds = [];
        $getProductIds = [];
        $buyQuantity = null;
        $getQuantity = null;

        if ($validated['type'] === 'amount-product') {
            $productIds = array_map('intval', $validated['product_ids'] ?? []);
        }

        if ($validated['type'] === 'buy-x-get-y') {
            $buyProductIds = array_map('intval', $validated['buy_product_ids'] ?? []);
            $getProductIds = array_map('intval', $validated['get_product_ids'] ?? []);
            $buyQuantity = (int) $validated['buy_quantity'];
            $getQuantity = (int) $validated['get_quantity'];
        }

        Coupon::create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'value_type' => $validated['value_type'],
            'value' => $validated['value'],
            'points_value' => $validated['points_value'],
            'is_mystery_box_coupon' => $request->boolean('is_mystery_box_coupon'),
            'tier_id' => $validated['tier_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
            'status' => 'draft',
            'product_ids' => $productIds ?: null,
            'buy_product_ids' => $buyProductIds ?: null,
            'get_product_ids' => $getProductIds ?: null,
            'buy_quantity' => $buyQuantity,
            'get_quantity' => $getQuantity,
            'buyx_discount_type' => $validated['type'] === 'buy-x-get-y' ? $validated['buyx_discount_type'] : null,
            'buyx_discount_value' => $validated['type'] === 'buy-x-get-y' ? $validated['buyx_discount_value'] : null,
        ]);

        return redirect()->route('coupons');
    }

    public function edit(Request $request, Coupon $coupon, ShopifyProductService $shopifyProducts)
    {
        if ($coupon->status !== 'draft') {
            return redirect()->route('coupons')->withErrors(['coupon' => 'Active coupons cannot be edited.']);
        }

        $tiers = Tier::query()->orderBy('min_points')->get();
        $products = [];
        $productError = null;
        try {
            $products = $shopifyProducts->listProducts();
        } catch (\Throwable $exception) {
            $productError = $exception->getMessage();
        }

        return view('coupons-edit', compact('coupon', 'tiers', 'products', 'productError'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        if ($coupon->status !== 'draft') {
            return redirect()->route('coupons')->withErrors(['coupon' => 'Active coupons cannot be edited.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:amount-order,amount-product,buy-x-get-y,free-shipping'],
            'value_type' => ['nullable', 'in:percentage,fixed,none'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'points_value' => ['required', 'integer', 'min:0'],
            'is_mystery_box_coupon' => ['nullable', 'boolean'],
            'tier_id' => ['nullable', 'exists:tiers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'product_ids' => ['required_if:type,amount-product', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
            'buy_product_ids' => ['required_if:type,buy-x-get-y', 'array', 'min:1'],
            'buy_product_ids.*' => ['integer'],
            'get_product_ids' => ['required_if:type,buy-x-get-y', 'array', 'min:1'],
            'get_product_ids.*' => ['integer'],
            'buy_quantity' => ['required_if:type,buy-x-get-y', 'integer', 'min:1'],
            'get_quantity' => ['required_if:type,buy-x-get-y', 'integer', 'min:1'],
            'buyx_discount_type' => ['required_if:type,buy-x-get-y', 'in:percentage,amount,free'],
            'buyx_discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['value_type'] = $validated['value_type'] ?? null;
        $validated['value'] = $validated['value'] ?? null;
        $validated['buyx_discount_type'] = $validated['buyx_discount_type'] ?? null;
        $validated['buyx_discount_value'] = $validated['buyx_discount_value'] ?? null;

        if (in_array($validated['type'], ['amount-order', 'amount-product'], true)) {
            if (!$request->filled('value_type') || $validated['value_type'] === 'none') {
                return back()
                    ->withErrors(['value_type' => 'Value type is required for this coupon type.'])
                    ->withInput();
            }
            if (!$request->filled('value')) {
                return back()
                    ->withErrors(['value' => 'Value is required for this coupon type.'])
                    ->withInput();
            }
        }

        if ($validated['type'] === 'buy-x-get-y') {
            if (($validated['buyx_discount_type'] ?? '') !== 'free' && !$request->filled('buyx_discount_value')) {
                return back()
                    ->withErrors(['buyx_discount_value' => 'Discount value is required for this option.'])
                    ->withInput();
            }

            $validated['value_type'] = $validated['buyx_discount_type'] === 'amount' ? 'fixed' : 'percentage';
            $validated['value'] = $validated['buyx_discount_type'] === 'free'
                ? 100
                : (float) $validated['buyx_discount_value'];
        }

        if (in_array($validated['type'], ['amount-order', 'amount-product'], true) && $validated['value_type'] === 'none') {
            return back()
                ->withErrors(['value_type' => 'Value type is required for this coupon type.'])
                ->withInput();
        }

        if ($validated['value_type'] === 'none') {
            $validated['value'] = null;
        }

        if ($validated['type'] === 'free-shipping') {
            $validated['value_type'] = 'percentage';
            $validated['value'] = 100;
        }

        $coupon->update([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'value_type' => $validated['value_type'],
            'value' => $validated['value'],
            'points_value' => $validated['points_value'],
            'is_mystery_box_coupon' => $request->boolean('is_mystery_box_coupon'),
            'tier_id' => $validated['tier_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
            'product_ids' => $validated['type'] === 'amount-product' ? array_map('intval', $validated['product_ids'] ?? []) : null,
            'buy_product_ids' => $validated['type'] === 'buy-x-get-y' ? array_map('intval', $validated['buy_product_ids'] ?? []) : null,
            'get_product_ids' => $validated['type'] === 'buy-x-get-y' ? array_map('intval', $validated['get_product_ids'] ?? []) : null,
            'buy_quantity' => $validated['type'] === 'buy-x-get-y' ? (int) $validated['buy_quantity'] : null,
            'get_quantity' => $validated['type'] === 'buy-x-get-y' ? (int) $validated['get_quantity'] : null,
            'buyx_discount_type' => $validated['type'] === 'buy-x-get-y' ? $validated['buyx_discount_type'] : null,
            'buyx_discount_value' => $validated['type'] === 'buy-x-get-y' ? $validated['buyx_discount_value'] : null,
        ]);

        return redirect()->route('coupons');
    }

    public function activate(Coupon $coupon, ShopifyDiscountService $shopifyDiscounts)
    {
        if ($coupon->status === 'active') {
            return redirect()->route('coupons');
        }

        $startDate = Carbon::now();
        $endDate = Carbon::parse($coupon->end_date);
        $code = $coupon->code ?: $this->generateCode($coupon->title);

        if ($endDate->lt($startDate)) {
            return back()->withErrors(['shopify' => 'End date must be in the future to activate this coupon.']);
        }

        $payload = $this->buildPriceRulePayload([
            'title' => $coupon->title,
            'type' => $coupon->type,
            'value_type' => $coupon->value_type,
            'value' => $coupon->value,
            'product_ids' => $coupon->product_ids ?? [],
            'buy_product_ids' => $coupon->buy_product_ids ?? [],
            'get_product_ids' => $coupon->get_product_ids ?? [],
            'buy_quantity' => $coupon->buy_quantity ?? 1,
            'get_quantity' => $coupon->get_quantity ?? 1,
            'buyx_discount_type' => $coupon->buyx_discount_type,
            'buyx_discount_value' => $coupon->buyx_discount_value,
        ], $startDate, $endDate, $code);

        try {
            if ($coupon->shopify_price_rule_id) {
                $shopifyDiscounts->updatePriceRule((int) $coupon->shopify_price_rule_id, [
                    'starts_at' => $startDate->toIso8601String(),
                    'ends_at' => $endDate->toIso8601String(),
                ]);
                if (!$coupon->shopify_discount_code_id) {
                    $discountCode = $shopifyDiscounts->createDiscountCode((int) $coupon->shopify_price_rule_id, $code);
                    $coupon->shopify_discount_code_id = (string) ($discountCode['id'] ?? '');
                    $coupon->code = $code;
                }
            } else {
                $priceRule = $shopifyDiscounts->createPriceRule($payload);
                $discountCode = $shopifyDiscounts->createDiscountCode((int) $priceRule['id'], $code);
                $coupon->shopify_price_rule_id = (string) ($priceRule['id'] ?? '');
                $coupon->shopify_discount_code_id = (string) ($discountCode['id'] ?? '');
                $coupon->code = $code;
            }
        } catch (\Throwable $exception) {
            return back()->withErrors(['shopify' => $exception->getMessage()]);
        }

        $coupon->status = 'active';
        $coupon->save();

        return redirect()->route('coupons');
    }

    public function deactivate(Coupon $coupon, ShopifyDiscountService $shopifyDiscounts)
    {
        if ($coupon->status !== 'active') {
            return redirect()->route('coupons');
        }

        if ($coupon->shopify_price_rule_id) {
            try {
                $shopifyDiscounts->updatePriceRule((int) $coupon->shopify_price_rule_id, [
                    'ends_at' => Carbon::now()->toIso8601String(),
                ]);
            } catch (\Throwable $exception) {
                return back()->withErrors(['shopify' => $exception->getMessage()]);
            }
        }

        $coupon->status = 'paused';
        $coupon->save();

        return redirect()->route('coupons');
    }

    public function destroy(Coupon $coupon, ShopifyDiscountService $shopifyDiscounts)
    {
        if ($coupon->shopify_price_rule_id) {
            try {
                $shopifyDiscounts->deletePriceRule((int) $coupon->shopify_price_rule_id);
            } catch (\Throwable $exception) {
                return back()->withErrors(['shopify' => $exception->getMessage()]);
            }
        }

        $coupon->delete();

        return redirect()->route('coupons');
    }

    public function view(Request $request, Coupon $coupon)
    {
        if ($coupon->status !== 'active') {
            return redirect()->route('coupons')->withErrors(['coupon' => 'Coupon must be active to view redemptions.']);
        }

        $now = now();
        $baseQuery = CustomerCoupon::query()
            ->where('coupon_id', $coupon->id);

        $purchasedCount = (clone $baseQuery)->count();
        $usedCount = (clone $baseQuery)
            ->where(function ($query) {
                $query->where('status', 'used')
                    ->orWhereNotNull('used_at');
            })
            ->count();
        $expiredCount = (clone $baseQuery)
            ->where(function ($query) use ($now) {
                $query->where('status', 'expired')
                    ->orWhere(function ($query) use ($now) {
                        $query->whereNotNull('expires_at')
                            ->where('expires_at', '<', $now);
                    });
            })
            ->count();
        $unusedCount = (clone $baseQuery)
            ->where('status', 'active')
            ->whereNull('used_at')
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            })
            ->count();

        $query = CustomerCoupon::query()
            ->with('customer')
            ->where('coupon_id', $coupon->id);

        $status = $request->input('status', 'all');
        if ($status === 'used') {
            $query->where(function ($query) {
                $query->where('status', 'used')
                    ->orWhereNotNull('used_at');
            });
        } elseif ($status === 'in_progress') {
            $query->where('status', 'in_progress');
        } elseif ($status === 'expired') {
            $query->where(function ($query) use ($now) {
                $query->where('status', 'expired')
                    ->orWhere(function ($query) use ($now) {
                        $query->whereNotNull('expires_at')
                            ->where('expires_at', '<', $now);
                    });
            });
        } elseif ($status === 'unused') {
            $query->where('status', 'active')
                ->whereNull('used_at')
                ->where(function ($query) use ($now) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', $now);
                });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $redemptions = $query
            ->orderByDesc('redeemed_at')
            ->paginate(15)
            ->withQueryString();

        return view('coupon-view', [
            'coupon' => $coupon->load('tier'),
            'redemptions' => $redemptions,
            'summary' => [
                'purchased' => $purchasedCount,
                'used' => $usedCount,
                'unused' => $unusedCount,
                'expired' => $expiredCount,
            ],
        ]);
    }

    public function export(Request $request, Coupon $coupon)
    {
        if ($coupon->status !== 'active') {
            return redirect()->route('coupons')->withErrors(['coupon' => 'Coupon must be active to export redemptions.']);
        }

        $status = $request->input('status', 'all');
        $search = $request->input('search');
        $now = now();

        $query = CustomerCoupon::query()
            ->with('customer')
            ->where('coupon_id', $coupon->id);

        if ($status === 'used') {
            $query->where(function ($query) {
                $query->where('status', 'used')
                    ->orWhereNotNull('used_at');
            });
        } elseif ($status === 'in_progress') {
            $query->where('status', 'in_progress');
        } elseif ($status === 'expired') {
            $query->where(function ($query) use ($now) {
                $query->where('status', 'expired')
                    ->orWhere(function ($query) use ($now) {
                        $query->whereNotNull('expires_at')
                            ->where('expires_at', '<', $now);
                    });
            });
        } elseif ($status === 'unused') {
            $query->where('status', 'active')
                ->whereNull('used_at')
                ->where(function ($query) use ($now) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', $now);
                });
        }

        if ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $filename = 'coupon-redemptions-'.$coupon->id.'.csv';

        return response()->streamDownload(function () use ($query, $coupon, $now) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Number',
                'Customer Name',
                'Customer Email',
                'Coupon Code',
                'Status',
                'Purchased At',
                'Valid Until',
            ]);

            $rows = $query->orderByDesc('redeemed_at')->get();
            foreach ($rows as $index => $record) {
                $customer = $record->customer;
                $nameParts = array_filter([$customer?->first_name, $customer?->last_name]);
                $name = $nameParts ? implode(' ', $nameParts) : ($customer?->email ?? 'Customer');

                $statusLabel = 'Unused';
                if ($record->status === 'used' || $record->used_at) {
                    $statusLabel = 'Used';
                } else {
                    $expiresAt = $record->expires_at ?? $coupon->end_date;
                    if ($record->status === 'expired' || ($expiresAt && $expiresAt->lt($now))) {
                        $statusLabel = 'Expired';
                    } elseif ($record->status === 'in_progress') {
                        $statusLabel = 'In progress';
                    }
                }

                $expiresAt = $record->expires_at ?? $coupon->end_date;

                fputcsv($handle, [
                    $index + 1,
                    $name,
                    $customer?->email ?? '',
                    $record->code ?? '',
                    $statusLabel,
                    optional($record->redeemed_at)->toIso8601String(),
                    optional($expiresAt)->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function generateCode(string $title): string
    {
        $prefix = strtoupper(Str::slug($title));
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', $prefix), 0, 8);
        $suffix = strtoupper(Str::random(6));

        return trim($prefix.'-'.$suffix, '-');
    }

    private function buildPriceRulePayload(array $validated, Carbon $startDate, Carbon $endDate, string $code): array
    {
        $type = $validated['type'];
        $valueType = $validated['value_type'];
        $value = (float) ($validated['value'] ?? 0);

        $payload = [
            'title' => $validated['title'].' '.$code,
            'target_type' => 'line_item',
            'target_selection' => 'all',
            'allocation_method' => 'across',
            'value_type' => $valueType === 'fixed' ? 'fixed_amount' : 'percentage',
            'value' => $valueType === 'fixed' ? -$value : -$value,
            'customer_selection' => 'all',
            'starts_at' => $startDate->toIso8601String(),
            'ends_at' => $endDate->toIso8601String(),
            'once_per_customer' => false,
        ];

        if ($type === 'free-shipping') {
            $payload['target_type'] = 'shipping_line';
            $payload['value_type'] = 'percentage';
            $payload['value'] = -100.0;
            $payload['allocation_method'] = 'each';
        }

        if ($type === 'amount-product') {
            $payload['target_selection'] = 'entitled';
            $payload['entitled_product_ids'] = array_map('intval', $validated['product_ids'] ?? []);
            $payload['allocation_method'] = 'each';
        }

        if ($type === 'buy-x-get-y') {
            $payload['target_selection'] = 'entitled';
            $payload['entitled_product_ids'] = array_map('intval', $validated['get_product_ids'] ?? []);
            $payload['prerequisite_product_ids'] = array_map('intval', $validated['buy_product_ids'] ?? []);
            $payload['allocation_method'] = 'each';
            $discountType = $validated['buyx_discount_type'] ?? 'free';
            $discountValue = (float) ($validated['buyx_discount_value'] ?? 0);
            if ($discountType === 'amount') {
                $payload['value_type'] = 'fixed_amount';
                $payload['value'] = -$discountValue;
            } elseif ($discountType === 'percentage') {
                $payload['value_type'] = 'percentage';
                $payload['value'] = -$discountValue;
            } else {
                $payload['value_type'] = 'percentage';
                $payload['value'] = -100.0;
            }
            $payload['prerequisite_to_entitlement_quantity_ratio'] = [
                'prerequisite_quantity' => (int) ($validated['buy_quantity'] ?? 1),
                'entitled_quantity' => (int) ($validated['get_quantity'] ?? 1),
            ];
        }

        return $payload;
    }
}
