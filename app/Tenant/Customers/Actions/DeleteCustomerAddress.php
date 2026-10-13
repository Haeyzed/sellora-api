<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Models\CustomerAddress;

/**
 * Deletes one of a customer's saved addresses for good. If it was a default, the customer simply has no default any more (the database clears the link).
 *
 * Past orders keep their own copy, so they don't change.
 */
final readonly class DeleteCustomerAddress
{
    public function handle(CustomerAddress $address): void
    {
        $address->delete();
    }
}
