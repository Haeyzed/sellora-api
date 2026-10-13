<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For LegalDocumentManagementTest.php
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to the platform's legal documents API as the given admin.
 *
 * @param  array<string, mixed>  $data
 */
function manageLegalDocuments(string $method, string $path, array $data = [], ?PlatformAdmin $as = null): TestResponse
{
    forgetSignIns();
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($as ?? test()->legalAdmin, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->json($method, centralUrl('/api/v1/platform/legal-documents'.$path), $data);
    forgetSignIns();

    return $response;
}
