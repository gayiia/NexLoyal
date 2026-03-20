<?php

// This file defines all web routes for the admin UI, widget, and webhook endpoints.

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\AiClusterAwardController;
use App\Http\Controllers\AiDataImportController;
use App\Http\Controllers\AiInsightsController;
use App\Http\Controllers\AiSandboxController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\ExclusiveChatAdminApiController;
use App\Http\Controllers\ExclusiveChatController;
use App\Http\Controllers\LoyaltyWidgetController;
use App\Http\Controllers\MysteryBoxController;
use App\Http\Controllers\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

// Shopify webhooks are throttled to protect the app from bursts and retries.
Route::middleware('throttle:60,1')->group(function () {
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
});

// Widget endpoints are throttled separately because they can be called frequently from storefronts.
Route::middleware('throttle:120,1')->group(function () {
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
});

// Root redirects to the login page for the admin experience.
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// All admin routes require authentication and verified email.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers');
    Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::post('customers/bulk-delete', [CustomerController::class, 'bulkDestroy'])->name('customers.bulk-delete');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/export', [CustomerController::class, 'exportDetail'])->name('customers.show.export');
    Route::get('customer-groups', [CustomerGroupController::class, 'index'])
        ->name('customer-groups');
    Route::get('coupons', [CouponController::class, 'index'])->name('coupons');
    Route::get('coupons/export', [CouponController::class, 'exportList'])->name('coupons.export.list');
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

    // Exclusive chat admin UI and exports.
    Route::prefix('admin/notifications/exclusive-chat')->group(function () {
        Route::get('/', [ExclusiveChatController::class, 'index'])->name('exclusive-chat');
        Route::get('export', [ExclusiveChatController::class, 'exportMessages'])->name('exclusive-chat.export');
        Route::post('messages', [ExclusiveChatController::class, 'storeMessage'])->name('exclusive-chat.messages.store');
        Route::get('settings', [ExclusiveChatController::class, 'settings'])->name('exclusive-chat.settings');
        Route::post('settings', [ExclusiveChatController::class, 'updateSettings'])->name('exclusive-chat.settings.update');
        Route::get('{message}/view', [ExclusiveChatController::class, 'view'])->name('exclusive-chat.view');
        Route::get('{message}/export', [ExclusiveChatController::class, 'exportPoll'])->name('exclusive-chat.view.export');
        Route::delete('{message}', [ExclusiveChatController::class, 'destroy'])->name('exclusive-chat.destroy');
    });

    // Admin API endpoints used by poll analytics modals.
    Route::prefix('admin/api/chat')->group(function () {
        Route::get('polls/{poll}/analytics', [ExclusiveChatAdminApiController::class, 'analytics'])
            ->name('exclusive-chat.polls.analytics');
        Route::get('polls/{poll}/options/{option}/voters', [ExclusiveChatAdminApiController::class, 'voters'])
            ->name('exclusive-chat.polls.voters');
    });

    // AI insights and clustering workflows.
    Route::get('admin/ai-insights', [AiInsightsController::class, 'index'])->name('ai-insights');
    Route::post('admin/ai-insights/run', [AiInsightsController::class, 'run'])
        ->middleware('throttle:6,1')
        ->name('ai-insights.run');
    Route::get('admin/ai-insights/status', [AiInsightsController::class, 'status'])->name('ai-insights.status');
    Route::get('admin/ai-insights/clusters/{cluster}/customers', [AiInsightsController::class, 'clusterCustomers'])
        ->name('ai-insights.clusters.customers');
    Route::get('admin/ai/sandbox', [AiSandboxController::class, 'index'])->name('ai-sandbox');
    Route::post('admin/ai/sandbox/compute-features', [AiSandboxController::class, 'computeFeatures'])
        ->middleware('throttle:6,1')
        ->name('ai-sandbox.compute');
    Route::post('admin/ai/sandbox/train', [AiSandboxController::class, 'train'])
        ->middleware('throttle:6,1')
        ->name('ai-sandbox.train');
    Route::post('admin/ai/predict', [AiSandboxController::class, 'predict'])
        ->middleware('throttle:12,1')
        ->name('ai-sandbox.predict');
    Route::get('admin/ai/features', [AiSandboxController::class, 'featurePreview'])->name('ai-features');
    Route::get('admin/ai/data/import', [AiDataImportController::class, 'index'])->name('ai-data-import');
    Route::post('admin/ai/data/import', [AiDataImportController::class, 'store'])->name('ai-data-import.store');
    Route::post('admin/ai/data/import/reset', [AiDataImportController::class, 'reset'])->name('ai-data-import.reset');
    Route::get('admin/ai/data/import/reset-status', [AiDataImportController::class, 'resetStatus'])->name('ai-data-import.reset-status');
    Route::get('admin/ai-insights/clusters/{cluster}/export', [AiInsightsController::class, 'exportCluster'])
        ->name('ai-insights.clusters.export');
    Route::get('admin/ai-insights/awards/create', [AiClusterAwardController::class, 'create'])
        ->name('ai-insights.awards.create');
    Route::post('admin/ai-insights/awards', [AiClusterAwardController::class, 'store'])
        ->name('ai-insights.awards.store');
    Route::get('admin/ai-insights/awards/{award}/edit', [AiClusterAwardController::class, 'edit'])
        ->name('ai-insights.awards.edit');
    Route::patch('admin/ai-insights/awards/{award}', [AiClusterAwardController::class, 'update'])
        ->name('ai-insights.awards.update');
    Route::patch('admin/ai-insights/awards/{award}/activate', [AiClusterAwardController::class, 'activate'])
        ->name('ai-insights.awards.activate');
    Route::patch('admin/ai-insights/awards/{award}/deactivate', [AiClusterAwardController::class, 'deactivate'])
        ->name('ai-insights.awards.deactivate');
    Route::delete('admin/ai-insights/awards/{award}', [AiClusterAwardController::class, 'destroy'])
        ->name('ai-insights.awards.destroy');
    Route::get('admin/ai-insights/awards/{award}/export', [AiClusterAwardController::class, 'export'])
        ->name('ai-insights.awards.export');

    // Report builder endpoints for generating and exporting summaries.
    Route::get('admin/reports', [ReportsController::class, 'index'])->name('reports');
    Route::post('admin/reports/generate', [ReportsController::class, 'generate'])->name('reports.generate');
    Route::get('admin/reports/export/excel', [ReportsController::class, 'exportExcel'])->name('reports.export.excel');
    Route::get('admin/reports/export/pdf', [ReportsController::class, 'exportPdf'])->name('reports.export.pdf');
});

// Settings routes are separated for clarity.
require __DIR__.'/settings.php';
