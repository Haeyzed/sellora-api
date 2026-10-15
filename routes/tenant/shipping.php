<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Shipping\Http\Controllers\ShippingZoneController;
use Illuminate\Support\Facades\Route;

/*
| The store's shipping setup (Tenant\Shipping). Staff work with it under
| /api/v1/staff/shipping with the shipping.view and shipping.manage
| permissions.
*/

Route::prefix('staff/shipping')
    ->name('staff.shipping.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::apiResource('zones', ShippingZoneController::class);
        Route::put('zones/{zone}/regions', [ShippingZoneController::class, 'replaceRegions'])->name('zones.regions.update');
    });
