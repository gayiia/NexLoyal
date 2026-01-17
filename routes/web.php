<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\ExclusiveChatAdminApiController;
use App\Http\Controllers\ExclusiveChatController;
use App\Http\Controllers\LoyaltyWidgetController;
use App\Http\Controllers\MysteryBoxController;
use App\Http\Controllers\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/shopify/customers', [ShopifyWebhookController::class, 'handleCustomers'])
    ->name('webhooks.shopify.customers');
Route::post('webhooks/shopify/orders', [ShopifyWebhookController::class, 'handleOrders'])
    ->name('webhooks.shopify.orders');
Route::post('webhooks/shopify/orders/paid', [ShopifyWebhookController::class, 'handleOrders'])
    ->name('webhooks.shopify.orders.paid');
Route::post('webhooks/shopify/orders/create', [ShopifyWebhookController::class, 'handleOrders'])
    ->name('webhooks.shopify.orders.create');
Route::post('webhooks/shopify/orders/fulfilled', [ShopifyWebhookController::class, 'handleOrders'])
    ->name('webhooks.shopify.orders.fulfilled');
Route::post('webhooks/shopify/orders/refunded', [ShopifyWebhookController::class, 'handleOrders'])
    ->name('webhooks.shopify.orders.refunded');
Route::post('webhooks/shopify/orders/cancelled', [ShopifyWebhookController::class, 'handleOrders'])
    ->name('webhooks.shopify.orders.cancelled');

Route::get('loyalty/token', [LoyaltyWidgetController::class, 'token'])->name('loyalty.token');
Route::options('loyalty/token', [LoyaltyWidgetController::class, 'tokenOptions']);
Route::get('loyalty/data', [LoyaltyWidgetController::class, 'data'])->name('loyalty.data');
Route::options('loyalty/data', [LoyaltyWidgetController::class, 'dataOptions']);
Route::get('loyalty/profile', [LoyaltyWidgetController::class, 'profile'])->name('loyalty.profile');
Route::post('loyalty/profile', [LoyaltyWidgetController::class, 'updateProfile'])->name('loyalty.profile.update');
Route::options('loyalty/profile', [LoyaltyWidgetController::class, 'profileOptions']);
Route::get('loyalty/coupons', [LoyaltyWidgetController::class, 'coupons'])->name('loyalty.coupons');
Route::options('loyalty/coupons', [LoyaltyWidgetController::class, 'couponsOptions']);
Route::post('loyalty/coupons/{coupon}/redeem', [LoyaltyWidgetController::class, 'redeemCoupon'])
    ->name('loyalty.coupons.redeem');
Route::get('loyalty/my-coupons', [LoyaltyWidgetController::class, 'myCoupons'])->name('loyalty.my-coupons');
Route::options('loyalty/my-coupons', [LoyaltyWidgetController::class, 'myCouponsOptions']);
Route::get('loyalty/my-coupons/{redemption}', [LoyaltyWidgetController::class, 'widgetMyCouponDetail'])
    ->name('loyalty.my-coupons.show');
Route::get('loyalty/dashboard', [LoyaltyWidgetController::class, 'dashboard'])->name('loyalty.dashboard');
Route::get('loyalty/mystery-box', [LoyaltyWidgetController::class, 'mysteryBoxPage'])
    ->name('loyalty.mystery-box');

Route::get('api/widget/my-coupons', [LoyaltyWidgetController::class, 'widgetMyCoupons'])
    ->name('widget.my-coupons');
Route::get('api/widget/my-coupons/{redemption}', [LoyaltyWidgetController::class, 'widgetMyCouponDetail'])
    ->name('widget.my-coupons.show');
Route::get('api/widget/earn/rules', [LoyaltyWidgetController::class, 'earnRules'])
    ->name('widget.earn.rules');
Route::get('api/widget/earn/status', [LoyaltyWidgetController::class, 'earnStatus'])
    ->name('widget.earn.status');
Route::post('api/widget/earn/social', [LoyaltyWidgetController::class, 'earnSocial'])
    ->name('widget.earn.social');
Route::options('api/widget/earn/social', [LoyaltyWidgetController::class, 'earnSocialOptions']);
Route::get('api/widget/mystery-box/active', [LoyaltyWidgetController::class, 'mysteryBoxActive'])
    ->name('widget.mystery-box.active');
Route::post('api/widget/mystery-box/{mysteryBox}/claim', [LoyaltyWidgetController::class, 'mysteryBoxClaim'])
    ->name('widget.mystery-box.claim');
Route::options('api/widget/mystery-box/{mysteryBox}/claim', [LoyaltyWidgetController::class, 'mysteryBoxClaimOptions']);
Route::get('api/widget/chat/messages', [LoyaltyWidgetController::class, 'chatMessages'])
    ->name('widget.chat.messages');
Route::post('api/widget/chat/polls/{poll}/vote', [LoyaltyWidgetController::class, 'chatPollVote'])
    ->name('widget.chat.polls.vote');
Route::options('api/widget/chat/polls/{poll}/vote', [LoyaltyWidgetController::class, 'chatPollVoteOptions']);
Route::get('api/widget/points/history', [LoyaltyWidgetController::class, 'pointsHistory'])
    ->name('widget.points.history');

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
    Route::get('coupons/{coupon}/view', [CouponController::class, 'view'])->name('coupons.view');
    Route::get('coupons/{coupon}/export', [CouponController::class, 'export'])->name('coupons.export');
    Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
    Route::patch('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    Route::patch('coupons/{coupon}/activate', [CouponController::class, 'activate'])->name('coupons.activate');
    Route::patch('coupons/{coupon}/deactivate', [CouponController::class, 'deactivate'])->name('coupons.deactivate');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');

    Route::get('coupons/mystery-box', [MysteryBoxController::class, 'index'])->name('mystery-boxes');
    Route::get('coupons/mystery-box/create', [MysteryBoxController::class, 'create'])->name('mystery-boxes.create');
    Route::post('coupons/mystery-box', [MysteryBoxController::class, 'store'])->name('mystery-boxes.store');
    Route::get('coupons/mystery-box/{mysteryBox}/view', [MysteryBoxController::class, 'view'])->name('mystery-boxes.view');
    Route::get('coupons/mystery-box/{mysteryBox}/export', [MysteryBoxController::class, 'export'])->name('mystery-boxes.export');
    Route::get('coupons/mystery-box/{mysteryBox}/edit', [MysteryBoxController::class, 'edit'])->name('mystery-boxes.edit');
    Route::patch('coupons/mystery-box/{mysteryBox}', [MysteryBoxController::class, 'update'])->name('mystery-boxes.update');
    Route::patch('coupons/mystery-box/{mysteryBox}/activate', [MysteryBoxController::class, 'activate'])->name('mystery-boxes.activate');
    Route::patch('coupons/mystery-box/{mysteryBox}/deactivate', [MysteryBoxController::class, 'deactivate'])->name('mystery-boxes.deactivate');
    Route::delete('coupons/mystery-box/{mysteryBox}', [MysteryBoxController::class, 'destroy'])->name('mystery-boxes.destroy');

    Route::prefix('admin/notifications/exclusive-chat')->group(function () {
        Route::get('/', [ExclusiveChatController::class, 'index'])->name('exclusive-chat');
        Route::post('messages', [ExclusiveChatController::class, 'storeMessage'])->name('exclusive-chat.messages.store');
        Route::get('settings', [ExclusiveChatController::class, 'settings'])->name('exclusive-chat.settings');
        Route::post('settings', [ExclusiveChatController::class, 'updateSettings'])->name('exclusive-chat.settings.update');
        Route::get('{message}/view', [ExclusiveChatController::class, 'view'])->name('exclusive-chat.view');
        Route::delete('{message}', [ExclusiveChatController::class, 'destroy'])->name('exclusive-chat.destroy');
    });

    Route::prefix('admin/api/chat')->group(function () {
        Route::get('polls/{poll}/analytics', [ExclusiveChatAdminApiController::class, 'analytics'])
            ->name('exclusive-chat.polls.analytics');
        Route::get('polls/{poll}/options/{option}/voters', [ExclusiveChatAdminApiController::class, 'voters'])
            ->name('exclusive-chat.polls.voters');
    });
});

require __DIR__.'/settings.php';
