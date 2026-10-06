<?php

declare(strict_types=1);

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Http\Controllers\StoreClosureController;
use Illuminate\Support\Facades\Route;

/*
| The store as a whole (Tenant\Settings), under /api/v1/staff/store.
| Closing the store is for its owner only.
*/

Route::prefix('staff/store')
    ->name('staff.store.')
    ->middleware(['auth:'.StaffMember::GUARD, 'throttle:api'])
    ->group(static function (): void {
        Route::post('closure', [StoreClosureController::class, 'store'])->name('closure.store');
    });
