<?php

declare(strict_types=1);

use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalAcceptance;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\OwnershipTransferStatus;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\OwnershipTransferOfferNotification;
use App\Tenant\Identity\OwnershipTransferredNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

/*
 * Section 10: ownership moves in two steps. The owner offers the store
 * (password, plus a code with 2FA, choosing the roles they keep); the chosen
 * colleague accepts while signed in within 72 hours, accepting the terms of
 * service in force. Nothing changes before that, the owner can cancel, and a
 * store has one pending transfer at most.
 */
uses(DatabaseTruncation::class);

const OWNERSHIP_PASSWORD = 'a-long-owner-password';

beforeEach(function (): void {
    Notification::fake();
    $this->termsOfService = LegalDocument::factory()->ofType(LegalDocumentType::TermsOfService)->inForce()->create();

    $this->store = createStore('owned-store');
    [$this->owner, $this->colleague, $this->manager, $this->managerRole] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create(['name' => 'Olu Owner', 'email' => 'owner@example.com', 'password' => OWNERSHIP_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);
        $managerRole = Role::findOrCreate('Manager', StaffMember::GUARD);
        $colleague = StaffMember::factory()->create(['name' => 'Ada Colleague', 'email' => 'ada@example.com', 'password' => OWNERSHIP_PASSWORD]);
        $manager = StaffMember::factory()->create(['email' => 'manager@example.com', 'password' => OWNERSHIP_PASSWORD]);
        $manager->assignRole($managerRole);

        return [$owner, $colleague, $manager, $managerRole];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * Sends a request to a store's ownership transfer API as the given staff member.
 */
function ownershipTransfer(string $method, string $path, array $data, StaffMember $as, string $subdomain = 'owned-store'): TestResponse
{
    forgetSignIns();
    $store = Tenant::query()->whereHas('domains', static fn ($query) => $query->where('domain', Tenant::platformDomainFor($subdomain)))->firstOrFail();
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/team/ownership-transfers'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * The owner's offer to the colleague, keeping the given roles.
 *
 * @param  list<string>  $keptRoleIds
 */
function offerStoreToColleague(array $keptRoleIds = [], array $extra = []): TestResponse
{
    return ownershipTransfer('POST', '', [
        'staff_member' => test()->colleague->public_id,
        'current_password' => OWNERSHIP_PASSWORD,
        'roles' => $keptRoleIds,
        ...$extra,
    ], test()->owner);
}

function acceptStore(string $transferId, ?StaffMember $as = null, ?string $termsOfServiceId = null): TestResponse
{
    return ownershipTransfer('POST', "/{$transferId}/acceptance", [
        'accepted_terms_of_service' => $termsOfServiceId ?? test()->termsOfService->public_id,
    ], $as ?? test()->colleague);
}

/**
 * @return array{owner: bool, colleague: bool, owner_roles: list<string>, colleague_roles: list<string>}
 */
function storeOwnershipState(): array
{
    return test()->store->run(static function (): array {
        $owner = StaffMember::query()->findOrFail(test()->owner->id);
        $colleague = StaffMember::query()->findOrFail(test()->colleague->id);

        return [
            'owner' => $owner->hasRole(StaffRole::Owner->value),
            'colleague' => $colleague->hasRole(StaffRole::Owner->value),
            'owner_roles' => array_values($owner->getRoleNames()->all()),
            'colleague_roles' => array_values($colleague->getRoleNames()->all()),
        ];
    });
}

it('changes nothing until the colleague accepts, then hands over the store and keeps the chosen roles', function (): void {
    $transferId = offerStoreToColleague([$this->managerRole->public_id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.to.id', $this->colleague->public_id)
        ->assertJsonPath('data.kept_roles.0.name', 'Manager')
        ->json('data.id');

    Notification::assertSentTo($this->colleague, OwnershipTransferOfferNotification::class, static fn (OwnershipTransferOfferNotification $notification): bool => str_contains($notification->acceptUrl(), $transferId));
    expect(storeOwnershipState())->toMatchArray(['owner' => true, 'colleague' => false]);

    acceptStore($transferId)->assertOk()->assertJsonPath('data.status', 'accepted');

    expect(storeOwnershipState())->toBe(['owner' => false, 'colleague' => true, 'owner_roles' => ['Manager'], 'colleague_roles' => [StaffRole::Owner->value]]);

    $store = $this->store->refresh();
    expect($store->owner_name)->toBe('Ada Colleague')
        ->and($store->owner_email)->toBe('ada@example.com')
        ->and(LegalAcceptance::query()->where('tenant_id', $store->id)->where('legal_document_id', $this->termsOfService->id)->where('accepted_by_email', 'ada@example.com')->exists())->toBeTrue();

    Notification::assertSentTo($this->colleague, OwnershipTransferredNotification::class, static fn (OwnershipTransferredNotification $notification): bool => $notification->toNewOwner);
    Notification::assertSentTo($this->owner, OwnershipTransferredNotification::class, static fn (OwnershipTransferredNotification $notification): bool => ! $notification->toNewOwner);
    expect($this->store->run(static fn (): bool => Activity::query()->where('event', 'ownership_transferred')->exists()))->toBeTrue();
});

it('leaves the previous owner with no roles when they keep none', function (): void {
    $transferId = offerStoreToColleague([])->assertCreated()->json('data.id');

    acceptStore($transferId)->assertOk();

    expect(storeOwnershipState())->toMatchArray(['owner' => false, 'owner_roles' => [], 'colleague' => true]);
});

it('needs the roles the owner keeps, and never the Owner role', function (): void {
    ownershipTransfer('POST', '', ['staff_member' => $this->colleague->public_id, 'current_password' => OWNERSHIP_PASSWORD], $this->owner)
        ->assertUnprocessable()->assertJsonValidationErrors('roles');

    $ownerRoleId = $this->store->run(static fn (): string => Role::findByName(StaffRole::Owner->value, StaffMember::GUARD)->public_id);
    offerStoreToColleague([$ownerRoleId])->assertUnprocessable()->assertJsonPath('code', 'role_not_assignable');
});

it('needs the current password, and a code when the owner uses two-factor authentication', function (): void {
    offerStoreToColleague(extra: ['current_password' => 'not-the-password'])->assertUnprocessable()->assertJsonPath('code', 'current_password_incorrect');

    $secret = $this->store->run(fn (): string => enableTwoFactor(StaffMember::query()->findOrFail($this->owner->id)));
    offerStoreToColleague()->assertUnprocessable()->assertJsonPath('code', 'two_factor_code_invalid');
    offerStoreToColleague(extra: ['code' => '000000'])->assertUnprocessable()->assertJsonPath('code', 'two_factor_code_invalid');
    expect($this->store->run(static fn (): int => OwnershipTransfer::query()->count()))->toBe(0);

    offerStoreToColleague(extra: ['code' => twoFactorCode($secret)])->assertCreated();
});

it('lets only the owner offer the store, and only to another active colleague', function (): void {
    ownershipTransfer('POST', '', ['staff_member' => $this->colleague->public_id, 'current_password' => OWNERSHIP_PASSWORD, 'roles' => []], $this->manager)->assertForbidden();

    ownershipTransfer('POST', '', ['staff_member' => $this->owner->public_id, 'current_password' => OWNERSHIP_PASSWORD, 'roles' => []], $this->owner)
        ->assertUnprocessable()->assertJsonPath('code', 'ownership_transfer_recipient_invalid');

    $this->store->run(fn () => StaffMember::query()->whereKey($this->colleague->id)->update(['is_active' => false]));
    offerStoreToColleague()->assertUnprocessable()->assertJsonPath('code', 'ownership_transfer_recipient_invalid');
});

it('lets only the colleague it was offered to accept it', function (): void {
    $transferId = offerStoreToColleague()->assertCreated()->json('data.id');

    acceptStore($transferId, as: $this->manager)->assertForbidden();
    acceptStore($transferId, as: $this->owner)->assertForbidden();

    expect(storeOwnershipState())->toMatchArray(['owner' => true, 'colleague' => false]);
});

it('changes nothing in the store when the terms of service accepted are not the ones in force', function (): void {
    $transferId = offerStoreToColleague()->assertCreated()->json('data.id');
    $oldTerms = LegalDocument::factory()->ofType(LegalDocumentType::TermsOfService)->create(['published_at' => now()->subYear(), 'effective_at' => now()->subYear()]);

    acceptStore($transferId, termsOfServiceId: $oldTerms->public_id)
        ->assertUnprocessable()->assertJsonPath('code', 'terms_of_service_not_accepted');

    expect(storeOwnershipState())->toMatchArray(['owner' => true, 'colleague' => false])
        ->and($this->store->refresh()->owner_email)->not->toBe('ada@example.com')
        ->and(LegalAcceptance::query()->where('accepted_by_email', 'ada@example.com')->exists())->toBeFalse();

    acceptStore($transferId)->assertOk();
});

it('allows one pending transfer per store, which the owner can cancel', function (): void {
    $transferId = offerStoreToColleague()->assertCreated()->json('data.id');
    offerStoreToColleague()->assertConflict()->assertJsonPath('code', 'ownership_transfer_already_pending');

    ownershipTransfer('DELETE', "/{$transferId}", [], $this->colleague)->assertForbidden();
    ownershipTransfer('DELETE', "/{$transferId}", [], $this->owner)->assertNoContent();

    acceptStore($transferId)->assertConflict()->assertJsonPath('code', 'ownership_transfer_not_pending');
    offerStoreToColleague()->assertCreated();
});

it('refuses a second pending transfer in the database too', function (): void {
    $this->store->run(function (): void {
        $insertPending = fn (): bool => DB::table('ownership_transfers')->insert([
            'public_id' => (string) Str::ulid(),
            'from_staff_member_id' => $this->owner->id,
            'to_staff_member_id' => $this->colleague->id,
            'status' => OwnershipTransferStatus::Pending->value,
            'expires_at' => now()->addDay(),
        ]);

        $insertPending();

        expect($insertPending)->toThrow(QueryException::class, 'ownership_transfers_one_pending');
    });
});

it('expires after 72 hours: it can no longer be accepted, and no longer blocks a new one', function (): void {
    $transferId = offerStoreToColleague()->assertCreated()->json('data.id');

    $this->travel(73)->hours();

    acceptStore($transferId)->assertConflict()->assertJsonPath('code', 'ownership_transfer_not_pending');
    expect(storeOwnershipState())->toMatchArray(['owner' => true, 'colleague' => false]);

    offerStoreToColleague()->assertCreated();
    expect($this->store->run(static fn (): OwnershipTransferStatus => OwnershipTransfer::query()->where('public_id', $transferId)->firstOrFail()->status))
        ->toBe(OwnershipTransferStatus::Expired);
});

it('shows the pending transfer only to the owner and the colleague it was offered to', function (): void {
    $transferId = offerStoreToColleague()->assertCreated()->json('data.id');

    ownershipTransfer('GET', '/pending', [], $this->owner)->assertOk()->assertJsonPath('data.id', $transferId);
    ownershipTransfer('GET', '/pending', [], $this->colleague)->assertOk()->assertJsonPath('data.id', $transferId);
    ownershipTransfer('GET', '/pending', [], $this->manager)->assertNotFound()->assertJsonPath('code', 'ownership_transfer_not_found');
});

it('never lets a transfer of one store be used in another', function (): void {
    $transferId = offerStoreToColleague()->assertCreated()->json('data.id');

    $otherStore = createStore('other-store');
    $otherStaffMember = $otherStore->run(static fn (): StaffMember => StaffMember::factory()->create());

    ownershipTransfer('POST', "/{$transferId}/acceptance", ['accepted_terms_of_service' => $this->termsOfService->public_id], $otherStaffMember, 'other-store')->assertNotFound();
    expect(storeOwnershipState())->toMatchArray(['owner' => true, 'colleague' => false]);
});
