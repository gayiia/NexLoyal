<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PointRuleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TierRuleController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return view('settings.appearance');
    })->name('appearance.edit');

    Route::get('settings/point-rules', [PointRuleController::class, 'edit'])->name('point-rules');
    Route::post('settings/point-rules', [PointRuleController::class, 'update'])->name('point-rules.update');

    Route::get('settings/tier-rules', [TierRuleController::class, 'index'])->name('tier-rules');
    Route::post('settings/tier-rules', [TierRuleController::class, 'store'])->name('tier-rules.store');
    Route::patch('settings/tier-rules/{tier}', [TierRuleController::class, 'update'])->name('tier-rules.update');
    Route::patch('settings/tier-rules/{tier}/status', [TierRuleController::class, 'updateStatus'])->name('tier-rules.status');
    Route::delete('settings/tier-rules/{tier}', [TierRuleController::class, 'destroy'])->name('tier-rules.destroy');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
