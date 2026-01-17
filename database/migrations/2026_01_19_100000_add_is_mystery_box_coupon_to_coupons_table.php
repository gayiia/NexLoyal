<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('is_mystery_box_coupon')->default(false)->after('status');
            $table->index('is_mystery_box_coupon');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['is_mystery_box_coupon']);
            $table->dropColumn('is_mystery_box_coupon');
        });
    }
};
