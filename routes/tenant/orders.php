<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Http\Controllers\OrderNoteController;
use App\Tenant\Orders\Http\Controllers\StaffOrderController;
use Illuminate\Support\Facades\Route;

/*
| Orders (Tenant\Orders). Staff work with them under /api/v1/staff/orders
| with the orders.view, orders.manage and orders.fulfil permissions. Orders
| are found by their ID, never by their number.
*/

Route::prefix('staff')
    ->name('staff.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::prefix('orders')->name('orders.')->group(static function (): void {
            Route::get('/', [StaffOrderController::class, 'index'])->name('index');
            Route::get('{order}', [StaffOrderController::class, 'show'])->name('show');
            Route::get('{order}/events', [StaffOrderController::class, 'events'])->name('events.index');
            Route::post('{order}/cancel', [StaffOrderController::class, 'cancel'])->name('cancel');
            Route::put('{order}/tags', [StaffOrderController::class, 'tags'])->name('tags.update');
            Route::put('{order}/contact', [StaffOrderController::class, 'contact'])->name('contact.update');
            Route::put('{order}/shipping-address', [StaffOrderController::class, 'shippingAddress'])->name('shipping-address.update');
            Route::get('{order}/notes', [OrderNoteController::class, 'index'])->name('notes.index');
            Route::post('{order}/notes', [OrderNoteController::class, 'store'])->name('notes.store');
            Route::delete('{order}/notes/{note}', [OrderNoteController::class, 'destroy'])->name('notes.destroy')->scopeBindings();
        });

        Route::get('customers/{customer}/orders', [StaffOrderController::class, 'forCustomer'])->name('customers.orders.index')->withTrashed();
    });
