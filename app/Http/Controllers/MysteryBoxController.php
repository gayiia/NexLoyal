<?php

// This controller manages admin CRUD and reporting for mystery boxes.
namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CustomerCoupon;
use App\Models\MysteryBox;
use App\Models\MysteryBoxItem;
use App\Enums\SourceType;
use App\Models\Tier;
use Illuminate\Http\Request;

// This class creates, updates, activates, and exports mystery box data.
class MysteryBoxController extends Controller
{
    // This lists all mystery boxes and tiers for the admin view.
    public function index()
    {
        $boxes = MysteryBox::query()
            ->orderByDesc('id')
            ->get();

        $tiers = Tier::query()->orderBy('min_points')->get();

        return view('mystery-boxes', [
            'boxes' => $boxes,
            'tiers' => $tiers,
        ]);
    }

    // This shows the create form with eligible tiers and coupons.
    public function create()
    {
        $tiers = Tier::query()->orderBy('min_points')->get();
        $coupons = Coupon::query()
            ->where('is_mystery_box_coupon', true)
            ->orderBy('title')
            ->get();

        return view('mystery-boxes-create', [
            'tiers' => $tiers,
            'coupons' => $coupons,
        ]);
    }

    // This validates and stores a new mystery box in inactive state.
    public function store(Request $request)
    {
        // These validations enforce required tiers, coupons, and claim rules.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*' => ['integer', 'exists:tiers,id'],
            'coupons' => ['required', 'array', 'min:1'],
            'coupons.*' => ['integer', 'exists:coupons,id'],
            'claim_rule' => ['required', 'in:ONCE_EVER,ONCE_PER_DAY,ONCE_PER_WEEK'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        // This ensures all selected coupons are flagged for mystery box use.
        $eligibleCoupons = Coupon::query()
            ->whereIn('id', $validated['coupons'])
            ->where('is_mystery_box_coupon', true)
            ->count();
        if ($eligibleCoupons !== count($validated['coupons'])) {
            return back()
                ->withErrors(['coupons' => 'All selected coupons must be flagged as Mystery Box coupons.'])
                ->withInput();
        }

        // This creates the box and stores the tier list and schedule.
        $box = MysteryBox::create([
            'name' => $validated['name'],
            'tiers' => array_values(array_map('intval', $validated['tiers'])),
            'claim_rule' => $validated['claim_rule'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_active' => false,
        ]);

        // This creates one item per coupon with default weight.
        $items = array_map(function ($couponId) use ($box) {
            return [
                'mystery_box_id' => $box->id,
                'coupon_id' => (int) $couponId,
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $validated['coupons']);

        MysteryBoxItem::insert($items);

        return redirect()->route('mystery-boxes');
    }

    // This shows the edit form for an inactive mystery box.
    public function edit(MysteryBox $mysteryBox)
    {
        if ($mysteryBox->is_active) {
            return redirect()->route('mystery-boxes.view', $mysteryBox)
                ->withErrors(['mysteryBox' => 'Active mystery boxes cannot be edited.']);
        }

        // These lookups populate the form with options and selected items.
        $tiers = Tier::query()->orderBy('min_points')->get();
        $coupons = Coupon::query()
            ->where('is_mystery_box_coupon', true)
            ->orderBy('title')
            ->get();

        $selectedCoupons = $mysteryBox->items()->pluck('coupon_id')->all();

        return view('mystery-boxes-edit', [
            'mysteryBox' => $mysteryBox,
            'tiers' => $tiers,
            'coupons' => $coupons,
            'selectedCoupons' => $selectedCoupons,
        ]);
    }

    // This updates an inactive mystery box and its items.
    public function update(Request $request, MysteryBox $mysteryBox)
    {
        if ($mysteryBox->is_active) {
            return redirect()->route('mystery-boxes.view', $mysteryBox)
                ->withErrors(['mysteryBox' => 'Active mystery boxes cannot be edited.']);
        }

        // These validations enforce required tiers, coupons, and claim rules.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*' => ['integer', 'exists:tiers,id'],
            'coupons' => ['required', 'array', 'min:1'],
            'coupons.*' => ['integer', 'exists:coupons,id'],
            'claim_rule' => ['required', 'in:ONCE_EVER,ONCE_PER_DAY,ONCE_PER_WEEK'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        // This ensures all selected coupons are flagged for mystery box use.
        $eligibleCoupons = Coupon::query()
            ->whereIn('id', $validated['coupons'])
            ->where('is_mystery_box_coupon', true)
            ->count();
        if ($eligibleCoupons !== count($validated['coupons'])) {
            return back()
                ->withErrors(['coupons' => 'All selected coupons must be flagged as Mystery Box coupons.'])
                ->withInput();
        }

        // This updates the box settings and schedule.
        $mysteryBox->update([
            'name' => $validated['name'],
            'tiers' => array_values(array_map('intval', $validated['tiers'])),
            'claim_rule' => $validated['claim_rule'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        // This replaces the set of items to match the selected coupons.
        $mysteryBox->items()->delete();
        $items = array_map(function ($couponId) use ($mysteryBox) {
            return [
                'mystery_box_id' => $mysteryBox->id,
                'coupon_id' => (int) $couponId,
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $validated['coupons']);
        MysteryBoxItem::insert($items);

        return redirect()->route('mystery-boxes');
    }

    // This activates a mystery box for customer use.
    public function activate(MysteryBox $mysteryBox)
    {
        if ($mysteryBox->is_active) {
            return redirect()->route('mystery-boxes');
        }

        $mysteryBox->update(['is_active' => true]);

        return redirect()->route('mystery-boxes');
    }

    // This deactivates a mystery box so it is no longer claimable.
    public function deactivate(MysteryBox $mysteryBox)
    {
        if (!$mysteryBox->is_active) {
            return redirect()->route('mystery-boxes');
        }

        $mysteryBox->update(['is_active' => false]);

        return redirect()->route('mystery-boxes');
    }

    // This deletes an inactive mystery box.
    public function destroy(MysteryBox $mysteryBox)
    {
        if ($mysteryBox->is_active) {
            return redirect()->route('mystery-boxes')
                ->withErrors(['mysteryBox' => 'Active mystery boxes cannot be deleted.']);
        }

        $mysteryBox->delete();

        return redirect()->route('mystery-boxes');
    }

    // This shows claim history and summary for a specific mystery box.
    public function view(Request $request, MysteryBox $mysteryBox)
    {
        $now = now();
        $tierIds = array_map('intval', $mysteryBox->tiers ?? []);
        $tierNames = $tierIds
            ? Tier::query()->whereIn('id', $tierIds)->pluck('title')->all()
            : [];
        $baseQuery = CustomerCoupon::query()
            ->where('mystery_box_id', $mysteryBox->id)
            ->where('source', SourceType::MYSTERY_BOX->value);

        // These totals are used in the summary panel.
        $totalClaims = (clone $baseQuery)->count();
        $usedClaims = (clone $baseQuery)
            ->where(function ($query) {
                $query->where('status', 'used')
                    ->orWhereNotNull('used_at');
            })
            ->count();
        $expiredClaims = (clone $baseQuery)
            ->where(function ($query) use ($now) {
                $query->where('status', 'expired')
                    ->orWhere(function ($query) use ($now) {
                        $query->whereNotNull('expires_at')
                            ->where('expires_at', '<', $now);
                    });
            })
            ->count();
        $activeClaims = (clone $baseQuery)
            ->where('status', 'active')
            ->whereNull('used_at')
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            })
            ->count();

        // These filters control the claims table.
        $status = $request->input('status', 'all');
        $search = $request->input('search');

        $query = CustomerCoupon::query()
            ->with(['customer', 'coupon'])
            ->where('mystery_box_id', $mysteryBox->id)
            ->where('source', SourceType::MYSTERY_BOX->value);

        if ($status === 'used') {
            $query->where(function ($query) {
                $query->where('status', 'used')
                    ->orWhereNotNull('used_at');
            });
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
                    })
                    ->orWhereHas('coupon', function ($couponQuery) use ($search) {
                        $couponQuery->where('title', 'like', "%{$search}%");
                    });
            });
        }

        // This paginates claims in reverse chronological order.
        $claims = $query->orderByDesc('redeemed_at')->paginate(15)->withQueryString();

        return view('mystery-boxes-view', [
            'mysteryBox' => $mysteryBox->load('items.coupon'),
            'tierNames' => $tierNames,
            'claims' => $claims,
            'summary' => [
                'total' => $totalClaims,
                'active' => $activeClaims,
                'used' => $usedClaims,
                'expired' => $expiredClaims,
            ],
        ]);
    }

    // This exports mystery box claim records to CSV.
    public function export(Request $request, MysteryBox $mysteryBox)
    {
        $status = $request->input('status', 'all');
        $search = $request->input('search');
        $now = now();

        $query = CustomerCoupon::query()
            ->with(['customer', 'coupon'])
            ->where('mystery_box_id', $mysteryBox->id)
            ->where('source', SourceType::MYSTERY_BOX->value);

        if ($status === 'used') {
            $query->where(function ($query) {
                $query->where('status', 'used')
                    ->orWhereNotNull('used_at');
            });
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
                    })
                    ->orWhereHas('coupon', function ($couponQuery) use ($search) {
                        $couponQuery->where('title', 'like', "%{$search}%");
                    });
            });
        }

        $filename = 'mystery-box-claims-'.$mysteryBox->id.'.csv';

        // This streams the export to avoid loading all rows into memory.
        return response()->streamDownload(function () use ($query, $now) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Customer Name',
                'Customer Email',
                'Coupon Title',
                'Code',
                'Status',
                'Claimed At',
                'Expires At',
                'Used At',
            ]);

            $rows = $query->orderByDesc('redeemed_at')->get();
            foreach ($rows as $record) {
                $customer = $record->customer;
                $coupon = $record->coupon;
                $nameParts = array_filter([$customer?->first_name, $customer?->last_name]);
                $name = $nameParts ? implode(' ', $nameParts) : ($customer?->email ?? 'Customer');

                // This derives a human-friendly status label for export.
                $statusLabel = 'Unused';
                if ($record->status === 'used' || $record->used_at) {
                    $statusLabel = 'Used';
                } else {
                    $expiresAt = $record->expires_at ?? $coupon?->end_date;
                    if ($record->status === 'expired' || ($expiresAt && $expiresAt->lt($now))) {
                        $statusLabel = 'Expired';
                    } elseif ($record->status === 'in_progress') {
                        $statusLabel = 'In progress';
                    }
                }

                fputcsv($handle, [
                    $name,
                    $customer?->email ?? '',
                    $coupon?->title ?? '',
                    $record->code ?? '',
                    $statusLabel,
                    optional($record->redeemed_at)->toIso8601String(),
                    optional($record->expires_at ?? $coupon?->end_date)->toIso8601String(),
                    optional($record->used_at)->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
