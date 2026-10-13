<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when asking for prices in a currency the store doesn't offer right now: not one of its currencies, inactive, or without a usable rate.
 */
final class CurrencyNotOfferedException extends DomainException
{
    public function __construct(string $currency)
    {
        parent::__construct(['currency' => $currency]);
    }

    public function errorCode(): string
    {
        return 'currency_not_offered';
    }
}
