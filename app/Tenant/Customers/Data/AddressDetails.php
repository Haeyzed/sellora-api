<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Data;

/**
 * A whole saved address as validated, and whether it should be the customer's default for delivery or billing.
 */
final readonly class AddressDetails
{
    /**
     * @param  array{label: string|null, recipient_name: string, company: string|null, line1: string, line2: string|null, city: string|null, state_code: string|null, postal_code: string|null, country_code: string, phone: string|null}  $fields  By column; a phone in E.164.
     * @param  bool|null  $defaultShipping  True to make it the default delivery address, false to stop it being the default, null to leave that as it is.
     * @param  bool|null  $defaultBilling  The same, for billing.
     */
    public function __construct(
        public array $fields,
        public ?bool $defaultShipping = null,
        public ?bool $defaultBilling = null,
    ) {}
}
