<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Customers\Http\Controllers\CustomerAccountController;
use App\Tenant\Customers\Http\Controllers\CustomerAddressController;
use App\Tenant\Customers\Http\Controllers\CustomerNoteController;
use App\Tenant\Customers\Http\Controllers\CustomerPrivacyController;
use App\Tenant\Customers\Http\Controllers\CustomerTaxIdController;
use App\Tenant\Customers\Http\Controllers\EmailChangeController;
use App\Tenant\Customers\Http\Controllers\EmailVerificationController;
use App\Tenant\Customers\Http\Controllers\MarketingConsentController;
use App\Tenant\Customers\Http\Controllers\StaffCustomerController;
use App\Tenant\Customers\Http\Controllers\StaffCustomerPrivacyController;
use App\Tenant\Customers\Http\Controllers\StaffMarketingConsentController;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use Illuminate\Support\Facades\Route;

/*
| A customer's own account (Tenant\Customers), under /api/v1/customer.
| Answers in the language the customer's device asks for, when the store
| publishes in it, like the storefront catalog: messages, and the consent
| text a customer agrees to.
*/

// Opened from an emailed link, often on another device, so no sign-in.
Route::post('customer/auth/email-verifications', [EmailVerificationController::class, 'verify'])
    ->middleware(['throttle:one-time-password', UseStoreLanguage::class])
    ->name('customer.auth.email-verifications.store');

Route::prefix('customer/account')->name('customer.account.')->middleware(['auth:'.Customer::GUARD, 'throttle:api', UseStoreLanguage::class])->group(static function (): void {
    Route::patch('/', [CustomerAccountController::class, 'update'])->name('update');
    Route::delete('/', [CustomerPrivacyController::class, 'destroy'])->name('destroy');
    Route::get('data-exports', [CustomerPrivacyController::class, 'index'])->name('data-exports.index');
    Route::post('data-exports', [CustomerPrivacyController::class, 'store'])->middleware('throttle:account-emails')->name('data-exports.store');
    Route::get('data-exports/{export}/download', [CustomerPrivacyController::class, 'download'])->name('data-exports.download');
    Route::apiResource('addresses', CustomerAddressController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::put('tax-id', [CustomerTaxIdController::class, 'update'])->name('tax-id.update');
    Route::delete('tax-id', [CustomerTaxIdController::class, 'destroy'])->name('tax-id.destroy');
    Route::get('marketing-consents', [MarketingConsentController::class, 'index'])->name('marketing-consents.index');
    Route::put('marketing-consents/{channel}', [MarketingConsentController::class, 'update'])->name('marketing-consents.update')->whereIn('channel', ['email', 'sms']);

    Route::middleware('throttle:account-emails')->group(static function (): void {
        Route::post('email-verifications', [EmailVerificationController::class, 'store'])->name('email-verifications.store');
        Route::post('email-changes', [EmailChangeController::class, 'store'])->name('email-changes.store');
    });

    Route::post('email-changes/confirmations', [EmailChangeController::class, 'confirm'])->middleware('throttle:one-time-password')->name('email-changes.confirmations.store');
});

/*
| Staff managing customers, under /api/v1/staff/customers, with the
| customers.view and customers.manage permissions. Customers in the trash
| are found too, so they can be seen and restored.
*/

Route::prefix('staff/customers')
    ->name('staff.customers.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('/', [StaffCustomerController::class, 'index'])->name('index');
        Route::get('{customer}', [StaffCustomerController::class, 'show'])->name('show')->withTrashed();
        Route::patch('{customer}', [StaffCustomerController::class, 'update'])->name('update')->withTrashed();
        Route::delete('{customer}', [StaffCustomerController::class, 'destroy'])->name('destroy');
        Route::post('{customer}/restore', [StaffCustomerController::class, 'restore'])->name('restore')->withTrashed();
        Route::post('{customer}/deactivate', [StaffCustomerController::class, 'deactivate'])->name('deactivate')->withTrashed();
        Route::post('{customer}/reactivate', [StaffCustomerController::class, 'reactivate'])->name('reactivate')->withTrashed();
        Route::get('{customer}/addresses', [StaffCustomerController::class, 'addresses'])->name('addresses.index')->withTrashed();
        Route::put('{customer}/tax-exemption', [StaffCustomerController::class, 'taxExemption'])->name('tax-exemption.update')->withTrashed();
        Route::get('{customer}/personal-data', [StaffCustomerPrivacyController::class, 'show'])->name('personal-data.show')->withTrashed();
        Route::post('{customer}/erasure', [StaffCustomerPrivacyController::class, 'destroy'])->name('erasure.store')->withTrashed();
        Route::get('{customer}/notes', [CustomerNoteController::class, 'index'])->name('notes.index')->withTrashed();
        Route::post('{customer}/notes', [CustomerNoteController::class, 'store'])->name('notes.store')->withTrashed();
        Route::delete('{customer}/notes/{note}', [CustomerNoteController::class, 'destroy'])->name('notes.destroy')->scopeBindings()->withTrashed();
        Route::get('{customer}/marketing-consents', [StaffMarketingConsentController::class, 'index'])->name('marketing-consents.index')->withTrashed();
        Route::post('{customer}/marketing-consents/withdrawals', [StaffMarketingConsentController::class, 'withdraw'])->name('marketing-consents.withdrawals.store')->withTrashed();
    });
