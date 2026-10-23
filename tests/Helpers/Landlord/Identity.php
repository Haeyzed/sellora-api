<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\PlatformAdminInvitationNotification;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| For PlatformAdminAuthTest.php
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| For PlatformAdminTwoFactorTest.php
|--------------------------------------------------------------------------
*/

function startPlatformAdminSignIn(string $deviceName = 'Ada\'s laptop'): string
{
    return test()->postJson('/api/v1/platform/auth/tokens', ['email' => 'ada@sellora.test', 'password' => TWO_FACTOR_ADMIN_PASSWORD, 'device_name' => $deviceName])
        ->assertAccepted()
        ->json('data.challenge_token');
}

function answerPlatformAdminChallenge(string $challengeToken, array $answer): TestResponse
{
    return test()->postJson('/api/v1/platform/auth/two-factor-challenges', ['challenge_token' => $challengeToken, ...$answer]);
}

function platformAdminToken(PlatformAdmin $platformAdmin): string
{
    return app(AccessTokenIssuer::class)->issue($platformAdmin, PlatformAdmin::GUARD, 'test')->plainTextToken;
}

/*
|--------------------------------------------------------------------------
| For PlatformTeamTest.php
|--------------------------------------------------------------------------
*/

function platformTeamMember(string $name, ?string $roleName = null): PlatformAdmin
{
    $platformAdmin = PlatformAdmin::factory()->create(['name' => $name, 'password' => PLATFORM_TEAM_PASSWORD]);
    enableTwoFactor($platformAdmin);

    if ($roleName !== null) {
        $platformAdmin->assignRole(Role::findOrCreate($roleName, PlatformAdmin::GUARD));
    }

    return $platformAdmin;
}

/**
 * @param  array<string, mixed>  $data
 */
function platformTeam(string $method, string $path, array $data = [], ?PlatformAdmin $as = null): TestResponse
{
    forgetSignIns();
    $request = test();

    if ($as !== null) {
        $request = $request->withToken(app(AccessTokenIssuer::class)->issue($as, PlatformAdmin::GUARD, 'test')->plainTextToken);
    }

    $response = $request->json($method, centralUrl('/api/v1/platform/'.$path), $data);
    forgetSignIns();

    return $response;
}

function lastPlatformInvitationToken(string $email): string
{
    $tokens = [];

    Notification::assertSentOnDemand(PlatformAdminInvitationNotification::class, static function (PlatformAdminInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$tokens): bool {
        if ($notifiable->routes['mail'] === $email) {
            $tokens[] = emailedLinkFragment($notification->acceptUrl())['token'] ?? '';
        }

        return true;
    });

    return $tokens === [] ? throw new RuntimeException("No invitation was sent to {$email}.") : end($tokens);
}
