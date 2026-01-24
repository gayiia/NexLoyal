<?php

namespace App\Enums;

enum SourceType: string
{
    case ORDER = 'ORDER';
    case RULE = 'RULE';
    case REGISTER = 'REGISTER';
    case SOCIAL = 'SOCIAL';
    case BIRTHDAY = 'BIRTHDAY';
    case PROFILE = 'PROFILE';
    case COUPON = 'COUPON';
    case AI = 'AI';
    case IMPORT = 'IMPORT';
    case MYSTERY_BOX = 'MYSTERY_BOX';
    case REDEEM = 'REDEEM';
}
