<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Enums;

/**
 * What a taxable line is for, so a calculator can treat shipping differently where a country requires it.
 */
enum TaxLineKind: string
{
    /** Something sold: a variant, at its tax class. */
    case Goods = 'goods';

    /** The delivery charge, at the store's shipping tax class (Step 13). */
    case Shipping = 'shipping';
}
