<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Customers\CustomerEmailLinkNotification;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For CustomerAuthTest.php
|--------------------------------------------------------------------------
*/

/**
 * @return array<string, string>
 */
function newCustomerDetails(string $email = 'ada@example.com'): array
{
    return [
        'name' => 'Ada Lovelace',
        'email' => $email,
        'password' => 'a-long-shopper-password',
        'password_confirmation' => 'a-long-shopper-password',
        'device_name' => 'Ada\'s phone',
    ];
}

/*
|--------------------------------------------------------------------------
| For CustomerAccountTest.php
|--------------------------------------------------------------------------
*/

/**
 * A token for the customer at a store, as signing in would issue.
 */
function customerTokenFor(Customer $customer, string $subdomain = 'first-store'): string
{
    $store = Tenant::query()
        ->whereHas('domains', static fn ($query) => $query->where('domain', $subdomain.'.'.config('platform.domain')))
        ->firstOrFail();

    return $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($customer, Customer::GUARD, 'test')->plainTextToken);
}

/**
 * Sends a request to a store's customer API, under /api/v1/customer/, with a customer's token or none.
 *
 * @param  array<string, mixed>  $data
 */
function customerRequest(string $method, string $path, array $data = [], Customer|string|null $as = null, string $subdomain = 'first-store'): TestResponse
{
    forgetSignIns();
    $token = $as instanceof Customer ? customerTokenFor($as, $subdomain) : $as;
    $request = $token === null ? test() : test()->withToken($token);

    $response = $request->json($method, storeUrl($subdomain, '/api/v1/customer/'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * The token in the last link of this purpose emailed to an address (Notification::fake()).
 */
function emailedCustomerToken(CustomerEmailTokenPurpose $purpose, string $email): string
{
    $url = null;

    Notification::assertSentOnDemand(CustomerEmailLinkNotification::class, static function (CustomerEmailLinkNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($purpose, $email, &$url): bool {
        if ($notifiable->routeNotificationFor('mail') !== $email || $notification->purpose() !== $purpose) {
            return false;
        }

        $url = $notification->url();

        return true;
    });

    return emailedLinkFragment((string) $url)['token'] ?? throw new LogicException('The emailed link has no token.');
}

/*
|--------------------------------------------------------------------------
| For CustomerAddressTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lagosAddress(array $overrides = []): array
{
    return [...['recipient_name' => 'Ada Lovelace', 'line1' => '12 Admiralty Way', 'city' => 'Lekki', 'state' => 'LA', 'postal_code' => '106104', 'country' => 'NG'], ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tokyoAddress(array $overrides = []): array
{
    return [...['recipient_name' => 'Ada Lovelace', 'line1' => '2-1-1 Nihonbashi', 'state' => '13', 'postal_code' => '103-0027', 'country' => 'JP'], ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function londonAddress(array $overrides = []): array
{
    return [...['recipient_name' => 'Ada Lovelace', 'line1' => '10 Downing Street', 'city' => 'London', 'postal_code' => 'sw1a 1aa', 'country' => 'gb'], ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function californiaAddress(array $overrides = []): array
{
    return [...['recipient_name' => 'Ada Lovelace', 'line1' => '1600 Amphitheatre Parkway', 'city' => 'Mountain View', 'state' => 'CA', 'postal_code' => '94043', 'country' => 'US'], ...$overrides];
}

/**
 * An address in a country whose states the address data doesn't list.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function berlinAddress(array $overrides = []): array
{
    return [...['recipient_name' => 'Ada Lovelace', 'line1' => 'Unter den Linden 1', 'city' => 'Berlin', 'postal_code' => '10117', 'country' => 'DE'], ...$overrides];
}

/*
|--------------------------------------------------------------------------
| For StaffCustomerTest.php
|--------------------------------------------------------------------------
*/

enum StaffMemberKind
{
    case Viewer;
    case Outsider;
}

/**
 * Sends a request to a store's staff customer API, under /api/v1/staff/customers, as a staff member or with a token.
 *
 * @param  array<string, mixed>  $data
 */
function staffCustomerRequest(string $method, string $path, array $data = [], StaffMember|string|null $as = null, string $subdomain = 'first-store'): TestResponse
{
    forgetSignIns();
    $token = $as instanceof StaffMember ? staffTokenFor($as, $subdomain) : $as;
    $request = $token === null ? test() : test()->withToken($token);

    $response = $request->json($method, storeUrl($subdomain, '/api/v1/staff/customers'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For CustomerPrivacyTest.php
|--------------------------------------------------------------------------
*/

/**
 * Where an export's file is, on the first store's private disk.
 */
function exportFileOf(string $exportId): string
{
    $path = test()->store->run(static fn (): ?string => App\Tenant\Customers\Models\CustomerDataExport::query()->where('public_id', $exportId)->value('path'));

    return is_string($path) ? $path : throw new LogicException('The export has no file.');
}

/**
 * What is left in the first store that held the test customer's personal data, and the contacts of the consent events left.
 *
 * @return array{addresses: int, notes: int, exports: int, tokens: int, consents: list<string>}
 */
function remainingPersonalRecords(): array
{
    return test()->store->run(static fn (): array => [
        'addresses' => App\Tenant\Customers\Models\CustomerAddress::query()->count(),
        'notes' => App\Tenant\Customers\Models\CustomerNote::query()->count(),
        'exports' => App\Tenant\Customers\Models\CustomerDataExport::query()->count(),
        'tokens' => Illuminate\Support\Facades\DB::table('personal_access_tokens')->where('tokenable_type', 'customer')->count(),
        'consents' => array_values(App\Tenant\Customers\Models\MarketingConsent::query()->orderBy('id')->pluck('contact')->all()),
    ]);
}

/**
 * A customer of the first store, created directly.
 *
 * @param  array<string, mixed>  $attributes
 */
function firstStoreCustomer(array $attributes = []): Customer
{
    return test()->store->run(static fn (): Customer => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password', ...$attributes]));
}

/*
|--------------------------------------------------------------------------
| For CustomerGroupsTest.php
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to a store's customer groups API, under /api/v1/staff/customer-groups, as a staff member or with a token.
 *
 * @param  array<string, mixed>  $data
 */
function customerGroupRequest(string $method, string $path, array $data = [], StaffMember|string|null $as = null, string $subdomain = 'first-store'): TestResponse
{
    forgetSignIns();
    $token = $as instanceof StaffMember ? staffTokenFor($as, $subdomain) : $as;
    $request = $token === null ? test() : test()->withToken($token);

    $response = $request->json($method, storeUrl($subdomain, '/api/v1/staff/customer-groups'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}
