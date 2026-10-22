<?php

declare(strict_types=1);

namespace Tests\Fixtures\Checkout;

use App\Tenant\Checkout\Contracts\CheckoutValidator;
use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\CheckoutIssue;

/**
 * A module's checkout rule for the tests: an order must come to at least the minimum, in minor units of its currency.
 */
final class MinimumOrderValidator implements CheckoutValidator
{
    public static int $minimum = 0;

    public function validate(CheckoutContext $context): array
    {
        return $context->total->minorAmount() < self::$minimum ? [new CheckoutIssue('minimum_order_not_reached')] : [];
    }
}
