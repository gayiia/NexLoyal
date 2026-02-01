<?php

// This migration adds loyalty points and tier linkage to customers.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns store the current points balance and tier.
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedInteger('loyalty_points')->default(0)->after('shopify_created_at');
            $table->foreignId('tier_id')->nullable()->after('loyalty_points')->constrained('tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // This removes the tier foreign key and points column.
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['tier_id']);
            $table->dropColumn(['tier_id', 'loyalty_points']);
        });
    }
};
