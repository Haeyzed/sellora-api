<?php

declare(strict_types=1);

use App\Tenant\Cart\Http\Middleware\AuthenticateCustomerIfSignedIn;
use App\Tenant\Checkout\Http\Controllers\CheckoutController;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use Illuminate\Support\Facades\Route;

/*
| Checking out the shopper's cart (Tenant\Checkout), under /api/v1/checkout:
| a signed-in customer's (bearer token) or a guest's (X-Cart-Token header),
| while the store allows guest checkout. Limited per cart or customer, under
| a higher ceiling per IP address (section 14).
*/

Route::prefix('checkout')
    ->name('checkout.')
    ->middleware([AuthenticateCustomerIfSignedIn::class, UseStoreLanguage::class, 'throttle:checkout'])
    ->group(static function (): void {
        Route::post('/', [CheckoutController::class, 'store'])->name('store');
        Route::get('/', [CheckoutController::class, 'show'])->name('show');
        Route::patch('/', [CheckoutController::class, 'update'])->name('update');
    });
