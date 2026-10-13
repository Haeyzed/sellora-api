<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Http\Controllers\TaxClassController;
use App\Tenant\Pricing\Http\Controllers\TaxRateController;
use App\Tenant\Pricing\Http\Controllers\VariantPricingController;
use Illuminate\Support\Facades\Route;

/*
| The store's tax and currency setup (Tenant\Pricing). Staff work with it
| under /api/v1/staff/pricing with the pricing.view and pricing.manage
| permissions.
*/

Route::prefix('staff/pricing')
    ->name('staff.pricing.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::apiResource('tax-classes', TaxClassController::class)->parameters(['tax-classes' => 'taxClass']);
        Route::apiResource('tax-rates', TaxRateController::class)->parameters(['tax-rates' => 'taxRate']);
        Route::get('variants/{variant}', [VariantPricingController::class, 'show'])->name('variants.show')->withTrashed();
        Route::patch('variants/{variant}', [VariantPricingController::class, 'update'])->name('variants.update')->withTrashed();
        Route::put('products/{product}/tax-class', [VariantPricingController::class, 'updateProduct'])->name('products.tax-class.update')->withTrashed();
    });
