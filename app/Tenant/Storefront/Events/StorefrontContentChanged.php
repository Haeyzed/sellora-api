<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something shoppers see on the storefront changed: a page, menu, banner or the storefront settings. Announced once the change is saved, so the cached storefront is renewed (and, later, a CDN purged).
 */
final class StorefrontContentChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
}
