<?php

declare(strict_types=1);

use App\Shared\Privacy\StoreExportRegistry;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 10b.1: what customer social sign-in keeps, and where. The central
 * relay's table holds routing only (state hash, store, provider, expiry);
 * everything about the customer stays in the store's database. A customer's
 * identity with a provider is found only by the provider's own user ID,
 * unique per provider in each store, at most one per provider per customer.
 * Accounts that only ever signed in with a provider have no password. Each
 * store chooses its providers among those the platform has set up.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    config(['services.google.client_id' => 'platform-google-app', 'services.apple.client_id' => null]);
    $this->store = createStore('settings-store', ['name' => 'Ada Fabrics', 'country_code' => 'NG']);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('keeps only routing in the central table: the state\'s hash, the store, the provider and when it expires', function (): void {
    expect(Schema::connection(config()->string('tenancy.database.central_connection'))->getColumnListing('social_sign_in_states'))
        ->toEqualCanonicalizing(['id', 'state_hash', 'tenant_id', 'provider', 'expires_at', 'used_at', 'created_at']);
});

it('finds an identity only by the provider\'s user ID, unique per provider in a store, and one per provider per customer', function (): void {
    $other = createStore('other-store', ['country_code' => 'NG']);
    $identity = static fn (int $customerId, string $provider, string $userId): array => [
        'public_id' => strtolower((string) Str::ulid()), 'customer_id' => $customerId, 'provider' => $provider, 'provider_user_id' => $userId,
        'email' => 'ada@example.com', 'email_verified' => true, 'created_at' => CarbonImmutable::now(),
    ];

    $this->store->run(static function () use ($identity): void {
        [$ada, $eve] = [Customer::factory()->create(), Customer::factory()->create()];
        DB::table('customer_identities')->insert($identity($ada->id, 'google', 'g-1'));

        expect(static fn () => DB::table('customer_identities')->insert($identity($eve->id, 'google', 'g-1')))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('customer_identities')->insert($identity($ada->id, 'google', 'g-2')))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('customer_identities')->insert($identity($ada->id, 'facebook', 'f-1')))->toThrow(QueryException::class);

        // The same person's Apple identity sits beside their Google one.
        DB::table('customer_identities')->insert($identity($ada->id, 'apple', 'a-1'));
        expect(CustomerIdentity::query()->where('customer_id', $ada->id)->pluck('provider')->map->value->sort()->values()->all())->toBe(['apple', 'google']);
    });

    // Another store's customer with the same Google account is someone else's identity there.
    $other->run(static function () use ($identity): void {
        DB::table('customer_identities')->insert($identity(Customer::factory()->create()->id, 'google', 'g-1'));
    });

    expect($other->run(static fn (): int => CustomerIdentity::query()->count()))->toBe(1);
});

it('ties a started link to a customer, and a sign-in to nobody', function (): void {
    $this->store->run(static function (): void {
        $customer = Customer::factory()->create();
        $attempt = static fn (string $intent, ?int $customerId): array => [
            'state_hash' => hash('sha256', Str::random(40)), 'provider' => 'google', 'intent' => $intent, 'customer_id' => $customerId,
            'code_verifier' => 'x', 'nonce' => 'n', 'expires_at' => CarbonImmutable::now()->addMinutes(10), 'created_at' => CarbonImmutable::now(),
        ];

        DB::table('social_sign_in_attempts')->insert($attempt('sign_in', null));
        DB::table('social_sign_in_attempts')->insert($attempt('link', $customer->id));

        expect(static fn () => DB::table('social_sign_in_attempts')->insert($attempt('sign_in', $customer->id)))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('social_sign_in_attempts')->insert($attempt('link', null)))->toThrow(QueryException::class);
    });
});

it('lets an account that only ever signed in with a provider have no password, and a password sign-in to it fails as a wrong one', function (): void {
    $this->store->run(static function (): void {
        Customer::factory()->create(['email' => 'ada@example.com', 'password' => null]);
        Customer::factory()->create(['email' => 'eve@example.com', 'password' => 'a-long-shopper-password']);
    });

    $noPassword = customerRequest('POST', 'auth/tokens', ['email' => 'ada@example.com', 'password' => 'a-long-shopper-password'], subdomain: 'settings-store');
    $wrongPassword = customerRequest('POST', 'auth/tokens', ['email' => 'eve@example.com', 'password' => 'not-the-password-at-all'], subdomain: 'settings-store');

    expect([$noPassword->status(), $noPassword->json('code')])->toBe([$wrongPassword->status(), $wrongPassword->json('code')])
        ->and($noPassword->json('code'))->toBe('invalid_credentials');
});

it('lets the store choose its providers among those the platform has set up', function (): void {
    expect(storeSettingsRequest('GET')->assertOk()->json('data.social_sign_in_providers'))->toBe([]);

    expect(storeSettingsRequest('PATCH', ['social_sign_in_providers' => ['google']])->assertOk()->json('data.social_sign_in_providers'))->toBe(['google']);

    // Apple has no platform credentials here; unknown and repeated providers aren't offered either.
    storeSettingsRequest('PATCH', ['social_sign_in_providers' => ['google', 'apple']])->assertUnprocessable()->assertJsonValidationErrors('social_sign_in_providers.1');
    storeSettingsRequest('PATCH', ['social_sign_in_providers' => ['facebook']])->assertUnprocessable()->assertJsonValidationErrors('social_sign_in_providers.0');
    storeSettingsRequest('PATCH', ['social_sign_in_providers' => ['google', 'google']])->assertUnprocessable();

    expect(storeSettingsRequest('PATCH', ['social_sign_in_providers' => []])->assertOk()->json('data.social_sign_in_providers'))->toBe([]);
});

it('exports identities without the provider\'s user ID, and never the sign-ins in progress', function (): void {
    $tables = app(StoreExportRegistry::class)->tables();

    expect($tables['customer_identities']->exportedColumns())->not->toContain('provider_user_id')->toContain('email', 'provider')
        ->and($tables['customer_identities']->excluded)->toHaveKey('provider_user_id')
        ->and([$tables['social_sign_in_attempts']->isExcluded(), $tables['social_sign_in_tickets']->isExcluded()])->toBe([true, true]);
});
