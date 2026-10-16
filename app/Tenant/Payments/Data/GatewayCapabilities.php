<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

/**
 * What a gateway declares it can do: the currencies it takes, the countries its buyers may pay from, and how they pay.
 */
final readonly class GatewayCapabilities
{
    /**
     * @param  list<string>  $currencies  ISO 4217 codes it takes payments in.
     * @param  list<string>|null  $countries  ISO 3166-1 alpha-2 codes buyers may pay from; null for anywhere.
     * @param  list<string>  $methods  How buyers pay on its page, such as "card", "bank_transfer", "ussd", "mobile_money".
     * @param  bool  $partialRefunds  Whether it refunds part of a payment.
     */
    public function __construct(
        public array $currencies,
        public ?array $countries,
        public array $methods,
        public bool $partialRefunds = true,
    ) {}

    public function takes(string $currency): bool
    {
        return in_array(mb_strtoupper($currency), $this->currencies, true);
    }

    public function servesBuyersIn(?string $countryCode): bool
    {
        return $this->countries === null || ($countryCode !== null && in_array(mb_strtoupper($countryCode), $this->countries, true));
    }
}
