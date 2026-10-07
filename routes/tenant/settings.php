<?php

declare(strict_types=1);

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Http\Controllers\StoreClosureController;
use App\Tenant\Settings\Http\Controllers\StoreExportController;
use App\Tenant\Settings\Http\Controllers\StoreSettingsController;
use Illuminate\Support\Facades\Route;

/*
| The store as a whole (Tenant\Settings), under /api/v1/staff/store: its
| settings (settings.view and settings.manage permissions), and closing it and
| exporting all of its data, which are for its owner only.
*/

Route::prefix('staff/store')
    ->name('staff.store.')
    ->middleware(['auth:'.StaffMember::GUARD, 'throttle:api'])
    ->group(static function (): void {
        Route::get('settings', [StoreSettingsController::class, 'show'])->name('settings.show');
        Route::patch('settings', [StoreSettingsController::class, 'update'])->name('settings.update');

        Route::post('closure', [StoreClosureController::class, 'store'])->name('closure.store');

        Route::post('exports', [StoreExportController::class, 'store'])->name('exports.store');
        Route::get('exports/{storeExport}', [StoreExportController::class, 'show'])->name('exports.show')->whereUlid('storeExport');
        Route::get('exports/{storeExport}/download', [StoreExportController::class, 'download'])->name('exports.download')->whereUlid('storeExport');
    });
