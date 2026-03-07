<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('running')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_import_batch_customer_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_import_batch_id')->constrained('ai_import_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('shopify_id');
            $table->boolean('existed_before')->default(false);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['ai_import_batch_id', 'shopify_id'], 'ai_import_batch_customer_unique');
            $table->index('customer_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (!Schema::hasColumn('customers', 'ai_import_batch_id')) {
                $table->foreignId('ai_import_batch_id')->nullable()->after('points_pending')->constrained('ai_import_batches')->nullOnDelete();
            }
        });

        Schema::table('points_transactions', function (Blueprint $table): void {
            if (!Schema::hasColumn('points_transactions', 'ai_import_batch_id')) {
                $table->foreignId('ai_import_batch_id')->nullable()->after('reference_id')->constrained('ai_import_batches')->nullOnDelete();
            }
        });

        Schema::table('customer_coupons', function (Blueprint $table): void {
            if (!Schema::hasColumn('customer_coupons', 'ai_import_batch_id')) {
                $table->foreignId('ai_import_batch_id')->nullable()->after('source')->constrained('ai_import_batches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_coupons', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_coupons', 'ai_import_batch_id')) {
                $table->dropConstrainedForeignId('ai_import_batch_id');
            }
        });

        Schema::table('points_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('points_transactions', 'ai_import_batch_id')) {
                $table->dropConstrainedForeignId('ai_import_batch_id');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'ai_import_batch_id')) {
                $table->dropConstrainedForeignId('ai_import_batch_id');
            }
        });

        Schema::dropIfExists('ai_import_batch_customer_snapshots');
        Schema::dropIfExists('ai_import_batches');
    }
};
