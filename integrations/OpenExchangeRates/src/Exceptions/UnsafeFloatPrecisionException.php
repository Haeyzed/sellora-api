<?php

declare(strict_types=1);

namespace Integrations\OpenExchangeRates\Exceptions;

use LogicException;

/**
 * PHP is set up so JSON numbers can't be read back exactly (serialize_precision is not -1): a server misconfiguration, never a provider outage.
 *
 * Rates arrive as JSON floats, and only with serialize_precision = -1 does
 * PHP give their shortest exact form ("0.92", not "0.92000000000000004").
 * Fetching stops instead of recording a rate that isn't what was sent, and
 * the error reaches the error tracker on every scheduled run until fixed.
 */
final class UnsafeFloatPrecisionException extends LogicException
{
    public static function for(string $serializePrecision): self
    {
        return new self("PHP's serialize_precision is \"{$serializePrecision}\", not -1, so exchange rates can't be read exactly; none were recorded. Set serialize_precision = -1 in php.ini.");
    }
}
