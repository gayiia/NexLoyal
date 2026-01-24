<?php

use App\Models\User;

it('loads the reports page', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('reports'));
    $response->assertOk();
});

it('generates a report payload', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->post(route('reports.generate'), [
        'report_key' => 'loyalty_participation_rate',
        'start_date' => now()->subDays(29)->toDateString(),
        'end_date' => now()->toDateString(),
        'customer_type' => 'all',
        'group_by' => 'day',
        'top_n' => 10,
    ]);

    $response->assertOk();
    $response->assertViewHas('payload');
});
