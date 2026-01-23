<?php

namespace App\Jobs;

use App\Models\AiAwardIssuance;
use App\Models\AiClusterAward;
use App\Models\AiClusterAwardCustomer;
use App\Models\CouponCode;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointsTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IssueAiAwardChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $awardId, public array $customerIds)
    {
    }

    public function handle(): void
    {
        $award = AiClusterAward::query()
            ->with('coupon')
            ->find($this->awardId);

        if (!$award || $award->status !== 'active') {
            return;
        }

        foreach ($this->customerIds as $customerId) {
            DB::transaction(function () use ($award, $customerId): void {
                $alreadyIssued = AiAwardIssuance::query()
                    ->where('ai_cluster_award_id', $award->id)
                    ->where('customer_id', $customerId)
                    ->exists();

                if ($alreadyIssued) {
                    return;
                }

                $awardCustomer = AiClusterAwardCustomer::query()
                    ->where('ai_cluster_award_id', $award->id)
                    ->where('customer_id', $customerId)
                    ->lockForUpdate()
                    ->first();

                if (!$awardCustomer || $awardCustomer->status === 'issued') {
                    return;
                }

                if ($award->type === 'points') {
                    $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->first();
                    if (!$customer) {
                        return;
                    }

                    $points = (int) ($award->points_amount ?? 0);
                    if ($points <= 0) {
                        return;
                    }

                    $eventKey = "ai_award:{$award->id}:{$customerId}";
                    $exists = PointsTransaction::query()
                        ->where('customer_id', $customerId)
                        ->where('event_key', $eventKey)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    $customer->loyalty_points = (int) ($customer->loyalty_points ?? 0) + $points;
                    $customer->save();

                    $transaction = PointsTransaction::create([
                        'customer_id' => $customerId,
                        'points' => $points,
                        'status' => 'APPROVED',
                        'source' => 'AI',
                        'source_type' => 'AI',
                        'type' => 'EARN',
                        'event_key' => $eventKey,
                        'reason' => 'Smart Offer',
                        'reference_type' => 'AiClusterAward',
                        'reference_id' => (string) $award->id,
                        'meta' => [
                            'title' => $award->title,
                            'badge' => 'Smart Offer',
                            'award_type' => 'points',
                        ],
                    ]);

                    AiAwardIssuance::create([
                        'ai_cluster_award_id' => $award->id,
                        'customer_id' => $customerId,
                        'reference_type' => 'PointsTransaction',
                        'reference_id' => (string) $transaction->id,
                        'issued_at' => now(),
                    ]);

                    $awardCustomer->update([
                        'status' => 'issued',
                        'issued_at' => now(),
                    ]);
                }

                if ($award->type === 'coupon' && $award->coupon_id) {
                    $couponCode = CouponCode::query()
                        ->where('coupon_id', $award->coupon_id)
                        ->where('status', 'available')
                        ->lockForUpdate()
                        ->first();

                    if (!$couponCode) {
                        $awardCustomer->update([
                            'status' => 'failed',
                        ]);
                        return;
                    }

                    $couponCode->update([
                        'status' => 'issued',
                        'customer_id' => $customerId,
                        'issued_at' => now(),
                    ]);

                    $redemption = CustomerCoupon::create([
                        'customer_id' => $customerId,
                        'coupon_id' => $award->coupon_id,
                        'points_spent' => 0,
                        'code' => $couponCode->code,
                        'status' => 'active',
                        'source' => 'AI',
                        'redeemed_at' => now(),
                        'expires_at' => $award->coupon?->end_date,
                    ]);

                    $eventKey = "ai_award:{$award->id}:{$customerId}";
                    PointsTransaction::create([
                        'customer_id' => $customerId,
                        'points' => 0,
                        'status' => 'APPROVED',
                        'source' => 'AI',
                        'source_type' => 'AI',
                        'type' => 'EARN',
                        'event_key' => $eventKey,
                        'reason' => 'Smart Offer',
                        'reference_type' => 'CustomerCoupon',
                        'reference_id' => (string) $redemption->id,
                        'meta' => [
                            'title' => $award->title,
                            'badge' => 'Smart Offer',
                            'award_type' => 'coupon',
                            'coupon_id' => $award->coupon_id,
                        ],
                    ]);

                    AiAwardIssuance::create([
                        'ai_cluster_award_id' => $award->id,
                        'customer_id' => $customerId,
                        'reference_type' => 'CustomerCoupon',
                        'reference_id' => (string) $redemption->id,
                        'issued_at' => now(),
                    ]);

                    $awardCustomer->update([
                        'status' => 'issued',
                        'issued_at' => now(),
                    ]);
                }
            });
        }
    }
}
