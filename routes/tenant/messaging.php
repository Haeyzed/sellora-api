<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Shared\Http\Middleware\UseDashboardLanguage;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Messaging\Http\Controllers\MessagingSettingsController;
use Illuminate\Support\Facades\Route;

/*
| The store's messages (Tenant\Messaging), for staff under
| /api/v1/staff/messaging: who the store's emails come from and where
| replies go, with the settings.view and settings.manage permissions.
*/

Route::prefix('staff/messaging')
    ->name('staff.messaging.')
    ->middleware([UseDashboardLanguage::class, 'auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('settings', [MessagingSettingsController::class, 'show'])->name('settings.show');
        Route::patch('settings', [MessagingSettingsController::class, 'update'])->name('settings.update');
    });
