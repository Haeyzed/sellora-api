<?php

declare(strict_types=1);

use App\Landlord\Legal\Http\Controllers\LegalDocumentInForceController;
use App\Landlord\Tenancy\Http\Controllers\HostingRegionController;
use App\Landlord\Tenancy\Http\Controllers\StoreRegistrationCodeController;
use App\Landlord\Tenancy\Http\Controllers\StoreRegistrationController;
use App\Landlord\Tenancy\Http\Controllers\StoreRegistrationVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Store Registration API
|--------------------------------------------------------------------------
|
| Merchants registering a new store, served on central domains only under
| /api/v1, without signing in. Landlord\Tenancy and Landlord\Legal.
|
*/

Route::middleware('throttle:public')->group(static function (): void {
    Route::get('hosting-regions', [HostingRegionController::class, 'index'])->name('hosting-regions.index');
    Route::get('legal-documents', [LegalDocumentInForceController::class, 'index'])->name('legal-documents.index');
    Route::get('store-registrations/{storeRegistration}', [StoreRegistrationController::class, 'show'])->name('store-registrations.show');
    Route::post('store-registrations/{storeRegistration}/verification', [StoreRegistrationVerificationController::class, 'store'])->name('store-registrations.verification.store');
});

Route::middleware('throttle:registration')->group(static function (): void {
    Route::post('store-registrations', [StoreRegistrationController::class, 'store'])->name('store-registrations.store');
    Route::post('store-registrations/{storeRegistration}/codes', [StoreRegistrationCodeController::class, 'store'])->name('store-registrations.codes.store');
});
