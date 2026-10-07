<?php

declare(strict_types=1);

namespace App\Shared\Geography;

/**
 * An ISO 4217 currency in use today, as offered to merchants.
 */
final readonly class Currency
{
    /**
     * @param  string  $code  Such as "NGN".
     * @param  int  $decimalPlaces  Of its minor unit: 2 for NGN, 0 for JPY, 3 for KWD.
     */
    public function __construct(
        public string $code,
        public string $name,
        public int $decimalPlaces,
    ) {}
}
