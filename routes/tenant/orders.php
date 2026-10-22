<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Http\Controllers\CustomerOrderController;
use App\Tenant\Orders\Http\Controllers\CustomerTaxDocumentController;
use App\Tenant\Orders\Http\Controllers\GuestOrderController;
use App\Tenant\Orders\Http\Controllers\GuestPrivacyController;
use App\Tenant\Orders\Http\Controllers\GuestTaxDocumentController;
use App\Tenant\Orders\Http\Controllers\OrderNoteController;
use App\Tenant\Orders\Http\Controllers\ShipmentController;
use App\Tenant\Orders\Http\Controllers\StaffOrderController;
use App\Tenant\Orders\Http\Controllers\TaxDocumentController;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use Illuminate\Support\Facades\Route;

/*
| Orders (Tenant\Orders). Staff work with them under /api/v1/staff/orders
| with the orders.view, orders.manage and orders.fulfil permissions, and their
| shipments under /api/v1/staff/shipments. Orders are found by their ID,
| never by their number. Invoices and credit notes, under
| /api/v1/staff/invoices and /api/v1/staff/credit-notes, need invoices.view.
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
            Route::get('{order}/shipments', [ShipmentController::class, 'forOrder'])->name('shipments.index');
            Route::post('{order}/shipments', [ShipmentController::class, 'store'])->middleware('idempotent')->name('shipments.store');
        });

        Route::prefix('shipments')->name('shipments.')->group(static function (): void {
            Route::get('/', [ShipmentController::class, 'index'])->name('index');
            Route::get('{shipment}', [ShipmentController::class, 'show'])->name('show');
            Route::patch('{shipment}', [ShipmentController::class, 'update'])->name('update');
            Route::post('{shipment}/ready', [ShipmentController::class, 'ready'])->name('ready');
            Route::post('{shipment}/ship', [ShipmentController::class, 'ship'])->name('ship');
            Route::post('{shipment}/deliver', [ShipmentController::class, 'deliver'])->name('deliver');
            Route::post('{shipment}/cancel', [ShipmentController::class, 'cancel'])->name('cancel');
        });

        Route::prefix('invoices')->name('invoices.')->group(static function (): void {
            Route::get('/', [TaxDocumentController::class, 'invoices'])->name('index');
            Route::get('{invoice}', [TaxDocumentController::class, 'invoice'])->name('show');
            Route::get('{invoice}/pdf', [TaxDocumentController::class, 'invoicePdf'])->name('pdf');
        });

        Route::prefix('credit-notes')->name('credit-notes.')->group(static function (): void {
            Route::get('/', [TaxDocumentController::class, 'creditNotes'])->name('index');
            Route::get('{creditNote}', [TaxDocumentController::class, 'creditNote'])->name('show');
            Route::get('{creditNote}/pdf', [TaxDocumentController::class, 'creditNotePdf'])->name('pdf');
        });

        Route::get('customers/{customer}/orders', [StaffOrderController::class, 'forCustomer'])->name('customers.orders.index')->withTrashed();

        // A guest's data-protection request. The email goes in the body, never the URL, so it stays out of logs.
        Route::post('guest-personal-data', [GuestPrivacyController::class, 'show'])->name('guest-personal-data.show');
        Route::post('guest-personal-data/erasure', [GuestPrivacyController::class, 'destroy'])->name('guest-personal-data.erasure.store');
    });

/*
| A signed-in customer's own orders, under /api/v1/customer/orders, guest
| orders placed with their verified email included, with their invoices and
| credit notes.
*/

Route::prefix('customer/orders')->name('customer.orders.')->middleware(['auth:'.Customer::GUARD, 'throttle:api', UseStoreLanguage::class])->group(static function (): void {
    Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
    Route::get('{order}', [CustomerOrderController::class, 'show'])->name('show');
    Route::post('{order}/cancel', [CustomerOrderController::class, 'cancel'])->name('cancel');
    Route::get('{order}/invoice', [CustomerTaxDocumentController::class, 'invoice'])->name('invoice.show');
    Route::get('{order}/invoice/pdf', [CustomerTaxDocumentController::class, 'invoicePdf'])->name('invoice.pdf');
    Route::get('{order}/credit-notes/{creditNote}', [CustomerTaxDocumentController::class, 'creditNote'])->name('credit-notes.show');
    Route::get('{order}/credit-notes/{creditNote}/pdf', [CustomerTaxDocumentController::class, 'creditNotePdf'])->name('credit-notes.pdf');
});

/*
| A guest's order, under /api/v1/guest/orders, with the access token given
| when it was placed, in the X-Order-Token header only (never the URL).
| Limited per token, under a ceiling per IP address.
*/

Route::prefix('guest/orders')->name('guest.orders.')->middleware([UseStoreLanguage::class, 'throttle:guest-order'])->group(static function (): void {
    Route::get('{order}', [GuestOrderController::class, 'show'])->name('show');
    Route::post('{order}/cancel', [GuestOrderController::class, 'cancel'])->name('cancel');
    Route::get('{order}/invoice', [GuestTaxDocumentController::class, 'invoice'])->name('invoice.show');
    Route::get('{order}/invoice/pdf', [GuestTaxDocumentController::class, 'invoicePdf'])->name('invoice.pdf');
    Route::get('{order}/credit-notes/{creditNote}', [GuestTaxDocumentController::class, 'creditNote'])->name('credit-notes.show');
    Route::get('{order}/credit-notes/{creditNote}/pdf', [GuestTaxDocumentController::class, 'creditNotePdf'])->name('credit-notes.pdf');
});
