<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\CredentialCheck;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\IssuedAccessToken;
use App\Tenant\Customers\Models\Customer;
use Carbon\CarbonImmutable;

/**
 * Signs a shopper in to the store's storefront with their email and password.
 */
final readonly class SignInCustomer
{
    public function __construct(
        private CredentialCheck $credentialCheck,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @param  string  $email  Already trimmed and lower-cased.
     *
     * @throws InvalidCredentialsException When the email or password is wrong.
     * @throws SignInTemporarilyLockedException After too many wrong attempts for this email in this store.
     * @throws AccountDeactivatedException When the password is right but the account is deactivated.
     */
    public function handle(string $email, string $password, string $deviceName): IssuedAccessToken
    {
        $customer = $this->credentialCheck->ensureValid(
            Customer::GUARD,
            $email,
            Customer::query()->where('email', $email)->first(),
            $password,
        );

        if (! $customer->is_active) {
            throw new AccountDeactivatedException;
        }

        $customer->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();

        return $this->accessTokenIssuer->issue($customer, Customer::GUARD, $deviceName);
    }
}
