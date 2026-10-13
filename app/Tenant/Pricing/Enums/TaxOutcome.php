<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Enums;

/**
 * How a line's tax came about, so staff can tell zero-rated from exempt, and see where a rate is missing.
 */
enum TaxOutcome: string
{
    /** Taxed at a rate above zero. */
    case Taxed = 'taxed';

    /** Taxable at 0%: no tax, but still reported as a taxable sale. */
    case ZeroRated = 'zero_rated';

    /** Outside tax, by its tax class or an exemption: no tax and no rate looked up. */
    case Exempt = 'exempt';

    /** No rate is set for its class at the destination, so no tax was charged. Staff should check their rates. */
    case NoRate = 'no_rate';
}
