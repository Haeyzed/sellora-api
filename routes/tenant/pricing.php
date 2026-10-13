<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Http\Controllers\QuoteController;
use App\Tenant\Pricing\Http\Controllers\StoreCurrencyController;
use App\Tenant\Pricing\Http\Controllers\StorefrontCurrencyController;
use App\Tenant\Pricing\Http\Controllers\TaxClassController;
use App\Tenant\Pricing\Http\Controllers\TaxRateController;
use App\Tenant\Pricing\Http\Controllers\VariantPricingController;
use Illuminate\Support\Facades\Route;

/*
| The store's tax and currency setup (Tenant\Pricing). Staff work with it
| under /api/v1/staff/pricing with the pricing.view and pricing.manage
| permissions. Customers see the currencies they can buy in under
| /api/v1/catalog/currencies.
*/

Route::prefix('catalog')
    ->name('catalog.')
    ->middleware(['throttle:public'])
    ->group(static function (): void {
        Route::get('currencies', [StorefrontCurrencyController::class, 'index'])->name('currencies.index');
    });

Route::prefix('staff/pricing')
    ->name('staff.pricing.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::apiResource('tax-classes', TaxClassController::class)->parameters(['tax-classes' => 'taxClass']);
        Route::apiResource('tax-rates', TaxRateController::class)->parameters(['tax-rates' => 'taxRate']);
        Route::get('variants/{variant}', [VariantPricingController::class, 'show'])->name('variants.show')->withTrashed();
        Route::patch('variants/{variant}', [VariantPricingController::class, 'update'])->name('variants.update')->withTrashed();
        Route::put('variants/{variant}/prices/{currency}', [VariantPricingController::class, 'setFixedPrice'])->name('variants.prices.update')->withTrashed();
        Route::delete('variants/{variant}/prices/{currency}', [VariantPricingController::class, 'removeFixedPrice'])->name('variants.prices.destroy')->withTrashed();
        Route::get('currencies', [StoreCurrencyController::class, 'index'])->name('currencies.index');
        Route::post('currencies', [StoreCurrencyController::class, 'store'])->name('currencies.store');
        Route::get('currencies/{currency}', [StoreCurrencyController::class, 'show'])->name('currencies.show');
        Route::patch('currencies/{currency}', [StoreCurrencyController::class, 'update'])->name('currencies.update');
        Route::put('currencies/{currency}/rate', [StoreCurrencyController::class, 'setRate'])->name('currencies.rate.update');
        Route::delete('currencies/{currency}/rate', [StoreCurrencyController::class, 'clearRate'])->name('currencies.rate.destroy');
        Route::post('quotes', QuoteController::class)->name('quotes.store');
        Route::put('products/{product}/tax-class', [VariantPricingController::class, 'updateProduct'])->name('products.tax-class.update')->withTrashed();
    });
