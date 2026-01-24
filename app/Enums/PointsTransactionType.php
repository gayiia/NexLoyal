<?php

namespace App\Enums;

enum PointsTransactionType: string
{
    case EARN = 'EARN';
    case SPEND = 'SPEND';
    case ADJUST = 'ADJUST';
}
