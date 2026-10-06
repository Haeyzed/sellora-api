<?php

declare(strict_types=1);

use App\Landlord\Identity\Http\Controllers\CurrentPlatformAdminController;
use App\Landlord\Identity\Http\Controllers\SignInController;
use App\Landlord\Identity\Http\Controllers\TwoFactorChallengeController;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Http\Controllers\AccessTokenController;
use App\Shared\Auth\Http\Controllers\PasswordController;
use App\Shared\Auth\Http\Controllers\PasswordResetController;
use App\Shared\Auth\Http\Controllers\PasswordResetLinkController;
use App\Shared\Auth\Http\Controllers\RecoveryCodeController;
use App\Shared\Auth\Http\Controllers\TwoFactorConfirmationController;
use App\Shared\Auth\Http\Controllers\TwoFactorController;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorIsEnabled;
use Illuminate\Support\Facades\Route;

/*
| Platform admin sign-in (Landlord\Identity), under /api/v1/platform/auth.
*/

Route::prefix('auth')->name('auth.')->group(static function (): void {
    Route::middleware('throttle:login')->group(static function (): void {
        Route::post('tokens', SignInController::class)->name('tokens.store');
        Route::post('two-factor-challenges', TwoFactorChallengeController::class)->name('two-factor-challenges.store');
    });

    Route::middleware('throttle:password-reset')->group(static function (): void {
        Route::post('password-reset-links', [PasswordResetLinkController::class, 'store'])->defaults('broker', PlatformAdmin::PASSWORD_BROKER)->name('password-reset-links.store');
        Route::post('password-resets', [PasswordResetController::class, 'store'])->defaults('broker', PlatformAdmin::PASSWORD_BROKER)->name('password-resets.store');
    });

    Route::middleware(['auth:'.PlatformAdmin::GUARD, 'throttle:api'])->group(static function (): void {
        // Two-factor authentication is mandatory for platform admins. Until it
        // is set up, these are the only routes they can use.
        Route::delete('tokens/current', [AccessTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('me', CurrentPlatformAdminController::class)->name('me');
        Route::post('two-factor', [TwoFactorController::class, 'store'])->name('two-factor.store');
        Route::post('two-factor/confirmation', TwoFactorConfirmationController::class)->name('two-factor.confirmation.store');

        Route::middleware(EnsureTwoFactorIsEnabled::class)->group(static function (): void {
            Route::put('tokens/current', [AccessTokenController::class, 'update'])->name('tokens.update');
            Route::put('password', [PasswordController::class, 'update'])->name('password.update');
            Route::delete('two-factor', [TwoFactorController::class, 'destroy'])->name('two-factor.destroy');
            Route::post('two-factor/recovery-codes', RecoveryCodeController::class)->name('two-factor.recovery-codes.store');
        });
    });
});
