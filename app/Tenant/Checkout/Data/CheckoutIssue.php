<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

/**
 * Something that stops a checkout becoming an order until the shopper sorts it out, such as a missing address, or a module's rule (a checkout validator).
 */
final readonly class CheckoutIssue
{
    /**
     * @param  string  $code  Stable and lower snake case, such as "shipping_address_required"; a module prefixes its own with its key.
     * @param  string|null  $field  The checkout field to fix, when there is one.
     */
    public function __construct(
        public string $code,
        public ?string $field = null,
    ) {}
}
