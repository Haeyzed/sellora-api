<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Http\Controllers\ManualPaymentMethodController;
use App\Tenant\Payments\Http\Controllers\PaymentController;
use App\Tenant\Payments\Http\Controllers\PaymentGatewayController;
use App\Tenant\Payments\Http\Controllers\RefundController;
use Illuminate\Support\Facades\Route;

/*
| Payments (Tenant\Payments). Staff work with them under /api/v1/staff with
| the payments.view, payments.confirm, payments.refund and
| payments.manage_gateways permissions. Gateways are named by their key, such
| as "paystack"; payments and refunds by their ID. Refunding an order is
| idempotent.
*/

Route::prefix('staff')
    ->name('staff.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::prefix('payment-gateways')->name('payment-gateways.')->where(['gateway' => '[a-z0-9_]{1,64}'])->group(static function (): void {
            Route::get('/', [PaymentGatewayController::class, 'index'])->name('index');
            Route::put('{gateway}/connection', [PaymentGatewayController::class, 'connect'])->name('connection.update');
            Route::patch('{gateway}/connection', [PaymentGatewayController::class, 'update'])->name('connection.change');
            Route::delete('{gateway}/connection', [PaymentGatewayController::class, 'disconnect'])->name('connection.destroy');
            Route::post('{gateway}/connection/checks', [PaymentGatewayController::class, 'check'])->name('connection.checks.store');
        });

        Route::prefix('payment-methods')->name('payment-methods.')->group(static function (): void {
            Route::get('/', [ManualPaymentMethodController::class, 'index'])->name('index');
            Route::patch('{method}', [ManualPaymentMethodController::class, 'update'])->whereIn('method', ['bank_transfer', 'cash_on_delivery'])->name('update');
        });

        Route::prefix('payments')->name('payments.')->group(static function (): void {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::get('{payment}', [PaymentController::class, 'show'])->name('show');
            Route::post('{payment}/receipts', [PaymentController::class, 'receipt'])->name('receipts.store');
            Route::post('{payment}/collections', [PaymentController::class, 'collection'])->name('collections.store');
            Route::post('{payment}/checks', [PaymentController::class, 'check'])->name('checks.store');
        });

        Route::get('orders/{order}/payments', [PaymentController::class, 'forOrder'])->name('orders.payments.index');
        Route::post('orders/{order}/refunds', [RefundController::class, 'store'])->middleware('idempotent')->name('orders.refunds.store');

        Route::prefix('refunds')->name('refunds.')->group(static function (): void {
            Route::get('/', [RefundController::class, 'index'])->name('index');
            Route::get('{refund}', [RefundController::class, 'show'])->name('show');
        });
    });
