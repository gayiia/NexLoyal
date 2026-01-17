<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_coupons', function (Blueprint $table) {
            $table->string('status')->default('active')->after('code');
            $table->timestamp('expires_at')->nullable()->after('used_at');
            $table->index(['coupon_id', 'status']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_coupons', function (Blueprint $table) {
            $table->dropIndex(['coupon_id', 'status']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['status', 'expires_at']);
        });
    }
};
