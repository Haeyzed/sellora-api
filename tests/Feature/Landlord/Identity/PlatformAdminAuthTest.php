<?php

declare(strict_types=1);

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\PasswordResetLinkNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

const PLATFORM_ADMIN_PASSWORD = 'a-long-platform-password';

beforeEach(function (): void {
    $this->admin = PlatformAdmin::factory()->create(['email' => 'ada@sellora.test', 'password' => PLATFORM_ADMIN_PASSWORD]);
});

/**
 * Signs in with the password alone, which gives a token only while two-factor authentication isn't set up yet.
 */
function signInAsPlatformAdmin(string $password = PLATFORM_ADMIN_PASSWORD): string
{
    return test()->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => $password])->json('data.token');
}

/**
 * A token for the test admin with two-factor authentication on, as after a completed two-factor sign-in.
 */
function fullySignedInPlatformAdminToken(PlatformAdmin $platformAdmin): string
{
    if (! $platformAdmin->hasTwoFactorEnabled()) {
        enableTwoFactor($platformAdmin);
    }

    return app(AccessTokenIssuer::class)->issue($platformAdmin, PlatformAdmin::GUARD, 'test')->plainTextToken;
}

it('returns a 24-hour bearer token for the right email and password, whatever the email\'s case', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00:00'));

    $this->postJson('/api/v1/platform/auth/tokens', ['email' => ' ADA@Sellora.test', 'password' => PLATFORM_ADMIN_PASSWORD, 'device_name' => 'Ada\'s laptop'])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.expires_at', '2026-10-07T09:00:00+00:00');

    $this->assertDatabaseHas('personal_access_tokens', ['tokenable_type' => 'platform_admin', 'tokenable_id' => $this->admin->id, 'name' => 'Ada\'s laptop']);
    expect($this->admin->fresh()->last_signed_in_at)->not->toBeNull();
});

it('returns the same 422 for a wrong password and an unknown email, so it reveals no accounts', function (string $email, string $password): void {
    $this->postJson('/api/v1/platform/auth/tokens', ['email' => $email, 'password' => $password])
        ->assertUnprocessable()
        ->assertExactJson(['message' => __('errors.invalid_credentials'), 'code' => 'invalid_credentials', 'errors' => []]);

    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with([
    'wrong password' => ['ada@sellora.test', 'not-the-password'],
    'unknown email' => ['nobody@sellora.test', PLATFORM_ADMIN_PASSWORD],
]);

it('returns 403 only after the right password when the account is deactivated', function (): void {
    $this->admin->update(['is_active' => false]);

    $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => PLATFORM_ADMIN_PASSWORD])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_deactivated');
    $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => 'not-the-password'])
        ->assertJsonPath('code', 'invalid_credentials');
});

it('pauses sign-in for an email after 5 wrong passwords, even with the right one, and clears the count on success', function (): void {
    config(['api.rate_limits.login' => 100]);

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => 'wrong-'.$attempt])->assertUnprocessable();
    }
    $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => PLATFORM_ADMIN_PASSWORD])->assertOk();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => 'wrong-'.$attempt])->assertUnprocessable();
    }

    $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => PLATFORM_ADMIN_PASSWORD])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'sign_in_temporarily_locked')
        ->assertJsonPath('message', 'Too many incorrect attempts. Try again in 15 minutes.');
});

it('shows the signed-in admin with their roles and permissions, and never their password', function (): void {
    $role = Role::findOrCreate('support', PlatformAdmin::GUARD);
    $role->givePermissionTo(Permission::findOrCreate('stores.view', PlatformAdmin::GUARD));
    $this->admin->assignRole($role);
    $token = signInAsPlatformAdmin();

    $this->withToken($token)->getJson('/api/v1/platform/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $this->admin->public_id)
        ->assertJsonPath('data.roles', ['support'])
        ->assertJsonPath('data.permissions', ['stores.view'])
        ->assertJsonMissingPath('data.password');
});

it('returns 401 without a token', function (): void {
    $this->getJson('/api/v1/platform/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('replaces the token on refresh, so the old one stops working', function (): void {
    $oldToken = fullySignedInPlatformAdminToken($this->admin);

    $newToken = $this->withToken($oldToken)->putJson('/api/v1/platform/auth/tokens/current')->assertOk()->json('data.token');
    forgetSignIns();

    $this->withToken($oldToken)->getJson('/api/v1/platform/auth/me')->assertUnauthorized();
    forgetSignIns();
    $this->withToken($newToken)->getJson('/api/v1/platform/auth/me')->assertOk();
});

it('signs out only the device that asks', function (): void {
    $laptopToken = signInAsPlatformAdmin();
    $phoneToken = signInAsPlatformAdmin();

    $this->withToken($laptopToken)->deleteJson('/api/v1/platform/auth/tokens/current')->assertNoContent();
    forgetSignIns();

    $this->withToken($laptopToken)->getJson('/api/v1/platform/auth/me')->assertUnauthorized();
    forgetSignIns();
    $this->withToken($phoneToken)->getJson('/api/v1/platform/auth/me')->assertOk();
});

it('changes the password and signs out every other device, keeping this one', function (): void {
    $thisDeviceToken = fullySignedInPlatformAdminToken($this->admin);
    $otherDeviceToken = fullySignedInPlatformAdminToken($this->admin);

    $this->withToken($thisDeviceToken)->putJson('/api/v1/platform/auth/password', [
        'current_password' => PLATFORM_ADMIN_PASSWORD,
        'password' => 'a-brand-new-long-password',
        'password_confirmation' => 'a-brand-new-long-password',
    ])->assertNoContent();
    forgetSignIns();

    $this->withToken($otherDeviceToken)->getJson('/api/v1/platform/auth/me')->assertUnauthorized();
    forgetSignIns();
    $this->withToken($thisDeviceToken)->getJson('/api/v1/platform/auth/me')->assertOk();
    $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => 'a-brand-new-long-password'])->assertAccepted();
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('refuses a password change with the wrong current password or a short new one', function (array $input, string $invalidField): void {
    $token = fullySignedInPlatformAdminToken($this->admin);

    $this->withToken($token)->putJson('/api/v1/platform/auth/password', $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidField);

    expect(password_verify(PLATFORM_ADMIN_PASSWORD, $this->admin->fresh()->password))->toBeTrue();
})->with([
    'wrong current password' => [['current_password' => 'not-it', 'password' => 'a-brand-new-long-password', 'password_confirmation' => 'a-brand-new-long-password'], 'current_password'],
    'new password under 12 characters' => [['current_password' => PLATFORM_ADMIN_PASSWORD, 'password' => 'short-pass', 'password_confirmation' => 'short-pass'], 'password'],
]);

it('answers 202 to every reset request but only emails active accounts, with a link into the admin app', function (): void {
    Notification::fake();
    config(['auth.passwords.platform_admins.reset_url' => 'https://admin.sellora.test/reset?token={token}&email={email}']);
    PlatformAdmin::factory()->deactivated()->create(['email' => 'former@sellora.test']);

    foreach (['ada@sellora.test', 'nobody@sellora.test', 'former@sellora.test'] as $email) {
        $this->postJson('/api/v1/platform/auth/password-reset-links', ['email' => $email])->assertAccepted();
    }

    Notification::assertSentTimes(PasswordResetLinkNotification::class, 1);
    Notification::assertSentTo($this->admin, PasswordResetLinkNotification::class, static fn (PasswordResetLinkNotification $notification): bool => str_starts_with($notification->resetUrl(), 'https://admin.sellora.test/reset?token=')
        && str_ends_with($notification->resetUrl(), '&email=ada%40sellora.test'));
});

it('resets the password from a valid link and signs the account out everywhere', function (): void {
    $existingToken = signInAsPlatformAdmin();
    $resetToken = Password::broker('platform_admins')->createToken($this->admin);

    $this->postJson('/api/v1/platform/auth/password-resets', [
        'token' => $resetToken,
        'email' => 'ada@sellora.test',
        'password' => 'a-brand-new-long-password',
        'password_confirmation' => 'a-brand-new-long-password',
    ])->assertNoContent();
    forgetSignIns();

    $this->withToken($existingToken)->getJson('/api/v1/platform/auth/me')->assertUnauthorized();
    expect(password_verify('a-brand-new-long-password', $this->admin->fresh()->password))->toBeTrue();
});

it('refuses a wrong or reused reset token with the same 422', function (): void {
    $this->postJson('/api/v1/platform/auth/password-resets', [
        'token' => 'not-a-real-token',
        'email' => 'ada@sellora.test',
        'password' => 'a-brand-new-long-password',
        'password_confirmation' => 'a-brand-new-long-password',
    ])->assertUnprocessable()->assertJsonPath('code', 'password_reset_invalid');

    expect(password_verify(PLATFORM_ADMIN_PASSWORD, $this->admin->fresh()->password))->toBeTrue();
});

/**
 * Makes new authenticator secrets predictable, so a test can type the code an app would show.
 */
function useFixedTwoFactorSecret(string $secret): void
{
    app()->instance(Google2FA::class, new class($secret) extends Google2FA
    {
        public function __construct(private readonly string $fixedSecret) {}

        public function generateSecretKey($length = 32, $prefix = ''): string
        {
            return $this->fixedSecret;
        }
    });
}

it('creates the first super admin from the command line, with two-factor authentication set up before the account exists', function (): void {
    $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    useFixedTwoFactorSecret($secret);

    $this->artisan('platform:create-admin', ['--name' => 'Grace Hopper', '--email' => 'Grace@Sellora.test', '--super-admin' => true])
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-founder-password')
        ->expectsOutputToContain('otpauth://totp/')
        ->expectsQuestion('6-digit code from the authenticator app', '000000')
        ->expectsOutputToContain('That code is incorrect')
        ->expectsQuestion('6-digit code from the authenticator app', twoFactorCode($secret))
        ->expectsOutputToContain('Recovery codes')
        ->assertSuccessful();

    $grace = PlatformAdmin::query()->where('email', 'grace@sellora.test')->firstOrFail();
    expect($grace->hasRole(PlatformRole::SuperAdmin->value))->toBeTrue()
        ->and($grace->can('anything.at.all'))->toBeTrue()
        ->and($this->admin->can('anything.at.all'))->toBeFalse()
        ->and($grace->hasTwoFactorEnabled())->toBeTrue()
        ->and($grace->two_factor_secret)->toBe($secret)
        ->and($grace->two_factor_recovery_codes)->toHaveCount(8);

    $this->postJson('/api/v1/platform/auth/tokens', ['email' => 'grace@sellora.test', 'password' => 'a-long-founder-password'])->assertAccepted();
});

it('creates no admin when the authenticator code is wrong three times', function (): void {
    useFixedTwoFactorSecret('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');

    $this->artisan('platform:create-admin', ['--name' => 'Grace Hopper', '--email' => 'grace@sellora.test'])
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-founder-password')
        ->expectsQuestion('6-digit code from the authenticator app', '000000')
        ->expectsQuestion('6-digit code from the authenticator app', 'abcdef')
        ->expectsQuestion('6-digit code from the authenticator app', '111111')
        ->assertFailed();

    $this->assertDatabaseMissing('platform_admins', ['email' => 'grace@sellora.test']);
});

it('refuses to create an admin with a short password or an email already in use', function (): void {
    $this->artisan('platform:create-admin', ['--name' => 'Copy', '--email' => 'ada@sellora.test'])
        ->expectsQuestion('Password (at least 12 characters)', 'short')
        ->assertFailed();

    $this->assertDatabaseCount('platform_admins', 1);
});
