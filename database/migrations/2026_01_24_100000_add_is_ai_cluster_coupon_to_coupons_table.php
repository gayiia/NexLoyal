<?php

// This migration flags coupons that can be used for AI cluster awards.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This column and index enable filtering for AI coupons.
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('is_ai_cluster_coupon')->default(false)->after('is_mystery_box_coupon');
            $table->index('is_ai_cluster_coupon');
        });
    }

    public function down(): void
    {
        // This removes the AI coupon flag and index.
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['is_ai_cluster_coupon']);
            $table->dropColumn('is_ai_cluster_coupon');
        });
    }
};
