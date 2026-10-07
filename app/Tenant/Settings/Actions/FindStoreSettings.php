<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Actions;

use App\Tenant\Settings\Exceptions\MissingStoreSettingsException;
use App\Tenant\Settings\Models\StoreSettings;

/**
 * The store's settings, which every store gets when it is set up. A missing row fails loudly instead of being created on read (section 3.3).
 */
final readonly class FindStoreSettings
{
    /**
     * @throws MissingStoreSettingsException When the store has no settings row, which is a bug.
     */
    public function handle(): StoreSettings
    {
        return StoreSettings::query()->find(1) ?? throw new MissingStoreSettingsException;
    }

    /**
     * The settings row, locked until the current transaction ends, for changing it.
     *
     * @throws MissingStoreSettingsException When the store has no settings row, which is a bug.
     */
    public function forUpdate(): StoreSettings
    {
        return StoreSettings::query()->lockForUpdate()->find(1) ?? throw new MissingStoreSettingsException;
    }
}
