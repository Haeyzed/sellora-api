<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Section 10: two-factor authentication is mandatory for platform admins.
 * A new admin's first token can only set it up; afterwards, sign-in needs the
 * password and a code, every code works once, and wrong codes lock sign-in.
 */
uses(LazilyRefreshDatabase::class);

const TWO_FACTOR_ADMIN_PASSWORD = 'a-long-platform-password';

beforeEach(function (): void {
    $this->admin = PlatformAdmin::factory()->create(['email' => 'ada@sellora.test', 'password' => TWO_FACTOR_ADMIN_PASSWORD]);
});

function startPlatformAdminSignIn(string $deviceName = 'Ada\'s laptop'): string
{
    return test()->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => TWO_FACTOR_ADMIN_PASSWORD, 'device_name' => $deviceName])
        ->assertAccepted()
        ->json('data.challenge_token');
}

function answerPlatformAdminChallenge(string $challengeToken, array $answer): Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/platform/auth/two-factor-challenges', ['challenge_token' => $challengeToken, ...$answer]);
}

function platformAdminToken(PlatformAdmin $platformAdmin): string
{
    return app(AccessTokenIssuer::class)->issue($platformAdmin, PlatformAdmin::GUARD, 'test')->plainTextToken;
}

it('lets a new admin only see their profile, set up two-factor authentication and sign out', function (): void {
    $token = $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => TWO_FACTOR_ADMIN_PASSWORD])->assertOk()->json('data.token');

    $this->withToken($token)->getJson('/api/v1/platform/auth/me')->assertOk()->assertJsonPath('data.two_factor_enabled', false);
    $this->withToken($token)->putJson('/api/v1/platform/auth/tokens/current')
        ->assertForbidden()
        ->assertJsonPath('code', 'two_factor_setup_required');
    $this->withToken($token)->putJson('/api/v1/platform/auth/password', ['current_password' => TWO_FACTOR_ADMIN_PASSWORD, 'password' => 'a-brand-new-long-password', 'password_confirmation' => 'a-brand-new-long-password'])
        ->assertForbidden();
});

it('sets up two-factor authentication: a secret for the app, then recovery codes once a code from the app is confirmed', function (): void {
    $token = platformAdminToken($this->admin);

    $setup = $this->withToken($token)->postJson('/api/v1/platform/auth/two-factor', ['current_password' => TWO_FACTOR_ADMIN_PASSWORD])
        ->assertOk()
        ->json('data');
    expect($setup['setup_url'])->toStartWith('otpauth://totp/')->toContain('ada%40sellora.test', 'secret='.$setup['secret'], 'issuer=');

    $this->withToken($token)->postJson('/api/v1/platform/auth/two-factor/confirmation', ['code' => twoFactorCode($setup['secret'], -2)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'two_factor_code_invalid')
        ->assertJsonValidationErrors('code');

    $recoveryCodes = $this->withToken($token)->postJson('/api/v1/platform/auth/two-factor/confirmation', ['code' => twoFactorCode($setup['secret'])])
        ->assertOk()
        ->json('data.recovery_codes');
    expect($recoveryCodes)->toHaveCount(8)->each->toMatch('/^[a-z2-9]{4}(-[a-z2-9]{4}){3}$/');

    forgetSignIns();
    $this->withToken($token)->getJson('/api/v1/platform/auth/me')->assertJsonPath('data.two_factor_enabled', true);
    $this->withToken($token)->putJson('/api/v1/platform/auth/tokens/current')->assertOk();
});

it('stores the secret encrypted and only hashes of the recovery codes', function (): void {
    $secret = enableTwoFactor($this->admin, 'abcd-efgh-jkmn-pqrs');

    $stored = DB::table('platform_admins')->where('id', $this->admin->id)->first(['two_factor_secret', 'two_factor_recovery_codes']);

    expect($stored->two_factor_secret)->not->toContain($secret)
        ->and(decrypt($stored->two_factor_secret, unserialize: false))->toBe($secret)
        ->and($stored->two_factor_recovery_codes)->not->toContain('abcd');
});

it('needs the current password to start setup, and refuses to start while two-factor authentication is on', function (): void {
    $token = platformAdminToken($this->admin);

    $this->withToken($token)->postJson('/api/v1/platform/auth/two-factor', ['current_password' => 'not-the-password'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'current_password_incorrect');

    enableTwoFactor($this->admin);
    forgetSignIns();

    $this->withToken($token)->postJson('/api/v1/platform/auth/two-factor', ['current_password' => TWO_FACTOR_ADMIN_PASSWORD])
        ->assertConflict()
        ->assertJsonPath('code', 'two_factor_already_enabled');
});

it('signs out every other device when two-factor authentication is turned on', function (): void {
    $thisDeviceToken = platformAdminToken($this->admin);
    $otherDeviceToken = platformAdminToken($this->admin);
    $secret = $this->withToken($thisDeviceToken)->postJson('/api/v1/platform/auth/two-factor', ['current_password' => TWO_FACTOR_ADMIN_PASSWORD])->json('data.secret');

    $this->withToken($thisDeviceToken)->postJson('/api/v1/platform/auth/two-factor/confirmation', ['code' => twoFactorCode($secret)])->assertOk();
    forgetSignIns();

    $this->withToken($otherDeviceToken)->getJson('/api/v1/platform/auth/me')->assertUnauthorized();
    forgetSignIns();
    $this->withToken($thisDeviceToken)->getJson('/api/v1/platform/auth/me')->assertOk();
});

it('answers sign-in with a challenge instead of a token, and gives the token for the right code', function (): void {
    $secret = enableTwoFactor($this->admin);

    $challenge = $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => TWO_FACTOR_ADMIN_PASSWORD, 'device_name' => 'Ada\'s laptop'])
        ->assertAccepted()
        ->assertJsonMissingPath('data.token')
        ->json('data');
    $this->assertDatabaseCount('personal_access_tokens', 0);
    expect($challenge['challenge_token'])->toHaveLength(64);

    $token = answerPlatformAdminChallenge($challenge['challenge_token'], ['code' => twoFactorCode($secret)])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->json('data.token');

    $this->withToken($token)->putJson('/api/v1/platform/auth/tokens/current')->assertOk();
    $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $this->admin->id, 'name' => 'Ada\'s laptop']);
    expect($this->admin->fresh()->last_signed_in_at)->not->toBeNull();
});

it('never accepts the same code twice, even in a new sign-in', function (): void {
    $secret = enableTwoFactor($this->admin);
    $code = twoFactorCode($secret);

    answerPlatformAdminChallenge(startPlatformAdminSignIn(), ['code' => $code])->assertOk();

    answerPlatformAdminChallenge(startPlatformAdminSignIn(), ['code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'two_factor_code_invalid');
});

it('accepts each recovery code once, whatever its case or spacing', function (): void {
    enableTwoFactor($this->admin, 'abcd-efgh-jkmn-pqrs', 'stuv-wxyz-2345-6789');

    answerPlatformAdminChallenge(startPlatformAdminSignIn(), ['recovery_code' => ' ABCD efgh-JKMN pqrs '])->assertOk();

    answerPlatformAdminChallenge(startPlatformAdminSignIn(), ['recovery_code' => 'abcd-efgh-jkmn-pqrs'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recovery_code');
    expect($this->admin->fresh()->two_factor_recovery_codes)->toHaveCount(1);
});

it('pauses sign-in after 5 wrong codes, even with the right password and code, until the lockout ends', function (): void {
    config(['api.rate_limits.login' => 100]);
    $secret = enableTwoFactor($this->admin);

    foreach (range(1, 5) as $attempt) {
        answerPlatformAdminChallenge(startPlatformAdminSignIn(), ['code' => twoFactorCode($secret, 10 + $attempt)])->assertUnprocessable();
    }

    answerPlatformAdminChallenge(startPlatformAdminSignIn(), ['code' => twoFactorCode($secret)])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'sign_in_temporarily_locked');
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('refuses a challenge that expired, was already answered, or never existed', function (): void {
    $secret = enableTwoFactor($this->admin);
    $answeredChallenge = startPlatformAdminSignIn();
    answerPlatformAdminChallenge($answeredChallenge, ['code' => twoFactorCode($secret)])->assertOk();
    $expiredChallenge = startPlatformAdminSignIn();
    $this->travel(6)->minutes();

    foreach ([$answeredChallenge, $expiredChallenge, str_repeat('x', 64)] as $challengeToken) {
        answerPlatformAdminChallenge($challengeToken, ['code' => twoFactorCode($secret, 1)])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'two_factor_challenge_invalid');
    }
});

it('refuses the code step when the account was deactivated after the password step', function (): void {
    $secret = enableTwoFactor($this->admin);
    $challengeToken = startPlatformAdminSignIn();
    $this->admin->update(['is_active' => false]);

    answerPlatformAdminChallenge($challengeToken, ['code' => twoFactorCode($secret)])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_deactivated');
});

it('needs exactly one of a code or a recovery code', function (array $answer): void {
    enableTwoFactor($this->admin);

    answerPlatformAdminChallenge(startPlatformAdminSignIn(), $answer)->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
})->with([
    'neither' => [[]],
    'both' => [['code' => '123456', 'recovery_code' => 'abcd-efgh-jkmn-pqrs']],
    'not 6 digits' => [['code' => '12345a']],
]);

it('replaces the recovery codes, so the old ones stop working', function (): void {
    enableTwoFactor($this->admin, 'abcd-efgh-jkmn-pqrs');
    $token = platformAdminToken($this->admin);

    $newRecoveryCodes = $this->withToken($token)->postJson('/api/v1/platform/auth/two-factor/recovery-codes', ['current_password' => TWO_FACTOR_ADMIN_PASSWORD])
        ->assertOk()
        ->json('data.recovery_codes');

    answerPlatformAdminChallenge(startPlatformAdminSignIn(), ['recovery_code' => 'abcd-efgh-jkmn-pqrs'])->assertUnprocessable();
    answerPlatformAdminChallenge(startPlatformAdminSignIn(), ['recovery_code' => $newRecoveryCodes[0]])->assertOk();
});

it('needs both the current password and a valid code to turn two-factor authentication off', function (array $proof, string $errorCode): void {
    $secret = enableTwoFactor($this->admin);
    $token = platformAdminToken($this->admin);
    $proof = array_map(static fn (string $value): string => $value === 'CURRENT_CODE' ? twoFactorCode($secret) : $value, $proof);

    $this->withToken($token)->deleteJson('/api/v1/platform/auth/two-factor', $proof)
        ->assertUnprocessable()
        ->assertJsonPath('code', $errorCode);

    expect($this->admin->fresh()->hasTwoFactorEnabled())->toBeTrue();
})->with([
    'password only' => [['current_password' => TWO_FACTOR_ADMIN_PASSWORD], 'validation_failed'],
    'code only' => [['code' => 'CURRENT_CODE'], 'validation_failed'],
    'wrong password' => [['current_password' => 'not-the-password', 'code' => 'CURRENT_CODE'], 'current_password_incorrect'],
    'wrong code' => [['current_password' => TWO_FACTOR_ADMIN_PASSWORD, 'code' => '000000'], 'two_factor_code_invalid'],
    'unknown recovery code' => [['current_password' => TWO_FACTOR_ADMIN_PASSWORD, 'recovery_code' => 'aaaa-bbbb-cccc-dddd'], 'two_factor_code_invalid'],
]);

it('turns two-factor authentication off with a recovery code instead of the app', function (): void {
    enableTwoFactor($this->admin, 'abcd-efgh-jkmn-pqrs');

    $this->withToken(platformAdminToken($this->admin))
        ->deleteJson('/api/v1/platform/auth/two-factor', ['current_password' => TWO_FACTOR_ADMIN_PASSWORD, 'recovery_code' => 'abcd-efgh-jkmn-pqrs'])
        ->assertNoContent();

    expect($this->admin->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('turns two-factor authentication off with the current password and code, after which the admin must set it up again', function (): void {
    $secret = enableTwoFactor($this->admin);
    $token = platformAdminToken($this->admin);

    $this->withToken($token)->deleteJson('/api/v1/platform/auth/two-factor', ['current_password' => TWO_FACTOR_ADMIN_PASSWORD, 'code' => twoFactorCode($secret)])->assertNoContent();
    forgetSignIns();

    $this->withToken($token)->putJson('/api/v1/platform/auth/tokens/current')->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
    expect($this->admin->fresh())
        ->two_factor_secret->toBeNull()
        ->two_factor_recovery_codes->toBeNull();
});

it('pauses current-password checks after 5 wrong passwords, so a stolen token can\'t guess the password', function (): void {
    enableTwoFactor($this->admin);
    $token = platformAdminToken($this->admin);

    foreach (range(1, 5) as $attempt) {
        $this->withToken($token)->postJson('/api/v1/platform/auth/two-factor/recovery-codes', ['current_password' => 'wrong-'.$attempt])->assertUnprocessable();
    }

    $this->withToken($token)->putJson('/api/v1/platform/auth/password', ['current_password' => TWO_FACTOR_ADMIN_PASSWORD, 'password' => 'a-brand-new-long-password', 'password_confirmation' => 'a-brand-new-long-password'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_incorrect_attempts');
});
