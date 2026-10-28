<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Controllers\AccessTokenController;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Delivery\Http\Controllers\CurrentDriverCashController;
use App\Tenant\Delivery\Http\Controllers\CurrentDriverController;
use App\Tenant\Delivery\Http\Controllers\DeliveryController;
use App\Tenant\Delivery\Http\Controllers\DeliveryLocationController;
use App\Tenant\Delivery\Http\Controllers\DeliveryProofController;
use App\Tenant\Delivery\Http\Controllers\DeliveryTrackingController;
use App\Tenant\Delivery\Http\Controllers\DriverCashController;
use App\Tenant\Delivery\Http\Controllers\DriverController;
use App\Tenant\Delivery\Http\Controllers\DriverDeliveryController;
use App\Tenant\Delivery\Http\Controllers\DriverLocationController;
use App\Tenant\Delivery\Http\Controllers\DriverPinController;
use App\Tenant\Delivery\Http\Controllers\DriverPrivacyController;
use App\Tenant\Delivery\Http\Controllers\SignInController;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use Illuminate\Support\Facades\Route;

/*
| The store's own delivery drivers (Tenant\Delivery). Staff manage drivers
| and deliveries under /api/v1/staff/delivery; the driver app signs in under
| /api/v1/driver/auth and works its deliveries under /api/v1/driver/deliveries,
| sending the driver's location while one is under way.
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

Route::get('driver/cash', CurrentDriverCashController::class)->middleware(['auth:'.Driver::GUARD, 'throttle:api'])->name('driver.cash.show');

Route::prefix('driver/deliveries')
    ->name('driver.deliveries.')
    ->middleware(['auth:'.Driver::GUARD, 'throttle:api'])
    ->group(static function (): void {
        Route::get('/', [DriverDeliveryController::class, 'index'])->name('index');
        Route::get('{delivery}', [DriverDeliveryController::class, 'show'])->name('show');
        Route::post('{delivery}/pick-up', [DriverDeliveryController::class, 'pickUp'])->name('pick-up');
        Route::post('{delivery}/on-the-way', [DriverDeliveryController::class, 'onTheWay'])->name('on-the-way');
        Route::post('{delivery}/delivered', [DriverDeliveryController::class, 'delivered'])->middleware('idempotent')->name('delivered');
        Route::post('{delivery}/failed', [DriverDeliveryController::class, 'failed'])->name('failed');
        Route::post('{delivery}/locations', [DriverLocationController::class, 'store'])->middleware('throttle:driver-location')->name('locations.store');
    });

/*
| The buyer following the store's own deliveries of their order while one is
| on the way: a signed-in customer, or a guest with the order's access token
| in the X-Order-Token header (never the URL).
*/

Route::get('customer/orders/{order}/delivery-tracking', [DeliveryTrackingController::class, 'customer'])
    ->middleware(['auth:'.Customer::GUARD, UseStoreLanguage::class, 'throttle:delivery-tracking'])
    ->name('customer.orders.delivery-tracking.show');

Route::get('guest/orders/{order}/delivery-tracking', [DeliveryTrackingController::class, 'guest'])
    ->middleware([UseStoreLanguage::class, 'throttle:delivery-tracking'])
    ->name('guest.orders.delivery-tracking.show');

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
        Route::get('deliveries/{delivery}/proof/{kind}', [DeliveryProofController::class, 'show'])->name('deliveries.proof.show');
        Route::get('deliveries/{delivery}/locations', [DeliveryLocationController::class, 'index'])->name('deliveries.locations.index');
        Route::post('shipments/{shipment}/deliveries', [DeliveryController::class, 'store'])->name('shipments.deliveries.store');
        Route::post('deliveries/{delivery}/reassign', [DeliveryController::class, 'reassign'])->name('deliveries.reassign');
        Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');
        Route::post('deliveries/{delivery}/status', [DeliveryController::class, 'move'])->middleware('idempotent')->name('deliveries.status');
        Route::post('deliveries/{delivery}/retry', [DeliveryController::class, 'retry'])->name('deliveries.retry');
        Route::post('deliveries/{delivery}/return', [DeliveryController::class, 'returnToStore'])->name('deliveries.return');

        Route::get('drivers/{driver}/cash', [DriverCashController::class, 'show'])->name('drivers.cash.show');
        Route::get('drivers/{driver}/cash-entries', [DriverCashController::class, 'entries'])->name('drivers.cash-entries.index');
        Route::post('drivers/{driver}/cash-handovers', [DriverCashController::class, 'handover'])->middleware('idempotent')->name('drivers.cash-handovers.store');
    });
