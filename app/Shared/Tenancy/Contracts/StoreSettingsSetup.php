<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Gives a new store its settings, while tenancy is initialized for that store.
 *
 * Implemented by Tenant\Settings, so the platform side that sets stores up
 * never writes to a store's database itself. Safe to call again: a store
 * that has its settings keeps them.
 */
interface StoreSettingsSetup
{
    /**
     * Creates the store's settings from what it was registered with, and its country's defaults, unless it has them already.
     */
    public function initialize(): void;
}
