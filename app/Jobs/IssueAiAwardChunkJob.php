<?php

// This queued job issues AI awards to a chunk of customers.
namespace App\Jobs;

use App\Models\AiAwardIssuance;
use App\Models\AiClusterAward;
use App\Models\AiClusterAwardCustomer;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PointsTransaction;
use App\Enums\PointsTransactionType;
use App\Enums\SourceType;
use App\Services\ShopifyDiscountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// This job grants AI-driven points or coupon awards while preventing duplicates.
class IssueAiAwardChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // This stores the award ID and the list of customers to process in this chunk.
    public function __construct(public int $awardId, public array $customerIds)
    {
    }

    // This issues the configured award to each customer in the batch.
    public function handle(ShopifyDiscountService $shopifyDiscounts): void
    {
        // This loads the award and its linked coupon so we can act on its type.
        $award = AiClusterAward::query()
            ->with('coupon')
            ->find($this->awardId);

        if (!$award || $award->status !== 'active') {
            return;
        }

        // Each customer is processed inside its own transaction to keep integrity.
        foreach ($this->customerIds as $customerId) {
            DB::transaction(function () use ($award, $customerId): void {
                // This prevents duplicate issuance for the same award/customer pair.
                $alreadyIssued = AiAwardIssuance::query()
                    ->where('ai_cluster_award_id', $award->id)
                    ->where('customer_id', $customerId)
                    ->exists();

                if ($alreadyIssued) {
                    return;
                }

                // This locks the award customer row to avoid double updates.
                $awardCustomer = AiClusterAwardCustomer::query()
                    ->where('ai_cluster_award_id', $award->id)
                    ->where('customer_id', $customerId)
                    ->lockForUpdate()
                    ->first();

                if (!$awardCustomer || $awardCustomer->status === 'issued') {
                    return;
                }

                // Points-based awards directly update the customer's balance.
                if ($award->type === 'points') {
                    $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->first();
                    if (!$customer) {
                        return;
                    }

                    $points = (int) ($award->points_amount ?? 0);
                    if ($points <= 0) {
                        return;
                    }

                    // This ensures the points award is idempotent.
                    $eventKey = "ai_award:{$award->id}:{$customerId}";
                    $exists = PointsTransaction::query()
                        ->where('customer_id', $customerId)
                        ->where('event_key', $eventKey)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    // This updates the balance before logging the transaction.
                    $customer->loyalty_points = (int) ($customer->loyalty_points ?? 0) + $points;
                    $customer->save();

                    // This records the award as a points transaction for audit history.
                    $transaction = PointsTransaction::create([
                        'customer_id' => $customerId,
                        'points' => $points,
                        'status' => 'APPROVED',
                        'source' => SourceType::AI->value,
                        'source_type' => SourceType::AI->value,
                        'type' => PointsTransactionType::EARN->value,
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

                    // This records the issuance so it can be referenced later.
                    AiAwardIssuance::create([
                        'ai_cluster_award_id' => $award->id,
                        'customer_id' => $customerId,
                        'reference_type' => 'PointsTransaction',
                        'reference_id' => (string) $transaction->id,
                        'issued_at' => now(),
                    ]);

                    // This marks the award customer row as issued.
                    $awardCustomer->update([
                        'status' => 'issued',
                        'issued_at' => now(),
                    ]);
                }

                // Coupon-based awards create a Shopify code and a local redemption record.
                if ($award->type === 'coupon' && $award->coupon_id) {
                    $coupon = $award->coupon;
                    if (!$coupon || !$coupon->shopify_price_rule_id) {
                        $awardCustomer->update([
                            'status' => 'failed',
                        ]);
                        return;
                    }

                    // This generates a unique code for the coupon redemption.
                    $code = $this->generateRedeemCode($coupon);

                    try {
                        // This creates the discount code in Shopify.
                        $shopifyDiscounts->createDiscountCode((int) $coupon->shopify_price_rule_id, $code);
                    } catch (\Throwable $exception) {
                        $awardCustomer->update([
                            'status' => 'failed',
                        ]);
                        return;
                    }

                    // This creates the local coupon redemption record.
                    $redemption = CustomerCoupon::create([
                        'customer_id' => $customerId,
                        'coupon_id' => $award->coupon_id,
                        'points_spent' => 0,
                        'code' => $code,
                        'status' => 'active',
                        'source' => SourceType::AI->value,
                        'redeemed_at' => now(),
                        'expires_at' => $coupon->end_date,
                    ]);

                    // This logs the award in the points history with a zero-point entry.
                    $eventKey = "ai_award:{$award->id}:{$customerId}";
                    PointsTransaction::create([
                        'customer_id' => $customerId,
                        'points' => 0,
                        'status' => 'APPROVED',
                        'source' => SourceType::AI->value,
                        'source_type' => SourceType::AI->value,
                        'type' => PointsTransactionType::EARN->value,
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

                    // This records the issuance so it can be referenced later.
                    AiAwardIssuance::create([
                        'ai_cluster_award_id' => $award->id,
                        'customer_id' => $customerId,
                        'reference_type' => 'CustomerCoupon',
                        'reference_id' => (string) $redemption->id,
                        'issued_at' => now(),
                    ]);

                    // This marks the award customer row as issued.
                    $awardCustomer->update([
                        'status' => 'issued',
                        'issued_at' => now(),
                    ]);
                }
            });
        }
    }

    // This generates a unique coupon code with a readable prefix.
    private function generateRedeemCode($coupon): string
    {
        // This builds a compact prefix from the coupon title.
        $prefix = strtoupper(Str::slug((string) $coupon->title));
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', $prefix), 0, 8);

        // This retries a few times to avoid code collisions.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = trim($prefix.'-'.strtoupper(Str::random(8)), '-');
            $exists = CustomerCoupon::where('code', $code)->exists();
            if (!$exists) {
                return $code;
            }
        }

        // This fallback uses a random code if all attempts collide.
        return strtoupper(Str::random(12));
    }
}
