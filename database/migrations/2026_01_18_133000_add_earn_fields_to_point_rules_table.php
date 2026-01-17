<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_rules', function (Blueprint $table): void {
            $table->unsignedInteger('welcome_points')->default(0)->after('id');
            $table->unsignedInteger('amount_per_point')->default(100)->after('profile_completion_points');
            $table->string('social_linkedin_url')->nullable()->after('amount_per_point');
            $table->unsignedInteger('social_linkedin_points')->default(0)->after('social_linkedin_url');
            $table->string('social_tiktok_url')->nullable()->after('social_linkedin_points');
            $table->unsignedInteger('social_tiktok_points')->default(0)->after('social_tiktok_url');
            $table->string('social_facebook_url')->nullable()->after('social_tiktok_points');
            $table->unsignedInteger('social_facebook_points')->default(0)->after('social_facebook_url');
            $table->string('social_x_url')->nullable()->after('social_facebook_points');
            $table->unsignedInteger('social_x_points')->default(0)->after('social_x_url');
            $table->string('social_instagram_url')->nullable()->after('social_x_points');
            $table->unsignedInteger('social_instagram_points')->default(0)->after('social_instagram_url');
            $table->string('social_youtube_url')->nullable()->after('social_instagram_points');
            $table->unsignedInteger('social_youtube_points')->default(0)->after('social_youtube_url');
        });
    }

    public function down(): void
    {
        Schema::table('point_rules', function (Blueprint $table): void {
            $table->dropColumn([
                'welcome_points',
                'amount_per_point',
                'social_linkedin_url',
                'social_linkedin_points',
                'social_tiktok_url',
                'social_tiktok_points',
                'social_facebook_url',
                'social_facebook_points',
                'social_x_url',
                'social_x_points',
                'social_instagram_url',
                'social_instagram_points',
                'social_youtube_url',
                'social_youtube_points',
            ]);
        });
    }
};
