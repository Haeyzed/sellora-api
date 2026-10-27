<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Controllers\AccessTokenController;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Delivery\Http\Controllers\CurrentDriverController;
use App\Tenant\Delivery\Http\Controllers\DeliveryController;
use App\Tenant\Delivery\Http\Controllers\DriverController;
use App\Tenant\Delivery\Http\Controllers\DriverDeliveryController;
use App\Tenant\Delivery\Http\Controllers\DriverPinController;
use App\Tenant\Delivery\Http\Controllers\DriverPrivacyController;
use App\Tenant\Delivery\Http\Controllers\SignInController;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\Route;

/*
| The store's own delivery drivers (Tenant\Delivery). Staff manage drivers
| and deliveries under /api/v1/staff/delivery; the driver app signs in under
| /api/v1/driver/auth and works its deliveries under /api/v1/driver/deliveries.
| Staff never set a PIN: they issue a one-time setup code, and the driver
| chooses their own PIN with it.
*/

Route::prefix('driver/auth')->name('driver.auth.')->group(static function (): void {
    Route::post('tokens', SignInController::class)->middleware('throttle:login')->name('tokens.store');
    Route::post('pin', [DriverPinController::class, 'store'])->middleware('throttle:login')->name('pin.store');

    Route::middleware(['auth:'.Driver::GUARD, 'throttle:api'])->group(static function (): void {
        Route::put('tokens/current', [AccessTokenController::class, 'update'])->name('tokens.update');
        Route::delete('tokens/current', [AccessTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('me', CurrentDriverController::class)->name('me');
        Route::put('pin', [DriverPinController::class, 'update'])->name('pin.update');
    });
});

Route::prefix('driver/deliveries')
    ->name('driver.deliveries.')
    ->middleware(['auth:'.Driver::GUARD, 'throttle:api'])
    ->group(static function (): void {
        Route::get('/', [DriverDeliveryController::class, 'index'])->name('index');
        Route::get('{delivery}', [DriverDeliveryController::class, 'show'])->name('show');
        Route::post('{delivery}/pick-up', [DriverDeliveryController::class, 'pickUp'])->name('pick-up');
        Route::post('{delivery}/on-the-way', [DriverDeliveryController::class, 'onTheWay'])->name('on-the-way');
        Route::post('{delivery}/delivered', [DriverDeliveryController::class, 'delivered'])->name('delivered');
        Route::post('{delivery}/failed', [DriverDeliveryController::class, 'failed'])->name('failed');
    });

Route::prefix('staff/delivery')
    ->name('staff.delivery.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::apiResource('drivers', DriverController::class)->except('destroy');
        Route::post('drivers/{driver}/deactivate', [DriverController::class, 'deactivate'])->name('drivers.deactivate');
        Route::post('drivers/{driver}/reactivate', [DriverController::class, 'reactivate'])->name('drivers.reactivate');
        Route::post('drivers/{driver}/setup-codes', [DriverController::class, 'issueSetupCode'])->name('drivers.setup-codes.store');
        Route::get('drivers/{driver}/personal-data', [DriverPrivacyController::class, 'show'])->name('drivers.personal-data.show');
        Route::post('drivers/{driver}/erasure', [DriverPrivacyController::class, 'destroy'])->name('drivers.erasure.store');

        Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
        Route::post('shipments/{shipment}/deliveries', [DeliveryController::class, 'store'])->name('shipments.deliveries.store');
        Route::post('deliveries/{delivery}/reassign', [DeliveryController::class, 'reassign'])->name('deliveries.reassign');
        Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');
        Route::post('deliveries/{delivery}/status', [DeliveryController::class, 'move'])->name('deliveries.status');
        Route::post('deliveries/{delivery}/retry', [DeliveryController::class, 'retry'])->name('deliveries.retry');
        Route::post('deliveries/{delivery}/return', [DeliveryController::class, 'returnToStore'])->name('deliveries.return');
    });
