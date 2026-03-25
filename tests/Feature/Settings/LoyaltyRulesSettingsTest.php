<?php

use App\Models\PointRule;
use App\Models\Tier;
use App\Models\User;

test('point rules can be updated from settings', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->post(route('point-rules.update'), [
        'welcome_points' => 25,
        'birthday_points' => 50,
        'profile_completion_points' => 75,
        'amount_per_point' => 200,
        'social_instagram_url' => 'https://instagram.com/nexloyal',
        'social_instagram_points' => 10,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Point rules saved.');

    $rule = PointRule::query()->first();
    expect($rule)->not()->toBeNull();
    expect($rule->amount_per_point)->toBe(200);
    expect($rule->social_instagram_points)->toBe(10);
});

test('tiers can be created and activated from settings', function () {
    $this->actingAs(User::factory()->create());

    $createResponse = $this->post(route('tier-rules.store'), [
        'title' => 'Silver',
        'color' => '#94a3b8',
        'min_points' => 1000,
        'max_points' => 4999,
        'single_point_value' => 1.2,
        'description' => 'Growing loyalty.',
    ]);

    $createResponse->assertRedirect(route('tier-rules'));

    $tier = Tier::query()->where('title', 'Silver')->first();
    expect($tier)->not()->toBeNull();
    expect($tier->status)->toBe('inactive');

    $statusResponse = $this->patch(route('tier-rules.status', $tier), [
        'status' => 'active',
    ]);

    $statusResponse->assertRedirect(route('tier-rules'));

    $tier->refresh();
    expect($tier->status)->toBe('active');
});
