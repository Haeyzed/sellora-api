<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Exceptions\InvalidCustomerEmailLinkException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerEmailToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a customer's email from the link sent to it. The link works once, and only while the account still has the address it was sent to.
 *
 * Works without signing in, because the link is often opened on another
 * device: holding the link proves access to the mailbox.
 */
final readonly class VerifyCustomerEmail
{
    /**
     * @throws InvalidCustomerEmailLinkException When the link is unknown, expired or used, or the account's email changed since, or the account is closed.
     */
    public function handle(string $token): void
    {
        DB::transaction(static function () use ($token): void {
            $emailToken = CustomerEmailToken::query()->usable($token, CustomerEmailTokenPurpose::Verification)->lockForUpdate()->first();
            $customer = $emailToken === null ? null : Customer::query()->lockForUpdate()->find($emailToken->customer_id);

            if ($emailToken === null || $customer === null || ! $customer->is_active || $customer->email !== $emailToken->email) {
                throw new InvalidCustomerEmailLinkException;
            }

            $customer->forceFill(['email_verified_at' => CarbonImmutable::now()])->save();
            $emailToken->delete();
        });
    }
}
