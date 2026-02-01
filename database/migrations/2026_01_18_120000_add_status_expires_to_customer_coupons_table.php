<?php

// This migration adds status and expiration tracking to customer coupons.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns support redemption status and expiry filtering.
        Schema::table('customer_coupons', function (Blueprint $table) {
            $table->string('status')->default('active')->after('code');
            $table->timestamp('expires_at')->nullable()->after('used_at');
            $table->index(['coupon_id', 'status']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        // This removes the status and expiration columns and indexes.
        Schema::table('customer_coupons', function (Blueprint $table) {
            $table->dropIndex(['coupon_id', 'status']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['status', 'expires_at']);
        });
    }
};
