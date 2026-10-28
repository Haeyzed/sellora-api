<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Returns\Http\Controllers\ReturnSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Returns (Tenant\Returns, Step 18): buyers sending units of their orders
| back to be refunded. Staff manage returns and the return settings under
| /api/v1/staff/returns.
*/

Route::prefix('staff/returns')
    ->name('staff.returns.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('settings', [ReturnSettingsController::class, 'show'])->name('settings.show');
        Route::patch('settings', [ReturnSettingsController::class, 'update'])->name('settings.update');
    });
