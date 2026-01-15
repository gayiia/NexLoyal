<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PointRule;
use Illuminate\Http\Request;

class PointRuleController extends Controller
{
    public function edit()
    {
        $rule = PointRule::query()->firstOrCreate([], [
            'birthday_points' => 0,
            'profile_completion_points' => 0,
        ]);

        return view('settings.point-rules', [
            'rule' => $rule,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'birthday_points' => ['required', 'integer', 'min:0'],
            'profile_completion_points' => ['required', 'integer', 'min:0'],
        ]);

        $rule = PointRule::query()->firstOrCreate([]);
        $rule->update($validated);

        return back()->with('status', 'Point rules saved.');
    }
}
