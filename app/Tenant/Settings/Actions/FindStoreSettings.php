<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Actions;

use App\Tenant\Settings\Models\StoreSettings;
use App\Tenant\Settings\StoreSettingsDefaults;

/**
 * The store's settings. A store set up before settings existed gets its first ones now, from what it was registered with.
 */
final readonly class FindStoreSettings
{
    public function __construct(private StoreSettingsDefaults $storeSettingsDefaults) {}

    public function handle(): StoreSettings
    {
        $settings = StoreSettings::query()->find(1);

        if ($settings !== null) {
            return $settings;
        }

        $this->storeSettingsDefaults->initialize();

        return StoreSettings::query()->findOrFail(1);
    }
}
