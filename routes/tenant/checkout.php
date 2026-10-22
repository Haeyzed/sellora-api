<?php

declare(strict_types=1);

use App\Tenant\Cart\Http\Middleware\AuthenticateCustomerIfSignedIn;
use App\Tenant\Checkout\Http\Controllers\CheckoutController;
use App\Tenant\Checkout\Http\Controllers\CheckoutOrderController;
use App\Tenant\Checkout\Http\Controllers\CustomerOrderPaymentController;
use App\Tenant\Checkout\Http\Controllers\PaymentReturnController;
use App\Tenant\Customers\Models\Customer;
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
        Route::post('orders', [CheckoutOrderController::class, 'store'])->middleware('idempotent')->name('orders.store');
    });

/*
| A buyer back from a gateway's page, under
| /api/v1/checkout/payments/{reference}: no sign-in, only the payment's
| status. Limited per payment, under a ceiling per IP address.
*/

Route::get('checkout/payments/{reference}', [PaymentReturnController::class, 'show'])
    ->where('reference', 'pay_[0-9a-z]{26}')
    ->middleware([UseStoreLanguage::class, 'throttle:payment-return'])
    ->name('checkout.payments.show');

/*
| A signed-in customer paying again for their order, under
| /api/v1/customer/orders/{order}/payments.
*/

Route::post('customer/orders/{order}/payments', [CustomerOrderPaymentController::class, 'store'])
    ->middleware(['auth:'.Customer::GUARD, UseStoreLanguage::class, 'throttle:checkout', 'idempotent'])
    ->name('customer.orders.payments.store');
