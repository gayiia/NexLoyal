<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

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

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $customers = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('customers', compact('customers'));
    }

    public function show(Customer $customer)
    {
        return view('customer-show', compact('customer'));
    }
}
