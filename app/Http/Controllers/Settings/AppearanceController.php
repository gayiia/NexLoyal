<?php

// This controller renders and updates appearance-related admin settings.
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BrandingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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

        $extension = $logo->extension() ?: 'png';
        $fileName = 'logo-'.Str::uuid().'.'.$extension;
        $relativePath = 'branding/'.$fileName;
        $disk = Storage::disk('public');

        $disk->putFileAs('branding', $logo, $fileName);

        if ($branding->logo_path && str_starts_with($branding->logo_path, 'branding/logo-')) {
            if ($disk->exists($branding->logo_path)) {
                $disk->delete($branding->logo_path);
            }

            if (File::exists(public_path($branding->logo_path))) {
                File::delete(public_path($branding->logo_path));
            }
        }

        $branding->logo_path = $relativePath;
        $branding->save();

        return redirect()
            ->route('appearance.edit')
            ->with('status', 'branding-updated');
    }
}
