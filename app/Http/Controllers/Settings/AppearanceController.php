<?php

// This controller renders and updates appearance-related admin settings.
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BrandingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    public function edit(): View
    {
        return view('settings.appearance');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $branding = BrandingSetting::current() ?? BrandingSetting::query()->create();
        $logo = $validated['logo'];

        File::ensureDirectoryExists(public_path('branding'));

        $extension = $logo->extension() ?: 'png';
        $fileName = 'logo-'.Str::uuid().'.'.$extension;
        $relativePath = 'branding/'.$fileName;

        $logo->move(public_path('branding'), $fileName);

        if (
            $branding->logo_path &&
            str_starts_with($branding->logo_path, 'branding/logo-') &&
            File::exists(public_path($branding->logo_path))
        ) {
            File::delete(public_path($branding->logo_path));
        }

        $branding->logo_path = $relativePath;
        $branding->save();

        return redirect()
            ->route('appearance.edit')
            ->with('status', 'branding-updated');
    }
}
