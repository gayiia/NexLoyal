<?php

use App\Models\Customer;
use App\Models\CustomerFeature;
use App\Models\User;

test('ai sandbox page loads', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('ai-sandbox'));
    $response->assertOk();
});

test('feature preview page loads with data', function () {
    $this->actingAs(User::factory()->create());

    $customer = Customer::create(['shopify_id' => 'cust_preview']);
    CustomerFeature::create([
        'customer_id' => $customer->id,
        'orders_count' => 1,
        'total_spent' => 120,
        'avg_order_value' => 120,
        'redeemed_coupons' => 0,
        'points_earned' => 0,
        'points_spent' => 0,
        'loyalty_points' => 0,
        'points_pending' => 0,
        'days_since_last_order' => 5,
        'tenure_days' => 10,
        'features' => ['orders_count' => 1],
        'computed_at' => now(),
    ]);

    $response = $this->get(route('ai-features'));
    $response->assertOk();
    $response->assertViewHas('features');
});
