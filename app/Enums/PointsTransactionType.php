<?php

// This enum defines the types of points transactions recorded in the ledger.
namespace App\Enums;

// These values are stored in the database and used for reporting.
enum PointsTransactionType: string
{
    // This represents points being awarded to a customer.
    case EARN = 'EARN';
    // This represents points being redeemed or spent.
    case SPEND = 'SPEND';
    // This represents manual adjustments outside standard flows.
    case ADJUST = 'ADJUST';
}
