<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRule extends Model
{
    protected $fillable = [
        'welcome_points',
        'birthday_points',
        'profile_completion_points',
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
    ];

    protected $casts = [
        'welcome_points' => 'integer',
        'birthday_points' => 'integer',
        'profile_completion_points' => 'integer',
        'amount_per_point' => 'integer',
        'social_linkedin_points' => 'integer',
        'social_tiktok_points' => 'integer',
        'social_facebook_points' => 'integer',
        'social_x_points' => 'integer',
        'social_instagram_points' => 'integer',
        'social_youtube_points' => 'integer',
    ];
}
