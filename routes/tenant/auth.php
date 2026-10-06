<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Controllers\AccessTokenController;
use App\Shared\Auth\Http\Controllers\PasswordController;
use App\Shared\Auth\Http\Controllers\PasswordResetController;
use App\Shared\Auth\Http\Controllers\PasswordResetLinkController;
use App\Tenant\Customers\Http\Controllers\CurrentCustomerController;
use App\Tenant\Customers\Http\Controllers\RegistrationController;
use App\Tenant\Customers\Http\Controllers\SignInController as CustomerSignInController;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Http\Controllers\CurrentStaffMemberController;
use App\Tenant\Identity\Http\Controllers\SignInController as StaffSignInController;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\Route;

/*
| Staff sign-in (Tenant\Identity), under /api/v1/staff/auth.
*/

Route::prefix('staff/auth')->name('staff.auth.')->group(static function (): void {
    Route::post('tokens', StaffSignInController::class)->middleware('throttle:login')->name('tokens.store');

    Route::middleware('throttle:password-reset')->group(static function (): void {
        Route::post('password-reset-links', [PasswordResetLinkController::class, 'store'])->defaults('broker', StaffMember::PASSWORD_BROKER)->name('password-reset-links.store');
        Route::post('password-resets', [PasswordResetController::class, 'store'])->defaults('broker', StaffMember::PASSWORD_BROKER)->name('password-resets.store');
    });

    Route::middleware(['auth:'.StaffMember::GUARD, 'throttle:api'])->group(static function (): void {
        Route::put('tokens/current', [AccessTokenController::class, 'update'])->name('tokens.update');
        Route::delete('tokens/current', [AccessTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('me', CurrentStaffMemberController::class)->name('me');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });
});

/*
| Customer sign-up and sign-in (Tenant\Customers), under /api/v1/customer/auth.
*/

Route::prefix('customer/auth')->name('customer.auth.')->group(static function (): void {
    Route::post('accounts', RegistrationController::class)->middleware('throttle:registration')->name('accounts.store');
    Route::post('tokens', CustomerSignInController::class)->middleware('throttle:login')->name('tokens.store');

    Route::middleware('throttle:password-reset')->group(static function (): void {
        Route::post('password-reset-links', [PasswordResetLinkController::class, 'store'])->defaults('broker', Customer::PASSWORD_BROKER)->name('password-reset-links.store');
        Route::post('password-resets', [PasswordResetController::class, 'store'])->defaults('broker', Customer::PASSWORD_BROKER)->name('password-resets.store');
    });

    Route::middleware(['auth:'.Customer::GUARD, 'throttle:api'])->group(static function (): void {
        Route::put('tokens/current', [AccessTokenController::class, 'update'])->name('tokens.update');
        Route::delete('tokens/current', [AccessTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('me', CurrentCustomerController::class)->name('me');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });
});
