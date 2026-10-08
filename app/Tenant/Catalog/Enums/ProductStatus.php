<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Enums;

/**
 * Where a product is in its life: being prepared, on sale, or taken off sale but kept.
 */
enum ProductStatus: string
{
    /** Being prepared; customers can't see it. Every new product starts here. */
    case Draft = 'draft';

    /** Published: customers can see and buy it. */
    case Active = 'active';

    /** Taken off sale: customers can't see it, but it is kept and still shows on old orders. */
    case Archived = 'archived';
}
