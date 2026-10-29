<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Returns\Http\Controllers\CustomerReturnController;
use App\Tenant\Returns\Http\Controllers\GuestReturnController;
use App\Tenant\Returns\Http\Controllers\ReturnController;
use App\Tenant\Returns\Http\Controllers\ReturnReasonController;
use App\Tenant\Returns\Http\Controllers\ReturnSettingsController;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use Illuminate\Support\Facades\Route;

/*
| Returns (Tenant\Returns, Step 18): buyers sending units of their orders
| back to be refunded. A signed-in customer asks under /api/v1/customer; a
| guest under /api/v1/guest/orders with the order's access token in the
| X-Order-Token header (never the URL). Staff manage returns and the return
| settings under /api/v1/staff/returns, and start returns for buyers.
*/

Route::get('returns/reasons', [ReturnReasonController::class, 'index'])
    ->middleware([UseStoreLanguage::class, 'throttle:api'])
    ->name('returns.reasons.index');

Route::middleware(['auth:'.Customer::GUARD, UseStoreLanguage::class])->name('customer.')->group(static function (): void {
    Route::post('customer/orders/{order}/returns', [CustomerReturnController::class, 'store'])->middleware('throttle:return-requests')->name('orders.returns.store');

    Route::middleware('throttle:api')->group(static function (): void {
        Route::get('customer/returns', [CustomerReturnController::class, 'index'])->name('returns.index');
        Route::get('customer/returns/{return}', [CustomerReturnController::class, 'show'])->name('returns.show');
        Route::post('customer/returns/{return}/cancel', [CustomerReturnController::class, 'cancel'])->name('returns.cancel');
    });
});

Route::prefix('guest/orders/{order}/returns')->middleware(UseStoreLanguage::class)->name('guest.orders.returns.')->group(static function (): void {
    Route::post('/', [GuestReturnController::class, 'store'])->middleware('throttle:return-requests')->name('store');

    Route::middleware('throttle:guest-order')->group(static function (): void {
        Route::get('/', [GuestReturnController::class, 'index'])->name('index');
        Route::get('{return}', [GuestReturnController::class, 'show'])->name('show');
        Route::post('{return}/cancel', [GuestReturnController::class, 'cancel'])->name('cancel');
    });
});

Route::middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])->group(static function (): void {
    Route::post('staff/orders/{order}/returns', [ReturnController::class, 'store'])->name('staff.orders.returns.store');

    Route::prefix('staff/returns')->name('staff.returns.')->group(static function (): void {
        Route::get('settings', [ReturnSettingsController::class, 'show'])->name('settings.show');
        Route::patch('settings', [ReturnSettingsController::class, 'update'])->name('settings.update');
        Route::get('/', [ReturnController::class, 'index'])->name('index');
        Route::get('{return}', [ReturnController::class, 'show'])->name('show');
        Route::get('{return}/photos/{position}', [ReturnController::class, 'photo'])->whereNumber('position')->name('photos.show');
        Route::post('{return}/approve', [ReturnController::class, 'approve'])->name('approve');
        Route::post('{return}/reject', [ReturnController::class, 'reject'])->name('reject');
        Route::post('{return}/receive', [ReturnController::class, 'receive'])->name('receive');
        Route::post('{return}/inspect', [ReturnController::class, 'inspect'])->name('inspect');
        Route::post('{return}/cancel', [ReturnController::class, 'cancel'])->name('cancel');
    });
});
