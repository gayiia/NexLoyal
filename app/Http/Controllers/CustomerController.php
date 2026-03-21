<?php

// This controller manages customer listing, export, detail views, and creation.
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PointsTransaction;
use App\Services\AiClusterStatsService;
use App\Support\PointsHistoryFormatter;
use App\Services\ShopifyCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// This class provides CRUD-like endpoints for customer management and reporting.
class CustomerController extends Controller
{
    // This lists customers with filter and pagination support.
    public function index(Request $request)
    {
        // This applies filter rules based on query parameters.
        $query = $this->applyFilters(Customer::query(), $request);

        // This enforces allowed page sizes to keep responses predictable.
        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $customers = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('customers', compact('customers'));
    }

    // This streams a CSV export of the filtered customer list.
    public function export(Request $request)
    {
        $query = $this->applyFilters(Customer::query(), $request)
            ->orderByDesc('id');

        // This builds a timestamped filename for the export.
        $fileName = 'customers_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // This streams results in chunks to avoid loading all rows into memory.
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Name',
                'Email',
                'Phone',
                'Status',
                'Orders',
                'Total Spent',
                'Loyalty Points',
                'Tier ID',
                'Created At',
            ]);

            $query->chunk(500, function ($customers) use ($handle) {
                foreach ($customers as $customer) {
                    // This writes a single customer row to the CSV output.
                    fputcsv($handle, [
                        $customer->id,
                        $customer->full_name,
                        $customer->email,
                        $customer->phone,
                        $customer->status,
                        $customer->orders_count,
                        $customer->total_spent,
                        $customer->loyalty_points,
                        $customer->tier_id,
                        optional($customer->shopify_created_at)->format('Y-m-d H:i:s') ?: null,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, $headers);
    }

    // This applies filter options from the request to the customer query.
    private function applyFilters($query, Request $request)
    {
        // These branches support filters for missing or present names.
        if ($request->filled('name') && $request->input('name') !== 'all') {
            if ($request->input('name') === 'has') {
                $query->where(function ($builder) {
                    $builder->where(function ($nested) {
                        $nested->whereNotNull('first_name')->where('first_name', '!=', '');
                    })->orWhere(function ($nested) {
                        $nested->whereNotNull('last_name')->where('last_name', '!=', '');
                    });
                });
            } elseif ($request->input('name') === 'missing') {
                $query->where(function ($builder) {
                    $builder->where(function ($nested) {
                        $nested->whereNull('first_name')->orWhere('first_name', '');
                    })->where(function ($nested) {
                        $nested->whereNull('last_name')->orWhere('last_name', '');
                    });
                });
            }
        }

        // These branches support filters for missing or present email.
        if ($request->filled('email') && $request->input('email') !== 'all') {
            if ($request->input('email') === 'has') {
                $query->whereNotNull('email')->where('email', '!=', '');
            } elseif ($request->input('email') === 'missing') {
                $query->where(function ($builder) {
                    $builder->whereNull('email')->orWhere('email', '');
                });
            }
        }

        // These branches support filters for missing or present phone numbers.
        if ($request->filled('mobile') && $request->input('mobile') !== 'all') {
            if ($request->input('mobile') === 'has') {
                $query->whereNotNull('phone')->where('phone', '!=', '');
            } elseif ($request->input('mobile') === 'missing') {
                $query->where(function ($builder) {
                    $builder->whereNull('phone')->orWhere('phone', '');
                });
            }
        }

        // This filters by the explicit customer status when provided.
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // This applies a simple text search across common identity fields.
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    // This shows a single customer and a paginated points history.
    public function show(Customer $customer)
    {
        // This loads the customer's points transactions in reverse chronological order.
        $transactions = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        // This maps transactions into display-friendly summaries.
        $transactions->setCollection(
            $transactions->getCollection()->map(function (PointsTransaction $transaction) {
                return PointsHistoryFormatter::format($transaction);
            })
        );

        return view('customer-show', [
            'customer' => $customer,
            'transactions' => $transactions,
        ]);
    }

    // This streams a CSV export of a single customer's points history.
    public function exportDetail(Customer $customer)
    {
        $query = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        // This builds a timestamped filename for the export.
        $fileName = 'customer_' . $customer->id . '_points_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // This streams results in chunks to avoid loading all rows into memory.
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Transaction ID',
                'Status',
                'Direction',
                'Points',
                'Title',
                'Type',
                'Created At',
            ]);

            $query->chunk(500, function ($transactions) use ($handle) {
                foreach ($transactions as $transaction) {
                    // This formats the transaction consistently with the UI.
                    $formatted = PointsHistoryFormatter::format($transaction);
                    fputcsv($handle, [
                        $formatted['id'] ?? null,
                        $formatted['status'] ?? null,
                        $formatted['direction'] ?? null,
                        $formatted['points'] ?? null,
                        $formatted['title'] ?? null,
                        $formatted['type'] ?? null,
                        $formatted['created_at'] ?? null,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, $headers);
    }

    // This creates a new Shopify customer and mirrors it locally.
    public function store(Request $request, ShopifyCustomerService $shopify)
    {
        // These fields are required to create the customer in Shopify.
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'gender' => ['required', 'in:female,male,nonbinary,other,na'],
            'email' => ['required', 'email', 'max:255'],
            'phone_country' => ['required', 'string', 'max:8'],
            'phone' => ['required', 'string', 'max:40'],
        ]);

        // This builds a full phone number string stored in Shopify.
        $fullPhone = trim($validated['phone_country'].' '.$validated['phone']);

        // This payload matches Shopify's expected customer fields.
        $payload = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $fullPhone,
            'tags' => 'gender:'.$validated['gender'],
        ];

        try {
            // This creates the customer in Shopify and returns the record.
            $shopifyCustomer = $shopify->createCustomer($payload);
        } catch (\Throwable $exception) {
            // This returns Shopify API errors back to the form.
            return back()
                ->withErrors(['shopify' => $exception->getMessage()])
                ->withInput();
        }

        // This mirrors the Shopify customer into the local database.
        Customer::create([
            'shopify_id' => (string) ($shopifyCustomer['id'] ?? ''),
            'first_name' => $shopifyCustomer['first_name'] ?? $validated['first_name'],
            'last_name' => $shopifyCustomer['last_name'] ?? $validated['last_name'],
            'email' => $shopifyCustomer['email'] ?? $validated['email'],
            'phone' => $shopifyCustomer['phone'] ?? $fullPhone,
            'gender' => $validated['gender'],
            'status' => $shopifyCustomer['state'] ?? null,
            'orders_count' => (int) ($shopifyCustomer['orders_count'] ?? 0),
            'total_spent' => (float) ($shopifyCustomer['total_spent'] ?? 0),
            'currency' => $shopifyCustomer['currency'] ?? null,
            'shopify_created_at' => $shopifyCustomer['created_at'] ?? null,
        ]);

        return redirect()->route('customers');
    }

    // This deletes selected customers in Shopify and then removes local records.
    public function bulkDestroy(Request $request, ShopifyCustomerService $shopify, AiClusterStatsService $clusterStats)
    {
        $validator = Validator::make($request->all(), [
            'selected_customer_ids' => ['required', 'array', 'min:1'],
            'selected_customer_ids.*' => ['integer', 'exists:customers,id'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors([
                'bulk_delete' => 'Select at least one customer to delete.',
            ]);
        }

        $ids = collect($request->input('selected_customer_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $customers = Customer::query()
            ->whereIn('id', $ids)
            ->get();

        if ($customers->isEmpty()) {
            return back()->withErrors([
                'bulk_delete' => 'No valid customers were selected.',
            ]);
        }

        $deletedCount = 0;
        $failed = [];

        foreach ($customers as $customer) {
            try {
                $shopify->deleteCustomer((string) $customer->shopify_id);
                $customer->delete();
                $deletedCount++;
            } catch (\Throwable $exception) {
                $failed[] = $customer->email ?: ('ID '.$customer->id);
            }
        }

        if (!$deletedCount) {
            return back()->withErrors([
                'bulk_delete' => 'Unable to delete selected customers right now.',
            ]);
        }

        if ($failed) {
            $clusterStats->refreshLatestCompletedRun();

            return redirect()
                ->route('customers', $request->query())
                ->with('status', "{$deletedCount} customer(s) deleted. Some failed: ".implode(', ', array_slice($failed, 0, 5)));
        }

        $clusterStats->refreshLatestCompletedRun();

        return redirect()
            ->route('customers', $request->query())
            ->with('status', "{$deletedCount} customer(s) deleted successfully.");
    }

}
