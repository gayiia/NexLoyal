<?php

// This helper formats points transactions into display-friendly fields.
namespace App\Support;

use App\Models\PointsTransaction;

// This class centralizes label and title rules for points history UI.
class PointsHistoryFormatter
{
    // This builds a normalized payload for a single points transaction.
    public static function format(PointsTransaction $transaction): array
    {
        // These derived fields ensure consistent display in the UI.
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

    // This determines whether a transaction is an earn or redeem movement.
    public static function direction(PointsTransaction $transaction): string
    {
        // Negative points or spend type are treated as redemption in the UI.
        if (strtoupper((string) $transaction->type) === 'SPEND' || $transaction->points < 0) {
            return 'REDEEM';
        }

        return 'EARN';
    }

    // This maps the source type to a human-readable category label.
    public static function typeLabel(PointsTransaction $transaction): string
    {
        $sourceType = strtoupper((string) ($transaction->source_type ?? $transaction->source ?? ''));
        return match ($sourceType) {
            'ORDER' => 'Order',
            'COUPON' => 'Coupon',
            'AI' => 'Smart Offer',
            'REGISTER' => 'Register',
            'SOCIAL' => 'Social Media',
            'BIRTHDAY' => 'Birthday',
            'PROFILE' => 'Profile',
            'MANUAL' => 'Manual',
            default => 'Manual',
        };
    }

    // This builds a descriptive title that explains why the points changed.
    public static function title(PointsTransaction $transaction): string
    {
        $sourceType = strtoupper((string) ($transaction->source_type ?? $transaction->source ?? ''));
        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $referenceId = $transaction->reference_id ?? $transaction->order_id;

        // These branches select a title based on the source type and available metadata.
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

        if ($sourceType === 'AI') {
            $title = $meta['title'] ?? null;
            if ($title) {
                return "Smart Offer: {$title}";
            }
            return 'Smart Offer';
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
