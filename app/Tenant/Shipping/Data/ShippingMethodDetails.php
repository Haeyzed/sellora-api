<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

/**
 * What staff set on a shipping method, already validated: the fields sent, and the lists that replace the current ones. Null means "leave as it is".
 */
final readonly class ShippingMethodDetails
{
    /**
     * @param  array<string, mixed>  $fields  Model attributes by name, including shipping_zone_id and stock_location_id as database IDs; amounts as Money in the base currency.
     * @param  array<string, array<string, string|null>>  $translations  Changed translations of name, description and pickup_instructions, by language; null for a language removes it.
     * @param  list<array{from: int, to: int|null, amount: int, currency_amounts: array<string, int>}>|null  $rates  Replaces every bracket; amounts in minor units, exact ones by currency.
     * @param  array<string, array{free_over: int|null, backup: int|null}>|null  $currencyAmounts  Replaces the exact "free over" thresholds and backup prices, in minor units by currency.
     */
    public function __construct(
        public array $fields,
        public array $translations = [],
        public ?array $rates = null,
        public ?array $currencyAmounts = null,
    ) {}
}
