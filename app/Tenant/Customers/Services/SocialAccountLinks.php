<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Services;

use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Tenant\Customers\Data\SocialProfile;
use App\Tenant\Customers\Data\SocialSignInResult;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInStatus;
use App\Tenant\Customers\Enums\SocialSignInTicketKind;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Exceptions\LastSignInMethodException;
use App\Tenant\Customers\Exceptions\SocialIdentityInUseException;
use App\Tenant\Customers\Exceptions\SocialLinkEmailNotAllowedException;
use App\Tenant\Customers\Exceptions\SocialProviderAlreadyLinkedException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Customers\Models\SocialSignInTicket;
use App\Tenant\Customers\SocialLinkNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Connecting providers to accounts, and disconnecting them (Step 10b).
 *
 * - An existing account with the provider's email is connected only once its
 *   owner proves it is theirs: with the account's password, or, only when the
 *   provider is trusted and says the email is verified, with a link emailed
 *   to the account. Connecting never changes the account's email, nor marks
 *   it verified.
 * - A signed-in customer connects a provider through a sign-in started for
 *   them; a provider account already connected to someone else is refused.
 * - A provider is disconnected only while another way to sign in remains,
 *   even when two disconnections race.
 */
final readonly class SocialAccountLinks
{
    public function __construct(
        private SocialAccounts $socialAccounts,
        private SocialSignInTickets $socialSignInTickets,
        private SocialProviders $socialProviders,
        private CurrentPasswordCheck $currentPasswordCheck,
    ) {}

    /**
     * The owner proves the account is theirs with its password; the provider is connected and they are signed in.
     *
     * @throws InvalidSocialSignInException When the ticket is unknown, used or expired.
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     * @throws AccountDeactivatedException
     * @throws SocialIdentityInUseException
     */
    public function confirmWithPassword(string $ticket, string $password, string $deviceName): SocialSignInResult
    {
        return DB::transaction(function () use ($ticket, $password, $deviceName): SocialSignInResult {
            $found = $this->socialSignInTickets->find($ticket, SocialSignInTicketKind::LinkAccount);
            $account = $found === null ? null : Customer::withTrashed()->lockForUpdate()->find($found->customer_id);

            if ($found === null || $account === null) {
                throw new InvalidSocialSignInException;
            }

            $this->currentPasswordCheck->ensureCorrect($account, $password);

            return $this->connectFromTicket($found, $account, $deviceName);
        });
    }

    /**
     * Emails the account a one-time link to confirm connecting the provider: only when the provider is trusted and says the email is verified.
     *
     * @throws InvalidSocialSignInException
     * @throws SocialLinkEmailNotAllowedException
     */
    public function sendEmailLink(string $ticket): void
    {
        DB::transaction(function () use ($ticket): void {
            $found = $this->socialSignInTickets->find($ticket, SocialSignInTicketKind::LinkAccount);
            $account = $found === null ? null : Customer::query()->find($found->customer_id);

            if ($found === null || $account === null || $account->email === null) {
                throw new InvalidSocialSignInException;
            }

            if (! $this->socialSignInTickets->profile($found)->hasTrustedEmail() || $found->email !== $account->email) {
                throw new SocialLinkEmailNotAllowedException;
            }

            $token = Str::random(64);
            $found->forceFill([
                'email_link_token_hash' => SocialSignInTicket::hash($token),
                'email_link_sent_at' => CarbonImmutable::now(),
                'expires_at' => CarbonImmutable::now()->addMinutes(CustomerEmailTokenPurpose::SocialLink->expiresInMinutes()),
            ])->save();

            $notification = SocialLinkNotification::forToken($found->provider, $token);

            if ($account->locale !== null) {
                $notification->locale($account->locale);
            }

            Notification::route('mail', $account->email)->notify($notification);
        });
    }

    /**
     * The owner confirms from the emailed link, perhaps on another device: the provider is connected and they are signed in there.
     *
     * @throws InvalidSocialSignInException When the link is unknown, used or expired, or the account's email changed since.
     * @throws AccountDeactivatedException
     * @throws SocialIdentityInUseException
     */
    public function confirmWithEmailLink(string $token, string $deviceName): SocialSignInResult
    {
        return DB::transaction(function () use ($token, $deviceName): SocialSignInResult {
            $found = SocialSignInTicket::query()
                ->where('email_link_token_hash', SocialSignInTicket::hash($token))
                ->where('kind', SocialSignInTicketKind::LinkAccount->value)
                ->where('expires_at', '>', CarbonImmutable::now())
                ->lockForUpdate()
                ->first();
            $account = $found === null ? null : Customer::withTrashed()->lockForUpdate()->find($found->customer_id);

            if ($found === null || $account === null || $account->email !== $found->email || ! $this->socialSignInTickets->profile($found)->hasTrustedEmail()) {
                throw new InvalidSocialSignInException;
            }

            return $this->connectFromTicket($found, $account, $deviceName);
        });
    }

    /**
     * Connects the provider to the signed-in customer, with what it said about them.
     *
     * @throws SocialIdentityInUseException When the provider account is someone else's here.
     * @throws SocialProviderAlreadyLinkedException When the customer already has another account of this provider connected.
     */
    public function connectToSignedIn(Customer $customer, SocialProfile $profile): CustomerIdentity
    {
        return DB::transaction(function () use ($customer, $profile): CustomerIdentity {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
            $existing = CustomerIdentity::query()->where('provider', $profile->provider->value)->where('provider_user_id', $profile->providerUserId)->first();

            if ($existing !== null) {
                return $existing->customer_id === $customer->id ? $existing : throw new SocialIdentityInUseException;
            }

            if (CustomerIdentity::query()->where('customer_id', $customer->id)->where('provider', $profile->provider->value)->exists()) {
                throw new SocialProviderAlreadyLinkedException;
            }

            return $this->socialAccounts->connect($customer, $profile);
        });
    }

    /**
     * Disconnects a provider from the customer, only while another way to sign in remains: a password, or another provider the store still offers.
     *
     * @param  string|null  $currentPassword  Needed when the account has a password.
     *
     * @throws LastSignInMethodException
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function disconnect(Customer $customer, CustomerIdentity $identity, ?string $currentPassword): void
    {
        DB::transaction(function () use ($customer, $identity, $currentPassword): void {
            // Every disconnection of this customer's waits here, so two never both see the other's provider still there.
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($locked->password !== null) {
                $this->currentPasswordCheck->ensureCorrect($locked, (string) $currentPassword);
            }

            $offered = array_map(static fn (SocialProvider $provider): string => $provider->value, $this->socialProviders->offered());
            $othersRemaining = CustomerIdentity::query()
                ->where('customer_id', $locked->id)
                ->whereKeyNot($identity->id)
                ->whereIn('provider', $offered)
                ->exists();

            if ($locked->password === null && ! $othersRemaining) {
                throw new LastSignInMethodException;
            }

            CustomerIdentity::query()->whereKey($identity->id)->where('customer_id', $locked->id)->delete();
        });
    }

    private function connectFromTicket(SocialSignInTicket $ticket, Customer $account, string $deviceName): SocialSignInResult
    {
        $profile = $this->socialSignInTickets->profile($ticket);
        $taken = CustomerIdentity::query()->where('provider', $profile->provider->value)->where('provider_user_id', $profile->providerUserId)->first();

        if ($taken !== null && $taken->customer_id !== $account->id) {
            throw new SocialIdentityInUseException;
        }

        if ($taken === null && CustomerIdentity::query()->where('customer_id', $account->id)->where('provider', $profile->provider->value)->exists()) {
            throw new SocialProviderAlreadyLinkedException;
        }

        $token = $this->socialAccounts->tokenFor($account, $deviceName);

        if ($taken === null) {
            $this->socialAccounts->connect($account, $profile);
        }

        $ticket->delete();

        return new SocialSignInResult(SocialSignInStatus::SignedIn, $token);
    }
}
