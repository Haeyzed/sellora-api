<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Contracts;

use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\CheckoutIssue;

/**
 * A module's rule a checkout must meet before it becomes an order (section 6), such as a minimum order value. Registered with CheckoutExtensions under the module's feature key, and asked only while the module is enabled for the store.
 */
interface CheckoutValidator
{
    /**
     * What stops this checkout becoming an order; none when it may. Reads only: it never changes anything.
     *
     * @return list<CheckoutIssue>
     */
    public function validate(CheckoutContext $context): array;
}
