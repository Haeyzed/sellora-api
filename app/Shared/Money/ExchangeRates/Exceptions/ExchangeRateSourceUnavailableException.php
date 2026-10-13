<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates\Exceptions;

use RuntimeException;

/**
 * Raised when the exchange rate provider can't give rates right now. The message never contains credentials or the provider's response.
 */
final class ExchangeRateSourceUnavailableException extends RuntimeException {}
