<?php

declare(strict_types=1);

namespace App\Tenant\Promotions;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Promotions\Contracts\OrderHistory;

/**
 * The order history until Orders replaces it (Step 14): it answers that everyone has ordered before.
 *
 * So a first-order promotion is never given away by mistake: it fails
 * closed, unlike the legacy code, which treated every order as a first one.
 */
final readonly class NoOrderHistory implements OrderHistory
{
    public function hasOrdered(?Customer $customer, ?string $email): bool
    {
        return true;
    }
}
