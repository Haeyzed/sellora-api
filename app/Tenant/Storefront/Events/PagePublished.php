<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A page went on the storefront, announced once saved (for a sitemap ping or a CDN purge later).
 */
final class PagePublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  string  $pageId  The page's public ID.
     */
    public function __construct(public readonly string $pageId) {}
}
