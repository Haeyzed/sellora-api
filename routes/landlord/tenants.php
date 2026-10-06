<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Http\Controllers\FeatureGrantController;
use App\Landlord\Tenancy\Http\Controllers\LimitOverrideController;
use App\Landlord\Tenancy\Http\Controllers\ManagedStoreClosureController;
use App\Landlord\Tenancy\Http\Controllers\ManagedStoreController;
use App\Landlord\Tenancy\Http\Controllers\RetryStoreProvisioningController;
use App\Landlord\Tenancy\Http\Controllers\StoreSuspensionController;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorIsEnabled;
use Illuminate\Support\Facades\Route;

/*
| Managing stores (Landlord\Tenancy, Landlord\Subscriptions), under
| /api/v1/platform/stores. Each endpoint checks its own permission.
*/

Route::model('store', Tenant::class);

Route::prefix('stores')
    ->name('stores.')
    ->middleware(['auth:'.PlatformAdmin::GUARD, EnsureTwoFactorIsEnabled::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('/', [ManagedStoreController::class, 'index'])->name('index');
        Route::get('{store}', [ManagedStoreController::class, 'show'])->name('show');

        Route::post('{store}/suspension', [StoreSuspensionController::class, 'store'])->name('suspension.store');
        Route::delete('{store}/suspension', [StoreSuspensionController::class, 'destroy'])->name('suspension.destroy');
        Route::post('{store}/provisioning-retries', RetryStoreProvisioningController::class)->name('provisioning-retries.store');
        Route::post('{store}/closure', [ManagedStoreClosureController::class, 'store'])->name('closure.store');
        Route::delete('{store}/closure', [ManagedStoreClosureController::class, 'destroy'])->name('closure.destroy');

        Route::scopeBindings()->group(static function (): void {
            Route::get('{store}/feature-grants', [FeatureGrantController::class, 'index'])->name('feature-grants.index');
            Route::post('{store}/feature-grants', [FeatureGrantController::class, 'store'])->name('feature-grants.store');
            Route::delete('{store}/feature-grants/{featureGrant}', [FeatureGrantController::class, 'destroy'])->name('feature-grants.destroy');
        });

        Route::get('{store}/limit-overrides', [LimitOverrideController::class, 'index'])->name('limit-overrides.index');
        Route::put('{store}/limit-overrides/{limitKey}', [LimitOverrideController::class, 'update'])->name('limit-overrides.update')
            ->whereIn('limitKey', config()->array('features.limit_keys'));
        Route::delete('{store}/limit-overrides/{limitKey}', [LimitOverrideController::class, 'destroy'])->name('limit-overrides.destroy')
            ->whereIn('limitKey', config()->array('features.limit_keys'));
    });
