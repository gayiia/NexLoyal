<?php

// This enum identifies the origin of a loyalty event or transaction.
namespace App\Enums;

// These values are stored in the database and used to drive UI labels.
enum SourceType: string
{
    // This is used for order-related point events.
    case ORDER = 'ORDER';
    // This represents rule-based awards like welcome or profile points.
    case RULE = 'RULE';
    // This represents customer registration events.
    case REGISTER = 'REGISTER';
    // This represents social engagement events.
    case SOCIAL = 'SOCIAL';
    // This represents birthday reward events.
    case BIRTHDAY = 'BIRTHDAY';
    // This represents profile completion events.
    case PROFILE = 'PROFILE';
    // This represents coupon-related events.
    case COUPON = 'COUPON';
    // This represents AI-driven awards and offers.
    case AI = 'AI';
    // This represents imported data from external files.
    case IMPORT = 'IMPORT';
    // This represents mystery box reward events.
    case MYSTERY_BOX = 'MYSTERY_BOX';
    // This represents redemption actions in the loyalty widget.
    case REDEEM = 'REDEEM';
}
