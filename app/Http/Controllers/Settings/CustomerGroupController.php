<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function index(): Response
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

        return Inertia::render('settings/customer-groups', [
            'customers' => $customers,
            'tiers' => $tiers,
        ]);
    }
}
