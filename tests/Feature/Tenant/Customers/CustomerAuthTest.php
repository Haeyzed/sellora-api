<?php

declare(strict_types=1);

use App\Tenant\Customers\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(DatabaseTruncation::class);

beforeEach(function (): void {
    $this->store = createStore('first-store');
});

afterEach(function (): void {
    deleteAllStores();
});

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

it('creates a customer account at the store and returns a token that works straight away', function (): void {
    $token = $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), newCustomerDetails('Ada@Example.com '))
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->json('data.token');
    tenancy()->end();

    $this->withToken($token)->getJson(storeUrl('first-store', '/api/v1/customer/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Ada Lovelace')
        ->assertJsonPath('data.email', 'ada@example.com');
});

it('refuses a second account with the same email, whatever its case', function (): void {
    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), newCustomerDetails())->assertCreated();
    tenancy()->end();

    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), newCustomerDetails('ADA@example.com'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($this->store->run(static fn (): int => Customer::query()->count()))->toBe(1);
});

it('lets the same person open separate accounts at two stores', function (): void {
    createStore('second-store');

    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), newCustomerDetails())->assertCreated();
    tenancy()->end();
    $this->postJson(storeUrl('second-store', '/api/v1/customer/auth/accounts'), newCustomerDetails())->assertCreated();
});

it('refuses a password under 12 characters or one that doesn\'t match its confirmation', function (array $override): void {
    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), [...newCustomerDetails(), ...$override])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
})->with([
    'too short' => [['password' => 'short-pass', 'password_confirmation' => 'short-pass']],
    'not confirmed' => [['password_confirmation' => 'a-different-long-password']],
]);

it('refuses a password found in a known data breach, sending only the start of its hash', function (): void {
    $this->markAsBreached('a-long-shopper-password');

    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), newCustomerDetails())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://api.pwnedpasswords.com/range/'.mb_substr(mb_strtoupper(sha1('a-long-shopper-password')), 0, 5));
});

it('accepts the password when the breached-password service is down, so an outage never blocks sign-up', function (): void {
    $this->markAsBreached('a-long-shopper-password');
    $this->takeBreachedPasswordServiceDown();

    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), newCustomerDetails())->assertCreated();
});

it('signs a customer in, and refuses a deactivated account only after the right password', function (): void {
    $this->store->run(static fn (): Customer => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password']));

    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/tokens'), ['email' => 'ada@example.com', 'password' => 'a-long-shopper-password'])->assertOk();
    tenancy()->end();
    $this->store->run(static fn (): bool => Customer::query()->update(['is_active' => false]) === 1);

    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/tokens'), ['email' => 'ada@example.com', 'password' => 'a-long-shopper-password'])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_deactivated');
});
