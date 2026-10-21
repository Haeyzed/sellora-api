<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Services;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\IssuedAccessToken;
use App\Tenant\Customers\Data\SocialProfile;
use App\Tenant\Customers\Data\SocialSignInResult;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Enums\SocialSignInStatus;
use App\Tenant\Customers\Enums\SocialSignInTicketKind;
use App\Tenant\Customers\Events\CustomerEmailVerified;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Who a provider's answer signs in (Step 10b): found only by the provider's user ID, never by email.
 *
 * - An identity already connected signs its customer in.
 * - An account with the provider's email is never connected automatically:
 *   its owner proves it is theirs first (SocialAccountLinks), so nobody takes
 *   over an account by making a provider account with someone else's email.
 * - Otherwise a new account is made, verified only when the provider is
 *   trusted and says the email is verified (then guest orders placed with
 *   it join the account; a private relay address never matches any).
 * - Without an email, the customer is asked for one.
 */
final readonly class SocialAccounts
{
    public function __construct(
        private AccessTokenIssuer $accessTokenIssuer,
        private CustomerEmailLinks $customerEmailLinks,
        private SocialSignInTickets $socialSignInTickets,
    ) {}

    /**
     * @throws AccountDeactivatedException When the identity's account, or the account with the provider's email, can't be used.
     */
    public function signIn(SocialProfile $profile, string $deviceName): SocialSignInResult
    {
        try {
            return DB::transaction(fn (): SocialSignInResult => $this->decide($profile, $deviceName));
        } catch (UniqueConstraintViolationException) {
            // Another sign-in made the account or identity at the same moment: decide again, now that it exists.
            return DB::transaction(fn (): SocialSignInResult => $this->decide($profile, $deviceName));
        }
    }

    /**
     * Finishes a sign-in whose provider gave no email, with the email the customer gave. It starts unverified: nobody proved it yet. An account that already has it is never connected automatically: its owner proves it with its password.
     *
     * @param  string  $email  Trimmed and lower-cased.
     *
     * @throws InvalidSocialSignInException When the ticket is unknown, used or expired.
     * @throws AccountDeactivatedException
     */
    public function completeWithEmail(string $ticket, string $email, string $deviceName): SocialSignInResult
    {
        return DB::transaction(function () use ($ticket, $email, $deviceName): SocialSignInResult {
            $found = $this->socialSignInTickets->find($ticket, SocialSignInTicketKind::EmailRequired);

            if ($found === null) {
                throw new InvalidSocialSignInException;
            }

            $given = $this->socialSignInTickets->profile($found);
            $profile = new SocialProfile($given->provider, $given->providerUserId, $email, false, false, $given->name);
            $found->delete();

            return $this->decide($profile, $deviceName);
        });
    }

    /**
     * Makes a new account for the person, with this email when the provider gave none (unverified: nobody proved it yet).
     */
    public function createAccount(SocialProfile $profile, string $email, string $deviceName): SocialSignInResult
    {
        $verified = $profile->email === $email && $profile->hasTrustedEmail();

        $customer = Customer::query()->create([
            'name' => $this->nameFor($profile, $email),
            'email' => $email,
            'password' => null,
            'is_active' => true,
        ]);

        if ($verified) {
            $customer->forceFill(['email_verified_at' => CarbonImmutable::now()])->save();

            // Guest orders placed with the email join the account; a private relay address is never one a guest ordered with.
            if (! $profile->isPrivateRelay) {
                CustomerEmailVerified::dispatch($customer);
            }
        } else {
            $this->customerEmailLinks->send($customer, CustomerEmailTokenPurpose::Verification, $email);
        }

        $this->connect($customer, $profile);

        return new SocialSignInResult(SocialSignInStatus::SignedIn, $this->tokenFor($customer, $deviceName), newAccount: true);
    }

    /**
     * Connects the provider to the customer's account. Never changes the account's email, nor marks it verified.
     */
    public function connect(Customer $customer, SocialProfile $profile): CustomerIdentity
    {
        $identity = new CustomerIdentity;
        $identity->forceFill([
            'customer_id' => $customer->id,
            'provider' => $profile->provider,
            'provider_user_id' => $profile->providerUserId,
            'email' => $profile->email,
            'email_verified' => $profile->emailVerified,
            'is_private_relay' => $profile->isPrivateRelay,
            'last_used_at' => CarbonImmutable::now(),
            'created_at' => CarbonImmutable::now(),
        ])->save();

        return $identity;
    }

    /**
     * A token for the customer on this device, once their account is known to be usable.
     *
     * @throws AccountDeactivatedException When the account is deactivated, in the trash or erased.
     */
    public function tokenFor(Customer $customer, string $deviceName): IssuedAccessToken
    {
        if (! $customer->is_active || $customer->trashed() || $customer->isErased()) {
            throw new AccountDeactivatedException;
        }

        $customer->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();

        return $this->accessTokenIssuer->issue($customer, Customer::GUARD, $deviceName);
    }

    /**
     * How the owner of an existing account can prove it is theirs: their password when it has one, and a link emailed to it only when the provider is trusted and says the email is verified.
     *
     * @return list<string>
     */
    public function proofMethods(Customer $account, SocialProfile $profile): array
    {
        return array_values(array_filter([
            $account->password !== null ? 'password' : null,
            $profile->hasTrustedEmail() ? 'email_link' : null,
        ]));
    }

    private function decide(SocialProfile $profile, string $deviceName): SocialSignInResult
    {
        $identity = CustomerIdentity::query()
            ->where('provider', $profile->provider->value)
            ->where('provider_user_id', $profile->providerUserId)
            ->lockForUpdate()
            ->first();

        if ($identity !== null) {
            $customer = Customer::withTrashed()->findOrFail($identity->customer_id);
            $identity->forceFill(['last_used_at' => CarbonImmutable::now(), 'email' => $profile->email, 'email_verified' => $profile->emailVerified, 'is_private_relay' => $profile->isPrivateRelay])->save();

            return new SocialSignInResult(SocialSignInStatus::SignedIn, $this->tokenFor($customer, $deviceName));
        }

        if ($profile->email === null) {
            return new SocialSignInResult(SocialSignInStatus::EmailRequired, ticket: $this->socialSignInTickets->issue(SocialSignInTicketKind::EmailRequired, $profile));
        }

        $existing = Customer::withTrashed()->where('email', $profile->email)->first();

        if ($existing !== null) {
            if (! $existing->is_active || $existing->trashed()) {
                throw new AccountDeactivatedException;
            }

            return new SocialSignInResult(
                SocialSignInStatus::LinkAccount,
                ticket: $this->socialSignInTickets->issue(SocialSignInTicketKind::LinkAccount, $profile, $existing),
                proofMethods: $this->proofMethods($existing, $profile),
            );
        }

        return $this->createAccount($profile, $profile->email, $deviceName);
    }

    private function nameFor(SocialProfile $profile, string $email): string
    {
        $name = trim((string) $profile->name);
        $localPart = strstr($email, '@', true);

        return mb_substr($name !== '' ? $name : ($localPart === false || $localPart === '' ? $email : $localPart), 0, 120);
    }
}
