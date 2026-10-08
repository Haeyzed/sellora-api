<?php

declare(strict_types=1);

namespace App\Tenant\Catalog;

/**
 * What a slug in the catalog may look like: lower-case letters and numbers in groups joined by single hyphens, such as "mens-shirts".
 *
 * The same rule is a check constraint on every table with a slug.
 */
final class CatalogSlug
{
    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const int MAX_LENGTH = 190;
}
