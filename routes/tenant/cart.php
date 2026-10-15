<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Cart\Http\Controllers\CartCodeController;
use App\Tenant\Cart\Http\Controllers\CartController;
use App\Tenant\Cart\Http\Controllers\CartLineController;
use App\Tenant\Cart\Http\Controllers\CartMergeController;
use App\Tenant\Cart\Http\Controllers\StaffCustomerCartController;
use App\Tenant\Cart\Http\Middleware\AuthenticateCustomerIfSignedIn;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
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
            Route::get('shipping-options', [CartController::class, 'shippingOptions'])->name('shipping-options.index');
            Route::post('price-change-acknowledgements', [CartController::class, 'acknowledgePriceChanges'])->name('price-change-acknowledgements.store');
            Route::put('code', [CartCodeController::class, 'update'])->middleware('throttle:coupon-redemption')->name('code.update');
            Route::delete('code', [CartCodeController::class, 'destroy'])->name('code.destroy');
            Route::post('merges', [CartMergeController::class, 'store'])->middleware('auth:'.Customer::GUARD)->name('merges.store');
            Route::post('lines', [CartLineController::class, 'store'])->name('lines.store');
            Route::delete('lines', [CartLineController::class, 'destroyAll'])->name('lines.destroy-all');
            Route::patch('lines/{line}', [CartLineController::class, 'update'])->name('lines.update');
            Route::delete('lines/{line}', [CartLineController::class, 'destroy'])->name('lines.destroy');
        });
    });

/*
| A customer's cart as staff see it, read only, under
| /api/v1/staff/customers/{customer}/cart, with customers.view.
*/

Route::get('staff/customers/{customer}/cart', [StaffCustomerCartController::class, 'show'])
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api', UseStoreLanguage::class])
    ->withTrashed()
    ->name('staff.customers.cart.show');
