<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Models\Customer;

/**
 * Changes a customer's own profile: their name, phone and language. The email changes only through a confirmed link (StartCustomerEmailChange).
 */
final readonly class UpdateCustomerProfile
{
    /**
     * @param  array{name?: string, phone?: string|null, locale?: string|null}  $changes  Validated; a phone is in E.164 format.
     */
    public function handle(Customer $customer, array $changes): Customer
    {
        $customer->fill($changes)->save();

        return $customer;
    }
}
