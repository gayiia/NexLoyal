<?php

// This model stores global branding assets used across Blade views.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BrandingSetting extends Model
{
    protected $fillable = [
        'logo_path',
    ];

    protected static ?bool $tableExists = null;

    public static function current(): ?self
    {
        if (! static::hasBackingTable()) {
            return null;
        }

        return static::query()->firstOrCreate([], [
            'logo_path' => null,
        ]);
    }

    public static function logoUrl(): string
    {
        return asset(static::current()?->logo_path ?: 'branding/default-logo.svg');
    }

    protected static function hasBackingTable(): bool
    {
        return static::$tableExists ??= Schema::hasTable((new static())->getTable());
    }
}
