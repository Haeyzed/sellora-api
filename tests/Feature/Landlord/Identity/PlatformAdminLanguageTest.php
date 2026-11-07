<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\PasswordResetLinkNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(LazilyRefreshDatabase::class);

/*
 * Step 20a: a platform admin's preferred language, for what the platform
 * sends them outside a request (platform alerts from the queue or the
 * scheduler). They choose one of the languages the dashboards are
 * translated into, or null for the platform's default; their profile shows
 * it, and messages sent to them use it.
 */

it('lets a platform admin choose their language, shows it on their profile, and forgets it on null', function (): void {
    $admin = tap(PlatformAdmin::factory()->create(), enableTwoFactor(...));

    platformTeam('PUT', 'auth/language', ['locale' => 'fr'], as: $admin)->assertNoContent();
    platformTeam('GET', 'auth/me', as: $admin)->assertOk()->assertJsonPath('data.locale', 'fr');

    platformTeam('PUT', 'auth/language', ['locale' => null], as: $admin)->assertNoContent();
    platformTeam('GET', 'auth/me', as: $admin)->assertOk()->assertJsonPath('data.locale', null);
});

it('refuses a language the dashboards are not translated into, and an empty request', function (): void {
    $admin = tap(PlatformAdmin::factory()->create(), enableTwoFactor(...));

    platformTeam('PUT', 'auth/language', ['locale' => 'xx'], as: $admin)->assertUnprocessable()->assertJsonValidationErrors('locale');
    platformTeam('PUT', 'auth/language', ['locale' => 'fr-CA'], as: $admin)->assertUnprocessable()->assertJsonValidationErrors('locale');
    platformTeam('PUT', 'auth/language', [], as: $admin)->assertUnprocessable()->assertJsonValidationErrors('locale');
    platformTeam('PUT', 'auth/language', ['locale' => 'fr'])->assertUnauthorized();

    expect($admin->fresh()?->locale)->toBeNull();
});

it('is closed to a platform admin until they set up two-factor authentication', function (): void {
    $admin = PlatformAdmin::factory()->create();

    platformTeam('PUT', 'auth/language', ['locale' => 'fr'], as: $admin)->assertForbidden();
});

it('writes what is sent to them in the language they chose', function (): void {
    Notification::fake();
    $admin = tap(PlatformAdmin::factory()->create(), enableTwoFactor(...));
    platformTeam('PUT', 'auth/language', ['locale' => 'de'], as: $admin)->assertNoContent();

    $admin->fresh()?->sendPasswordResetNotification('token');

    Notification::assertSentTo($admin, PasswordResetLinkNotification::class, static fn (PasswordResetLinkNotification $notification, array $channels, PlatformAdmin $notifiable, ?string $locale): bool => $locale === 'de');
});
