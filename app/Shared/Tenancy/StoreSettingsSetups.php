<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use App\Shared\Tenancy\Contracts\StoreSettingsSetup;

/**
 * Gives a new store every settings row it needs, in order: the store-wide settings first, then each core domain's own (such as Returns'), so none is ever missing or read lazily (section 10).
 *
 * Each step is safe to run again, so setting a store up again keeps what
 * the merchant changed.
 */
final readonly class StoreSettingsSetups implements StoreSettingsSetup
{
    /**
     * @param  list<StoreSettingsSetup>  $setups  In the order they run.
     */
    public function __construct(private array $setups) {}

    public function initialize(): void
    {
        foreach ($this->setups as $setup) {
            $setup->initialize();
        }
    }
}
