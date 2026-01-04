<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;

class CustomerGroupController extends Controller
{
    public function index(): View
    {
        $customers = Customer::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (Customer $customer): array {
                $name = $customer->full_name;

                if ($name === '') {
                    $name = $customer->email ?: "Customer #{$customer->id}";
                }

                return [
                    'id' => $customer->id,
                    'name' => $name,
                ];
            })
            ->values();

        $tiers = [
            'Bronze',
            'Silver',
            'Gold',
            'Platinum',
        ];

        return view('customer-groups', compact('customers', 'tiers'));
    }
}
