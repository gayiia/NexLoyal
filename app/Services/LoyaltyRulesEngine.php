<?php

// This service encapsulates the loyalty rules that award, approve, and reverse points.
namespace App\Services;

use App\Enums\PointsTransactionType;
use App\Enums\SourceType;
use App\Models\Customer;
use App\Models\PointRule;
use App\Models\PointsTransaction;
use App\Models\Tier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// This class provides a single place to apply loyalty logic based on orders and profile activity.
class LoyaltyRulesEngine
{
    // This resolves the active tier for a given points balance.
    public function resolveTier(int $points): ?Tier
    {
        return Tier::query()
            ->where('status', 'active')
            ->where('min_points', '<=', $points)
            ->orderByDesc('min_points')
            ->first();
    }

    // This awards a one-time welcome bonus to new customers.
    public function awardWelcomePoints(Customer $customer): void
    {
        // This reads the configured welcome bonus and exits if it is not enabled.
        $rule = PointRule::query()->first();
        $points = (int) ($rule?->welcome_points ?? 0);
        if ($points <= 0) {
            return;
        }

        // This locks the customer row to avoid double-awarding points.
        DB::transaction(function () use ($customer, $points): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            // This prevents awarding the welcome bonus more than once.
            $exists = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('event_key', 'welcome_bonus')
                ->exists();

            if ($exists) {
                return;
            }

            // This records a points transaction for the welcome bonus.
            PointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'points' => $points,
                'status' => 'APPROVED',
                'source' => SourceType::RULE->value,
                'source_type' => SourceType::REGISTER->value,
                'type' => PointsTransactionType::EARN->value,
                'event_key' => 'welcome_bonus',
                'reason' => 'Welcome bonus',
            ]);

            // This updates the customer's points balance after awarding.
            $lockedCustomer->loyalty_points = (int) ($lockedCustomer->loyalty_points ?? 0) + $points;
            $lockedCustomer->save();
        });
    }

    // This creates a pending points transaction for an order before fulfillment.
    public function applyOrderPending(
        Customer $customer,
        int $orderId,
        int $earnedPoints,
        float $eligibleAmount,
        int $amountPerPoint,
        array $orderData
    ): void {
        // This uses a unique event key so repeated webhooks do not double-count points.
        $eventKey = "order_earn_pending:{$orderId}";
        // This locks the customer row to keep points consistent while writing transactions.
        DB::transaction(function () use ($customer, $eventKey, $orderId, $earnedPoints, $eligibleAmount, $amountPerPoint, $orderData): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            // This skips creating a duplicate transaction if the webhook was already processed.
            $exists = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('event_key', $eventKey)
                ->exists();

            if ($exists) {
                return;
            }

            // This records the pending transaction with metadata used for reporting.
            PointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'points' => $earnedPoints,
                'status' => 'PENDING',
                'source' => SourceType::ORDER->value,
                'source_type' => SourceType::ORDER->value,
                'type' => PointsTransactionType::EARN->value,
                'order_id' => $orderId,
                'event_key' => $eventKey,
                'reason' => 'Order pending points',
                'reference_type' => 'Order',
                'reference_id' => (string) $orderId,
                'meta' => [
                    'eligible_amount' => $eligibleAmount,
                    'amount_per_point' => $amountPerPoint,
                    'order_number' => data_get($orderData, 'order_number') ?? data_get($orderData, 'name'),
                ],
            ]);

            // This increases the pending balance until the order is fulfilled.
            $lockedCustomer->points_pending = (int) ($lockedCustomer->points_pending ?? 0) + $earnedPoints;
            $lockedCustomer->save();
        });
    }

    // This approves pending order points once an order is fulfilled.
    public function approveOrderPoints(Customer $customer, int $orderId): void
    {
        // This transaction ensures the points move from pending to approved atomically.
        DB::transaction(function () use ($customer, $orderId): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            // This finds the pending transaction for the specific order.
            $transaction = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('order_id', $orderId)
                ->where('status', 'PENDING')
                ->first();

            if (!$transaction) {
                return;
            }

            // This moves the transaction to approved status and updates balances.
            $points = (int) $transaction->points;
            $transaction->status = 'APPROVED';
            $transaction->save();

            $lockedCustomer->points_pending = max(0, (int) ($lockedCustomer->points_pending ?? 0) - $points);
            $lockedCustomer->loyalty_points = (int) ($lockedCustomer->loyalty_points ?? 0) + $points;
            $lockedCustomer->save();
        });
    }

    // This reverses points for refunds or cancellations while preventing double adjustments.
    public function reverseOrderPoints(Customer $customer, int $orderId, array $refunds): void
    {
        // This ensures at least one refund entry exists so the logic runs once.
        $refunds = $refunds ?: [['id' => 'refund']];

        foreach ($refunds as $refund) {
            // This builds a unique event key per refund to avoid duplicates.
            $refundId = (string) (data_get($refund, 'id') ?? 'refund');
            $eventKey = "order_refund_adjust:{$orderId}:{$refundId}";

            DB::transaction(function () use ($customer, $orderId, $eventKey): void {
                $lockedCustomer = Customer::query()
                    ->whereKey($customer->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedCustomer) {
                    return;
                }

                // This skips processing if this refund has already been applied.
                $exists = PointsTransaction::query()
                    ->where('customer_id', $lockedCustomer->id)
                    ->where('event_key', $eventKey)
                    ->exists();

                if ($exists) {
                    return;
                }

                // This locates the original points transaction to know what to reverse.
                $transaction = PointsTransaction::query()
                    ->where('customer_id', $lockedCustomer->id)
                    ->where('order_id', $orderId)
                    ->whereIn('status', ['PENDING', 'APPROVED'])
                    ->first();

                if (!$transaction) {
                    return;
                }

                // This reverses only what is currently available in pending or approved balances.
                $points = (int) $transaction->points;
                $pending = (int) ($lockedCustomer->points_pending ?? 0);
                $available = (int) ($lockedCustomer->loyalty_points ?? 0);
                $reversePoints = min($points, $transaction->status === 'PENDING' ? $pending : $available);

                if ($reversePoints <= 0) {
                    return;
                }

                // This records a reversing transaction for audit and reporting.
                PointsTransaction::create([
                    'customer_id' => $lockedCustomer->id,
                    'points' => -$reversePoints,
                    'status' => 'REVERSED',
                    'source' => SourceType::ORDER->value,
                    'source_type' => SourceType::ORDER->value,
                    'type' => PointsTransactionType::EARN->value,
                    'order_id' => $orderId,
                    'event_key' => $eventKey,
                    'reason' => 'Order refund adjustment',
                    'reference_type' => 'Order',
                    'reference_id' => (string) $orderId,
                ]);

                // This adjusts the correct balance based on the original transaction status.
                if ($transaction->status === 'PENDING') {
                    $lockedCustomer->points_pending = max(0, $pending - $reversePoints);
                } else {
                    $lockedCustomer->loyalty_points = max(0, $available - $reversePoints);
                }

                $lockedCustomer->save();
            });
        }
    }

    // This awards profile completion and birthday points if eligibility conditions are met.
    public function awardProfileAndBirthday(Customer $customer, PointRule $rule): array
    {
        $awardedProfilePoints = false;
        $awardedBirthdayPoints = false;

        // This awards profile completion points only once and only when all fields are present.
        $profilePoints = (int) ($rule?->profile_completion_points ?? 0);
        if ($profilePoints > 0 && !$customer->profile_completed_at) {
            if ($customer->first_name && $customer->last_name && $customer->email && $customer->phone) {
                $customer->loyalty_points += $profilePoints;
                $awardedProfilePoints = true;
                PointsTransaction::create([
                    'customer_id' => $customer->id,
                    'points' => $profilePoints,
                    'status' => 'APPROVED',
                    'source' => SourceType::RULE->value,
                    'source_type' => SourceType::PROFILE->value,
                    'type' => PointsTransactionType::EARN->value,
                    'event_key' => 'profile_completion',
                    'reason' => 'Profile completion reward',
                ]);
                $customer->profile_completed_at = now();
            }
        }

        // This awards birthday points once per calendar year on the birthday date.
        $birthdayPoints = (int) ($rule?->birthday_points ?? 0);
        if ($birthdayPoints > 0 && $customer->birthday) {
            if ($customer->birthday->format('m-d') === now()->format('m-d')) {
                $lastReward = $customer->birthday_rewarded_at?->format('Y');
                if ($lastReward !== now()->format('Y')) {
                    $customer->loyalty_points += $birthdayPoints;
                    $awardedBirthdayPoints = true;
                    $customer->birthday_rewarded_at = now();
                    PointsTransaction::create([
                        'customer_id' => $customer->id,
                        'points' => $birthdayPoints,
                        'status' => 'APPROVED',
                        'source' => SourceType::RULE->value,
                        'source_type' => SourceType::BIRTHDAY->value,
                        'type' => PointsTransactionType::EARN->value,
                        'event_key' => 'birthday_reward:'.now()->format('Y'),
                        'reason' => 'Birthday reward',
                    ]);
                    // This sends a celebratory email to the customer if an address exists.
                    $this->sendBirthdayEmail($customer, $birthdayPoints);
                }
            }
        }

        // This persists any points or timestamp changes made above.
        $customer->save();

        return [
            'awarded_profile_points' => $awardedProfilePoints,
            'awarded_birthday_points' => $awardedBirthdayPoints,
        ];
    }

    // This awards points for visiting a configured social platform link.
    public function awardSocialVisit(Customer $customer, string $platform, PointRule $rule): array
    {
        // This normalizes the platform name for consistent lookup.
        $platform = strtolower(trim($platform));
        $platformConfig = [
            'linkedin' => ['url' => $rule->social_linkedin_url, 'points' => $rule->social_linkedin_points],
            'tiktok' => ['url' => $rule->social_tiktok_url, 'points' => $rule->social_tiktok_points],
            'facebook' => ['url' => $rule->social_facebook_url, 'points' => $rule->social_facebook_points],
            'x' => ['url' => $rule->social_x_url, 'points' => $rule->social_x_points],
            'instagram' => ['url' => $rule->social_instagram_url, 'points' => $rule->social_instagram_points],
            'youtube' => ['url' => $rule->social_youtube_url, 'points' => $rule->social_youtube_points],
        ];

        // This extracts the configured URL and points for the selected platform.
        $config = $platformConfig[$platform] ?? [];
        $points = (int) ($config['points'] ?? 0);
        $url = trim((string) ($config['url'] ?? ''));

        // This prevents awarding when the platform is not configured.
        if ($points <= 0 || $url === '') {
            return ['awarded' => false, 'message' => 'Social reward not configured.'];
        }

        // This ensures each platform reward is claimed only once per customer.
        $eventKey = "social_reward:{$platform}";
        $exists = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('event_key', $eventKey)
            ->exists();

        if ($exists) {
            return ['awarded' => false, 'message' => 'Already claimed.'];
        }

        // This records the reward transaction and stores the platform in metadata.
        PointsTransaction::create([
            'customer_id' => $customer->id,
            'points' => $points,
            'status' => 'APPROVED',
            'source' => SourceType::RULE->value,
            'source_type' => SourceType::SOCIAL->value,
            'type' => PointsTransactionType::EARN->value,
            'event_key' => $eventKey,
            'reason' => 'Social visit reward',
            'meta' => ['platform' => $platform],
        ]);

        // This updates the customer's points balance after the reward is granted.
        $customer->loyalty_points += $points;
        $customer->save();

        return ['awarded' => true, 'message' => 'Reward applied.', 'points' => $points, 'url' => $url];
    }

    // This sends a birthday email using the configured template.
    private function sendBirthdayEmail(Customer $customer, int $points): void
    {
        // This skips emailing when the customer has no address on file.
        if (!$customer->email) {
            return;
        }

        // This renders the birthday email and sends it to the customer.
        Mail::send('emails.birthday', [
            'customer' => $customer,
            'points' => $points,
        ], function ($message) use ($customer): void {
            $message->to($customer->email, $customer->full_name ?: $customer->email)
                ->subject('Happy Birthday from NexLoyal');
        });
    }
}
