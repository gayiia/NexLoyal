<?php

namespace App\Support;

use App\Models\PointsTransaction;

class PointsHistoryFormatter
{
    public static function format(PointsTransaction $transaction): array
    {
        $direction = self::direction($transaction);
        $points = abs((int) $transaction->points);
        $status = strtoupper((string) $transaction->status);
        $type = self::typeLabel($transaction);
        $title = self::title($transaction);

        return [
            'id' => $transaction->id,
            'direction' => $direction,
            'points' => $points,
            'status' => $status,
            'title' => $title,
            'type' => $type,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    public static function direction(PointsTransaction $transaction): string
    {
        if (strtoupper((string) $transaction->type) === 'SPEND' || $transaction->points < 0) {
            return 'REDEEM';
        }

        return 'EARN';
    }

    public static function typeLabel(PointsTransaction $transaction): string
    {
        $sourceType = strtoupper((string) ($transaction->source_type ?? $transaction->source ?? ''));
        return match ($sourceType) {
            'ORDER' => 'Order',
            'COUPON' => 'Coupon',
            'REGISTER' => 'Register',
            'SOCIAL' => 'Social Media',
            'BIRTHDAY' => 'Birthday',
            'PROFILE' => 'Profile',
            'MANUAL' => 'Manual',
            default => 'Manual',
        };
    }

    public static function title(PointsTransaction $transaction): string
    {
        $sourceType = strtoupper((string) ($transaction->source_type ?? $transaction->source ?? ''));
        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $referenceId = $transaction->reference_id ?? $transaction->order_id;

        if ($sourceType === 'ORDER') {
            $orderNumber = $meta['order_number'] ?? null;
            if ($orderNumber) {
                return "Order ID #{$orderNumber}";
            }
            if ($referenceId) {
                return "Order (ID: {$referenceId})";
            }
            return 'Order points';
        }

        if ($sourceType === 'REGISTER') {
            return 'Signed up';
        }

        if ($sourceType === 'SOCIAL') {
            $platform = $meta['platform'] ?? null;
            return $platform ? "Social reward: {$platform}" : 'Social reward';
        }

        if ($sourceType === 'BIRTHDAY') {
            return 'Birthday reward';
        }

        if ($sourceType === 'PROFILE') {
            return 'Profile completed';
        }

        if ($sourceType === 'COUPON') {
            $couponTitle = $meta['coupon_title'] ?? null;
            $couponId = $meta['coupon_id'] ?? null;
            if ($couponTitle && $couponId) {
                return "Coupon redeemed: {$couponTitle} (ID: {$couponId})";
            }
            if ($referenceId) {
                return "Coupon redeemed (Ref: {$referenceId})";
            }
            return 'Coupon redeemed';
        }

        if (!empty($meta['title'])) {
            return (string) $meta['title'];
        }

        if ($transaction->reason) {
            return (string) $transaction->reason;
        }

        return 'Points earned';
    }
}
