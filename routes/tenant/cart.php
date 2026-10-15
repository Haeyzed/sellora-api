<?php

declare(strict_types=1);

use App\Tenant\Cart\Http\Controllers\CartController;
use App\Tenant\Cart\Http\Controllers\CartLineController;
use App\Tenant\Cart\Http\Middleware\AuthenticateCustomerIfSignedIn;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use Illuminate\Support\Facades\Route;

/*
| The shopper's cart (Tenant\Cart), under /api/v1/cart: a signed-in
| customer's (bearer token) or a guest's (X-Cart-Token header). Limited per
| cart or customer, under a much higher ceiling per IP address, because many
| shoppers can share one address (section 14).
*/

Route::prefix('cart')
    ->name('cart.')
    ->middleware([AuthenticateCustomerIfSignedIn::class, UseStoreLanguage::class])
    ->group(static function (): void {
        Route::post('/', [CartController::class, 'store'])->middleware('throttle:cart-creation')->name('store');

        Route::middleware('throttle:cart')->group(static function (): void {
            Route::get('/', [CartController::class, 'show'])->name('show');
            Route::patch('/', [CartController::class, 'update'])->name('update');
            Route::post('price-change-acknowledgements', [CartController::class, 'acknowledgePriceChanges'])->name('price-change-acknowledgements.store');
            Route::post('lines', [CartLineController::class, 'store'])->name('lines.store');
            Route::delete('lines', [CartLineController::class, 'destroyAll'])->name('lines.destroy-all');
            Route::patch('lines/{line}', [CartLineController::class, 'update'])->name('lines.update');
            Route::delete('lines/{line}', [CartLineController::class, 'destroy'])->name('lines.destroy');
        });
    });
