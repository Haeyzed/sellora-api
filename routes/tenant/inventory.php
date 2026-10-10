<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Http\Controllers\StockLevelController;
use App\Tenant\Inventory\Http\Controllers\StockLocationController;
use App\Tenant\Inventory\Http\Controllers\VariantStockController;
use Illuminate\Support\Facades\Route;

/*
| The store's stock (Tenant\Inventory). Staff work with it under
| /api/v1/staff/inventory with the inventory.view and inventory.adjust
| permissions. Variants are addressed by their own IDs.
*/

Route::prefix('staff/inventory')
    ->name('staff.inventory.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('locations', [StockLocationController::class, 'index'])->name('locations.index');
        Route::get('levels', [StockLevelController::class, 'index'])->name('levels.index');
        Route::get('variants/{variant}', [VariantStockController::class, 'show'])->name('variants.show');
        Route::patch('variants/{variant}', [VariantStockController::class, 'update'])->name('variants.update');
        Route::post('variants/{variant}/adjustments', [VariantStockController::class, 'adjust'])->name('variants.adjustments.store');
        Route::post('variants/{variant}/receipts', [VariantStockController::class, 'receive'])->name('variants.receipts.store');
        Route::get('variants/{variant}/movements', [VariantStockController::class, 'movements'])->name('variants.movements.index')->withTrashed();
    });
