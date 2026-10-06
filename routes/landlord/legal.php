<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Http\Controllers\LegalDocumentController;
use App\Landlord\Legal\Http\Controllers\PublishLegalDocumentController;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorIsEnabled;
use Illuminate\Support\Facades\Route;

/*
| Managing Sellora's legal documents (Landlord\Legal), under /api/v1/platform.
*/

Route::middleware(['auth:'.PlatformAdmin::GUARD, EnsureTwoFactorIsEnabled::class, 'throttle:api'])->group(static function (): void {
    Route::apiResource('legal-documents', LegalDocumentController::class)->except('destroy')->parameters(['legal-documents' => 'legalDocument']);
    Route::post('legal-documents/{legalDocument}/publication', PublishLegalDocumentController::class)->name('legal-documents.publication.store');
});
