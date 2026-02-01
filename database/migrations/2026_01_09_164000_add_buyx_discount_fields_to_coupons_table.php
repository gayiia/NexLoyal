<?php

// This migration adds buy-x-get-y discount fields to coupons.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns define discount type and value for buy-x-get-y coupons.
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('buyx_discount_type')->nullable()->after('get_quantity');
            $table->decimal('buyx_discount_value', 10, 2)->nullable()->after('buyx_discount_type');
        });
    }

    public function down(): void
    {
        // This removes the buy-x-get-y discount columns.
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['buyx_discount_type', 'buyx_discount_value']);
        });
    }
};
