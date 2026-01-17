<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class CouponViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_view_summary_counts(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::create([
            'title' => 'Summer Deal',
            'type' => 'amount-order',
            'value_type' => 'percentage',
            'value' => 10,
            'points_value' => 100,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'shopify_id' => 'shopify-1',
            'email' => 'customer@example.com',
        ]);

        CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'code' => 'USED1',
            'status' => 'used',
            'redeemed_at' => now()->subDay(),
            'used_at' => now()->subDay(),
        ]);

        CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'code' => 'EXP1',
            'status' => 'active',
            'redeemed_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'code' => 'UNUSED1',
            'status' => 'active',
            'redeemed_at' => now()->subDays(2),
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($user)->get(route('coupons.view', $coupon));

        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['purchased'] === 3
                && $summary['used'] === 1
                && $summary['expired'] === 1
                && $summary['unused'] === 1;
        });
    }

    public function test_widget_my_coupons_status_labels(): void
    {
        $customer = Customer::create([
            'shopify_id' => 'shopify-2',
            'email' => 'widget@example.com',
        ]);

        $coupon = Coupon::create([
            'title' => 'Widget Deal',
            'type' => 'amount-order',
            'value_type' => 'percentage',
            'value' => 15,
            'points_value' => 150,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
        ]);

        CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'code' => 'ACTIVE1',
            'status' => 'active',
            'redeemed_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);

        CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'code' => 'USED2',
            'status' => 'used',
            'redeemed_at' => now()->subDays(2),
            'used_at' => now()->subDay(),
        ]);

        $token = Crypt::encryptString(json_encode([
            'shopify_id' => $customer->shopify_id,
            'email' => $customer->email,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]));

        $response = $this->get('/api/widget/my-coupons?token='.$token);

        $response->assertOk();
        $response->assertJsonFragment(['status' => 'UNUSED']);
        $response->assertJsonFragment(['status' => 'USED']);
    }

    public function test_export_csv_contains_headers(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::create([
            'title' => 'Export Deal',
            'type' => 'amount-order',
            'value_type' => 'percentage',
            'value' => 5,
            'points_value' => 50,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'shopify_id' => 'shopify-3',
            'email' => 'export@example.com',
        ]);

        CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'code' => 'EXP-USED',
            'status' => 'used',
            'redeemed_at' => now()->subDay(),
            'used_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('coupons.export', $coupon));

        $response->assertOk();
        $contentType = $response->headers->get('content-type');
        $this->assertStringContainsString('text/csv', (string) $contentType);
    }
}
