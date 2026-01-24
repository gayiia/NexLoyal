<?php

namespace App\Services;

use App\Enums\PointsTransactionType;
use App\Enums\SourceType;
use App\Models\Customer;
use App\Models\PointRule;
use App\Models\PointsTransaction;
use App\Models\Tier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LoyaltyRulesEngine
{
    public function resolveTier(int $points): ?Tier
    {
        return Tier::query()
            ->where('status', 'active')
            ->where('min_points', '<=', $points)
            ->orderByDesc('min_points')
            ->first();
    }

    public function awardWelcomePoints(Customer $customer): void
    {
        $rule = PointRule::query()->first();
        $points = (int) ($rule?->welcome_points ?? 0);
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $points): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            $exists = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('event_key', 'welcome_bonus')
                ->exists();

            if ($exists) {
                return;
            }

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

            $lockedCustomer->loyalty_points = (int) ($lockedCustomer->loyalty_points ?? 0) + $points;
            $lockedCustomer->save();
        });
    }

    public function applyOrderPending(
        Customer $customer,
        int $orderId,
        int $earnedPoints,
        float $eligibleAmount,
        int $amountPerPoint,
        array $orderData
    ): void {
        $eventKey = "order_earn_pending:{$orderId}";
        DB::transaction(function () use ($customer, $eventKey, $orderId, $earnedPoints, $eligibleAmount, $amountPerPoint, $orderData): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            $exists = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('event_key', $eventKey)
                ->exists();

            if ($exists) {
                return;
            }

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

            $lockedCustomer->points_pending = (int) ($lockedCustomer->points_pending ?? 0) + $earnedPoints;
            $lockedCustomer->save();
        });
    }

    public function approveOrderPoints(Customer $customer, int $orderId): void
    {
        DB::transaction(function () use ($customer, $orderId): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedCustomer) {
                return;
            }

            $transaction = PointsTransaction::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('order_id', $orderId)
                ->where('status', 'PENDING')
                ->first();

            if (!$transaction) {
                return;
            }

            $points = (int) $transaction->points;
            $transaction->status = 'APPROVED';
            $transaction->save();

            $lockedCustomer->points_pending = max(0, (int) ($lockedCustomer->points_pending ?? 0) - $points);
            $lockedCustomer->loyalty_points = (int) ($lockedCustomer->loyalty_points ?? 0) + $points;
            $lockedCustomer->save();
        });
    }

    public function reverseOrderPoints(Customer $customer, int $orderId, array $refunds): void
    {
        $refunds = $refunds ?: [['id' => 'refund']];

        foreach ($refunds as $refund) {
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

                $exists = PointsTransaction::query()
                    ->where('customer_id', $lockedCustomer->id)
                    ->where('event_key', $eventKey)
                    ->exists();

                if ($exists) {
                    return;
                }

                $transaction = PointsTransaction::query()
                    ->where('customer_id', $lockedCustomer->id)
                    ->where('order_id', $orderId)
                    ->whereIn('status', ['PENDING', 'APPROVED'])
                    ->first();

                if (!$transaction) {
                    return;
                }

                $points = (int) $transaction->points;
                $pending = (int) ($lockedCustomer->points_pending ?? 0);
                $available = (int) ($lockedCustomer->loyalty_points ?? 0);
                $reversePoints = min($points, $transaction->status === 'PENDING' ? $pending : $available);

                if ($reversePoints <= 0) {
                    return;
                }

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

                if ($transaction->status === 'PENDING') {
                    $lockedCustomer->points_pending = max(0, $pending - $reversePoints);
                } else {
                    $lockedCustomer->loyalty_points = max(0, $available - $reversePoints);
                }

                $lockedCustomer->save();
            });
        }
    }

    public function awardProfileAndBirthday(Customer $customer, PointRule $rule): array
    {
        $awardedProfilePoints = false;
        $awardedBirthdayPoints = false;

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
                    $this->sendBirthdayEmail($customer, $birthdayPoints);
                }
            }
        }

        $customer->save();

        return [
            'awarded_profile_points' => $awardedProfilePoints,
            'awarded_birthday_points' => $awardedBirthdayPoints,
        ];
    }

    public function awardSocialVisit(Customer $customer, string $platform, PointRule $rule): array
    {
        $platform = strtolower(trim($platform));
        $platformConfig = [
            'linkedin' => ['url' => $rule->social_linkedin_url, 'points' => $rule->social_linkedin_points],
            'tiktok' => ['url' => $rule->social_tiktok_url, 'points' => $rule->social_tiktok_points],
            'facebook' => ['url' => $rule->social_facebook_url, 'points' => $rule->social_facebook_points],
            'x' => ['url' => $rule->social_x_url, 'points' => $rule->social_x_points],
            'instagram' => ['url' => $rule->social_instagram_url, 'points' => $rule->social_instagram_points],
            'youtube' => ['url' => $rule->social_youtube_url, 'points' => $rule->social_youtube_points],
        ];

        $config = $platformConfig[$platform] ?? [];
        $points = (int) ($config['points'] ?? 0);
        $url = trim((string) ($config['url'] ?? ''));

        if ($points <= 0 || $url === '') {
            return ['awarded' => false, 'message' => 'Social reward not configured.'];
        }

        $eventKey = "social_reward:{$platform}";
        $exists = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('event_key', $eventKey)
            ->exists();

        if ($exists) {
            return ['awarded' => false, 'message' => 'Already claimed.'];
        }

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

        $customer->loyalty_points += $points;
        $customer->save();

        return ['awarded' => true, 'message' => 'Reward applied.', 'points' => $points, 'url' => $url];
    }

    private function sendBirthdayEmail(Customer $customer, int $points): void
    {
        if (!$customer->email) {
            return;
        }

        Mail::send('emails.birthday', [
            'customer' => $customer,
            'points' => $points,
        ], function ($message) use ($customer): void {
            $message->to($customer->email, $customer->full_name ?: $customer->email)
                ->subject('Happy Birthday from NexLoyal');
        });
    }
}
