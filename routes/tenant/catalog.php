<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Catalog\Http\Controllers\BrandController;
use App\Tenant\Catalog\Http\Controllers\RestoreBrandController;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use Illuminate\Support\Facades\Route;

/*
| The store's catalog (Tenant\Catalog). Staff manage it under
| /api/v1/staff/catalog with the catalog.view and catalog.manage permissions.
| Answers follow the store language the client asks for (Accept-Language).
*/

Route::prefix('staff/catalog')
    ->name('staff.catalog.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api', UseStoreLanguage::class])
    ->group(static function (): void {
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::get('brands/{brand}', [BrandController::class, 'show'])->name('brands.show')->withTrashed();
        Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
        Route::post('brands/{brand}/restore', RestoreBrandController::class)->name('brands.restore')->withTrashed();
    });
