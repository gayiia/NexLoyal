<?php

use App\Jobs\IssueAiAwardChunkJob;
use App\Models\AiAwardIssuance;
use App\Models\AiCluster;
use App\Models\AiClusterAward;
use App\Models\AiClusterAwardCustomer;
use App\Models\AiClusterCustomer;
use App\Models\AiClusterRun;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\Customer;
use App\Models\PointsTransaction;
use App\Models\User;
use App\Support\PointsHistoryFormatter;
use Illuminate\Support\Carbon;

test('awards are saved as draft and do not issue on create', function () {
    $this->actingAs(User::factory()->create());

    $run = AiClusterRun::create([
        'status' => 'completed',
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
        'total_customers' => 1,
        'total_clusters' => 1,
    ]);

    $cluster = AiCluster::create([
        'ai_cluster_run_id' => $run->id,
        'label' => 'Cluster 1',
        'customer_count' => 1,
    ]);

    $customer = Customer::create(['shopify_id' => 'cust_1']);
    AiClusterCustomer::create([
        'ai_cluster_run_id' => $run->id,
        'ai_cluster_id' => $cluster->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->post(route('ai-insights.awards.store'), [
        'title' => 'Welcome gift',
        'ai_cluster_id' => $cluster->id,
        'type' => 'points',
        'points_amount' => 120,
    ]);

    $response->assertRedirect(route('ai-insights'));

    $award = AiClusterAward::query()->first();
    expect($award)->not()->toBeNull();
    expect($award->status)->toBe('draft');
    expect(AiAwardIssuance::count())->toBe(0);
    expect(PointsTransaction::count())->toBe(0);
});

test('award issuing is idempotent per customer', function () {
    $customer = Customer::create(['shopify_id' => 'cust_2']);
    $run = AiClusterRun::create([
        'status' => 'completed',
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
        'total_customers' => 1,
        'total_clusters' => 1,
    ]);
    $cluster = AiCluster::create([
        'ai_cluster_run_id' => $run->id,
        'label' => 'Cluster 1',
        'customer_count' => 1,
    ]);
    $award = AiClusterAward::create([
        'ai_cluster_id' => $cluster->id,
        'title' => 'Smart offer',
        'type' => 'points',
        'points_amount' => 50,
        'status' => 'active',
        'activated_at' => now(),
    ]);

    AiClusterAwardCustomer::create([
        'ai_cluster_award_id' => $award->id,
        'customer_id' => $customer->id,
        'status' => 'pending',
    ]);

    IssueAiAwardChunkJob::dispatchSync($award->id, [$customer->id]);
    IssueAiAwardChunkJob::dispatchSync($award->id, [$customer->id]);

    expect(AiAwardIssuance::count())->toBe(1);
    expect(PointsTransaction::where('event_key', "ai_award:{$award->id}:{$customer->id}")->count())->toBe(1);
    $customer->refresh();
    expect($customer->loyalty_points)->toBe(50);
});

test('coupon awards require sufficient coupon codes', function () {
    $this->actingAs(User::factory()->create());

    $run = AiClusterRun::create([
        'status' => 'completed',
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
        'total_customers' => 2,
        'total_clusters' => 1,
    ]);

    $cluster = AiCluster::create([
        'ai_cluster_run_id' => $run->id,
        'label' => 'Cluster 1',
        'customer_count' => 2,
    ]);

    $coupon = Coupon::create([
        'title' => 'AI Coupon',
        'type' => 'amount-order',
        'value_type' => 'fixed',
        'value' => 10,
        'points_value' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'status' => 'active',
        'is_ai_cluster_coupon' => true,
    ]);

    $award = AiClusterAward::create([
        'ai_cluster_id' => $cluster->id,
        'title' => 'AI Coupon Award',
        'type' => 'coupon',
        'coupon_id' => $coupon->id,
        'status' => 'draft',
    ]);

    $customers = [
        Customer::create(['shopify_id' => 'cust_3']),
        Customer::create(['shopify_id' => 'cust_4']),
    ];

    foreach ($customers as $customer) {
        AiClusterAwardCustomer::create([
            'ai_cluster_award_id' => $award->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);
    }

    CouponCode::create([
        'coupon_id' => $coupon->id,
        'code' => 'AI-CODE-ONE',
        'status' => 'available',
    ]);

    $response = $this->patch(route('ai-insights.awards.activate', $award));
    $response->assertRedirect(route('ai-insights'));
    $response->assertSessionHasErrors('award');

    $award->refresh();
    expect($award->status)->toBe('draft');
});

test('points history formatter labels smart offers', function () {
    $customer = Customer::create(['shopify_id' => 'cust_5']);
    $transaction = PointsTransaction::create([
        'customer_id' => $customer->id,
        'points' => 50,
        'status' => 'APPROVED',
        'source' => 'AI',
        'source_type' => 'AI',
        'type' => 'EARN',
        'event_key' => 'ai_award:sample',
        'reason' => 'Smart Offer',
        'meta' => ['title' => 'VIP Bonus'],
        'created_at' => Carbon::now(),
    ]);

    $formatted = PointsHistoryFormatter::format($transaction);
    expect($formatted['type'])->toBe('Smart Offer');
    expect($formatted['title'])->toContain('Smart Offer');
});

test('ai insights charts use aggregated data', function () {
    $this->actingAs(User::factory()->create());

    $run = AiClusterRun::create([
        'status' => 'completed',
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
        'total_customers' => 3,
        'total_clusters' => 1,
        'silhouette_score' => 0.42,
    ]);

    $cluster = AiCluster::create([
        'ai_cluster_run_id' => $run->id,
        'label' => 'Cluster 1',
        'customer_count' => 3,
        'avg_total_spent' => 120.5,
    ]);

    $award = AiClusterAward::create([
        'ai_cluster_id' => $cluster->id,
        'title' => 'Points Award',
        'type' => 'points',
        'points_amount' => 25,
        'status' => 'active',
    ]);

    $customer = Customer::create(['shopify_id' => 'cust_6']);
    AiAwardIssuance::create([
        'ai_cluster_award_id' => $award->id,
        'customer_id' => $customer->id,
        'issued_at' => now(),
    ]);

    $response = $this->get(route('ai-insights'));
    $response->assertOk();
    $response->assertViewHas('charts', function ($charts) {
        return $charts['distribution'][0] === 3
            && $charts['avg_spend'][0] === 120.5
            && $charts['award_mix']['points'] === 1;
    });
});
