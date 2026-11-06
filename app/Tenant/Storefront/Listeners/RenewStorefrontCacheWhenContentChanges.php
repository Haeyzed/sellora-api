<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Listeners;

use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Services\StorefrontContentCache;

/**
 * Renews the store's cached storefront once a change to its content is saved, so shoppers see the change on their next visit.
 */
final readonly class RenewStorefrontCacheWhenContentChanges
{
    public function __construct(private StorefrontContentCache $storefrontContentCache) {}

    public function handle(StorefrontContentChanged $event): void
    {
        $this->storefrontContentCache->renew();
    }
}
