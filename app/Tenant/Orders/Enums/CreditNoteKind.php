<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * Why a credit note exists: only one for a refund has a refund behind it.
 */
enum CreditNoteKind: string
{
    /** Part of a refund that reduced the sale. */
    case Refund = 'refund';

    /** A held amount that stopped counting toward the sale, with no refund behind it: a method released it, or gave back what it took. */
    case SaleUndone = 'sale_undone';
}
