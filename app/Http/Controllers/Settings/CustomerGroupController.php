<?php

// This controller serves the Inertia page for customer group settings.
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Inertia\Inertia;
use Inertia\Response;

// This class prepares customer data for grouping in settings UI.
class CustomerGroupController extends Controller
{
    // This loads customers and static tier labels for the settings page.
    public function index(): Response
    {
        $customers = Customer::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (Customer $customer): array {
                // This falls back to email or ID when name fields are missing.
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

        // These tier names are used by the UI to create groups.
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
