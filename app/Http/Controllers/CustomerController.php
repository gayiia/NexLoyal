<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PointsTransaction;
use App\Support\PointsHistoryFormatter;
use App\Services\ShopifyCustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->applyFilters(Customer::query(), $request);

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $customers = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('customers', compact('customers'));
    }

    public function export(Request $request)
    {
        $query = $this->applyFilters(Customer::query(), $request)
            ->orderByDesc('id');

        $fileName = 'customers_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

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

    private function applyFilters($query, Request $request)
    {
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

        if ($request->filled('email') && $request->input('email') !== 'all') {
            if ($request->input('email') === 'has') {
                $query->whereNotNull('email')->where('email', '!=', '');
            } elseif ($request->input('email') === 'missing') {
                $query->where(function ($builder) {
                    $builder->whereNull('email')->orWhere('email', '');
                });
            }
        }

        if ($request->filled('mobile') && $request->input('mobile') !== 'all') {
            if ($request->input('mobile') === 'has') {
                $query->whereNotNull('phone')->where('phone', '!=', '');
            } elseif ($request->input('mobile') === 'missing') {
                $query->where(function ($builder) {
                    $builder->whereNull('phone')->orWhere('phone', '');
                });
            }
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

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

    public function show(Customer $customer)
    {
        $transactions = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

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

    public function exportDetail(Customer $customer)
    {
        $query = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        $fileName = 'customer_' . $customer->id . '_points_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

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

    public function store(Request $request, ShopifyCustomerService $shopify)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'gender' => ['required', 'in:female,male,nonbinary,other,na'],
            'email' => ['required', 'email', 'max:255'],
            'phone_country' => ['required', 'string', 'max:8'],
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $fullPhone = trim($validated['phone_country'].' '.$validated['phone']);

        $payload = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $fullPhone,
            'tags' => 'gender:'.$validated['gender'],
        ];

        try {
            $shopifyCustomer = $shopify->createCustomer($payload);
        } catch (\Throwable $exception) {
            return back()
                ->withErrors(['shopify' => $exception->getMessage()])
                ->withInput();
        }

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

}
