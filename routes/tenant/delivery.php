<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Controllers\AccessTokenController;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Delivery\Http\Controllers\CurrentDriverController;
use App\Tenant\Delivery\Http\Controllers\DriverController;
use App\Tenant\Delivery\Http\Controllers\DriverPinController;
use App\Tenant\Delivery\Http\Controllers\DriverPrivacyController;
use App\Tenant\Delivery\Http\Controllers\SignInController;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\Route;

/*
| The store's own delivery drivers (Tenant\Delivery). Staff manage drivers
| under /api/v1/staff/delivery; the driver app signs in under
| /api/v1/driver/auth. Staff never set a PIN: they issue a one-time setup
| code, and the driver chooses their own PIN with it.
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
    });
