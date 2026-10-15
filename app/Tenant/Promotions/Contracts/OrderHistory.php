<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Contracts;

use App\Tenant\Customers\Models\Customer;

/**
 * Whether a buyer has ordered before, for first-order promotions. Orders implements it (Step 14).
 */
interface OrderHistory
{
    /**
     * Whether the customer, or anyone with the email (guest orders included), has an order that wasn't cancelled.
     */
    public function hasOrdered(?Customer $customer, ?string $email): bool;
}
