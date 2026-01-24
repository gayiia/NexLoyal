<?php

namespace App\Http\Controllers;

use App\Jobs\IssueAiAwardChunkJob;
use App\Models\AiAwardIssuance;
use App\Models\AiCluster;
use App\Models\AiClusterAward;
use App\Models\AiClusterAwardCustomer;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Models\Coupon;
use App\Models\CouponCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiClusterAwardController extends Controller
{
    public function create()
    {
        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        $clusters = $latestRun
            ? AiCluster::query()
                ->where('ai_cluster_run_id', $latestRun->id)
                ->orderBy('label')
                ->get()
            : collect();

        $coupons = Coupon::query()
            ->where('is_ai_cluster_coupon', true)
            ->orderBy('title')
            ->get();

        return view('ai-awards-create', [
            'clusters' => $clusters,
            'coupons' => $coupons,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'ai_cluster_id' => ['required', 'exists:ai_clusters,id'],
            'type' => ['required', 'in:points,coupon'],
            'points_amount' => ['nullable', 'integer', 'min:1'],
            'coupon_id' => ['nullable', 'exists:coupons,id'],
        ]);

        if ($validated['type'] === 'points' && empty($validated['points_amount'])) {
            return back()->withErrors(['points_amount' => 'Points amount is required for points awards.'])->withInput();
        }

        if ($validated['type'] === 'coupon') {
            if (empty($validated['coupon_id'])) {
                return back()->withErrors(['coupon_id' => 'Coupon is required for coupon awards.'])->withInput();
            }
            $eligible = Coupon::query()
                ->where('id', $validated['coupon_id'])
                ->where('is_ai_cluster_coupon', true)
                ->exists();
            if (!$eligible) {
                return back()->withErrors(['coupon_id' => 'Coupon must be flagged for AI Insights.'])->withInput();
            }
        }

        $award = AiClusterAward::create([
            'ai_cluster_id' => $validated['ai_cluster_id'],
            'title' => $validated['title'],
            'type' => $validated['type'],
            'points_amount' => $validated['type'] === 'points' ? $validated['points_amount'] : null,
            'coupon_id' => $validated['type'] === 'coupon' ? $validated['coupon_id'] : null,
            'status' => 'draft',
        ]);

        $this->syncAwardCustomers($award);

        return redirect()->route('ai-insights');
    }

    public function edit(AiClusterAward $award)
    {
        if ($award->status !== 'draft') {
            return redirect()->route('ai-insights')->withErrors(['award' => 'Only draft awards can be edited.']);
        }

        $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
        $clusters = $latestRun
            ? AiCluster::query()
                ->where('ai_cluster_run_id', $latestRun->id)
                ->orderBy('label')
                ->get()
            : collect();

        $coupons = Coupon::query()
            ->where('is_ai_cluster_coupon', true)
            ->orderBy('title')
            ->get();

        return view('ai-awards-edit', [
            'award' => $award,
            'clusters' => $clusters,
            'coupons' => $coupons,
        ]);
    }

    public function update(Request $request, AiClusterAward $award)
    {
        if ($award->status !== 'draft') {
            return redirect()->route('ai-insights')->withErrors(['award' => 'Only draft awards can be edited.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'ai_cluster_id' => ['required', 'exists:ai_clusters,id'],
            'type' => ['required', 'in:points,coupon'],
            'points_amount' => ['nullable', 'integer', 'min:1'],
            'coupon_id' => ['nullable', 'exists:coupons,id'],
        ]);

        if ($validated['type'] === 'points' && empty($validated['points_amount'])) {
            return back()->withErrors(['points_amount' => 'Points amount is required for points awards.'])->withInput();
        }

        if ($validated['type'] === 'coupon') {
            if (empty($validated['coupon_id'])) {
                return back()->withErrors(['coupon_id' => 'Coupon is required for coupon awards.'])->withInput();
            }
            $eligible = Coupon::query()
                ->where('id', $validated['coupon_id'])
                ->where('is_ai_cluster_coupon', true)
                ->exists();
            if (!$eligible) {
                return back()->withErrors(['coupon_id' => 'Coupon must be flagged for AI Insights.'])->withInput();
            }
        }

        $clusterChanged = (int) $award->ai_cluster_id !== (int) $validated['ai_cluster_id'];

        $award->update([
            'ai_cluster_id' => $validated['ai_cluster_id'],
            'title' => $validated['title'],
            'type' => $validated['type'],
            'points_amount' => $validated['type'] === 'points' ? $validated['points_amount'] : null,
            'coupon_id' => $validated['type'] === 'coupon' ? $validated['coupon_id'] : null,
        ]);

        if ($clusterChanged) {
            $award->customers()->delete();
            $this->syncAwardCustomers($award);
        }

        return redirect()->route('ai-insights');
    }

    public function activate(AiClusterAward $award)
    {
        if ($award->status === 'active') {
            return redirect()->route('ai-insights');
        }

        $lock = Cache::lock("ai_award_issue:{$award->id}", 600);
        if (!$lock->get()) {
            return redirect()->route('ai-insights')->withErrors(['award' => 'Award is already being issued.']);
        }

        try {
            if ($award->customers()->count() === 0) {
                $this->syncAwardCustomers($award);
            }

            $pendingCount = AiClusterAwardCustomer::query()
                ->where('ai_cluster_award_id', $award->id)
                ->where('status', 'pending')
                ->count();

            $award->update([
                'status' => 'active',
                'activated_at' => now(),
                'deactivated_at' => null,
            ]);

            AiClusterAwardCustomer::query()
                ->where('ai_cluster_award_id', $award->id)
                ->where('status', 'pending')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($award): void {
                    $customerIds = $rows->pluck('customer_id')->all();
                    IssueAiAwardChunkJob::dispatch($award->id, $customerIds);
                });
        } finally {
            optional($lock)->release();
        }

        return redirect()->route('ai-insights');
    }

    public function deactivate(AiClusterAward $award)
    {
        if ($award->status !== 'active') {
            return redirect()->route('ai-insights');
        }

        $award->update([
            'status' => 'inactive',
            'deactivated_at' => now(),
        ]);

        return redirect()->route('ai-insights');
    }

    public function destroy(AiClusterAward $award)
    {
        if ($award->status !== 'draft') {
            return redirect()->route('ai-insights')->withErrors(['award' => 'Only draft awards can be deleted.']);
        }

        $award->delete();

        return redirect()->route('ai-insights');
    }

    public function export(AiClusterAward $award)
    {
        $customers = AiClusterAwardCustomer::query()
            ->with('customer')
            ->where('ai_cluster_award_id', $award->id)
            ->get();

        $issuances = AiAwardIssuance::query()
            ->where('ai_cluster_award_id', $award->id)
            ->get()
            ->keyBy('customer_id');

        $fileName = 'ai-award-' . $award->id . '-customers-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->streamDownload(function () use ($customers, $issuances) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Customer ID',
                'Name',
                'Email',
                'Status',
                'Issued At',
            ]);

            foreach ($customers as $row) {
                $customer = $row->customer;
                $nameParts = array_filter([$customer?->first_name, $customer?->last_name]);
                $name = $nameParts ? implode(' ', $nameParts) : ($customer?->email ?? 'Customer');
                $issuance = $issuances[$row->customer_id] ?? null;
                fputcsv($handle, [
                    $customer?->id,
                    $name,
                    $customer?->email,
                    $row->status,
                    optional($issuance?->issued_at)->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $fileName, $headers);
    }

    private function syncAwardCustomers(AiClusterAward $award): void
    {
        $customerIds = AiClusterCustomer::query()
            ->where('ai_cluster_id', $award->ai_cluster_id)
            ->pluck('customer_id')
            ->all();

        if (!$customerIds) {
            return;
        }

        $rows = array_map(function ($customerId) use ($award) {
            return [
                'ai_cluster_award_id' => $award->id,
                'customer_id' => $customerId,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $customerIds);

        foreach (array_chunk($rows, 500) as $chunk) {
            AiClusterAwardCustomer::insert($chunk);
        }
    }
}
