<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\IssuedAccessToken;
use App\Tenant\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Creates a shopper's account at the store and signs them in on the device they signed up from.
 */
final readonly class RegisterCustomer
{
    public function __construct(private AccessTokenIssuer $accessTokenIssuer) {}

    /**
     * @param  string  $email  Already trimmed and lower-cased.
     *
     * @throws ValidationException When another account at this store took the email at the same moment.
     */
    public function handle(string $name, string $email, string $password, string $deviceName): IssuedAccessToken
    {
        try {
            $customer = Customer::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => [__('validation.unique', ['attribute' => 'email'])]]);
        }

        $customer->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();

        return $this->accessTokenIssuer->issue($customer, Customer::GUARD, $deviceName);
    }
}
