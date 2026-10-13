<?php

declare(strict_types=1);

use App\Tenant\Customers\Http\Controllers\CustomerAccountController;
use App\Tenant\Customers\Http\Controllers\EmailChangeController;
use App\Tenant\Customers\Http\Controllers\EmailVerificationController;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Support\Facades\Route;

/*
| A customer's own account (Tenant\Customers), under /api/v1/customer.
*/

// Opened from an emailed link, often on another device, so no sign-in.
Route::post('customer/auth/email-verifications', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:one-time-password')
    ->name('customer.auth.email-verifications.store');

Route::prefix('customer/account')->name('customer.account.')->middleware(['auth:'.Customer::GUARD, 'throttle:api'])->group(static function (): void {
    Route::patch('/', [CustomerAccountController::class, 'update'])->name('update');

    Route::middleware('throttle:account-emails')->group(static function (): void {
        Route::post('email-verifications', [EmailVerificationController::class, 'store'])->name('email-verifications.store');
        Route::post('email-changes', [EmailChangeController::class, 'store'])->name('email-changes.store');
    });

    Route::post('email-changes/confirmations', [EmailChangeController::class, 'confirm'])->middleware('throttle:one-time-password')->name('email-changes.confirmations.store');
});
