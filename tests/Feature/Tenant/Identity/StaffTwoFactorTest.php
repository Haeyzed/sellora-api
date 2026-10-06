<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Testing\TestResponse;

/*
 * Section 10: two-factor authentication is optional for staff (a store
 * setting to require it comes with store settings). A challenge only ever
 * works in the store, and for the guard, that started it.
 */
uses(DatabaseTruncation::class);

const TWO_FACTOR_STAFF_PASSWORD = 'a-long-staff-password';

beforeEach(function (): void {
    $this->store = createStore('first-store');
    $this->staffMember = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create(['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD]));
});

afterEach(function (): void {
    deleteAllStores();
});

function staffRequest(string $subdomain, string $method, string $path, array $data = [], ?string $token = null): TestResponse
{
    $request = $token === null ? test() : test()->withToken($token);
    $response = $request->json($method, storeUrl($subdomain, '/api/v1/staff/auth/'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

it('lets staff without two-factor authentication use everything, and turn it on themselves', function (): void {
    $token = staffRequest('first-store', 'POST', 'tokens', ['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD])->assertOk()->json('data.token');
    staffRequest('first-store', 'PUT', 'tokens/current', token: $token)->assertOk();

    $token = staffRequest('first-store', 'POST', 'tokens', ['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD])->json('data.token');
    $secret = staffRequest('first-store', 'POST', 'two-factor', ['current_password' => TWO_FACTOR_STAFF_PASSWORD], $token)->assertOk()->json('data.secret');
    staffRequest('first-store', 'POST', 'two-factor/confirmation', ['code' => twoFactorCode($secret)], $token)->assertOk()->assertJsonCount(8, 'data.recovery_codes');

    $challengeToken = staffRequest('first-store', 'POST', 'tokens', ['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD])->assertAccepted()->json('data.challenge_token');
    staffRequest('first-store', 'POST', 'two-factor-challenges', ['challenge_token' => $challengeToken, 'code' => twoFactorCode($secret, 1)])->assertOk();
});

it('never accepts a challenge from one store in another, even for a staff member with the same ID', function (): void {
    $secondStore = createStore('second-store');
    [$secret, $secondSecret] = [
        $this->store->run(fn (): string => enableTwoFactor($this->staffMember)),
        $secondStore->run(static fn (): string => enableTwoFactor(StaffMember::factory()->create(['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD]))),
    ];

    $challengeToken = staffRequest('first-store', 'POST', 'tokens', ['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD])->assertAccepted()->json('data.challenge_token');

    staffRequest('second-store', 'POST', 'two-factor-challenges', ['challenge_token' => $challengeToken, 'code' => twoFactorCode($secondSecret)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'two_factor_challenge_invalid');
    staffRequest('first-store', 'POST', 'two-factor-challenges', ['challenge_token' => $challengeToken, 'code' => twoFactorCode($secret)])->assertOk();
});

it('never accepts a platform admin\'s challenge on a store, or a store\'s on the platform', function (): void {
    $platformAdmin = PlatformAdmin::factory()->create(['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD]);
    $platformSecret = enableTwoFactor($platformAdmin);
    $staffSecret = $this->store->run(fn (): string => enableTwoFactor($this->staffMember));

    $platformChallenge = $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD])->assertAccepted()->json('data.challenge_token');
    $staffChallenge = staffRequest('first-store', 'POST', 'tokens', ['email' => 'sam@example.com', 'password' => TWO_FACTOR_STAFF_PASSWORD])->assertAccepted()->json('data.challenge_token');

    staffRequest('first-store', 'POST', 'two-factor-challenges', ['challenge_token' => $platformChallenge, 'code' => twoFactorCode($staffSecret)])
        ->assertJsonPath('code', 'two_factor_challenge_invalid');
    $this->postJson(centralUrl('/api/v1/platform/auth/two-factor-challenges'), ['challenge_token' => $staffChallenge, 'code' => twoFactorCode($platformSecret)])
        ->assertJsonPath('code', 'two_factor_challenge_invalid');
});
