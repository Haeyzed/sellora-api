<?php

declare(strict_types=1);

namespace App\Tenant\Storefront;

use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Carbon\CarbonImmutable;

/**
 * A new store's storefront settings, from the defaults in config/storefront.php: created when the store is set up, so a store always has them (section 10).
 *
 * The row is inserted only if missing, so setting a store up again never
 * overwrites settings staff have changed. Reads never create it.
 */
final readonly class StorefrontSettingsDefaults implements StoreSettingsSetup
{
    public function initialize(): void
    {
        $now = CarbonImmutable::now();

        StorefrontSettings::query()->insertOrIgnore([
            'id' => 1,
            'seo_title_pattern' => config()->string('storefront.seo_title_pattern'),
            'robots' => config()->string('storefront.robots'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
