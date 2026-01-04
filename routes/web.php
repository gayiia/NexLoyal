<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/shopify/customers', [ShopifyWebhookController::class, 'handleCustomers'])
    ->name('webhooks.shopify.customers');

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
});

require __DIR__.'/settings.php';


