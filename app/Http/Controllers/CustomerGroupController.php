<?php

// This controller renders the customer groups management page.
namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;

// This class prepares customer lists and tier labels for grouping.
class CustomerGroupController extends Controller
{
    // This builds a simplified customer list and returns the group view.
    public function index(): View
    {
        $customers = Customer::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (Customer $customer): array {
                // This provides a fallback label when names are missing.
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

        // These tier names are used by the UI to define groups.
        $tiers = [
            'Bronze',
            'Silver',
            'Gold',
            'Platinum',
        ];

        return view('customer-groups', compact('customers', 'tiers'));
    }
}
