<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A page came off the storefront (unpublished or moved to the trash), announced once saved.
 */
final class PageUnpublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  string  $pageId  The page's public ID.
     */
    public function __construct(public readonly string $pageId) {}
}
