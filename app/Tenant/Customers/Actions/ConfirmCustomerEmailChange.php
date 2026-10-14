<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Customers\CustomerEmailChangedNotification;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Exceptions\InvalidCustomerEmailLinkException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerEmailToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Moves a customer's account to the new email from the link sent there, used while signed in to that same account.
 *
 * The new address counts as verified, since the link reached it. Every other
 * device is signed out, pending links and password resets for the old
 * address stop working, and the old address is told.
 */
final readonly class ConfirmCustomerEmailChange
{
    public function __construct(private AccessTokenIssuer $accessTokenIssuer) {}

    /**
     * @throws InvalidCustomerEmailLinkException When the link is unknown, expired, used, or for another account.
     * @throws ValidationException When another account took the new email in the meantime.
     */
    public function handle(Customer $customer, string $token): Customer
    {
        $oldEmail = DB::transaction(function () use ($customer, $token): string {
            $emailToken = CustomerEmailToken::query()->usable($token, CustomerEmailTokenPurpose::EmailChange)->lockForUpdate()->first();

            if ($emailToken === null || $emailToken->customer_id !== $customer->id) {
                throw new InvalidCustomerEmailLinkException;
            }

            $customer->newQuery()->lockForUpdate()->findOrFail($customer->id);
            // An erased customer has no email, and no sign-in to confirm with.
            $oldEmail = $customer->email ?? throw new InvalidCustomerEmailLinkException;

            try {
                $customer->forceFill(['email' => $emailToken->email, 'email_verified_at' => CarbonImmutable::now()])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['token' => [__('validation.unique', ['attribute' => 'email'])]]);
            }

            $customer->emailTokens()->delete();
            DB::table(config()->string('auth.passwords.'.Customer::PASSWORD_BROKER.'.table'))->where('email', $oldEmail)->delete();
            $this->accessTokenIssuer->revokeAllExceptCurrent($customer);

            return $oldEmail;
        });

        Notification::route('mail', $oldEmail)->notify(new CustomerEmailChangedNotification($customer->locale));

        return $customer;
    }
}
