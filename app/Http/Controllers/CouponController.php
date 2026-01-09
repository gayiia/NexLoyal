<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Tier;
use App\Services\ShopifyProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
            'value_type' => ['required', 'in:percentage,fixed,none'],
            'value' => ['nullable', 'numeric', 'min:0', 'required_unless:value_type,none'],
            'points_value' => ['required', 'integer', 'min:0'],
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
        ]);

        if ($validated['value_type'] === 'none') {
            $validated['value'] = null;
        }

        $today = Carbon::today();
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $status = 'scheduled';

        if ($endDate->lt($today)) {
            $status = 'expired';
        } elseif ($startDate->gt($today)) {
            $status = 'scheduled';
        } else {
            $status = 'active';
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
            'tier_id' => $validated['tier_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'product_ids' => $productIds ?: null,
            'buy_product_ids' => $buyProductIds ?: null,
            'get_product_ids' => $getProductIds ?: null,
            'buy_quantity' => $buyQuantity,
            'get_quantity' => $getQuantity,
        ]);

        return redirect()->route('coupons');
    }
}
