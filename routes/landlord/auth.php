<?php

declare(strict_types=1);

use App\Landlord\Identity\Http\Controllers\CurrentPlatformAdminController;
use App\Landlord\Identity\Http\Controllers\SignInController;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Http\Controllers\AccessTokenController;
use App\Shared\Auth\Http\Controllers\PasswordController;
use App\Shared\Auth\Http\Controllers\PasswordResetController;
use App\Shared\Auth\Http\Controllers\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/*
| Platform admin sign-in (Landlord\Identity), under /api/v1/platform/auth.
*/

Route::prefix('auth')->name('auth.')->group(static function (): void {
    Route::post('tokens', SignInController::class)->middleware('throttle:login')->name('tokens.store');

    Route::middleware('throttle:password-reset')->group(static function (): void {
        Route::post('password-reset-links', [PasswordResetLinkController::class, 'store'])->defaults('broker', PlatformAdmin::PASSWORD_BROKER)->name('password-reset-links.store');
        Route::post('password-resets', [PasswordResetController::class, 'store'])->defaults('broker', PlatformAdmin::PASSWORD_BROKER)->name('password-resets.store');
    });

    Route::middleware(['auth:'.PlatformAdmin::GUARD, 'throttle:api'])->group(static function (): void {
        Route::put('tokens/current', [AccessTokenController::class, 'update'])->name('tokens.update');
        Route::delete('tokens/current', [AccessTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('me', CurrentPlatformAdminController::class)->name('me');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });
});
