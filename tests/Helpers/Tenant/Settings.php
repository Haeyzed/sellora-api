<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Foundation\Auth\User;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For StaffTwoFactorRequirementTest.php
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to the store's API as the given account.
 *
 * @param  array<string, mixed>  $data
 */
function securedStoreRequest(string $method, string $path, User $as, string $guard = StaffMember::GUARD, array $data = []): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as, $guard, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('secured-store', '/api/v1'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @param  array<string, mixed>  $data
 */
function requireStaffTwoFactor(bool $required, array $data = [], ?StaffMember $as = null): TestResponse
{
    return securedStoreRequest('PUT', '/staff/store/settings/staff-two-factor', $as ?? test()->owner, data: ['required' => $required, 'current_password' => TWO_FACTOR_OWNER_PASSWORD, ...$data]);
}

function staffTwoFactorIsRequired(): bool
{
    return test()->store->run(static fn (): bool => app(FindStoreSettings::class)->handle()->require_staff_two_factor);
}

/*
|--------------------------------------------------------------------------
| For StoreLanguageTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  list<string>  $enabledLocales
 */
function publishIn(Tenant $store, string $defaultLocale, array $enabledLocales): void
{
    $store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => $defaultLocale, 'enabled_locales' => $enabledLocales]));
}

function sampleLanguage(string $subdomain, ?string $acceptLanguage = null): TestResponse
{
    $request = $acceptLanguage === null ? test() : test()->withHeader('Accept-Language', $acceptLanguage);
    $response = $request->getJson(storeUrl($subdomain, '/api/v1/sample-language'));
    tenancy()->end();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For StoreSettingsTest.php
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to the store's settings API as the given staff member.
 *
 * @param  array<string, mixed>  $data
 */
function storeSettingsRequest(string $method, array $data = [], ?StaffMember $as = null, string $subdomain = 'settings-store'): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/store/settings'), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function currentStoreSettings(): StoreSettings
{
    return test()->store->run(static fn (): StoreSettings => app(FindStoreSettings::class)->handle());
}

/*
|--------------------------------------------------------------------------
| For StoreTranslationsTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  list<string>  $enabledLocales
 */
function useLanguages(Tenant $store, string $defaultLocale, array $enabledLocales): void
{
    $store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => $defaultLocale, 'enabled_locales' => $enabledLocales]));
}
