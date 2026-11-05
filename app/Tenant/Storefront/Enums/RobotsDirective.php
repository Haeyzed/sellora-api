<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Enums;

/**
 * Whether search engines may list the storefront's pages and follow their links, as the storefront tells them in its robots meta tag.
 */
enum RobotsDirective: string
{
    /** Listed, and their links followed. */
    case IndexFollow = 'index_follow';

    /** Not listed, but their links followed. */
    case NoindexFollow = 'noindex_follow';

    /** Neither listed nor followed, such as while a store is being set up. */
    case NoindexNofollow = 'noindex_nofollow';

    /**
     * The robots meta tag's content, such as "index, follow".
     */
    public function metaContent(): string
    {
        return match ($this) {
            self::IndexFollow => 'index, follow',
            self::NoindexFollow => 'noindex, follow',
            self::NoindexNofollow => 'noindex, nofollow',
        };
    }
}
