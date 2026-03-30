<?php

// This file contains authenticated settings routes for profile, password, and rules management.

use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PointRuleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ShopifyWebhookMonitorController;
use App\Http\Controllers\Settings\TierRuleController;
use Illuminate\Support\Facades\Route;

// Settings pages are only available to authenticated users.
Route::middleware('auth')->group(function () {
    // Default settings route points to the profile screen.
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('settings/profile/users/{user}/password', [ProfileController::class, 'updateManagedUserPassword'])
        ->middleware('throttle:6,1')
        ->name('profile.users.password');
    Route::delete('settings/profile/users/{user}', [ProfileController::class, 'destroyManagedUser'])
        ->name('profile.users.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    // Password updates are throttled to reduce abuse.
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

    Route::get('settings/point-rules', [PointRuleController::class, 'edit'])->name('point-rules');
    Route::post('settings/point-rules', [PointRuleController::class, 'update'])->name('point-rules.update');

    Route::get('settings/tier-rules', [TierRuleController::class, 'index'])->name('tier-rules');
    Route::post('settings/tier-rules', [TierRuleController::class, 'store'])->name('tier-rules.store');
    Route::patch('settings/tier-rules/{tier}', [TierRuleController::class, 'update'])->name('tier-rules.update');
    Route::patch('settings/tier-rules/{tier}/status', [TierRuleController::class, 'updateStatus'])->name('tier-rules.status');
    Route::delete('settings/tier-rules/{tier}', [TierRuleController::class, 'destroy'])->name('tier-rules.destroy');

    Route::get('settings/shopify-webhooks', [ShopifyWebhookMonitorController::class, 'index'])
        ->name('shopify-webhooks');
    Route::post('settings/shopify-webhooks', [ShopifyWebhookMonitorController::class, 'register'])
        ->name('shopify-webhooks.register');
    Route::delete('settings/shopify-webhooks', [ShopifyWebhookMonitorController::class, 'destroy'])
        ->name('shopify-webhooks.destroy');
    Route::get('settings/shopify-webhooks/logs/{log}', [ShopifyWebhookMonitorController::class, 'showLog'])
        ->name('shopify-webhooks.logs.show');
});
