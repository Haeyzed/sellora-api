<?php

declare(strict_types=1);

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalDocument;
use App\Shared\Auth\AccessTokenIssuer;
use Database\Seeders\Landlord\PlatformPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

/*
 * Landlord\Legal: Sellora's team drafts and publishes versions of the legal
 * documents merchants accept. A published version never changes, and one
 * published ahead of time leaves the version in force until it takes effect.
 */
uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformPermissionSeeder::class);

    $this->legalAdmin = PlatformAdmin::factory()->create();
    $this->legalAdmin->givePermissionTo(PlatformPermission::LegalDocumentsManage->value);
    enableTwoFactor($this->legalAdmin);
});

/**
 * Sends a request to the platform's legal documents API as the given admin.
 *
 * @param  array<string, mixed>  $data
 */
function manageLegalDocuments(string $method, string $path, array $data = [], ?PlatformAdmin $as = null): TestResponse
{
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($as ?? test()->legalAdmin, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->json($method, centralUrl('/api/v1/platform/legal-documents'.$path), $data);
    forgetSignIns();

    return $response;
}

it('drafts, revises and publishes a version, which then never changes', function (): void {
    $legalDocumentId = manageLegalDocuments('POST', '', ['type' => 'terms_of_service', 'version' => '2026-10', 'title' => 'Terms', 'body' => 'First draft.'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->json('data.id');

    manageLegalDocuments('PUT', "/{$legalDocumentId}", ['version' => '2026-10', 'title' => 'Terms of Service', 'body' => 'Final text.'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Terms of Service');

    manageLegalDocuments('POST', "/{$legalDocumentId}/publication")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    manageLegalDocuments('PUT', "/{$legalDocumentId}", ['version' => '2026-10', 'title' => 'Changed', 'body' => 'Changed.'])
        ->assertConflict()
        ->assertJsonPath('code', 'legal_document_already_published');
    manageLegalDocuments('POST', "/{$legalDocumentId}/publication")->assertConflict();

    $legalDocument = LegalDocument::query()->sole();
    expect($legalDocument->body)->toBe('Final text.')
        ->and(Activity::query()->where('causer_id', $this->legalAdmin->id)->pluck('event')->all())
        ->toBe(['legal_document_drafted', 'legal_document_revised', 'legal_document_published']);

    $this->getJson(centralUrl('/api/v1/legal-documents'))->assertOk()->assertJsonPath('data.0.id', $legalDocumentId);
});

it('keeps the version in force until a version published ahead of time takes effect', function (): void {
    $inForce = LegalDocument::factory()->ofType(LegalDocumentType::TermsOfService)->inForce()->create();
    $next = LegalDocument::factory()->ofType(LegalDocumentType::TermsOfService)->create();

    manageLegalDocuments('POST', "/{$next->public_id}/publication", ['effective_at' => now()->addWeek()->toIso8601String()])->assertOk();

    $this->getJson(centralUrl('/api/v1/legal-documents'))->assertOk()->assertJsonPath('data.0.id', $inForce->public_id);

    $this->travel(8)->days();

    $this->getJson(centralUrl('/api/v1/legal-documents'))->assertOk()->assertJsonPath('data.0.id', $next->public_id);
});

it('refuses a version label the document already has, and an effective date in the past', function (): void {
    $draft = LegalDocument::factory()->create(['version' => '2026-10']);

    manageLegalDocuments('POST', '', ['type' => 'terms_of_service', 'version' => '2026-10', 'title' => 'Terms', 'body' => 'Text.'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'legal_document_version_taken');
    manageLegalDocuments('POST', '', ['type' => 'privacy_policy', 'version' => '2026-10', 'title' => 'Privacy', 'body' => 'Text.'])
        ->assertCreated();

    manageLegalDocuments('POST', "/{$draft->public_id}/publication", ['effective_at' => now()->subDay()->toIso8601String()])
        ->assertJsonValidationErrors('effective_at');
});

it('refuses platform admins without the permission', function (): void {
    $colleague = PlatformAdmin::factory()->create();
    enableTwoFactor($colleague);
    $draft = LegalDocument::factory()->create();

    manageLegalDocuments('GET', '', as: $colleague)->assertForbidden();
    manageLegalDocuments('POST', '', ['type' => 'terms_of_service', 'version' => 'v9', 'title' => 'T', 'body' => 'B'], as: $colleague)->assertForbidden();
    manageLegalDocuments('POST', "/{$draft->public_id}/publication", as: $colleague)->assertForbidden();

    expect($draft->refresh()->isPublished())->toBeFalse();
});
