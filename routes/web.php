<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\LoyaltyWidgetController;
use App\Http\Controllers\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/shopify/customers', [ShopifyWebhookController::class, 'handleCustomers'])
    ->name('webhooks.shopify.customers');

Route::get('loyalty/token', [LoyaltyWidgetController::class, 'token'])->name('loyalty.token');
Route::options('loyalty/token', [LoyaltyWidgetController::class, 'tokenOptions']);
Route::get('loyalty/data', [LoyaltyWidgetController::class, 'data'])->name('loyalty.data');
Route::options('loyalty/data', [LoyaltyWidgetController::class, 'dataOptions']);
Route::get('loyalty/profile', [LoyaltyWidgetController::class, 'profile'])->name('loyalty.profile');
Route::post('loyalty/profile', [LoyaltyWidgetController::class, 'updateProfile'])->name('loyalty.profile.update');
Route::options('loyalty/profile', [LoyaltyWidgetController::class, 'profileOptions']);
Route::get('loyalty/dashboard', [LoyaltyWidgetController::class, 'dashboard'])->name('loyalty.dashboard');

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('customer-groups', [CustomerGroupController::class, 'index'])
        ->name('customer-groups');
    Route::get('coupons', [CouponController::class, 'index'])->name('coupons');
    Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
    Route::patch('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    Route::patch('coupons/{coupon}/activate', [CouponController::class, 'activate'])->name('coupons.activate');
    Route::patch('coupons/{coupon}/deactivate', [CouponController::class, 'deactivate'])->name('coupons.deactivate');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
});

require __DIR__.'/settings.php';
