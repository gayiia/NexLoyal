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
            'welcome_points' => 0,
            'birthday_points' => 0,
            'profile_completion_points' => 0,
            'amount_per_point' => 100,
        ]);

        return view('settings.point-rules', [
            'rule' => $rule,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'welcome_points' => ['sometimes', 'integer', 'min:0'],
            'birthday_points' => ['sometimes', 'integer', 'min:0'],
            'profile_completion_points' => ['sometimes', 'integer', 'min:0'],
            'amount_per_point' => ['sometimes', 'integer', 'min:1'],
            'social_linkedin_url' => ['sometimes', 'nullable', 'url'],
            'social_linkedin_points' => ['sometimes', 'integer', 'min:0'],
            'social_tiktok_url' => ['sometimes', 'nullable', 'url'],
            'social_tiktok_points' => ['sometimes', 'integer', 'min:0'],
            'social_facebook_url' => ['sometimes', 'nullable', 'url'],
            'social_facebook_points' => ['sometimes', 'integer', 'min:0'],
            'social_x_url' => ['sometimes', 'nullable', 'url'],
            'social_x_points' => ['sometimes', 'integer', 'min:0'],
            'social_instagram_url' => ['sometimes', 'nullable', 'url'],
            'social_instagram_points' => ['sometimes', 'integer', 'min:0'],
            'social_youtube_url' => ['sometimes', 'nullable', 'url'],
            'social_youtube_points' => ['sometimes', 'integer', 'min:0'],
        ]);

        $rule = PointRule::query()->firstOrCreate([]);
        $rule->fill($validated);
        $rule->save();

        return back()->with('status', 'Point rules saved.');
    }
}
