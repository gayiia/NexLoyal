<?php

// This controller manages AI cluster-based awards and issuance flows.
namespace App\Http\Controllers;

use App\Jobs\IssueAiAwardChunkJob;
use App\Models\AiAwardIssuance;
use App\Models\AiCluster;
use App\Models\AiClusterAward;
use App\Models\AiClusterAwardCustomer;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// This class creates, edits, activates, and exports AI awards.
class AiClusterAwardController extends Controller
{
    // This shows the create award form with eligible clusters and coupons.
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

    // This validates and stores a new AI award definition.
    public function store(Request $request)
    {
        // These validations enforce required fields by award type.
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'ai_cluster_id' => ['required', 'exists:ai_clusters,id'],
            'type' => ['required', 'in:points,coupon'],
            'points_amount' => ['nullable', 'integer', 'min:1'],
            'coupon_id' => ['nullable', 'exists:coupons,id'],
        ]);

        // Points awards require a points amount.
        if ($validated['type'] === 'points' && empty($validated['points_amount'])) {
            return back()->withErrors(['points_amount' => 'Points amount is required for points awards.'])->withInput();
        }

        if ($validated['type'] === 'coupon') {
            // Coupon awards must reference a coupon flagged for AI use.
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

        // This creates the award in draft status until activation.
        $award = AiClusterAward::create([
            'ai_cluster_id' => $validated['ai_cluster_id'],
            'title' => $validated['title'],
            'type' => $validated['type'],
            'points_amount' => $validated['type'] === 'points' ? $validated['points_amount'] : null,
            'coupon_id' => $validated['type'] === 'coupon' ? $validated['coupon_id'] : null,
            'status' => 'draft',
        ]);

        // This creates pending award customer records for the selected cluster.
        $this->syncAwardCustomers($award);

        return redirect()->route('ai-insights');
    }

    // This shows the edit form for a draft award.
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

    // This updates a draft award and refreshes its customer list if the cluster changed.
    public function update(Request $request, AiClusterAward $award)
    {
        if ($award->status !== 'draft') {
            return redirect()->route('ai-insights')->withErrors(['award' => 'Only draft awards can be edited.']);
        }

        // These validations enforce required fields by award type.
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'ai_cluster_id' => ['required', 'exists:ai_clusters,id'],
            'type' => ['required', 'in:points,coupon'],
            'points_amount' => ['nullable', 'integer', 'min:1'],
            'coupon_id' => ['nullable', 'exists:coupons,id'],
        ]);

        // Points awards require a points amount.
        if ($validated['type'] === 'points' && empty($validated['points_amount'])) {
            return back()->withErrors(['points_amount' => 'Points amount is required for points awards.'])->withInput();
        }

        if ($validated['type'] === 'coupon') {
            // Coupon awards must reference a coupon flagged for AI use.
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

        // This determines if customers need to be re-synced for a new cluster.
        $clusterChanged = (int) $award->ai_cluster_id !== (int) $validated['ai_cluster_id'];

        $award->update([
            'ai_cluster_id' => $validated['ai_cluster_id'],
            'title' => $validated['title'],
            'type' => $validated['type'],
            'points_amount' => $validated['type'] === 'points' ? $validated['points_amount'] : null,
            'coupon_id' => $validated['type'] === 'coupon' ? $validated['coupon_id'] : null,
        ]);

        if ($clusterChanged) {
            // This clears old customer mappings before syncing the new cluster.
            $award->customers()->delete();
            $this->syncAwardCustomers($award);
        }

        return redirect()->route('ai-insights');
    }

    // This activates an award and dispatches issuance jobs in chunks.
    public function activate(AiClusterAward $award)
    {
        if ($award->status === 'active') {
            return redirect()->route('ai-insights');
        }

        // This lock prevents concurrent activations from issuing duplicates.
        $lock = Cache::lock("ai_award_issue:{$award->id}", 600);
        if (!$lock->get()) {
            return redirect()->route('ai-insights')->withErrors(['award' => 'Award is already being issued.']);
        }

        try {
            // This ensures pending customers exist before issuing.
            if ($award->customers()->count() === 0) {
                $this->syncAwardCustomers($award);
            }

            $pendingCount = AiClusterAwardCustomer::query()
                ->where('ai_cluster_award_id', $award->id)
                ->where('status', 'pending')
                ->count();

            // This flips the award to active so jobs can proceed.
            $award->update([
                'status' => 'active',
                'activated_at' => now(),
                'deactivated_at' => null,
            ]);

            // This dispatches jobs in chunks to limit memory use.
            AiClusterAwardCustomer::query()
                ->where('ai_cluster_award_id', $award->id)
                ->where('status', 'pending')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($award): void {
                    $customerIds = $rows->pluck('customer_id')->all();
                    IssueAiAwardChunkJob::dispatch($award->id, $customerIds);
                });
        } finally {
            // This releases the lock even if dispatch fails.
            optional($lock)->release();
        }

        return redirect()->route('ai-insights');
    }

    // This deactivates an award so no further issuance occurs.
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

    // This deletes a draft award that has not been issued.
    public function destroy(AiClusterAward $award)
    {
        if ($award->status !== 'draft') {
            return redirect()->route('ai-insights')->withErrors(['award' => 'Only draft awards can be deleted.']);
        }

        $award->delete();

        return redirect()->route('ai-insights');
    }

    // This streams a CSV export of all customers tied to an award.
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

        // This streams results to avoid loading large datasets in memory.
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
                // This uses email as a fallback when names are missing.
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

    // This creates pending award-customer rows for the selected cluster.
    private function syncAwardCustomers(AiClusterAward $award): void
    {
        $customerIds = AiClusterCustomer::query()
            ->where('ai_cluster_id', $award->ai_cluster_id)
            ->pluck('customer_id')
            ->all();

        if (!$customerIds) {
            return;
        }

        // This prepares bulk insert rows for the award recipients.
        $rows = array_map(function ($customerId) use ($award) {
            return [
                'ai_cluster_award_id' => $award->id,
                'customer_id' => $customerId,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $customerIds);

        // This inserts in chunks to reduce query size.
        foreach (array_chunk($rows, 500) as $chunk) {
            AiClusterAwardCustomer::insert($chunk);
        }
    }
}
