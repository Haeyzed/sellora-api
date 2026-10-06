<?php

declare(strict_types=1);

use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Idempotency\IdempotencyStatus;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Shared\Retention\RetentionRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(LazilyRefreshDatabase::class);

function createIdempotencyKey(string $key, Carbon\CarbonInterface $expiresAt): IdempotencyKey
{
    return IdempotencyKey::query()->create([
        'scope' => 'ip:test',
        'route' => 'POST sample',
        'key' => $key,
        'request_hash' => str_repeat('a', 64),
        'status' => IdempotencyStatus::Completed,
        'locked_until' => now(),
        'expires_at' => $expiresAt,
    ]);
}

function createAccessToken(string $name, Carbon\CarbonInterface $createdAt): PersonalAccessToken
{
    $token = PersonalAccessToken::query()->forceCreate([
        'tokenable_type' => 'sample',
        'tokenable_id' => 1,
        'name' => $name,
        'token' => hash('sha256', $name),
        'abilities' => ['*'],
    ]);
    $token->forceFill(['created_at' => $createdAt])->save();

    return $token;
}

beforeEach(function (): void {
    config([
        'retention.periods.idempotency_keys' => 1,
        'retention.periods.expired_tokens' => 7,
        'sanctum.expiration' => 1440,
    ]);
});

it('deletes idempotency records whose replay window ended longer ago than the retention period', function (): void {
    createIdempotencyKey('long-expired-key', now()->subDays(2));
    createIdempotencyKey('recently-expired-key', now()->subHours(12));
    createIdempotencyKey('active-key', now()->addHours(12));

    PurgeExpiredRecords::dispatchSync();

    expect(IdempotencyKey::query()->orderBy('key')->pluck('key')->all())->toBe(['active-key', 'recently-expired-key']);
});

it('deletes sign-in tokens only after they have been expired for the whole retention period', function (): void {
    createAccessToken('issued-ten-days-ago', now()->subDays(10));
    createAccessToken('issued-five-days-ago', now()->subDays(5));

    PurgeExpiredRecords::dispatchSync();

    expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['issued-five-days-ago']);
});

it('runs each policy only on the databases it belongs to', function (): void {
    $policiesFor = static fn (bool $isTenantContext): array => array_map(
        static fn (object $policy): string => $policy::class,
        app(RetentionRegistry::class)->policiesFor($isTenantContext),
    );
    $centralPolicies = $policiesFor(false);
    $storePolicies = $policiesFor(true);

    expect($centralPolicies)->not->toContain(App\Tenant\Identity\StaffInvitationRetention::class)
        ->and($centralPolicies)->toContain(App\Landlord\Tenancy\StoreRegistrationRetention::class)
        ->and($storePolicies)->not->toContain(App\Landlord\Tenancy\StoreRegistrationRetention::class)
        ->and($storePolicies)->toContain(App\Tenant\Identity\StaffInvitationRetention::class);

    // Both have their own activity log and audit history.
    foreach ([App\Shared\Retention\Policies\ActivityLogRetention::class, App\Shared\Retention\Policies\AuditRetention::class] as $policy) {
        expect($centralPolicies)->toContain($policy)->and($storePolicies)->toContain($policy);
    }
});

it('fails loudly instead of keeping data forever when a retention period is missing', function (): void {
    config(['retention.periods' => []]);

    expect(fn () => PurgeExpiredRecords::dispatchSync())->toThrow(LogicException::class, 'idempotency_keys');
});

it('runs on the bulk queue so it never delays urgent work', function (): void {
    expect((new PurgeExpiredRecords)->queue)->toBe('bulk');
});
