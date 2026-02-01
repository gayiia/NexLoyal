<?php

// This controller manages tier rules in the settings area.
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// This class handles CRUD operations for loyalty tiers.
class TierRuleController extends Controller
{
    // This lists tiers with pagination for the settings screen.
    public function index(): View
    {
        $tiers = Tier::query()
            ->orderBy('min_points')
            ->orderBy('max_points')
            ->paginate(10);

        return view('settings.tier-rules', compact('tiers'));
    }

    // This creates a new tier in inactive status by default.
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTier($request);
        $validated['status'] = 'inactive';

        Tier::create($validated);

        return redirect()->route('tier-rules');
    }

    // This updates an existing tier's configuration.
    public function update(Request $request, Tier $tier): RedirectResponse
    {
        $validated = $this->validateTier($request, $tier->id);

        $tier->update($validated);

        return redirect()->route('tier-rules');
    }

    // This toggles the active status of a tier.
    public function updateStatus(Request $request, Tier $tier): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        $tier->update(['status' => $validated['status']]);

        return redirect()->route('tier-rules');
    }

    // This deletes a tier from the system.
    public function destroy(Tier $tier): RedirectResponse
    {
        $tier->delete();

        return redirect()->route('tier-rules');
    }

    // This validates tier fields and enforces numeric boundaries.
    private function validateTier(Request $request, ?int $tierId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'min_points' => ['required', 'integer', 'min:0'],
            'max_points' => ['required', 'integer', 'gte:min_points'],
            'single_point_value' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
