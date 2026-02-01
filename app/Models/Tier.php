<?php

// This model represents a loyalty tier configuration.
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// This class stores tier thresholds, display settings, and status.
class Tier extends Model
{
    use HasFactory;

    // These fields are mass assignable from tier settings.
    protected $fillable = [
        'title',
        'color',
        'min_points',
        'max_points',
        'single_point_value',
        'description',
        'status',
    ];

    // These casts normalize numeric tier fields.
    protected $casts = [
        'min_points' => 'integer',
        'max_points' => 'integer',
        'single_point_value' => 'decimal:2',
    ];
}
