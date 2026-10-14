<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Http\Controllers\PromotionController;
use Illuminate\Support\Facades\Route;

/*
| Promotions (Tenant\Promotions). Staff work with them under
| /api/v1/staff/promotions with the promotions.view and promotions.manage
| permissions. Customers enter codes in their cart (Step 12).
*/

Route::prefix('staff/promotions')
    ->name('staff.promotions.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('/', [PromotionController::class, 'index'])->name('index');
        Route::post('/', [PromotionController::class, 'store'])->name('store');
        Route::get('{promotion}', [PromotionController::class, 'show'])->name('show')->withTrashed();
        Route::patch('{promotion}', [PromotionController::class, 'update'])->name('update');
        Route::delete('{promotion}', [PromotionController::class, 'destroy'])->name('destroy');
        Route::post('{promotion}/activate', [PromotionController::class, 'activate'])->name('activate');
        Route::post('{promotion}/deactivate', [PromotionController::class, 'deactivate'])->name('deactivate');
    });
