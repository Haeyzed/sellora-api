<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

/**
 * What tax needs to know about the customer buying: whether staff made them tax-exempt, and their tax ID for records and outside tax services.
 */
final readonly class TaxCustomer
{
    /**
     * @param  string|null  $taxId  As the customer entered it, normalised; format-checked only, never verified with a registry.
     * @param  string|null  $taxIdCountry  ISO 3166-1 alpha-2 of the country that issued the tax ID.
     */
    public function __construct(
        public bool $isTaxExempt = false,
        public ?string $taxId = null,
        public ?string $taxIdCountry = null,
    ) {}
}
