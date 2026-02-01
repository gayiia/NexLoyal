<?php

// This model stores individual coupon codes that may be issued to customers.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This class links generated codes to coupons and customers.
class CouponCode extends Model
{
    // These fields are mass assignable when issuing codes.
    protected $fillable = [
        'coupon_id',
        'code',
        'status',
        'customer_id',
        'issued_at',
    ];

    // This cast normalizes the issued timestamp.
    protected $casts = [
        'issued_at' => 'datetime',
    ];

    // This links the code to its coupon definition.
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    // This links the code to the customer it was issued to.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
