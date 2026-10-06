<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Controllers\AccessTokenController;
use App\Tenant\Delivery\Http\Controllers\CurrentDriverController;
use App\Tenant\Delivery\Http\Controllers\SignInController;
use App\Tenant\Delivery\Models\Driver;
use Illuminate\Support\Facades\Route;

/*
| Driver app sign-in (Tenant\Delivery), under /api/v1/driver/auth.
| Drivers have no self-service PIN reset: staff reset PINs.
*/

Route::prefix('driver/auth')->name('driver.auth.')->group(static function (): void {
    Route::post('tokens', SignInController::class)->middleware('throttle:login')->name('tokens.store');

    Route::middleware(['auth:'.Driver::GUARD, 'throttle:api'])->group(static function (): void {
        Route::put('tokens/current', [AccessTokenController::class, 'update'])->name('tokens.update');
        Route::delete('tokens/current', [AccessTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('me', CurrentDriverController::class)->name('me');
    });
});
