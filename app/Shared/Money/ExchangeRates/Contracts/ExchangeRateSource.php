<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates\Contracts;

use App\Shared\Money\ExchangeRates\Exceptions\ExchangeRateSourceUnavailableException;
use App\Shared\Money\ExchangeRates\FetchedRates;

/**
 * Where the platform gets market exchange rates, once for every store (section 2.1). Implemented by a platform-level integration, such as Open Exchange Rates.
 */
interface ExchangeRateSource
{
    /**
     * The latest market rates against USD.
     *
     * @throws ExchangeRateSourceUnavailableException When the provider can't be reached, refuses the request, or answers with something unusable.
     */
    public function fetch(): FetchedRates;
}
