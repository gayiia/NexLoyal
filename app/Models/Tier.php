<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'color',
        'min_points',
        'max_points',
        'single_point_value',
        'description',
        'status',
    ];

    protected $casts = [
        'min_points' => 'integer',
        'max_points' => 'integer',
        'single_point_value' => 'decimal:2',
    ];
}
