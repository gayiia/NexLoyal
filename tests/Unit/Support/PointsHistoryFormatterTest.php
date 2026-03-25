<?php

use App\Models\PointsTransaction;
use App\Support\PointsHistoryFormatter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

function makeTransaction(array $attributes = []): PointsTransaction
{
    $transaction = new PointsTransaction();
    $transaction->forceFill(array_merge([
        'id' => 1,
        'points' => 100,
        'status' => 'approved',
        'source' => 'ORDER',
        'source_type' => 'ORDER',
        'type' => 'EARN',
        'reason' => null,
        'reference_id' => null,
        'order_id' => null,
        'meta' => [],
        'created_at' => Carbon::parse('2026-03-25 10:00:00'),
    ], $attributes));

    return $transaction;
}

test('it formats an earned order transaction with order number metadata', function () {
    $transaction = makeTransaction([
        'meta' => ['order_number' => '5063'],
    ]);

    expect(PointsHistoryFormatter::format($transaction))->toMatchArray([
        'id' => 1,
        'direction' => 'EARN',
        'points' => 100,
        'status' => 'APPROVED',
        'title' => 'Order ID #5063',
        'type' => 'Order',
        'created_at' => '2026-03-25T10:00:00+00:00',
    ]);
});

test('it treats spend transactions as redeem direction', function () {
    $transaction = makeTransaction([
        'points' => 250,
        'type' => 'SPEND',
    ]);

    expect(PointsHistoryFormatter::direction($transaction))->toBe('REDEEM');
});

test('it treats negative points as redeem direction', function () {
    $transaction = makeTransaction([
        'points' => -30,
        'type' => 'EARN',
    ]);

    expect(PointsHistoryFormatter::direction($transaction))->toBe('REDEEM');
});

test('it builds an order title from reference id when order number is missing', function () {
    $transaction = makeTransaction([
        'reference_id' => '99',
    ]);

    expect(PointsHistoryFormatter::title($transaction))->toBe('Order (ID: 99)');
});

test('it builds a coupon title from coupon metadata when available', function () {
    $transaction = makeTransaction([
        'source' => 'COUPON',
        'source_type' => 'COUPON',
        'type' => 'SPEND',
        'meta' => [
            'coupon_title' => 'Summer Deal',
            'coupon_id' => 42,
        ],
    ]);

    expect(PointsHistoryFormatter::title($transaction))
        ->toBe('Coupon redeemed: Summer Deal (ID: 42)');
});

test('it falls back to coupon reference id when coupon metadata is missing', function () {
    $transaction = makeTransaction([
        'source' => 'COUPON',
        'source_type' => 'COUPON',
        'type' => 'SPEND',
        'reference_id' => '7',
    ]);

    expect(PointsHistoryFormatter::title($transaction))->toBe('Coupon redeemed (Ref: 7)');
});

test('it uses ai meta title before reason and default', function () {
    $transaction = makeTransaction([
        'source' => 'AI',
        'source_type' => 'AI',
        'meta' => ['title' => 'VIP Bonus'],
        'reason' => 'Smart offer fallback',
    ]);

    expect(PointsHistoryFormatter::title($transaction))->toBe('Smart Offer: VIP Bonus');
});

test('it falls back to ai default title when meta title is missing', function () {
    $transaction = makeTransaction([
        'source' => 'AI',
        'source_type' => 'AI',
        'meta' => [],
        'reason' => null,
    ]);

    expect(PointsHistoryFormatter::title($transaction))->toBe('Smart Offer');
});

test('it maps source types to user facing labels', function () {
    $transaction = makeTransaction([
        'source' => 'SOCIAL',
        'source_type' => 'SOCIAL',
    ]);

    expect(PointsHistoryFormatter::typeLabel($transaction))->toBe('Social Media');
    expect(PointsHistoryFormatter::typeLabel(makeTransaction([
        'source' => 'UNKNOWN',
        'source_type' => 'UNKNOWN',
    ])))->toBe('Manual');
});
