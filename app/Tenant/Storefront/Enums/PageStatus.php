<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Enums;

/**
 * Whether shoppers can see a page.
 */
enum PageStatus: string
{
    /** Being written: only staff see it. */
    case Draft = 'draft';

    /** On the storefront. */
    case Published = 'published';
}
