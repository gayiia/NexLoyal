<?php

// This controller manages coupon CRUD, activation, and redemption reporting.
namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CustomerCoupon;
use App\Models\Tier;
use App\Services\ShopifyDiscountService;
use App\Services\ShopifyProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// This class handles coupon listing, creation, Shopify sync, and exports.
class CouponController extends Controller
{
    // This lists coupons with filters and loads Shopify products for selection.
    public function index(Request $request, ShopifyProductService $shopifyProducts)
    {
        $query = Coupon::query()->with('tier');

        // These filters narrow the coupon list by type, status, tier, and points.
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

        // This enforces allowed page sizes to keep responses predictable.
        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $coupons = $query->orderByDesc('id')->paginate($perPage)->withQueryString();
        $tiers = Tier::query()->orderBy('min_points')->get();

        // This loads Shopify products for product-specific coupon types.
        $products = [];
        $productError = null;
        try {
            $products = $shopifyProducts->listProducts();
        } catch (\Throwable $exception) {
            $productError = $exception->getMessage();
        }

        return view('coupons', compact('coupons', 'tiers', 'products', 'productError'));
    }

    // This validates input and stores a new coupon in draft status.
    public function store(Request $request)
    {
        // These validations enforce business rules for each coupon type.
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:amount-order,amount-product,buy-x-get-y,free-shipping'],
            'value_type' => ['nullable', 'in:percentage,fixed,none'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'points_value' => ['required', 'integer', 'min:0'],
            'is_mystery_box_coupon' => ['nullable', 'boolean'],
            'is_ai_cluster_coupon' => ['nullable', 'boolean'],
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

        // These defaults normalize nullable fields before validation logic.
        $validated['value_type'] = $validated['value_type'] ?? null;
        $validated['value'] = $validated['value'] ?? null;
        $validated['buyx_discount_type'] = $validated['buyx_discount_type'] ?? null;
        $validated['buyx_discount_value'] = $validated['buyx_discount_value'] ?? null;

        // Amount-based coupons require a value type and value.
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

        // Buy X Get Y coupons need discount settings to compute value.
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

        // A "none" value type is stored as null for consistency.
        if ($validated['value_type'] === 'none') {
            $validated['value'] = null;
        }

        // Free shipping is represented as 100% off.
        if ($validated['type'] === 'free-shipping') {
            $validated['value_type'] = 'percentage';
            $validated['value'] = 100;
        }

        // These fields are only relevant for certain coupon types.
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

        // This creates the coupon in draft status before Shopify activation.
        Coupon::create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'value_type' => $validated['value_type'],
            'value' => $validated['value'],
            'points_value' => $validated['points_value'],
            'is_mystery_box_coupon' => $request->boolean('is_mystery_box_coupon'),
            'is_ai_cluster_coupon' => $request->boolean('is_ai_cluster_coupon'),
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

    // This shows the edit form for a draft coupon.
    public function edit(Request $request, Coupon $coupon, ShopifyProductService $shopifyProducts)
    {
        if ($coupon->status !== 'draft') {
            return redirect()->route('coupons')->withErrors(['coupon' => 'Active coupons cannot be edited.']);
        }

        // These lookups populate dropdowns in the edit form.
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

    // This updates a draft coupon with new configuration.
    public function update(Request $request, Coupon $coupon)
    {
        if ($coupon->status !== 'draft') {
            return redirect()->route('coupons')->withErrors(['coupon' => 'Active coupons cannot be edited.']);
        }

        // These validations enforce business rules for each coupon type.
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:amount-order,amount-product,buy-x-get-y,free-shipping'],
            'value_type' => ['nullable', 'in:percentage,fixed,none'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'points_value' => ['required', 'integer', 'min:0'],
            'is_mystery_box_coupon' => ['nullable', 'boolean'],
            'is_ai_cluster_coupon' => ['nullable', 'boolean'],
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

        // These defaults normalize nullable fields before validation logic.
        $validated['value_type'] = $validated['value_type'] ?? null;
        $validated['value'] = $validated['value'] ?? null;
        $validated['buyx_discount_type'] = $validated['buyx_discount_type'] ?? null;
        $validated['buyx_discount_value'] = $validated['buyx_discount_value'] ?? null;

        // Amount-based coupons require a value type and value.
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

        // Buy X Get Y coupons need discount settings to compute value.
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

        // A "none" value type is stored as null for consistency.
        if ($validated['value_type'] === 'none') {
            $validated['value'] = null;
        }

        // Free shipping is represented as 100% off.
        if ($validated['type'] === 'free-shipping') {
            $validated['value_type'] = 'percentage';
            $validated['value'] = 100;
        }

        // This updates the coupon fields, only keeping product data for relevant types.
        $coupon->update([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'value_type' => $validated['value_type'],
            'value' => $validated['value'],
            'points_value' => $validated['points_value'],
            'is_mystery_box_coupon' => $request->boolean('is_mystery_box_coupon'),
            'is_ai_cluster_coupon' => $request->boolean('is_ai_cluster_coupon'),
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

    // This activates a coupon in Shopify and marks it active locally.
    public function activate(Coupon $coupon, ShopifyDiscountService $shopifyDiscounts)
    {
        if ($coupon->status === 'active') {
            return redirect()->route('coupons');
        }

        // This validates dates and generates a code if needed.
        $startDate = Carbon::now();
        $endDate = Carbon::parse($coupon->end_date);
        $code = $coupon->code ?: $this->generateCode($coupon->title);

        if ($endDate->lt($startDate)) {
            return back()->withErrors(['shopify' => 'End date must be in the future to activate this coupon.']);
        }

        // This builds the Shopify price rule payload based on coupon settings.
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
            // This updates existing price rules or creates new ones in Shopify.
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

        // This updates local status after a successful Shopify sync.
        $coupon->status = 'active';
        $coupon->save();

        return redirect()->route('coupons');
    }

    // This pauses an active coupon by ending it in Shopify and locally.
    public function deactivate(Coupon $coupon, ShopifyDiscountService $shopifyDiscounts)
    {
        if ($coupon->status !== 'active') {
            return redirect()->route('coupons');
        }

        if ($coupon->shopify_price_rule_id) {
            try {
                // This ends the price rule immediately in Shopify.
                $shopifyDiscounts->updatePriceRule((int) $coupon->shopify_price_rule_id, [
                    'ends_at' => Carbon::now()->toIso8601String(),
                ]);
            } catch (\Throwable $exception) {
                return back()->withErrors(['shopify' => $exception->getMessage()]);
            }
        }

        // This marks the coupon as paused locally.
        $coupon->status = 'paused';
        $coupon->save();

        return redirect()->route('coupons');
    }

    // This deletes a coupon and removes the Shopify price rule if present.
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

    // This shows redemption details for a specific coupon.
    public function view(Request $request, Coupon $coupon)
    {
        if ($coupon->status !== 'active') {
            return redirect()->route('coupons')->withErrors(['coupon' => 'Coupon must be active to view redemptions.']);
        }

        // These counts build the summary stats panel.
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

        // This prepares the filtered redemption list for the page.
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

        // This renders the coupon view with summary and redemption data.
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

    // This streams a CSV export of coupons matching the filter set.
    public function exportList(Request $request)
    {
        $query = Coupon::query()->with('tier');

        // These filters match the list view filters for export consistency.
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

        // This builds a timestamped filename for the export.
        $fileName = 'coupons_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // This streams results in chunks to avoid loading all rows into memory.
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Title',
                'Type',
                'Value Type',
                'Value',
                'Points Value',
                'Status',
                'Tier',
                'Start Date',
                'End Date',
                'Mystery Box Coupon',
                'AI Cluster Coupon',
                'Created At',
            ]);

            $query->orderByDesc('id')->chunk(500, function ($coupons) use ($handle) {
                foreach ($coupons as $coupon) {
                    // This writes a single coupon row to the CSV output.
                    fputcsv($handle, [
                        $coupon->id,
                        $coupon->title,
                        $coupon->type,
                        $coupon->value_type,
                        $coupon->value,
                        $coupon->points_value,
                        $coupon->status,
                        $coupon->tier?->title,
                        optional($coupon->start_date)->format('Y-m-d') ?: null,
                        optional($coupon->end_date)->format('Y-m-d') ?: null,
                        $coupon->is_mystery_box_coupon ? 'Yes' : 'No',
                        $coupon->is_ai_cluster_coupon ? 'Yes' : 'No',
                        optional($coupon->created_at)->format('Y-m-d H:i:s') ?: null,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, $headers);
    }

    // This streams a CSV export of coupon redemptions for a specific coupon.
    public function export(Request $request, Coupon $coupon)
    {
        if ($coupon->status !== 'active') {
            return redirect()->route('coupons')->withErrors(['coupon' => 'Coupon must be active to export redemptions.']);
        }

        // These filters mirror the redemption list view.
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

        // This streams the redemption rows to a CSV file.
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

                // This derives a human-friendly redemption status label.
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

    // This generates a readable coupon code based on the title.
    private function generateCode(string $title): string
    {
        $prefix = strtoupper(Str::slug($title));
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', $prefix), 0, 8);
        $suffix = strtoupper(Str::random(6));

        return trim($prefix.'-'.$suffix, '-');
    }

    // This builds the Shopify price rule payload for the coupon type.
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

        // Free shipping discounts target shipping lines and apply 100% off.
        if ($type === 'free-shipping') {
            $payload['target_type'] = 'shipping_line';
            $payload['value_type'] = 'percentage';
            $payload['value'] = -100.0;
            $payload['allocation_method'] = 'each';
        }

        // Product-level discounts must specify entitled products.
        if ($type === 'amount-product') {
            $payload['target_selection'] = 'entitled';
            $payload['entitled_product_ids'] = array_map('intval', $validated['product_ids'] ?? []);
            $payload['allocation_method'] = 'each';
        }

        // Buy X Get Y discounts define prerequisite and entitled products plus quantity ratio.
        if ($type === 'buy-x-get-y') {
            $payload['target_selection'] = 'entitled';
            $payload['entitled_product_ids'] = array_map('intval', $validated['get_product_ids'] ?? []);
            $payload['prerequisite_product_ids'] = array_map('intval', $validated['buy_product_ids'] ?? []);
            $payload['allocation_method'] = 'each';
            $discountType = $validated['buyx_discount_type'] ?? 'free';
            $discountValue = (float) ($validated['buyx_discount_value'] ?? 0);
            // These branches translate internal discount settings to Shopify fields.
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
