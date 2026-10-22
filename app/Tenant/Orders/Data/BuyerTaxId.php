<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

/**
 * The tax ID a buyer gave at checkout (Step 16), with the country that issued it. The order copies it, and its invoice and credit notes copy it from the order.
 */
final readonly class BuyerTaxId
{
    /**
     * @param  string  $id  Normalised (Customers' TaxIdFormat).
     * @param  string  $countryCode  ISO 3166-1 alpha-2.
     */
    public function __construct(
        public string $id,
        public string $countryCode,
    ) {}
}
