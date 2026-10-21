<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Services;

use App\Tenant\Customers\Data\ErasureConfirmation;
use App\Tenant\Customers\Data\SocialProfile;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Exceptions\ErasureConfirmedByPasswordException;
use App\Tenant\Customers\Exceptions\InvalidErasureConfirmationException;
use App\Tenant\Customers\Exceptions\SocialIdentityNotConnectedException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerEmailToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * How a customer without a password confirms erasing their account (section 3.3): asking for it is never harder than signing in.
 *
 * They get a one-time token, emailed to their account address, or given after
 * a fresh sign-in with a provider connected to their account. Only its hash is
 * kept, with their other one-time links; a new one replaces the last. Being
 * signed in alone is never enough, so a stolen access token can't erase an
 * account. Customers with a password confirm with it instead.
 */
final readonly class ErasureConfirmations
{
    public function __construct(private CustomerEmailLinks $customerEmailLinks) {}

    /**
     * Emails a link with a new token to the customer's account address.
     *
     * @throws ErasureConfirmedByPasswordException When the customer has a password.
     */
    public function sendEmail(Customer $customer): void
    {
        $this->ensureWithoutPassword($customer);

        $this->customerEmailLinks->send($customer, CustomerEmailTokenPurpose::Erasure, (string) $customer->email);
    }

    /**
     * After a fresh sign-in with a provider, returns a new token if that provider account is connected to the customer's.
     *
     * @throws ErasureConfirmedByPasswordException When the customer has a password.
     * @throws SocialIdentityNotConnectedException When the provider account isn't one of theirs.
     */
    public function afterReauthentication(Customer $customer, SocialProfile $profile): ErasureConfirmation
    {
        $this->ensureWithoutPassword($customer);

        $connected = $customer->identities()
            ->where('provider', $profile->provider->value)
            ->where('provider_user_id', $profile->providerUserId)
            ->exists();

        if (! $connected) {
            throw new SocialIdentityNotConnectedException;
        }

        $token = Str::random(64);
        $expiresAt = CarbonImmutable::now()->addMinutes(config()->integer('customers.erasure.reauthentication_expire_minutes'));

        CustomerEmailToken::query()->updateOrCreate(
            ['customer_id' => $customer->id, 'purpose' => CustomerEmailTokenPurpose::Erasure->value],
            [
                'email' => (string) $customer->email,
                'token_hash' => CustomerEmailToken::hash($token),
                'expires_at' => $expiresAt,
                'created_at' => CarbonImmutable::now(),
            ],
        );

        return new ErasureConfirmation($token, $expiresAt);
    }

    /**
     * Uses up the customer's token. Call inside the transaction that erases them, so it is used once even when the request is sent twice.
     *
     * @throws InvalidErasureConfirmationException When it isn't theirs, was used, or has expired.
     */
    public function consume(Customer $customer, string $token): void
    {
        $confirmation = CustomerEmailToken::query()
            ->usable($token, CustomerEmailTokenPurpose::Erasure)
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->first();

        if ($confirmation === null) {
            throw new InvalidErasureConfirmationException;
        }

        $confirmation->delete();
    }

    /**
     * @throws ErasureConfirmedByPasswordException
     */
    private function ensureWithoutPassword(Customer $customer): void
    {
        if ($customer->password !== null) {
            throw new ErasureConfirmedByPasswordException;
        }
    }
}
