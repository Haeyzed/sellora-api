<?php

declare(strict_types=1);

namespace App\Tenant\Storefront;

/**
 * The rules for a page's slug, the part of its storefront address that names it, such as "about-us".
 */
final class PageSlug
{
    /** Lower-case letters, numbers and single hyphens, the same as the database's check. */
    public const string PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public const int MAX_LENGTH = 190;
}
