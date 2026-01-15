<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRule extends Model
{
    protected $fillable = [
        'birthday_points',
        'profile_completion_points',
    ];

    protected $casts = [
        'birthday_points' => 'integer',
        'profile_completion_points' => 'integer',
    ];
}
