<?php

// This model stores global branding assets used across Blade views.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        $logoPath = static::current()?->logo_path;

        if (! $logoPath) {
            return asset('branding/default-logo.svg');
        }

        // Older uploads may still point at the legacy public/branding directory.
        if (File::exists(public_path($logoPath))) {
            return asset($logoPath);
        }

        return Storage::disk('public')->url($logoPath);
    }

    protected static function hasBackingTable(): bool
    {
        return static::$tableExists ??= Schema::hasTable((new static())->getTable());
    }
}
